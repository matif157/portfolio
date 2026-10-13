<?php
namespace App\Policies;
use App\Services\PermissionService;
final class BillingPolicy {
 public function view():bool{return (new PermissionService)->can('billing.view');}
}
