<?php
namespace App\Contracts\Documents;
interface DocumentStorageInterface { public function put(string $key,string $contents):string; public function get(string $key):string; public function delete(string $key):void; }
