<?php
namespace App\Listeners;
use App\Events\DomainEvent;
final class DeadlineListener { public function handle(DomainEvent $event):array{return ['listener'=>'DeadlineListener','event'=>$event->name,'handled'=>true];} }
