<?php
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$data  = json_decode(file_get_contents('php://input'), true) ?? [];
$fonte = trim($data['fonte'] ?? $_POST['fonte'] ?? 'all');

$locks = [
    'ml'       => __DIR__ . '/../storage/bot_ml.lock',
    'shopee'   => __DIR__ . '/../storage/bot_shopee.lock',
    'completo' => __DIR__ . '/../storage/bot.lock',
];

if ($fonte === '') $fonte = 'completo';
if ($fonte === 'all') {
    $targets = $locks;
} elseif (isset($locks[$fonte])) {
    $targets = [$fonte => $locks[$fonte]];
} else {
    jsonResponse(['ok' => false, 'error' => 'Fonte inválida.'], 400);
}

$limpos = [];
$avisos = [];

foreach ($targets as $nome => $lock) {
    if (!file_exists($lock)) {
        $limpos[] = "$nome: lock já estava limpo";
        continue;
    }

    $pid = (int)trim((string)@file_get_contents($lock));
    if ($pid > 0 && _pidDoBot($pid, $nome)) {
        _pararPid($pid);
        $avisos[] = "$nome: processo PID $pid recebeu ordem de parada";
    }

    if (@unlink($lock)) {
        $limpos[] = "$nome: lock removido";
    } else {
        jsonResponse(['ok' => false, 'error' => "Não foi possível remover $lock. Verifique permissões."]);
    }
}

$message = implode(' | ', array_merge($avisos, $limpos));
jsonResponse(['ok' => true, 'message' => $message ?: 'Nenhum lock encontrado.']);

function _pidDoBot(int $pid, string $fonte): bool {
    if ($pid <= 0) return false;

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("wmic process where ProcessId=$pid get CommandLine /value 2>NUL", $out);
        $cmd = implode(' ', $out);
    } else {
        $cmdline = "/proc/$pid/cmdline";
        if (!file_exists($cmdline)) return false;
        $cmd = (string)@file_get_contents($cmdline);
        $cmd = str_replace("\0", " ", $cmd);
    }

    if (!str_contains($cmd, 'main.py') || !str_contains($cmd, 'viana')) return false;
    if ($fonte === 'completo') return true;
    return str_contains($cmd, "--fonte $fonte") || str_contains($cmd, "--fonte=$fonte");
}

function _pararPid(int $pid): void {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        @exec("taskkill /PID $pid /T /F 2>NUL");
        return;
    }
    if (function_exists('posix_kill')) {
        @posix_kill($pid, 15);
        usleep(500000);
        @posix_kill($pid, 9);
        return;
    }
    @exec("kill -TERM $pid 2>/dev/null");
    usleep(500000);
    @exec("kill -KILL $pid 2>/dev/null");
}
