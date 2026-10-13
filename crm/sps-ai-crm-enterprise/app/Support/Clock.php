<?php
namespace App\Support;
use DateTimeImmutable,DateTimeZone;
final class Clock { public static function now():DateTimeImmutable{return new DateTimeImmutable('now',new DateTimeZone(config('app.timezone','UTC')));} }
