<?php
return [
 'code'=>'st_330_for_sales_tax',
 'name'=>'ST-330 For SALES TAX',
 'category'=>'tax',
 'default_price'=>0,
 'risk_level'=>'high',
 'approval_level'=>3,
 'workflow'=>['intake','eligibility','information','documents','validation','professional_review','client_confirmation','preparation','submission','response','completion','follow_up'],
 'automation'=>['progressive_collection'=>true,'missing_item_reminders'=>true,'ai_document_classification'=>true,'human_approval_required'=>true],
];
