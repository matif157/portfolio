<?php
if (!function_exists('report_base_url')) {
    function report_base_url()
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $dir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
        $dir = trim(str_replace('\\', '/', $dir), '/');
        return $scheme . '://' . $host . ($dir !== '' ? '/' . $dir : '');
    }
}

if (!function_exists('report_logo_src')) {
    function report_logo_src($relative)
    {
        $file = dirname(__DIR__) . '/' . $relative;
        if (!is_file($file)) {
            return '';
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = $ext === 'jpg' ? 'image/jpeg' : ('image/' . $ext);
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file));
    }
}

if (!function_exists('report_invoice_view_url')) {
    function report_invoice_view_url($filelink)
    {
        $name = basename((string) $filelink);
        if ($name === '' || $name === '.' || $name === '..') {
            return '#';
        }
        return report_base_url() . '/invoices/' . rawurlencode($name);
    }
}