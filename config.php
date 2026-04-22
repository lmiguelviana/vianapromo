<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/evolution.php';

$msg     = '';
$msgType = 'success';

// ── Salvar Evolution API ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_evolution'])) {
    setConfig('evolution_url',      trim($_POST['evolution_url']      ?? ''));
    setConfig('evolution_apikey',   trim($_POST['evolution_apikey']   ?? ''));
    setConfig('evolution_instance', trim($_POST['evolution_instance'] ?? ''));
    $msg = 'Configurações da Evolution API salvas!';
}

// ── Testar Evolution API ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar'])) {
    setConfig('evolution_url',      trim($_POST['evolution_url']      ?? ''));
    setConfig('evolution_apikey',   trim($_POST['evolution_apikey']   ?? ''));
    setConfig('evolution_instance', trim($_POST['evolution_instance'] ?? ''));

    $api    = new EvolutionAPI();
    $result = $api->testConnection();

    if ($result['ok']) {
        $state   = $result['data']['instance']['state'] ?? $result['data']['state'] ?? 'open';
        $msg     = "✅ Conexão OK! Estado: {$state}";
    } else {
        $msg     = 'Erro: ' . $result['error'];
        $msgType = 'error';
    }
}

// ── Salvar configurações do Bot ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_bot'])) {
    setConfig('ml_client_id',       trim($_POST['ml_client_id']      ?? ''));
    setConfig('ml_client_secret',   trim($_POST['ml_client_secret']  ?? ''));
    setConfig('ml_partner_id',      trim($_POST['ml_partner_id']     ?? ''));
    setConfig('openrouter_apikey',  trim($_POST['openrouter_apikey'] ?? ''));
    setConfig('openrouter_model',   trim($_POST['openrouter_model']  ?? 'minimax/minimax-01:free'));
    setConfig('usar_ia',            isset($_POST['usar_ia']) ? '1' : '0');
    setConfig('mensagem_padrao',    trim($_POST['mensagem_padrao'] ?? ''));
    setConfig('bot_desconto_minimo',trim($_POST['bot_desconto_minimo'] ?? '10'));
    setConfig('bot_preco_maximo',   trim($_POST['bot_preco_maximo']   ?? '500'));
    $msg = 'Configurações do Bot salvas!';
}

// Lê todas as configs
$evo_url      = getConfig('evolution_url');
$evo_apikey   = getConfig('evolution_apikey');
$evo_instance = getConfig('evolution_instance');

$ml_id        = getConfig('ml_client_id');
$ml_secret    = getConfig('ml_client_secret');
$ml_partner   = getConfig('ml_partner_id');
$ml_token     = getConfig('ml_access_token');
$ml_expires   = (int)getConfig('ml_token_expires');
$ml_user_id   = getConfig('ml_user_id');
$ml_conectado = $ml_token !== '' && $ml_expires > time();
$or_key       = getConfig('openrouter_apikey');
$or_model     = getConfig('openrouter_model') ?: 'minimax/minimax-01:free';
$usar_ia      = getConfig('usar_ia') !== '0'; // default: ativado
$msg_padrao   = getConfig('mensagem_padrao') ?: "{EMOJI} *{NOME}*\n\n~~R\$ {PRECO_DE}~~ por apenas *R\$ {PRECO_POR}* 🏷️ *{DESCONTO}% OFF*\n\n🔗 link de afiliado — comprar por aqui me ajuda sem custo extra pra você\n👉 {LINK}";
$desconto_min = getConfig('bot_desconto_minimo') ?: '10';
$preco_max    = getConfig('bot_preco_maximo')    ?: '500';

// URL de autorização ML
$ml_auth_url = 'https://auth.mercadolivre.com.br/authorization?response_type=code'
    . '&client_id=' . urlencode($ml_id)
    . '&redirect_uri=' . urlencode('https://www.google.com/');

// Modelos disponíveis no OpenRouter
$modelos = [
    // Gratuitos
    'minimax/minimax-m2.5:free'                   => ['label' => 'MiniMax M2.5 (GRÁTIS)',     'badge' => 'bg-emerald-100 text-emerald-800', 'grupo' => 'Gratuitos'],
    'openai/gpt-oss-120b:free'                    => ['label' => 'GPT OSS 120B (GRÁTIS)',     'badge' => 'bg-emerald-100 text-emerald-800', 'grupo' => 'Gratuitos'],
    'nvidia/nemotron-nano-12b-v2-vl:free'         => ['label' => 'Nvidia Nemotron (GRÁTIS)',  'badge' => 'bg-emerald-100 text-emerald-800', 'grupo' => 'Gratuitos'],
    'z-ai/glm-4.5-air:free'                       => ['label' => 'GLM 4.5 Air (GRÁTIS)',      'badge' => 'bg-emerald-100 text-emerald-800', 'grupo' => 'Gratuitos'],
    'minimax/minimax-01:free'                     => ['label' => 'MiniMax 01 (GRÁTIS)',       'badge' => 'bg-emerald-100 text-emerald-800', 'grupo' => 'Gratuitos'],
    'moonshotai/moonlight-16b-a3b-instruct:free'  => ['label' => 'Kimi (Moonshot) (GRÁTIS)',  'badge' => 'bg-emerald-100 text-emerald-800', 'grupo' => 'Gratuitos'],
    // Pagos / Muito baratos
    'deepseek/deepseek-chat-v3-0324'              => ['label' => 'DeepSeek V3 — ~R$0,01/dia', 'badge' => 'bg-blue-100 text-blue-800',       'grupo' => 'Baratos'],
    'google/gemini-flash-1.5'                     => ['label' => 'Gemini Flash 1.5',           'badge' => 'bg-blue-100 text-blue-800',       'grupo' => 'Baratos'],
    'meta-llama/llama-3.3-70b-instruct'           => ['label' => 'LLaMA 3.3 70B',             'badge' => 'bg-blue-100 text-blue-800',       'grupo' => 'Baratos'],
    // Premium
    'anthropic/claude-sonnet-4-5'                 => ['label' => 'Claude Sonnet 4.5',          'badge' => 'bg-amber-100 text-amber-800',     'grupo' => 'Premium'],
    'openai/gpt-4o-mini'                          => ['label' => 'GPT-4o Mini',                'badge' => 'bg-amber-100 text-amber-800',     'grupo' => 'Premium'],
];

layoutStart('config', 'Configurações');
toast();
?>

<div class="max-w-2xl space-y-6">

<?php if ($msg): ?>
    <div class="px-4 py-3 rounded-lg text-sm font-medium <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<!-- ══ Seção 1: Evolution API ══════════════════════════════════════════════ -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center">
            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Evolution API — WhatsApp</h2>
            <p class="text-xs text-gray-500">Conexão com o seu número de WhatsApp</p>
        </div>
    </div>
    <form method="POST" class="p-6 space-y-4">
        <div>
            <label class="label">URL da API</label>
            <input type="url" name="evolution_url" value="<?= htmlspecialchars($evo_url) ?>"
                placeholder="https://api.seudominio.com" class="input">
            <p class="text-xs text-gray-400 mt-1">Sem barra no final</p>
        </div>
        <div>
            <label class="label">API Key Global</label>
            <input type="text" name="evolution_apikey" value="<?= htmlspecialchars($evo_apikey) ?>"
                placeholder="sua-api-key-global" class="input">
        </div>
        <div>
            <label class="label">Nome da Instância</label>
            <input type="text" name="evolution_instance" value="<?= htmlspecialchars($evo_instance) ?>"
                placeholder="minha-instancia" class="input">
        </div>
        <div class="flex gap-3 pt-1">
            <button type="submit" name="salvar_evolution" class="btn-primary">Salvar</button>
            <button type="submit" name="testar"
                class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-5 py-2 rounded-lg transition">
                Testar Conexão
            </button>
        </div>
    </form>
</div>

<!-- ══ Seção 2: Bot Automático ═════════════════════════════════════════════ -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
        <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center">
            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H4a2 2 0 01-2-2V5a2 2 0 012-2h16a2 2 0 012 2v10a2 2 0 01-2 2h-1"/>
            </svg>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Bot Automático</h2>
            <p class="text-xs text-gray-500">Mercado Livre · OpenRouter IA · Filtros de oferta</p>
        </div>
    </div>

    <form method="POST" class="p-6 space-y-5">

        <!-- Mercado Livre -->
        <div>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Mercado Livre</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Client ID</label>
                    <input type="text" name="ml_client_id" value="<?= htmlspecialchars($ml_id) ?>"
                        placeholder="123456789" class="input">
                </div>
                <div>
                    <label class="label">Client Secret</label>
                    <input type="password" name="ml_client_secret" value="<?= htmlspecialchars($ml_secret) ?>"
                        placeholder="••••••••" class="input">
                </div>
            </div>
            <div class="mt-3">
                <label class="label">Partner ID (Afiliado)</label>
                <input type="text" name="ml_partner_id" value="<?= htmlspecialchars($ml_partner) ?>"
                    placeholder="Seu ID do programa de afiliados ML" class="input">
                <p class="text-xs text-gray-400 mt-1">
                    Encontre em <a href="https://afiliados.mercadolivre.com.br" target="_blank" class="text-emerald-600 hover:underline">afiliados.mercadolivre.com.br</a> → Meu painel
                </p>
            </div>
        </div>

        <hr class="border-gray-100">

        <!-- Modo de geração de texto -->
        <div>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Geração de Texto</h3>

            <!-- Toggle IA vs Template -->
            <label class="flex items-center justify-between p-4 border rounded-xl cursor-pointer mb-4
                <?= $usar_ia ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200 bg-gray-50' ?>">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Usar IA para criar mensagens</p>
                    <p class="text-xs text-gray-500 mt-0.5">Desligado: usa modelo de mensagem padrão abaixo</p>
                </div>
                <div class="relative">
                    <input type="checkbox" name="usar_ia" id="usar_ia" value="1" <?= $usar_ia ? 'checked' : '' ?>
                        onchange="toggleIA(this.checked)" class="sr-only">
                    <div id="toggle-track" class="w-11 h-6 rounded-full transition-colors <?= $usar_ia ? 'bg-emerald-500' : 'bg-gray-300' ?>">
                        <div id="toggle-thumb" class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform <?= $usar_ia ? 'translate-x-6' : 'translate-x-1' ?>"></div>
                    </div>
                </div>
            </label>

            <!-- Mensagem padrão (sem IA) -->
            <div id="bloco-template" class="mb-4 <?= $usar_ia ? 'hidden' : '' ?>">
                <label class="label">Modelo de mensagem padrão</label>
                <textarea name="mensagem_padrao" rows="6" class="input font-mono text-xs"
                    placeholder="{EMOJI} *{NOME}*&#10;~~R$ {PRECO_DE}~~ por *R$ {PRECO_POR}* — {DESCONTO}% OFF&#10;👉 {LINK}"><?= htmlspecialchars($msg_padrao) ?></textarea>
                <p class="text-xs text-gray-400 mt-1">Variáveis: <code>{NOME}</code> <code>{PRECO_DE}</code> <code>{PRECO_POR}</code> <code>{DESCONTO}</code> <code>{LINK}</code> <code>{EMOJI}</code></p>
            </div>

            <!-- OpenRouter / IA -->
            <div id="bloco-ia" class="<?= $usar_ia ? '' : 'hidden' ?>">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">OpenRouter — Modelo de IA</h3>
            <div class="mb-3">
                <label class="label">API Key</label>
                <input type="password" name="openrouter_apikey" value="<?= htmlspecialchars($or_key) ?>"
                    placeholder="sk-or-..." class="input">
                <div class="flex items-center gap-3 mt-2">
                    <p class="text-xs text-gray-400 flex-1">
                        Crie em <a href="https://openrouter.ai/keys" target="_blank" class="text-emerald-600 hover:underline">openrouter.ai/keys</a> — gratuito
                    </p>
                    <button type="button" onclick="testarIA()"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Testar IA
                    </button>
                </div>
                <div id="ia-test-result" class="hidden mt-2 p-3 rounded-lg text-xs font-medium"></div>
            </div>

            <div>
                <label class="label">Modelo de IA</label>
                <div class="grid gap-2 mt-1">
                    <?php
                    $grupos_modelo = ['Gratuitos' => [], 'Baratos' => [], 'Premium' => []];
                    foreach ($modelos as $id => $m) {
                        $grupos_modelo[$m['grupo']][$id] = $m;
                    }
                    foreach ($grupos_modelo as $grupo_nome => $grupo_modelos):
                    ?>
                        <p class="text-xs font-semibold text-gray-400 mt-1"><?= $grupo_nome ?></p>
                        <?php foreach ($grupo_modelos as $model_id => $m): ?>
                            <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition
                                <?= $or_model === $model_id ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' ?>">
                                <input type="radio" name="openrouter_model" value="<?= $model_id ?>"
                                    <?= $or_model === $model_id ? 'checked' : '' ?> class="accent-emerald-600">
                                <span class="flex-1 text-sm text-gray-700"><?= $m['label'] ?></span>
                                <?php if (str_contains($model_id, ':free')): ?>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded <?= $m['badge'] ?>">GRÁTIS</span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            </div><!-- /bloco-ia -->
        </div>

        <hr class="border-gray-100">

        <!-- Filtros -->
        <div>
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Filtros de Oferta</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Desconto mínimo (%)</label>
                    <div class="relative">
                        <input type="number" name="bot_desconto_minimo" value="<?= htmlspecialchars($desconto_min) ?>"
                            min="1" max="99" class="input pr-8">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                    </div>
                </div>
                <div>
                    <label class="label">Preço máximo (R$)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                        <input type="number" name="bot_preco_maximo" value="<?= htmlspecialchars($preco_max) ?>"
                            min="10" class="input pl-9">
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" name="salvar_bot" class="btn-primary">Salvar Configurações do Bot</button>
    </form>
</div>

<!-- ══ Seção 3: Conectar conta ML ════════════════════════════════════════ -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-yellow-50 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Conectar Conta Mercado Livre</h2>
                <p class="text-xs text-gray-500">Necessário para buscar produtos via API</p>
            </div>
        </div>
        <?php if ($ml_conectado): ?>
            <span class="text-xs font-semibold px-3 py-1 rounded-full bg-emerald-100 text-emerald-700">✓ Conectado (user <?= $ml_user_id ?>)</span>
        <?php else: ?>
            <span class="text-xs font-semibold px-3 py-1 rounded-full bg-red-100 text-red-700">✗ Não conectado</span>
        <?php endif; ?>
    </div>

    <div class="p-6">
        <?php if ($ml_conectado): ?>
            <div class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-lg mb-4">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm text-emerald-800">
                    Conta ML conectada! Token válido até <strong><?= date('d/m H:i', $ml_expires) ?></strong>.
                    O bot já pode buscar produtos automaticamente.
                </p>
            </div>
        <?php endif; ?>

        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-4 text-xs text-amber-800">
            ⚠️ <strong>Atenção:</strong> o código é de <strong>uso único</strong> e vale só para o ambiente onde foi gerado.
            Se você tem o sistema local <em>e</em> online (VPS), precisa autorizar separadamente em cada um — o código do local não funciona no online e vice-versa.
        </div>
        <p class="text-sm text-gray-600 mb-4">
            Siga os <strong>3 passos</strong> abaixo para conectar sua conta ML ao bot:
        </p>

        <!-- Passo 1 -->
        <div class="flex gap-4 mb-5">
            <div class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">1</div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800 mb-1">Clique no botão para autorizar</p>
                <p class="text-xs text-gray-500 mb-2">Vai abrir o Mercado Livre. Faça login e clique em "Permitir".</p>
                <?php if ($ml_id): ?>
                    <a href="<?= htmlspecialchars($ml_auth_url) ?>" target="_blank"
                       class="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-500 text-yellow-900 font-semibold px-4 py-2 rounded-lg text-sm transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Autorizar no Mercado Livre
                    </a>
                <?php else: ?>
                    <p class="text-xs text-red-600">⚠️ Preencha o Client ID na seção acima primeiro e salve.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Passo 2 -->
        <div class="flex gap-4 mb-5">
            <div class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">2</div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800 mb-1">Copie o código da URL do Google</p>
                <p class="text-xs text-gray-500 mb-1">Após autorizar, você vai parar em uma página do Google. A URL vai ter:</p>
                <code class="block bg-gray-100 text-gray-600 text-xs px-3 py-2 rounded-lg font-mono mb-1">https://www.google.com/?code=<strong class="text-emerald-700">COPIE_ESTE_CODIGO</strong>&state=...</code>
                <p class="text-xs text-gray-500">Copie somente o valor depois de <code>code=</code> até o <code>&</code></p>
            </div>
        </div>

        <!-- Passo 3 -->
        <div class="flex gap-4">
            <div class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 mt-0.5">3</div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-800 mb-2">Cole o código abaixo e conecte</p>
                <div class="flex gap-2">
                    <input type="text" id="ml-code-input"
                        placeholder="Cole o código aqui (TG-...)"
                        class="input flex-1 font-mono text-xs">
                    <button onclick="conectarML()"
                        class="btn-primary whitespace-nowrap text-sm" id="btn-ml-connect">
                        Conectar
                    </button>
                </div>
                <p id="ml-connect-msg" class="text-xs mt-2 hidden"></p>
            </div>
        </div>
    </div>
</div>

<script>
function conectarML() {
    let raw  = document.getElementById('ml-code-input').value.trim();
    const msg = document.getElementById('ml-connect-msg');
    const btn = document.getElementById('btn-ml-connect');
    if (!raw) { alert('Cole o código ou a URL completa primeiro!'); return; }

    // Extrai o code se o usuário colou a URL completa
    let code = raw;
    if (raw.includes('code=')) {
        const match = raw.match(/[?&]code=([^&]+)/);
        code = match ? match[1] : raw;
    }

    btn.disabled    = true;
    btn.textContent = 'Conectando...';
    msg.className   = 'text-xs mt-2 text-gray-500';
    msg.textContent = '⏳ Trocando código por token no ML...';

    fetch(BASE + '/api/ml_auth.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({code})
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled    = false;
        btn.textContent = 'Conectar';
        if (data.ok) {
            msg.className   = 'text-xs mt-2 text-emerald-700 font-semibold';
            msg.textContent = '✅ ' + data.message;
            setTimeout(() => location.reload(), 2000);
        } else {
            msg.className   = 'text-xs mt-2 text-red-600';
            msg.textContent = '❌ ' + data.error;
        }
    })
    .catch(() => {
        btn.disabled    = false;
        btn.textContent = 'Conectar';
        msg.className   = 'text-xs mt-2 text-red-600';
        msg.textContent = '❌ Erro de rede. Tente novamente.';
    });
}

function toggleIA(on) {
    document.getElementById('bloco-ia').classList.toggle('hidden', !on);
    document.getElementById('bloco-template').classList.toggle('hidden', on);
    const track = document.getElementById('toggle-track');
    const thumb = document.getElementById('toggle-thumb');
    track.className = `w-11 h-6 rounded-full transition-colors ${on ? 'bg-emerald-500' : 'bg-gray-300'}`;
    thumb.className = `absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${on ? 'translate-x-6' : 'translate-x-1'}`;
}

function testarIA() {
    const box = document.getElementById('ia-test-result');
    box.className = 'mt-2 p-3 rounded-lg text-xs font-medium bg-gray-100 text-gray-600';
    box.textContent = '⏳ Testando conexão com a IA...';

    fetch(BASE + '/api/testar_ia.php', {method: 'POST'})
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                box.className = 'mt-2 p-3 rounded-lg text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200';
                box.textContent = `✅ ${data.message}  (modelo: ${data.modelo})`;
            } else {
                box.className = 'mt-2 p-3 rounded-lg text-xs font-medium bg-red-50 text-red-700 border border-red-200';
                box.textContent = '❌ ' + (data.error || 'Erro desconhecido');
            }
        })
        .catch(() => {
            box.className = 'mt-2 p-3 rounded-lg text-xs font-medium bg-red-50 text-red-700 border border-red-200';
            box.textContent = '❌ Erro de rede ao testar IA.';
        });
}
</script>


<!-- ══ Seção 4: Como rodar o bot ══════════════════════════════════════════ -->
<div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
    <h3 class="text-sm font-semibold text-amber-800 mb-3">Como rodar o bot</h3>
    <div class="space-y-2 text-sm text-amber-700">
        <p><strong>1. Instalar dependências (uma vez):</strong></p>
        <code class="block bg-amber-100 rounded px-3 py-2 text-xs font-mono">
            cd C:\xampp\htdocs\viana\bot<br>
            pip install -r requirements.txt
        </code>
        <p class="mt-3"><strong>2. Testar manualmente:</strong></p>
        <code class="block bg-amber-100 rounded px-3 py-2 text-xs font-mono">
            python main.py
        </code>
        <p class="mt-3"><strong>3. Automatizar (Agendador de Tarefas do Windows):</strong></p>
        <code class="block bg-amber-100 rounded px-3 py-2 text-xs font-mono">
            Programa: C:\Python311\python.exe<br>
            Argumentos: C:\xampp\htdocs\viana\bot\main.py<br>
            Frequência: a cada 4 horas
        </code>
    </div>
</div>

</div>

<?php layoutEnd(); ?>

