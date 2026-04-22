<?php
/**
 * Script de cron job — disparar agendamentos pendentes.
 *
 * Windows: Agendador de Tarefas → a cada 1 minuto
 *   Programa: C:\xampp\php\php.exe
 *   Argumentos: C:\xampp\htdocs\viana\cron\dispatch.php
 */

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/evolution.php';

$db = getDB();

$diaSemana  = (int)date('w');        // 0=Dom ... 6=Sáb
$horaAtual  = date('H:i');           // ex: "14:30"
$dataHoje   = date('Y-m-d');

$agendamentos = $db->query("
    SELECT a.*, l.nome_produto, l.url_afiliado, l.mensagem as msg_personalizada,
           g.group_jid, g.nome as nome_grupo
    FROM agendamentos a
    JOIN links l ON l.id = a.link_id AND l.ativo = 1
    JOIN grupos g ON g.id = a.grupo_id AND g.ativo = 1
    WHERE a.ativo = 1
      AND (a.ultimo_envio IS NULL OR a.ultimo_envio != '$dataHoje')
      AND a.horario <= '$horaAtual'
")->fetchAll();

if (empty($agendamentos)) {
    echo "[" . date('Y-m-d H:i:s') . "] Nenhum disparo pendente.\n";
    exit;
}

$api = new EvolutionAPI();

if (!$api->isConfigured()) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: Evolution API não configurada.\n";
    exit(1);
}

foreach ($agendamentos as $ag) {
    $dias = json_decode($ag['dias_semana'], true) ?? [];

    if (!in_array($diaSemana, $dias)) {
        continue;
    }

    $texto = $ag['msg_personalizada'] ?: msgTemplate($ag['nome_produto'], $ag['url_afiliado']);

    $result = $api->sendText($ag['group_jid'], $texto);

    $status   = $result['ok'] ? 'sucesso' : 'erro';
    $erroMsg  = $result['ok'] ? null : $result['error'];

    $db->prepare('INSERT INTO historico (link_id, grupo_id, status, mensagem_erro) VALUES (?, ?, ?, ?)')
       ->execute([$ag['link_id'], $ag['grupo_id'], $status, $erroMsg]);

    $db->prepare('UPDATE agendamentos SET ultimo_envio = ? WHERE id = ?')
       ->execute([$dataHoje, $ag['id']]);

    $ts = date('Y-m-d H:i:s');
    if ($result['ok']) {
        echo "[$ts] OK — \"{$ag['nome_produto']}\" → {$ag['nome_grupo']}\n";
    } else {
        echo "[$ts] ERRO — \"{$ag['nome_produto']}\" → {$ag['nome_grupo']}: {$result['error']}\n";
    }
}
