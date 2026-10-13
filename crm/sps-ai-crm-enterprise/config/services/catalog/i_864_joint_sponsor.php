<?php
return [
 'code'=>'i_864_joint_sponsor',
 'name'=>'I-864 (Joint Sponsor)',
 'category'=>'immigration',
 'default_price'=>130,
 'risk_level'=>'high',
 'approval_level'=>3,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
