<?php
declare(strict_types=1); namespace App\Services;
class TaskEngine { public function fromDecision(array $decision):array{$tasks=[];foreach($decision['actions'] as $a){$tasks[]=['type'=>$a['type'],'status'=>'pending','payload'=>$a];}return $tasks;} }
