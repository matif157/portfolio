<?php
namespace App\Listeners;
use App\Events\DomainEvent;
final class ApprovalListener { public function handle(DomainEvent $event):array{return ['listener'=>'ApprovalListener','event'=>$event->name,'handled'=>true];} }
