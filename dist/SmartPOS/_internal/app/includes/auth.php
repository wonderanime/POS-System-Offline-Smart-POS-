<?php

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    return ['id' => $_SESSION['uid'], 'name' => $_SESSION['uname']];
}

function require_login(): array {
    $u = current_user();
    if (!$u) { header('Location: /login'); exit; }
    return $u;
}

function json_in(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
