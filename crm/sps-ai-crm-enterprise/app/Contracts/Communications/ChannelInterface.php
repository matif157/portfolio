<?php
namespace App\Contracts\Communications;
interface ChannelInterface { public function send(array $message):array; }
