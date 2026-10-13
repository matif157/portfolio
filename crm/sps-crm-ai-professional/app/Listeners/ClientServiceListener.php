<?php
namespace App\Listeners;
use App\Events\DomainEvent;
final class ClientServiceListener { public function handle(DomainEvent $event):array{return ['listener'=>'ClientServiceListener','event'=>$event->name,'handled'=>true];} }
