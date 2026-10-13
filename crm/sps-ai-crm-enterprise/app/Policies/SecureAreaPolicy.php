<?php
namespace App\Policies;
use App\Services\PermissionService;
final class SecureAreaPolicy {
 public function view():bool{return (new PermissionService)->can('secure.view');}
}
