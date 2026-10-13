<?php
namespace App\Policies;
use App\Services\PermissionService;
final class ClientPolicy {
 public function view():bool{return (new PermissionService)->can('clients.view');}
}
