<?php
return [
 'code'=>'itin_100',
 'name'=>'ITIN',
 'category'=>'professional',
 'default_price'=>325,
 'risk_level'=>'normal',
 'approval_level'=>2,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
