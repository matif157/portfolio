<?php
declare(strict_types=1); namespace App\Services;
class DeadlineEngine { public function classify(?string $due):string{if(!$due)return 'unknown';$d=(new \DateTimeImmutable($due))->diff(new \DateTimeImmutable('today'))->invert? -1:1;$days=(int)(new \DateTimeImmutable('today'))->diff(new \DateTimeImmutable($due))->format('%r%a');return $days<0?'overdue':($days<=3?'urgent':($days<=14?'soon':'normal'));} }
