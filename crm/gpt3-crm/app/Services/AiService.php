<?php
namespace App\Services;
final class AiService {
    public function analyze(string $prompt,array $context=[]): array {
        $cfg=require APP_ROOT.'/config/integrations.example.php';
        if(!$cfg['ai']['enabled']||!$cfg['ai']['endpoint']||!$cfg['ai']['api_key']) return ['status'=>'not_configured','confidence'=>0,'answer'=>'AI provider is not configured.'];
        $payload=['model'=>$cfg['ai']['model'],'messages'=>[['role'=>'system','content'=>'You are the SPS CRM operations assistant. Never fabricate legal requirements. Return structured JSON when requested.'],['role'=>'user','content'=>$prompt]],'context'=>$context];
        $ch=curl_init($cfg['ai']['endpoint']);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$cfg['ai']['api_key']],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>30]);$raw=curl_exec($ch);$err=curl_error($ch);curl_close($ch);if($raw===false)return ['status'=>'error','confidence'=>0,'answer'=>$err];$json=json_decode($raw,true);return ['status'=>'ok','confidence'=>0.5,'answer'=>$json['choices'][0]['message']['content']??$raw];
    }
}
