<?php
/**
 * api/oferta_enviar.php — Envia uma oferta para todos os grupos ativos.
 * Se mensagem_ia estiver vazia e usar_ia=0, gera pelo template do Config.
 */
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método inválido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int)($input['id'] ?? 0);
if (!$id) jsonResponse(['ok' => false, 'error' => 'ID inválido'], 400);

$db = getDB();

// Busca a oferta (qualquer status exceto rejeitada — enviada pode ser reenviada)
$stmt = $db->prepare("SELECT * FROM ofertas WHERE id = ? AND status != 'rejeitada'");
$stmt->execute([$id]);
$o = $stmt->fetch();

if (!$o) {
    jsonResponse(['ok' => false, 'error' => 'Oferta não encontrada ou rejeitada.'], 404);
}

// ── Garante que há texto para enviar ─────────────────────────────────────────
$texto = $o['mensagem_ia'] ?? '';

if (empty($texto)) {
    $usar_ia = getConfig('usar_ia') !== '0';

    if (!$usar_ia) {
        // Gera template direto no PHP sem precisar do Python
        $EMOJI_MAP = [
            'whey' => '🥤', 'proteina' => '💪', 'creatina' => '⚡',
            'pre treino' => '🔥', 'pre-treino' => '🔥', 'vitamina' => '🌿',
            'colageno' => '✨', 'luva' => '🥊', 'haltere' => '🏋️',
            'esteira' => '🏃', 'bicicleta' => '🚴', 'roupa' => '👕',
            'shorts' => '👟', 'tenis' => '👟', 'legging' => '👟',
            'bcaa' => '💊', 'omega' => '🌿', 'suplemento' => '💪',
            'massa' => '💪', 'whey' => '🥤', 'fit' => '🏃',
        ];
        $emoji = '🏋️';
        $nome_lower = mb_strtolower($o['nome']);
        foreach ($EMOJI_MAP as $palavra => $e) {
            if (str_contains($nome_lower, $palavra)) { $emoji = $e; break; }
        }

        $template = getConfig('mensagem_padrao') ?: "{EMOJI} *{NOME}*\n\n~~R\$ {PRECO_DE}~~ por apenas *R\$ {PRECO_POR}* 🏷️ *{DESCONTO}% OFF*\n\n🔗 link de afiliado — comprar por aqui me ajuda sem custo extra pra você\n👉 {LINK}";

        $preco_de  = $o['preco_de'] > 0 ? number_format($o['preco_de'],  2, ',', '.') : '';
        $preco_por = number_format($o['preco_por'], 2, ',', '.');

        $texto = str_replace(
            ['{EMOJI}', '{NOME}',    '{PRECO_DE}', '{PRECO_POR}', '{DESCONTO}',        '{LINK}'],
            [$emoji,    $o['nome'],  $preco_de,    $preco_por,    $o['desconto_pct'],   '{LINK}'],
            $template
        );

        // Salva o texto gerado para não repetir
        $db->prepare("UPDATE ofertas SET mensagem_ia = ?, status = 'pronta' WHERE id = ?")
           ->execute([$texto, $id]);

    } else {
        // IA ligada mas texto não gerado — tenta gerar agora via gerador.py para esta oferta
        $python = 'python';
        $script = realpath(__DIR__ . '/../bot/gerador.py');
        if ($script) {
            // Muda status para 'nova' temporariamente para o gerador processar
            $db->prepare("UPDATE ofertas SET status = 'nova' WHERE id = ?")->execute([$id]);
            exec("\"$python\" \"$script\" 2>&1");
            // Re-busca após geração
            $stmt->execute([$id]);
            $o = $stmt->fetch();
            $texto = $o['mensagem_ia'] ?? '';
        }
        if (empty($texto)) {
            jsonResponse(['ok' => false, 'error' => 'IA falhou ao gerar texto. Tente desligar a IA no Config e usar o Template.']);
        }
    }
}

// ── Substitui {LINK} pelo link real ──────────────────────────────────────────
$texto_final = str_replace('{LINK}', $o['url_afiliado'], $texto);

// ── Busca grupos ativos ───────────────────────────────────────────────────────
$grupos = $db->query("SELECT * FROM grupos WHERE ativo = 1")->fetchAll();
if (empty($grupos)) {
    jsonResponse(['ok' => false, 'error' => 'Nenhum grupo ativo configurado em Grupos.'], 422);
}

$evo_url      = rtrim(getConfig('evolution_url'), '/');
$evo_instance = getConfig('evolution_instance');
$evo_apikey   = getConfig('evolution_apikey');

if (!$evo_url || !$evo_instance || !$evo_apikey) {
    jsonResponse(['ok' => false, 'error' => 'Evolution API não configurada em Config.'], 422);
}

$headers  = ['Content-Type: application/json', "apikey: $evo_apikey"];
$enviados = 0;
$erros    = [];

foreach ($grupos as $grupo) {
    $jid = $grupo['group_jid'];

    // Decide entre imagem+legenda ou texto puro
    if (!empty($o['imagem_path']) && file_exists($o['imagem_path'])) {
        $url_api = "$evo_url/message/sendMedia/$evo_instance";
        $payload = [
            'number'    => $jid,
            'mediatype' => 'image',
            'mimetype'  => 'image/jpeg',
            'caption'   => $texto_final,
            'media'     => base64_encode(file_get_contents($o['imagem_path'])),
            'fileName'  => 'oferta.jpg',
        ];
    } elseif (!empty($o['imagem_url'])) {
        $url_api = "$evo_url/message/sendMedia/$evo_instance";
        $payload = [
            'number'    => $jid,
            'mediatype' => 'image',
            'mimetype'  => 'image/jpeg',
            'caption'   => $texto_final,
            'media'     => $o['imagem_url'],
            'fileName'  => 'oferta.jpg',
        ];
    } else {
        $url_api = "$evo_url/message/sendText/$evo_instance";
        $payload = ['number' => $jid, 'text' => $texto_final];
    }

    $ch = curl_init($url_api);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
    ]);
    $resp = json_decode(curl_exec($ch), true);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (isset($resp['key']) || ($http >= 200 && $http < 300)) {
        $enviados++;
    } else {
        $erros[] = ($resp['message'] ?? "HTTP $http");
    }
}

if ($enviados > 0) {
    $db->prepare("UPDATE ofertas SET status='enviada', enviado_em=datetime('now','localtime') WHERE id=?")
       ->execute([$id]);
    jsonResponse(['ok' => true, 'message' => "✅ Enviada para $enviados grupo(s)!", 'enviados' => $enviados]);
} else {
    $detalhe = !empty($erros) ? implode(' | ', $erros) : 'Verifique a Evolution API.';
    jsonResponse(['ok' => false, 'error' => "Falha ao enviar: $detalhe"]);
}
