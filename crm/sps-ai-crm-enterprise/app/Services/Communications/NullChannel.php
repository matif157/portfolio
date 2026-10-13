<?php
namespace App\Services\Communications;
use App\Contracts\Communications\ChannelInterface;
final class NullChannel implements ChannelInterface { public function send(array $message):array{return ['status'=>'not_configured','provider'=>'null','message'=>$message];} }
