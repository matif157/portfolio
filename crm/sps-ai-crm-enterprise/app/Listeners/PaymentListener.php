<?php
namespace App\Listeners;
use App\Events\DomainEvent;
final class PaymentListener { public function handle(DomainEvent $event):array{return ['listener'=>'PaymentListener','event'=>$event->name,'handled'=>true];} }
