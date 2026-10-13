<?php
return [
 'code'=>'new_keys_for_mail_box',
 'name'=>'NEW KEYS for Mail Box',
 'category'=>'professional',
 'default_price'=>15.00,
 'risk_level'=>'normal',
 'approval_level'=>2,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
