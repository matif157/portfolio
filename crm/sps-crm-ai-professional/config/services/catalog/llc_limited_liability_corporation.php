<?php
return [
 'code'=>'llc_limited_liability_corporation',
 'name'=>'LLC LIMITED LIABILITY CORPORATION',
 'category'=>'professional',
 'default_price'=>null,
 'risk_level'=>'normal',
 'approval_level'=>2,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
