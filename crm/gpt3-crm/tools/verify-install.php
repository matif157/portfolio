<?php
$checks=['PHP'=>PHP_VERSION,'PDO'=>extension_loaded('pdo')?'ok':'missing','PDO MySQL'=>extension_loaded('pdo_mysql')?'ok':'missing','OpenSSL'=>extension_loaded('openssl')?'ok':'missing','Fileinfo'=>extension_loaded('fileinfo')?'ok':'missing'];foreach($checks as $k=>$v)echo str_pad($k,18).": $v".PHP_EOL;
