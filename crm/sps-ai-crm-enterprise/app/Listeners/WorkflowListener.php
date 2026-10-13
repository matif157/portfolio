<?php
namespace App\Listeners;
use App\Events\DomainEvent;
final class WorkflowListener { public function handle(DomainEvent $event):array{return ['listener'=>'WorkflowListener','event'=>$event->name,'handled'=>true];} }
