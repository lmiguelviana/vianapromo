<?php
/**
 * api/log_tail.php — Retorna as últimas linhas do bot.log em JSON + HTML colorido.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

$logFile = __DIR__ . '/../storage/bot.log';
$tamanho = 0;
$html    = '';

if (file_exists($logFile)) {
    $tamanho = filesize($logFile);
    $raw     = file_get_contents($logFile);
    $raw     = str_replace(["\r\n", "\r"], "\n", $raw);
    $raw     = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
    $linhas  = explode("\n", $raw);
    $linhas  = array_slice($linhas, -500);
    $txt     = implode("\n", $linhas);

    // Colorize
    $txt = htmlspecialchars($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $txt = preg_replace('/\[(ERROR)\]/',   '<span style="color:#f87171">[$1]</span>',   $txt);
    $txt = preg_replace('/\[(WARNING)\]/', '<span style="color:#fbbf24">[$1]</span>',   $txt);
    $txt = preg_replace('/\[(INFO)\]/',    '<span style="color:#34d399">[$1]</span>',   $txt);
    $txt = preg_replace('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', '<span style="color:#6b7280">$1</span>', $txt);
    $html = $txt;
} else {
    $html = "Nenhum log encontrado.\nRode o bot para começar.";
}

jsonResponse(['size' => $tamanho, 'html' => $html]);
