<?php
namespace App\Core;
final class View {
    public static function render(string $view, array $data=[]): void { extract($data); require APP_ROOT.'/resources/views/'.$view.'.php'; }
    public static function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
