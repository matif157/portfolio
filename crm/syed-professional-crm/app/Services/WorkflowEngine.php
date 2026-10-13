<?php
declare(strict_types=1); namespace App\Services;
class WorkflowEngine { public function next(array $case,array $context=[]):array{$stage=$case['stage']??'intake';$map=['intake'=>'assessment','assessment'=>'processing','processing'=>'review','review'=>'completed','completed'=>'completed'];return ['from'=>$stage,'to'=>$map[$stage]??'intake','requires_human_approval'=>in_array($stage,['review'],true),'reason'=>'Derived from workflow stage and configured rules.'];} }
