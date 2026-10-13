<?php
return [
 'code'=>'resale_certificate_nys',
 'name'=>'RESALE CERTIFICATE NYS',
 'category'=>'professional',
 'default_price'=>50,
 'risk_level'=>'normal',
 'approval_level'=>2,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
