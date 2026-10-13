<?php
namespace App\Listeners;
use App\Events\DomainEvent;
final class CommunicationListener { public function handle(DomainEvent $event):array{return ['listener'=>'CommunicationListener','event'=>$event->name,'handled'=>true];} }
