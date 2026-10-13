<?php
namespace App\Core;
final class SecureValue {
    public static function encrypt(?string $plain):?string{
        if($plain===null||$plain==='')return null;
        $key=hash('sha256',(string)(getenv('APP_KEY')?:''),true);
        if(getenv('APP_KEY')==='CHANGE_ME'||strlen($key)!==32)throw new \RuntimeException('APP_KEY must be configured before storing sensitive data.');
        $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
        return base64_encode($iv.$tag.$cipher);
    }
    public static function decrypt(?string $payload):?string{
        if(!$payload)return null;$raw=base64_decode($payload,true);if($raw===false||strlen($raw)<28)return null;
        $key=hash('sha256',(string)(getenv('APP_KEY')?:''),true);$iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);
        $plain=openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);return $plain===false?null:$plain;
    }
}
