<?php
return [
 'code'=>'biennial_statements_nys_base_filing_2_years_base',
 'name'=>'Biennial Statements NYS Base filing 2 years base',
 'category'=>'professional',
 'default_price'=>65.00,
 'risk_level'=>'normal',
 'approval_level'=>2,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
