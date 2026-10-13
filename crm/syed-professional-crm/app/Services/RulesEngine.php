<?php
declare(strict_types=1); namespace App\Services;
class RulesEngine { public function evaluate(array $case,array $context=[]):array{$missing=$context['missing_requirements']??[];$actions=[];if($missing)$actions[]=['type'=>'request_documents','items'=>$missing];if(($context['deadline_days']??999)<=3)$actions[]=['type'=>'deadline_alert'];return ['actions'=>$actions,'approval_required'=>($context['requires_professional_review']??false)===true,'confidence'=>$missing?0.92:0.98];} }
