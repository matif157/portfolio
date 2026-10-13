<?php
return [
 'code'=>'n_600',
 'name'=>'N-600',
 'category'=>'immigration',
 'default_price'=>260,
 'risk_level'=>'high',
 'approval_level'=>3,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
