<?php
namespace App\Jobs;
use App\Services\RecurringProjectService;
final class RecurringProjectJob{public function handle():int{return (new RecurringProjectService)->process();}}
