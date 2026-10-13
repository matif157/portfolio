<?php
return [
 'code'=>'vape_lic_new_york',
 'name'=>'Vape Lic New York',
 'category'=>'professional',
 'default_price'=>425,
 'risk_level'=>'normal',
 'approval_level'=>2,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
