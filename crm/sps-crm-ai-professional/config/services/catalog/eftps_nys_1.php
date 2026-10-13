<?php
return [
 'code'=>'eftps_nys_1',
 'name'=>'EFTPS & NYS - 1',
 'category'=>'professional',
 'default_price'=>0,
 'risk_level'=>'normal',
 'approval_level'=>2,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
