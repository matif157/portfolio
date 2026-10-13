<?php
namespace App\Listeners;
use App\Events\DomainEvent;
final class RequirementListener { public function handle(DomainEvent $event):array{return ['listener'=>'RequirementListener','event'=>$event->name,'handled'=>true];} }
