<?php
namespace App\Services;
use App\Support\Database;
use App\Support\TenantContext;
use App\Support\Json;
final class OperationsOrchestrator {
 public function handle(string $event,array $context=[]):array{
  $result=['event'=>$event,'actions'=>[]];
  $result['actions'][]=(new AutomationService)->dispatch($event,$context);
  if(in_array($event,['NEW_CLIENT','SERVICE_REQUESTED','DOCUMENT_UPLOADED','CLIENT_RESPONDED','DEADLINE_APPROACHING','PAYMENT_RECEIVED','AUTHORITY_RESPONSE_RECEIVED','WORKFLOW_FAILED','CLIENT_INACTIVE'],true)){
   $decision=(new AI\AIOperationsService)->decide($event,$context);$result['decision']=$decision;
  }
  return $result;
 }
 public function requestService(int $clientId,int $serviceId,?int $businessId=null):int{return (new ServiceLifecycleService)->request($clientId,$serviceId,$businessId);}
}
