<?php
// Phase 4 regression - Service Engine full pipeline + idempotency

t_suite('workflow', 'Service version freeze, workflow, requirements, deadlines, idempotency', function () {
    $cid = 0; $sid = 0; $tid = 0; $cs_id = 0;
    try {
        db_execute("INSERT INTO clients (first_name,last_name,email,status) VALUES ('REG','WF','_regwf@example.test','active')");
        $cid = db_last_id();
        db_execute("INSERT INTO services (name,description,service_type,category,billing_type,fixed_price,status) VALUES ('_REG Smoke','x','tax','_regcat','fixed',100,'active')");
        $sid = db_last_id();
        db_execute("INSERT INTO workflow_templates (name,description,workflow_key,category,is_active) VALUES ('_REG Tpl','x','_reg_tpl','_regcat',1)");
        $tid = db_last_id();
        db_execute("INSERT INTO workflow_steps (workflow_id,step_order,step_name,step_type,is_required) VALUES ($tid,1,'A','task',1),($tid,2,'B','approval',1)");
        db_execute("INSERT INTO service_requirements (service_id,title,requirement_type,is_required,due_days,sort_order) VALUES ($sid,'Doc 1','document',1,7,1),($sid,'Info 1','information',1,NULL,2)");

        $r = svc_assign_service_to_client($cid, $sid, ['user_id' => 1]);
        $cs_id = (int)$r['client_services_id'];

        t_assert('version frozen', $r['service_version_id'] > 0);
        t_assert('workflow started', !empty($r['workflow_instance_id']));
        $w = (int)$r['workflow_instance_id'];
        t_eq('two steps copied', 2, (int)db_value('SELECT COUNT(*) FROM workflow_step_instances WHERE workflow_instance_id=?', [$w]));
        t_eq('requirements generated', 2, $r['requirements_created']);
        t_eq('deadline for document req', 1, $r['deadlines_created']);
        t_assert('SERVICE_STARTED logged',
            (int)db_value("SELECT COUNT(*) FROM activities WHERE module='services' AND action='service_started' AND record_id=?", [$cs_id]) >= 1);

        // Idempotency: run again
        $r2 = svc_assign_service_to_client($cid, $sid, ['user_id' => 1]);
        t_assert('repeat same assignment', $r2['client_services_id'] === $cs_id && $r2['workflow_instance_id'] === $w);
        t_eq('repeat zero new reqs', 0, $r2['requirements_created']);
        t_eq('repeat zero new deadlines', 0, $r2['deadlines_created']);
        t_eq('one version only', 1, (int)db_value('SELECT COUNT(*) FROM service_versions WHERE service_id=?', [$sid]));

        // Summary rollup
        $sum = svc_assignment_summary($cs_id);
        t_eq('summary open steps', 2, $sum['open_steps']);
        t_assert('summary deadlines>0', $sum['open_deadlines'] >= 1);
    } finally {
        if ($cs_id) db_execute("DELETE FROM activities WHERE module='services' AND record_id=?", [$cs_id]);
        if ($cid) db_execute('DELETE FROM clients WHERE id=?', [$cid]);
        if ($sid) db_execute('DELETE FROM services WHERE id=?', [$sid]);
        if ($tid) db_execute('DELETE FROM workflow_templates WHERE id=?', [$tid]);
    }
});