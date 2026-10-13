<?php
/* =============================================================================
   SPS CRM AUTOMATION — READY-MADE WORKFLOW TEMPLATES
   -----------------------------------------------------------------------------
   aut_templates() returns pre-filled recipes (trigger config + steps JSON).
   The Automation UI renders these as cards; picking one creates a DISABLED
   draft the admin edits then enables.

   Step JSON only uses keys accepted by aut_sanitize_steps.
   ===========================================================================*/

if (!function_exists('aut_templates')) {

function aut_templates() {
    $T = [];

    /* ── Money & Collections ──────────────────────────────────────────── */
    $T[] = [
        'id' => 'unpaid-invoices', 'cat' => 'Money & Collections',
        'name' => 'Daily unpaid-invoice reminder',
        'desc' => 'Every morning at 09:00 send a friendly payment reminder to each client with an unpaid invoice due within the next 7 days.',
        'trigger' => ['type' => 'schedule', 'config' => ['cron' => '0 9 * * *', 'object' => '', 'query' => '']],
        'steps' => [
            ['type' => 'loop', 'label' => 'Each unpaid invoice', 'sql' => "SELECT i.invoice_id,i.balance,i.total_amount,i.due_date,m.name,m.prm_email FROM billing_invoice i JOIN members m ON m.member_id=i.mem_id WHERE i.invoice_status=1 AND i.due_date BETWEEN CURDATE() AND CURDATE()+INTERVAL 7 DAY AND m.prm_email<>'' AND i.balance>0", 'steps' => [
                ['type' => 'email', 'label' => 'Reminder email', 'to' => '{{item.prm_email}}', 'subject' => 'Payment reminder — Invoice #{{item.invoice_id}}', 'body' => "Hello {{item.name}},\n\nThis is a gentle reminder that invoice #{{item.invoice_id}} for \${{item.balance}} is due on {{item.due_date}}.\n\nPlease complete payment at your earliest convenience.\n\nThank you,\n{{meta.wf_name}}"],
                ['type' => 'log', 'label' => 'Log reminder', 'action' => 'CREATE', 'object_type' => 'billing_invoice', 'object_id' => '{{item.invoice_id}}', 'description' => 'Unpaid-invoice reminder sent to {{item.prm_email}}'],
            ]],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    $T[] = [
        'id' => 'overdue-escalation', 'cat' => 'Money & Collections',
        'name' => 'Overdue invoice escalation',
        'desc' => 'Daily: invoices past due get a soft nudge, but balances over $500 escalate to a firmer email plus an admin notification.',
        'trigger' => ['type' => 'schedule', 'config' => ['cron' => '0 9 * * *', 'object' => '', 'query' => '']],
        'steps' => [
            ['type' => 'loop', 'label' => 'Each overdue invoice', 'sql' => "SELECT i.invoice_id,i.balance,i.due_date,m.name,m.prm_email FROM billing_invoice i JOIN members m ON m.member_id=i.mem_id WHERE i.invoice_status=1 AND i.due_date < CURDATE()-INTERVAL 7 DAY AND m.prm_email<>'' AND i.balance>0", 'steps' => [
                ['type' => 'condition', 'label' => 'High balance?', 'condition' => ['and' => [['op' => 'gt', 'f' => 'item.balance', 'v' => '500']]] , 'then' => [
                    ['type' => 'email', 'label' => 'Firm email', 'to' => '{{item.prm_email}}', 'subject' => 'Overdue invoice #{{item.invoice_id}}', 'body' => "Hello {{item.name}},\n\nInvoice #{{item.invoice_id}} for \${{item.balance}} was due on {{item.due_date}} and is now past due. Please arrange payment today.\n\nThank you,\n{{meta.wf_name}}"],
                    ['type' => 'notify', 'label' => 'Alert staff', 'recipients' => 'admins', 'message' => 'Overdue >$500: invoice #{{item.invoice_id}} for {{item.name}} (${{item.balance}})'],
                ], 'else' => [
                    ['type' => 'email', 'label' => 'Soft reminder', 'to' => '{{item.prm_email}}', 'subject' => 'A quick reminder — invoice #{{item.invoice_id}}', 'body' => "Hello {{item.name}},\n\nWe hope you are well. Just a reminder that invoice #{{item.invoice_id}} for \${{item.balance}} is past due.\n\nWarm regards,\n{{meta.wf_name}}"],
                ]],
            ]],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    $T[] = [
        'id' => 'payment-received', 'cat' => 'Money & Collections',
        'name' => 'Payment received — thank you',
        'desc' => 'When a payment is recorded, thank the client by email and log the activity.',
        'trigger' => ['type' => 'record', 'config' => ['object' => 'billing_payment_history', 'event' => 'created']],
        'steps' => [
            ['type' => 'loop', 'label' => 'Payment + client details', 'sql' => "SELECT p.his_id,p.paid,p.date,i.invoice_id,i.balance,m.name,m.prm_email FROM billing_payment_history p JOIN members m ON m.member_id=p.mem_id JOIN billing_invoice i ON i.invoice_id=p.inv_id WHERE p.his_id={{object.his_id}} AND m.prm_email<>''", 'steps' => [
                ['type' => 'email', 'label' => 'Thank-you email', 'to' => '{{item.prm_email}}', 'subject' => 'Thank you for your payment', 'body' => "Hello {{item.name}},\n\nWe received your payment of \${{item.paid}} on {{item.date}}. Thank you!\n\nRegards,\n{{meta.wf_name}}"],
                ['type' => 'log', 'label' => 'Log payment', 'action' => 'CREATE', 'object_type' => 'billing_payment_history', 'object_id' => '{{item.his_id}}', 'description' => 'Payment of ${{item.paid}} received for invoice #{{item.invoice_id}}'],
            ]],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    /* ── Client Care ───────────────────────────────────────────────────── */
    $T[] = [
        'id' => 'new-client-welcome', 'cat' => 'Client Care',
        'name' => 'New client welcome',
        'desc' => 'When a new client is created, send a welcome email and notify the team in chat.',
        'trigger' => ['type' => 'record', 'config' => ['object' => 'members', 'event' => 'created']],
        'steps' => [
            ['type' => 'email', 'label' => 'Welcome email', 'to' => '{{client.prm_email}}', 'subject' => 'Welcome to {{meta.wf_name}}!', 'body' => "Hello {{client.name}},\n\nWelcome! We are glad to have you with us.\n\nOur team will follow up shortly.\n\nBest regards,\n{{meta.wf_name}}"],
            ['type' => 'notify', 'label' => 'Notify team', 'recipients' => 'admins', 'message' => 'New client created: {{client.name}} (#{{client.member_id}}). Send the welcome package.'],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    $T[] = [
        'id' => 'renewal-30d', 'cat' => 'Client Care',
        'name' => 'Service renewal reminder (30 days)',
        'desc' => 'Daily at 08:00 email every client whose active order/service expires within the next 30 days.',
        'trigger' => ['type' => 'schedule', 'config' => ['cron' => '0 8 * * *', 'object' => '', 'query' => '']],
        'steps' => [
            ['type' => 'loop', 'label' => 'Each expiring order', 'sql' => "SELECT o.order_id,o.exp_date,o.renewal_date,o.total,m.name,m.prm_email FROM orders o JOIN members m ON m.member_id=o.mem_id WHERE o.order_status=1 AND o.exp_date BETWEEN CURDATE() AND CURDATE()+INTERVAL 30 DAY AND m.prm_email<>''", 'steps' => [
                ['type' => 'email', 'label' => 'Renewal reminder', 'to' => '{{item.prm_email}}', 'subject' => 'Your service renews on {{item.exp_date}}', 'body' => "Hello {{item.name}},\n\nYour service (order #{{item.order_id}}) renews on {{item.exp_date}}.\n\nLet us know if you would like to renew or make any changes.\n\nRegards,\n{{meta.wf_name}}"],
                ['type' => 'log', 'label' => 'Log reminder', 'action' => 'UPDATE', 'object_type' => 'orders', 'object_id' => '{{item.order_id}}', 'description' => 'Renewal reminder sent to {{item.prm_email}} (expires {{item.exp_date}})'],
            ]],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    $T[] = [
        'id' => 'birthday', 'cat' => 'Client Care',
        'name' => 'Birthday greeting',
        'desc' => 'Every morning send a happy-birthday email to clients whose birthday is today (auto-repeats each year).',
        'trigger' => ['type' => 'schedule', 'config' => ['cron' => '0 8 * * *', 'object' => '', 'query' => '']],
        'steps' => [
            ['type' => 'loop', 'label' => 'Birthdays today', 'sql' => "SELECT name,prm_email FROM members WHERE prm_email<>'' AND MONTH(dob)=MONTH(CURDATE()) AND DAY(dob)=DAY(CURDATE())", 'steps' => [
                ['type' => 'email', 'label' => 'Birthday email', 'to' => '{{item.prm_email}}', 'subject' => 'Happy Birthday, {{item.name}}!', 'body' => "Happy Birthday {{item.name}}!\n\nWishing you a wonderful day from all of us at {{meta.wf_name}}.\n\nBest wishes,\n{{meta.wf_name}}"],
            ]],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    $T[] = [
        'id' => 'welcome-followup', 'cat' => 'Client Care',
        'name' => 'New client + 7-day follow-up',
        'desc' => 'Welcome new clients, wait 7 days, then send a follow-up to keep them engaged.',
        'trigger' => ['type' => 'record', 'config' => ['object' => 'members', 'event' => 'created']],
        'steps' => [
            ['type' => 'email', 'label' => 'Welcome email', 'to' => '{{client.prm_email}}', 'subject' => 'Welcome to {{meta.wf_name}}!', 'body' => "Hello {{client.name}},\n\nWelcome! We are glad to have you with us.\n\nBest regards,\n{{meta.wf_name}}"],
            ['type' => 'delay', 'label' => 'Wait 7 days', 'minutes' => '10080'],
            ['type' => 'email', 'label' => 'Follow-up email', 'to' => '{{client.prm_email}}', 'subject' => 'Checking in with you', 'body' => "Hello {{client.name}},\n\nIt has been a week since you joined us. We hope everything is going well — reply to this email if you need anything.\n\nRegards,\n{{meta.wf_name}}"],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    /* ── Operations ────────────────────────────────────────────────────── */
    $T[] = [
        'id' => 'bank-statement', 'cat' => 'Operations',
        'name' => 'Bank statement not received',
        'desc' => 'On the 1st of every month chase clients whose bank statement has not been received yet.',
        'trigger' => ['type' => 'schedule', 'config' => ['cron' => '0 9 1 * *', 'object' => '', 'query' => '']],
        'steps' => [
            ['type' => 'loop', 'label' => 'Missing statements', 'sql' => "SELECT b.statement_id,b.statement_month,b.statement_year,m.name,m.prm_email FROM bank_statements b JOIN members m ON m.member_id=b.member_id WHERE b.received=0 AND m.prm_email<>''", 'steps' => [
                ['type' => 'email', 'label' => 'Chase email', 'to' => '{{item.prm_email}}', 'subject' => 'We are still waiting for your statement', 'body' => "Hello {{item.name}},\n\nWe have not yet received your bank statement for {{item.statement_month}}/{{item.statement_year}}. Please send it as soon as possible.\n\nRegards,\n{{meta.wf_name}}"],
                ['type' => 'notify', 'label' => 'Alert team', 'recipients' => 'admins', 'message' => 'Statement missing for {{item.name}} ({{item.statement_month}}/{{item.statement_year}})'],
            ]],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    $T[] = [
        'id' => 'expiring-this-week', 'cat' => 'Operations',
        'name' => 'Expiring services — staff digest',
        'desc' => 'Daily chat summary to admins listing every order expiring in the next 7 days.',
        'trigger' => ['type' => 'schedule', 'config' => ['cron' => '0 8 * * *', 'object' => '', 'query' => '']],
        'steps' => [
            ['type' => 'loop', 'label' => 'Expiring orders', 'sql' => "SELECT o.order_id,o.exp_date,m.name FROM orders o JOIN members m ON m.member_id=o.mem_id WHERE o.order_status=1 AND o.exp_date BETWEEN CURDATE() AND CURDATE()+INTERVAL 7 DAY", 'steps' => [
                ['type' => 'notify', 'label' => 'Notify per order', 'recipients' => 'admins', 'message' => 'Expiring {{item.exp_date}}: order #{{item.order_id}} — {{item.name}}'],
            ]],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    $T[] = [
        'id' => 'daily-status-sweep', 'cat' => 'Maintenance',
        'name' => 'Daily status sweep (housekeeping)',
        'desc' => 'Every night 02:00 marks expired orders past their end date as expired. Review before enabling.',
        'trigger' => ['type' => 'schedule', 'config' => ['cron' => '0 2 * * *', 'object' => '', 'query' => '']],
        'steps' => [
            ['type' => 'sql', 'label' => 'Flag expired orders', 'sql' => 'UPDATE orders SET order_status=0 WHERE order_status=1 AND exp_date < CURDATE()'],
            ['type' => 'log', 'label' => 'Log sweep', 'action' => 'UPDATE', 'object_type' => 'orders', 'object_id' => '0', 'description' => 'Daily status sweep ran'],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    /* ── Staff Alerts ──────────────────────────────────────────────────── */
    $T[] = [
        'id' => 'big-order-alert', 'cat' => 'Staff Alerts',
        'name' => 'Big order alert',
        'desc' => 'When an order above $10,000 is created, notify the whole team in chat immediately.',
        'trigger' => ['type' => 'record', 'config' => ['object' => 'orders', 'event' => 'created']],
        'steps' => [
            ['type' => 'condition', 'label' => 'Large order?', 'condition' => ['and' => [['op' => 'gt', 'f' => 'order.total', 'v' => '10000']]] , 'then' => [
                ['type' => 'notify', 'label' => 'Notify team', 'recipients' => 'admins', 'message' => 'BIG ORDER #{{order.order_id}} — client #{{order.mem_id}} — ${{order.total}}'],
                ['type' => 'log', 'label' => 'Log alert', 'action' => 'CREATE', 'object_type' => 'orders', 'object_id' => '{{order.order_id}}', 'description' => 'Big-order alert sent for ${{order.total}}'],
            ], 'else' => [
                ['type' => 'log', 'label' => 'No action', 'action' => 'CREATE', 'object_type' => 'orders', 'object_id' => '{{order.order_id}}', 'description' => 'Order #{{order.order_id}} below alert threshold'],
            ]],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    $T[] = [
        'id' => 'quotation-notify', 'cat' => 'Staff Alerts',
        'name' => 'New quotation notification',
        'desc' => 'When a quotation is created, notify staff and log it so nobody misses a lead.',
        'trigger' => ['type' => 'record', 'config' => ['object' => 'quotations', 'event' => 'created']],
        'steps' => [
            ['type' => 'notify', 'label' => 'Notify staff', 'recipients' => 'admins', 'message' => 'New quotation #{{quotation.quotation_id}} for {{quotation.name}} — ${{quotation.grand_total}}'],
            ['type' => 'log', 'label' => 'Log quotation', 'action' => 'CREATE', 'object_type' => 'quotations', 'object_id' => '{{quotation.quotation_id}}', 'description' => 'Quotation received for {{quotation.name}}'],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    /* ── Integrations & Webhooks ───────────────────────────────────────── */
    $T[] = [
        'id' => 'webhook-simple', 'cat' => 'Integrations & Webhooks',
        'name' => 'Webhook → log & notify',
        'desc' => 'Expose a secret URL. Anything that POSTs to it logs an activity and alerts the team. Great for connecting other apps to your CRM.',
        'trigger' => ['type' => 'webhook', 'config' => ['token' => '']],
        'steps' => [
            ['type' => 'log', 'label' => 'Log event', 'action' => 'CREATE', 'object_type' => 'members', 'object_id' => '{{object.member_id}}', 'description' => 'Webhook received: {{data.event}}'],
            ['type' => 'notify', 'label' => 'Notify team', 'recipients' => 'admins', 'message' => 'Webhook fired: {{data.event}} (object {{object_type}}:{{object_id}})'],
            ['type' => 'stop', 'label' => 'Done'],
        ],
    ];

    return $T;
}

} /* end function_exists guard */