<?php
namespace App\Listeners;
use App\Events\DomainEvent;
final class ExceptionListener { public function handle(DomainEvent $event):array{return ['listener'=>'ExceptionListener','event'=>$event->name,'handled'=>true];} }
