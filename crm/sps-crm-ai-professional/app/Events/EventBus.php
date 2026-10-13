<?php
namespace App\Events;
final class EventBus { private array $listeners=[]; public function subscribe(string $event,callable $listener):void{$this->listeners[$event][]=$listener;} public function dispatch(DomainEvent $event):array{$results=[];foreach($this->listeners[$event->name]??[] as $listener)$results[]=$listener($event);return $results;} }
