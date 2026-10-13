<?php
return [
 'code'=>'domain_hosting_estimated_for_first_year',
 'name'=>'Domain & Hosting (Estimated for first year)',
 'category'=>'professional',
 'default_price'=>100,
 'risk_level'=>'normal',
 'approval_level'=>2,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
