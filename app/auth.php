<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['usuario_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Sessão expirada. Faça login novamente.']);
            exit;
        }
        require_once __DIR__ . '/helpers.php';
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . BASE . '/login?redirect=' . $redirect);
        exit;
    }
}

function currentUser(): array {
    return [
        'id'    => $_SESSION['usuario_id']    ?? 0,
        'nome'  => $_SESSION['usuario_nome']  ?? '',
        'email' => $_SESSION['usuario_email'] ?? '',
    ];
}

requireLogin();
