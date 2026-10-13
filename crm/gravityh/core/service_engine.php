<?php
/**
 * GRAVITY CRM - Service Engine (SPS 2040 Phase 4)
 *
 * Spec coverage:
 *   Sec 7  Service Engine     - assignment -> version freeze -> requirements
 *                              -> workflow execution -> step executions ->
 *                              deadlines -> SERVICE_STARTED event
 *   Sec 8  Requirement Engine - fulfillment states, legacy-row adoption,
 *                              dedupe on request
 *   Sec 24 Deadline Engine    - per-requirement due windows
 *   G12 Versioning            - immutable service_versions snapshots
 *
 * Conventions: procedural, same style as auth.php / functions.php.
 * Every write path is idempotent and transaction-safe.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

/** Stable hash over the material fields of a service row. */
function svc_service_content_hash(array $service): string {
    $fields = ['name','description','service_type','category','is_recurring','billing_type',
               'hourly_rate','fixed_price','estimated_hours','is_taxable','tax_rate','status'];
    $buf = '';
    foreach ($fields as $f) {
        $buf .= $f . '=' . (string)($service[$f] ?? '') . '|';
    }
    return hash('sha256', $buf);
}

/**
 * Freeze current service state into an immutable version snapshot.
 * Dedupes by content hash; keeps services.current_version_id pointing at it.
 */
function svc_freeze_service_version(int $service_id, ?int $user_id = null): int {
    $svc = db_row('SELECT * FROM services WHERE id = ?', [$service_id]);
    if (!$svc) {
        throw new RuntimeException("Service {$service_id} not found.");
    }

    $hash = svc_service_content_hash($svc);

    $latest = db_row(
        'SELECT id, content_hash FROM service_versions WHERE service_id = ? ORDER BY version_number DESC LIMIT 1',
        [$service_id]
    );

    if ($latest && $latest['content_hash'] === $hash) {
        db_execute('UPDATE services SET current_version_id = ? WHERE id = ?', [(int)$latest['id'], $service_id]);
        return (int)$latest['id'];
    }

    $next = $latest ? ((int)$latest['version_number'] + 1) : 1;
    unset($svc['current_version_id']); // never snapshot the pointer itself

    db_execute(
        'INSERT INTO service_versions (service_id, version_number, snapshot, content_hash, created_by) VALUES (?, ?, ?, ?, ?)',
        [$service_id, $next, json_encode($svc, JSON_UNESCAPED_UNICODE), $hash, $user_id]
    );
    $version_id = db_last_id();

    db_execute('UPDATE services SET current_version_id = ? WHERE id = ?', [$version_id, $service_id]);
    return $version_id;
}

/**
 * Resolve governing workflow template: explicit link, then category,
 * then service_type match.
 */
function svc_resolve_workflow_template(array $service): ?array {
    if (!empty($service['workflow_template_id'])) {
        $t = db_row('SELECT * FROM workflow_templates WHERE id = ? AND is_active = 1', [(int)$service['workflow_template_id']]);
        if ($t) { return $t; }
    }
    foreach (['category', 'service_type'] as $key) {
        if (!empty($service[$key])) {
            $t = db_row(
                'SELECT * FROM workflow_templates WHERE is_active = 1 AND category = ? ORDER BY id ASC LIMIT 1',
                [$service[$key]]
            );
            if ($t) { return $t; }
        }
    }
    return null;
}

/**
 * Instantiate a workflow for an assignment: workflow_instances plus
 * executable step copies; first step activated.
 * Returns workflow_instances.id or null when no template resolves.
 */
function svc_start_workflow(int $client_services_id): ?int {
    $cs = db_row(
        'SELECT cs.*, s.workflow_template_id, s.category AS category, s.service_type AS service_type FROM client_services cs
         JOIN services s ON s.id = cs.service_id WHERE cs.id = ?',
        [$client_services_id]
    );
    if (!$cs) {
        throw new RuntimeException("Assignment {$client_services_id} not found.");
    }
    if (!empty($cs['workflow_instance_id'])) {
        return (int)$cs['workflow_instance_id']; // already running - idempotent
    }

    $template = svc_resolve_workflow_template($cs);
    if (!$template) {
        return null; // service legitimately runs without workflow (spec Sec 1)
    }

    $steps = db_query(
        'SELECT * FROM workflow_steps WHERE workflow_id = ? AND is_active = 1 ORDER BY step_order ASC',
        [(int)$template['id']]
    );

    db_execute(
        'INSERT INTO workflow_instances (workflow_id, client_id, project_id, client_services_id, status, started_by, started_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())',
        [
            (int)$template['id'],
            (int)$cs['client_id'],
            $cs['project_id'] !== null ? (int)$cs['project_id'] : null,
            $client_services_id,
            'in_progress',
            current_user()['id'] ?? null,
        ]
    );
    $instance_id = db_last_id();

    foreach ($steps as $step) {
        // assigned role stashed as JSON marker until dedicated column migration
        $meta = json_encode([
            'assigned_role_id' => $step['assigned_role_id'] ?? null,
            'config'           => json_decode($step['config_data'] ?? '{}', true),
        ], JSON_UNESCAPED_UNICODE);

        db_execute(
            'INSERT INTO workflow_step_instances
                (workflow_instance_id, step_id, step_order, step_type, title, status, assigned_user_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $instance_id,
                (int)$step['id'],
                (int)$step['step_order'],
                (string)$step['step_type'],
                (string)$step['step_name'],
                'pending',
                $step['assigned_user_id'] !== null ? (int)$step['assigned_user_id'] : null,
                $meta,
            ]
        );
    }

    $first = db_row(
        'SELECT * FROM workflow_step_instances WHERE workflow_instance_id = ? ORDER BY step_order ASC LIMIT 1',
        [$instance_id]
    );
    if ($first) {
        db_execute('UPDATE workflow_step_instances SET status = ?, started_at = NOW() WHERE id = ?', ['in_progress', (int)$first['id']]);
        db_execute('UPDATE workflow_instances SET current_step_id = ? WHERE id = ?', [(int)$first['step_id'], $instance_id]);
        db_execute('UPDATE client_services SET current_step_order = ? WHERE id = ?', [(int)$first['step_order'], $client_services_id]);
    }

    return $instance_id;
}

/**
 * One deadline per generated requirement declaring due_days (spec Sec 24).
 * Type map covers modern AND legacy requirement_type vocabularies.
 * Idempotent via source/source_id. Returns rows created.
 */
function svc_create_requirement_deadlines(int $client_services_id): int {
    $cs = db_row('SELECT * FROM client_services WHERE id = ?', [$client_services_id]);
    if (!$cs) { return 0; }

    $rows = db_query(
        'SELECT r.id AS csr_id, t.requirement_type, t.due_days, t.title AS tpl_title
         FROM client_service_requirements r
         JOIN service_requirements t ON t.id = r.requirement_id
         WHERE r.client_services_id = ? AND t.due_days IS NOT NULL',
        [$client_services_id]
    );

    $base = $cs['start_date'] ? strtotime((string)$cs['start_date']) : time();
    $type_map = [
        'document'       => 'document',
        'file_upload'    => 'document',
        'tax_form'       => 'document',
        'bank_statement' => 'document',
        'payment'        => 'invoice',
        'approval'       => 'internal',
    ];
    $created = 0;

    foreach ($rows as $r) {
        $dupe = db_value('SELECT COUNT(*) FROM deadlines WHERE source = ? AND source_id = ?', ['requirement', (int)$r['csr_id']]);
        if ((int)$dupe > 0) { continue; }

        $dl_type = $type_map[(string)$r['requirement_type']] ?? 'client';
        $due = date('Y-m-d 23:59:59', $base + ((int)$r['due_days']) * 86400);

        db_execute(
            'INSERT INTO deadlines
                (client_id, business_id, project_id, client_services_id, deadline_type, title, due_date, warn_days, source, source_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 3, ?, ?, ?)',
            [
                (int)$cs['client_id'],
                null,
                null,
                $client_services_id,
                $dl_type,
                'Requirement due: ' . (string)$r['tpl_title'],
                $due,
                'requirement',
                (int)$r['csr_id'],
                current_user()['id'] ?? null,
            ]
        );
        $created++;
    }

    return $created;
}

/**
 * Copy active requirement templates into the per-assignment fulfillment
 * matrix (spec Sec 8). Idempotent. Adopts pre-engine legacy rows instead
 * of duplicating them. Returns rows created or adopted.
 */
function svc_generate_requirements(int $client_services_id, ?int $user_id = null): int {
    $cs = db_row('SELECT * FROM client_services WHERE id = ?', [$client_services_id]);
    if (!$cs) {
        throw new RuntimeException("Assignment {$client_services_id} not found.");
    }

    $templates = db_query(
        'SELECT * FROM service_requirements WHERE service_id = ? AND is_active = 1 ORDER BY sort_order ASC',
        [(int)$cs['service_id']]
    );

    $created = 0;
    $adopted = 0;
    foreach ($templates as $tpl) {
        $row = db_row(
            'SELECT id, client_services_id FROM client_service_requirements
             WHERE requirement_id = ? AND client_id = ?
             ORDER BY (client_services_id = ?) DESC, id ASC LIMIT 1',
            [(int)$tpl['id'], (int)$cs['client_id'], $client_services_id]
        );

        if ($row && (int)$row['client_services_id'] === $client_services_id) {
            continue;
        }

        if ($row && $row['client_services_id'] === null) {
            db_execute(
                'UPDATE client_service_requirements
                 SET client_services_id = ?,
                     business_id = COALESCE(business_id, ?),
                     project_id = COALESCE(project_id, ?)
                 WHERE id = ?',
                [
                    $client_services_id,
                    $cs['business_id'] !== null ? (int)$cs['business_id'] : null,
                    $cs['project_id'] !== null ? (int)$cs['project_id'] : null,
                    (int)$row['id'],
                ]
            );
            $adopted++;
            continue;
        }

        if ($row) { continue; }

        db_execute(
            'INSERT INTO client_service_requirements
                (client_services_id, client_id, service_id, requirement_id, business_id, project_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $client_services_id,
                (int)$cs['client_id'],
                (int)$cs['service_id'],
                (int)$tpl['id'],
                $cs['business_id'] !== null ? (int)$cs['business_id'] : null,
                $cs['project_id'] !== null ? (int)$cs['project_id'] : null,
                $tpl['is_required'] ? 'not_started' : 'optional',
            ]
        );
        $created++;
    }

    return $created + $adopted;
}

/**
 * Full Sec 7 assignment pipeline. Idempotent for repeat assignments.
 *
 * @param array $opts {custom_rate,start_date,end_date,business_id,status,notes,user_id}
 * @return array{client_services_id:int, service_version_id:int, workflow_instance_id:?int, requirements_created:int, deadlines_created:int, created:bool}
 */
function svc_assign_service_to_client(int $client_id, int $service_id, array $opts = []): array {
    $user_id = $opts['user_id'] ?? (current_user()['id'] ?? null);

    return db_transaction(function () use ($client_id, $service_id, $opts, $user_id) {
        $cs = db_row(
            'SELECT * FROM client_services WHERE client_id = ? AND service_id = ?',
            [$client_id, $service_id]
        );

        $created = false;
        if (!$cs) {
            db_execute(
                'INSERT INTO client_services (client_id, service_id, business_id, status, start_date, end_date, custom_rate, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $client_id,
                    $service_id,
                    !empty($opts['business_id']) ? (int)$opts['business_id'] : null,
                    ($opts['status'] ?? 'active') ?: 'active',
                    !empty($opts['start_date']) ? $opts['start_date'] : date('Y-m-d'),
                    !empty($opts['end_date']) ? $opts['end_date'] : null,
                    !empty($opts['custom_rate']) ? (float)$opts['custom_rate'] : null,
                    $opts['notes'] ?? null,
                    $user_id,
                ]
            );
            $cs_id = db_last_id();
            $created = true;
        } else {
            $cs_id = (int)$cs['id'];
        }

        // G12: freeze the definition actually being delivered
        $version_id = svc_freeze_service_version($service_id, $user_id);
        db_execute('UPDATE client_services SET service_version_id = ? WHERE id = ?', [$version_id, $cs_id]);

        // Sec 7: instantiate the workflow (no-op when absent or already running)
        $wf_id = svc_start_workflow($cs_id);
        if ($wf_id) {
            db_execute('UPDATE client_services SET workflow_instance_id = ? WHERE id = ?', [$wf_id, $cs_id]);
        }

        // Sec 8 + Sec 24
        $reqs = svc_generate_requirements($cs_id, $user_id);
        $dls  = svc_create_requirement_deadlines($cs_id);

        // Sec 35: SERVICE_STARTED event (activity audit + durable bus)
        bus_emit(EVT_SERVICE_STARTED, ['requirements' => $reqs, 'deadlines' => $dls], ['source' => 'service_assignment', 'source_id' => $cs_id, 'client_id' => $client_id]);
        log_activity(
            'services',
            'service_started',
            $cs_id,
            "Service started for client #{$client_id}" . ($wf_id ? " | workflow #{$wf_id}" : '') . " | requirements: {$reqs} | deadlines: {$dls}",
            false
        );

        return [
            'client_services_id'   => $cs_id,
            'service_version_id'   => $version_id,
            'workflow_instance_id' => $wf_id,
            'requirements_created' => $reqs,
            'deadlines_created'    => $dls,
            'created'              => $created,
        ];
    });
}

/**
 * Compact operational rollup for one assignment (feeds Sec 6 profile).
 */
function svc_assignment_summary(int $client_services_id): array {
    return [
        'requirements' => db_query(
            'SELECT status, COUNT(*) AS n FROM client_service_requirements WHERE client_services_id = ? GROUP BY status',
            [$client_services_id]
        ),
        'open_steps' => (int) db_value(
            'SELECT COUNT(*) FROM workflow_step_instances wsi
             JOIN client_services cs ON cs.workflow_instance_id = wsi.workflow_instance_id
             WHERE cs.id = ? AND wsi.status IN (?, ?)',
            [$client_services_id, 'pending', 'in_progress']
        ),
        'open_deadlines' => (int) db_value(
            'SELECT COUNT(*) FROM deadlines WHERE client_services_id = ? AND status IN (?, ?, ?)',
            [$client_services_id, 'upcoming', 'due_soon', 'overdue']
        ),
    ];
}



