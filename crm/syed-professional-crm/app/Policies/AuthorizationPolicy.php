<?php
declare(strict_types=1); namespace App\Policies;
class AuthorizationPolicy { public function can(string $role,string $action):bool{if($role==='Admin')return true;return !in_array($action,['delete_client','submit_filing','approve_tax_return','send_sensitive_document'],true);} }
