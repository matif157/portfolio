<?php
$root=dirname(__DIR__);$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));$bad=[];$n=0;
foreach($it as $f){if(pathinfo((string)$f,PATHINFO_EXTENSION)==='php'){$n++;exec('php -l '.escapeshellarg((string)$f).' 2>&1',$out,$code);if($code!==0)$bad[(string)$f]=implode("\n",$out);$out=[];}}
echo "PHP files scanned: $n\nSyntax errors: ".count($bad)."\n";foreach($bad as $f=>$m)echo "--- $f\n$m\n";exit($bad?1:0);
