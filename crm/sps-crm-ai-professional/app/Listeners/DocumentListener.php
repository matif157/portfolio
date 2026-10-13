<?php
namespace App\Listeners;
use App\Events\DomainEvent;
final class DocumentListener { public function handle(DomainEvent $event):array{return ['listener'=>'DocumentListener','event'=>$event->name,'handled'=>true];} }
