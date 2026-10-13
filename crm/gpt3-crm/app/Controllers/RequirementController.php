<?php
namespace App\Controllers;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Services\RequirementService;

final class RequirementController
{
    public function list(int $clientServiceId): never
    {
        Auth::requireLogin();
        Response::json(['data'=>(new RequirementService(Database::pdo()))->missingForClientService($clientServiceId)]);
    }

    public function complete(int $id): never
    {
        Auth::requireLogin();
        (new RequirementService(Database::pdo()))->complete($id,(int)Auth::id(),$_POST['note'] ?? null);
        Response::json(['success'=>true]);
    }
}
