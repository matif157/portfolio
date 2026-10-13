<?php
function validate_input(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitize_filename(string $filename): string {
    return preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($filename));
}
