<?php
namespace App\Controllers;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Services\ServiceStatusService;
use App\Services\DocumentRequirementService;

final class ServiceOperationsController
{
    public function status(int $clientServiceId): never
    {
        Auth::requireLogin();
        Response::json([
            'data'=>(new ServiceStatusService(Database::pdo()))->summary($clientServiceId)
        ]);
    }

    public function attachDocument(): never
    {
        Auth::requireLogin();
        $documentId=(int)($_POST['document_id']??0);
        $requirementId=(int)($_POST['client_service_requirement_id']??0);
        if($documentId<1 || $requirementId<1){
            Response::json(['error'=>'document_id and client_service_requirement_id are required'],422);
        }

        try {
            (new DocumentRequirementService(Database::pdo()))->attach(
                $documentId,$requirementId,(int)Auth::id()
            );
            Response::json(['success'=>true]);
        } catch(\Throwable $e) {
            Response::json(['error'=>'Unable to attach document'],500);
        }
    }
}
