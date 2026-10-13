<?php
namespace App\Support;
final class TenantContext {
 public static function id(): int { return (int)(Session::get('tenant_id') ?: 1); }
 public static function set(int $id): void { Session::set('tenant_id',$id); }
}
