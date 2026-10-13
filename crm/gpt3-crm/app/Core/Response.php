<?php
namespace App\Core;
final class Response {
    public static function redirect(string $url): never { header('Location: '.$url); exit; }
    public static function json(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json'); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
}
