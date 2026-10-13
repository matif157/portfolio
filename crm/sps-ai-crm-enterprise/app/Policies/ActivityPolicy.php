<?php
namespace App\Policies;
use App\Services\PermissionService;
final class ActivityPolicy {
 public function view():bool{return (new PermissionService)->can('activities.view');}
}
