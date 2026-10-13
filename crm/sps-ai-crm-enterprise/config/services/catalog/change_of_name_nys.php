<?php
return [
 'code'=>'change_of_name_nys',
 'name'=>'Change of Name NYS',
 'category'=>'professional',
 'default_price'=>150,
 'risk_level'=>'normal',
 'approval_level'=>2,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
