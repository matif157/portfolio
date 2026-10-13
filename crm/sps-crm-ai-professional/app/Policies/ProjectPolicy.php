<?php
namespace App\Policies;
use App\Services\PermissionService;
final class ProjectPolicy {
 public function view():bool{return (new PermissionService)->can('projects.view');}
}
