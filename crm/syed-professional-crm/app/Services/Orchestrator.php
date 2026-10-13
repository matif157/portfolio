<?php
declare(strict_types=1); namespace App\Services;
class Orchestrator { public function __construct(private WorkflowEngine $workflow,private RulesEngine $rules){} public function decide(array $case,array $context=[]):array{$next=$this->workflow->next($case,$context);$rules=$this->rules->evaluate($case,$context);return ['next_stage'=>$next['to'],'actions'=>$rules['actions'],'approval_required'=>$next['requires_human_approval']||$rules['approval_required'],'confidence'=>$rules['confidence']];} }
