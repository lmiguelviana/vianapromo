<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'criar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $linkId  = (int)($input['link_id'] ?? 0);
    $grupoId = (int)($input['grupo_id'] ?? 0);
    $dias    = $input['dias_semana'] ?? [0,1,2,3,4,5,6];
    $horario = trim($input['horario'] ?? '');

    if (!$linkId || !$grupoId || !$horario) {
        jsonResponse(['ok' => false, 'error' => 'Link, grupo e horário são obrigatórios.'], 400);
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $horario)) {
        jsonResponse(['ok' => false, 'error' => 'Horário inválido (use HH:MM).'], 400);
    }

    $db->prepare('INSERT INTO agendamentos (link_id, grupo_id, dias_semana, horario) VALUES (?, ?, ?, ?)')
       ->execute([$linkId, $grupoId, json_encode($dias), $horario]);

    jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
}

if ($method === 'POST' && $action === 'excluir') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $db->prepare('DELETE FROM agendamentos WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

if ($method === 'POST' && $action === 'toggle') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $db->prepare('UPDATE agendamentos SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Ação inválida'], 400);
