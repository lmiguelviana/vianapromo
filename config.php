<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/evolution.php';

$msg     = '';
$msgType = 'success';
$active_tab = $_POST['active_tab'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfVerify();

// ── WhatsApp ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_evolution'])) {
    setConfig('evolution_url',      trim($_POST['evolution_url']      ?? ''));
    setConfig('evolution_apikey',   trim($_POST['evolution_apikey']   ?? ''));
    setConfig('evolution_instance', trim($_POST['evolution_instance'] ?? ''));
    $msg = 'Configurações da Evolution API salvas!';
    $active_tab = 'whatsapp';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['testar'])) {
    setConfig('evolution_url',      trim($_POST['evolution_url']      ?? ''));
    setConfig('evolution_apikey',   trim($_POST['evolution_apikey']   ?? ''));
    setConfig('evolution_instance', trim($_POST['evolution_instance'] ?? ''));
    $api    = new EvolutionAPI();
    $result = $api->testConnection();
    if ($result['ok']) {
        $state = $result['data']['instance']['state'] ?? $result['data']['state'] ?? 'open';
        $msg = "✅ Conexão OK! Estado: {$state}";
    } else {
        $msg = 'Erro: ' . $result['error'];
        $msgType = 'error';
    }
    $active_tab = 'whatsapp';
}

// ── Bot ML ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_bot_ml'])) {
    setConfig('bot_ml_ativo',                  isset($_POST['bot_ml_ativo']) ? '1' : '0');
    setConfig('bot_ml_intervalo_horas',        trim($_POST['bot_ml_intervalo_horas']        ?? '6'));
    setConfig('bot_ml_desconto_minimo',        trim($_POST['bot_ml_desconto_minimo']        ?? '10'));
    setConfig('bot_ml_preco_maximo',           trim($_POST['bot_ml_preco_maximo']           ?? '500'));
    setConfig('bot_ml_intervalo_entre_ofertas',trim($_POST['bot_ml_intervalo_entre_ofertas']?? '0'));
    setConfig('bot_ml_max_envios_por_ciclo',   (string)max(0, (int)($_POST['bot_ml_max_envios_por_ciclo'] ?? 0)));
    setConfig('bot_ml_dias_min_reenvio',       trim($_POST['bot_ml_dias_min_reenvio']       ?? '30'));
    setConfig('bot_ml_queda_minima_pct',       trim($_POST['bot_ml_queda_minima_pct']       ?? '5'));
    $msg = 'Configurações do Bot ML salvas!';
    $active_tab = 'bot_ml';
}

// ── Bot Shopee ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_bot_shopee'])) {
    setConfig('bot_shopee_ativo',                  isset($_POST['bot_shopee_ativo']) ? '1' : '0');
    setConfig('bot_shopee_intervalo_horas',        trim($_POST['bot_shopee_intervalo_horas']        ?? '6'));
    setConfig('bot_shopee_desconto_minimo',        trim($_POST['bot_shopee_desconto_minimo']        ?? '10'));
    setConfig('bot_shopee_preco_maximo',           trim($_POST['bot_shopee_preco_maximo']           ?? '500'));
    setConfig('bot_shopee_intervalo_entre_ofertas',trim($_POST['bot_shopee_intervalo_entre_ofertas']?? '0'));
    setConfig('bot_shopee_max_envios_por_ciclo',   (string)max(0, (int)($_POST['bot_shopee_max_envios_por_ciclo'] ?? 0)));
    setConfig('bot_shopee_dias_min_reenvio',       trim($_POST['bot_shopee_dias_min_reenvio']       ?? '30'));
    setConfig('bot_shopee_queda_minima_pct',       trim($_POST['bot_shopee_queda_minima_pct']       ?? '5'));
    $msg = 'Configurações do Bot Shopee salvas!';
    $active_tab = 'bot_shopee';
}

// ── IA & Texto ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_ia'])) {
    setConfig('site_url',          rtrim(trim($_POST['site_url'] ?? ''), '/'));
    setConfig('usar_ia',           isset($_POST['usar_ia']) ? '1' : '0');
    setConfig('mensagem_padrao',   trim($_POST['mensagem_padrao'] ?? ''));
    setConfig('openrouter_apikey', trim($_POST['openrouter_apikey'] ?? ''));
    setConfig('openrouter_model',  trim($_POST['openrouter_model']  ?? 'minimax/minimax-01:free'));
    $msg = 'Configurações de IA & Texto salvas!';
    $active_tab = 'ia';
}

// ── Fontes ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_ml_creds'])) {
    setConfig('ml_client_id',     trim($_POST['ml_client_id']     ?? ''));
    setConfig('ml_client_secret', trim($_POST['ml_client_secret'] ?? ''));
    setConfig('ml_partner_id',    trim($_POST['ml_partner_id']    ?? ''));
    $msg = 'Credenciais do Mercado Livre salvas!';
    $active_tab = 'fontes';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_magalu'])) {
    setConfig('magalu_smttag', trim($_POST['magalu_smttag'] ?? ''));
    setConfig('magalu_ativo',  isset($_POST['magalu_ativo']) ? '1' : '0');
    $msg = 'Configurações do Magalu salvas!';
    $active_tab = 'fontes';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_shopee'])) {
    setConfig('shopee_app_id',             trim($_POST['shopee_app_id']     ?? ''));
    setConfig('shopee_app_secret',         trim($_POST['shopee_app_secret'] ?? ''));
    setConfig('shopee_ativo',              isset($_POST['shopee_ativo']) ? '1' : '0');
    setConfig('shopee_limite_por_passada', (string)max(1, min(1000, (int)($_POST['shopee_limite_por_passada'] ?? 50))));
    $msg = 'Configurações da Shopee salvas!';
    $active_tab = 'fontes';
}

// ── Portal ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_portal'])) {
    setConfig('portal_banner_ativo',    isset($_POST['portal_banner_ativo']) ? '1' : '0');
    setConfig('portal_banner_titulo',   trim($_POST['portal_banner_titulo']    ?? ''));
    setConfig('portal_banner_subtitulo',trim($_POST['portal_banner_subtitulo'] ?? ''));
    $msg = 'Configurações do Portal salvas!';
    $active_tab = 'portal';
}

// ── Leitura ────────────────────────────────────────────────────────────────
$evo_url      = getConfig('evolution_url');
$evo_apikey   = getConfig('evolution_apikey');
$evo_instance = getConfig('evolution_instance');

$site_url     = getConfig('site_url');
$ml_id        = getConfig('ml_client_id');
$ml_secret    = getConfig('ml_client_secret');
$ml_partner   = getConfig('ml_partner_id');
$ml_token     = getConfig('ml_access_token');
$ml_refresh   = getConfig('ml_refresh_token');
$ml_expires   = (int)getConfig('ml_token_expires');
$ml_user_id   = getConfig('ml_user_id');
$ml_token_vivo  = $ml_token !== '' && $ml_expires > time();
$ml_tem_refresh = $ml_refresh !== '';
$ml_conectado   = $ml_token_vivo || $ml_tem_refresh;

$or_key     = getConfig('openrouter_apikey');
$or_model   = getConfig('openrouter_model') ?: 'minimax/minimax-01:free';
$usar_ia    = getConfig('usar_ia') !== '0';
$msg_padrao = getConfig('mensagem_padrao') ?: "{EMOJI} *{NOME}*\n\n~~R\$ {PRECO_DE}~~ por apenas *R\$ {PRECO_POR}* 🏷️ *{DESCONTO}% OFF*\n\n🔗 link de afiliado — comprar por aqui me ajuda sem custo extra pra você\n👉 {LINK}";

// Bot ML
$ml_bot_ativo         = getConfig('bot_ml_ativo') === '1';
$ml_bot_intervalo     = getConfig('bot_ml_intervalo_horas')          ?: '6';
$ml_desconto_min      = getConfig('bot_ml_desconto_minimo')          ?: '10';
$ml_preco_max         = getConfig('bot_ml_preco_maximo')             ?: '500';
$ml_intervalo_ofertas = getConfig('bot_ml_intervalo_entre_ofertas')  ?: '0';
$ml_max_envios        = getConfig('bot_ml_max_envios_por_ciclo')     ?: '0';
$ml_dias_reenvio      = getConfig('bot_ml_dias_min_reenvio')         ?: '30';
$ml_queda_pct         = getConfig('bot_ml_queda_minima_pct')         ?: '5';
$ml_ultimo_run        = getConfig('bot_ml_ultimo_run') ?: getConfig('bot_ultimo_run');
$ml_proximo_run       = $ml_ultimo_run && $ml_bot_ativo
    ? date('d/m H:i', strtotime($ml_ultimo_run) + (int)$ml_bot_intervalo * 3600)
    : null;

// Bot Shopee
$shp_bot_ativo         = getConfig('bot_shopee_ativo') === '1';
$shp_bot_intervalo     = getConfig('bot_shopee_intervalo_horas')          ?: '6';
$shp_desconto_min      = getConfig('bot_shopee_desconto_minimo')          ?: '10';
$shp_preco_max         = getConfig('bot_shopee_preco_maximo')             ?: '500';
$shp_intervalo_ofertas = getConfig('bot_shopee_intervalo_entre_ofertas')  ?: '0';
$shp_max_envios        = getConfig('bot_shopee_max_envios_por_ciclo')     ?: '0';
$shp_dias_reenvio      = getConfig('bot_shopee_dias_min_reenvio')         ?: '30';
$shp_queda_pct         = getConfig('bot_shopee_queda_minima_pct')         ?: '5';
$shp_ultimo_run        = getConfig('bot_shopee_ultimo_run');
$shp_proximo_run       = $shp_ultimo_run && $shp_bot_ativo
    ? date('d/m H:i', strtotime($shp_ultimo_run) + (int)$shp_bot_intervalo * 3600)
    : null;

// Mantém $bot_ativo para exibição na fila e em outros pontos legados
$bot_ativo = $ml_bot_ativo || $shp_bot_ativo;

$system_logo_url  = getConfig('system_logo_url');
$system_logo_path = getConfig('system_logo_path');
$tem_logo_sistema = $system_logo_url && file_exists($system_logo_path);

$shopee_app_id      = getConfig('shopee_app_id');
$shopee_app_secret  = getConfig('shopee_app_secret');
$shopee_ativo       = getConfig('shopee_ativo') === '1';
$shopee_limite      = getConfig('shopee_limite_por_passada') ?: '50';
$shopee_configurado = $shopee_app_id !== '' && $shopee_app_secret !== '';

$magalu_smttag = getConfig('magalu_smttag');
$magalu_ativo  = getConfig('magalu_ativo') === '1';

$portal_banner_ativo     = getConfig('portal_banner_ativo') !== '0';
$portal_banner_titulo    = getConfig('portal_banner_titulo')    ?: 'Melhores Ofertas Fitness';
$portal_banner_subtitulo = getConfig('portal_banner_subtitulo') ?: 'Suplementos, roupas e equipamentos com descontos todo dia';

$ml_auth_url = 'https://auth.mercadolivre.com.br/authorization?response_type=code'
    . '&client_id=' . urlencode($ml_id)
    . '&redirect_uri=' . urlencode('https://www.google.com/');

$modelos = [
    'minimax/minimax-m2.5:free'                  => ['label' => 'MiniMax M2.5 (GRÁTIS)',    'grupo' => 'Gratuitos'],
    'openai/gpt-oss-120b:free'                   => ['label' => 'GPT OSS 120B (GRÁTIS)',    'grupo' => 'Gratuitos'],
    'nvidia/nemotron-nano-12b-v2-vl:free'        => ['label' => 'Nvidia Nemotron (GRÁTIS)', 'grupo' => 'Gratuitos'],
    'z-ai/glm-4.5-air:free'                      => ['label' => 'GLM 4.5 Air (GRÁTIS)',     'grupo' => 'Gratuitos'],
    'minimax/minimax-01:free'                    => ['label' => 'MiniMax 01 (GRÁTIS)',      'grupo' => 'Gratuitos'],
    'moonshotai/moonlight-16b-a3b-instruct:free' => ['label' => 'Kimi Moonshot (GRÁTIS)',   'grupo' => 'Gratuitos'],
    'deepseek/deepseek-chat-v3-0324'             => ['label' => 'DeepSeek V3 (~R$0,01/dia)','grupo' => 'Baratos'],
    'google/gemini-flash-1.5'                    => ['label' => 'Gemini Flash 1.5',         'grupo' => 'Baratos'],
    'meta-llama/llama-3.3-70b-instruct'          => ['label' => 'LLaMA 3.3 70B',           'grupo' => 'Baratos'],
    'anthropic/claude-sonnet-4-5'                => ['label' => 'Claude Sonnet 4.5',        'grupo' => 'Premium'],
    'openai/gpt-4o-mini'                         => ['label' => 'GPT-4o Mini',              'grupo' => 'Premium'],
];

if (!in_array($active_tab, ['whatsapp','bot_ml','bot_shopee','fontes','ia','portal'], true)) {
    $active_tab = 'whatsapp';
}

layoutStart('config', 'Configurações');
toast();
?>

<?php if ($msg): ?>
<div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- ── Tab Nav ──────────────────────────────────────────────────────────── -->
<div class="flex overflow-x-auto gap-1 bg-white border border-gray-200 rounded-xl p-1 mb-6 sticky top-14 z-20 shadow-sm no-scrollbar">

    <button onclick="showTab('whatsapp')" id="tab-btn-whatsapp"
        class="tab-btn flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition flex-shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        WhatsApp
    </button>

    <button onclick="showTab('bot_ml')" id="tab-btn-bot_ml"
        class="tab-btn flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition flex-shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H4a2 2 0 01-2-2V5a2 2 0 012-2h16a2 2 0 012 2v10a2 2 0 01-2 2h-1"/>
        </svg>
        Bot ML
        <span class="w-2 h-2 rounded-full flex-shrink-0 <?= $ml_bot_ativo ? 'bg-emerald-500' : 'bg-red-400' ?>"></span>
    </button>

    <button onclick="showTab('bot_shopee')" id="tab-btn-bot_shopee"
        class="tab-btn flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition flex-shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
        </svg>
        Bot Shopee
        <span class="w-2 h-2 rounded-full flex-shrink-0 <?= $shp_bot_ativo ? 'bg-emerald-500' : 'bg-red-400' ?>"></span>
    </button>

    <button onclick="showTab('fontes')" id="tab-btn-fontes"
        class="tab-btn flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition flex-shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
        </svg>
        Fontes
    </button>

    <button onclick="showTab('ia')" id="tab-btn-ia"
        class="tab-btn flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition flex-shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
        </svg>
        IA & Texto
    </button>

    <button onclick="showTab('portal')" id="tab-btn-portal"
        class="tab-btn flex items-center gap-1.5 whitespace-nowrap px-3 py-2 rounded-lg text-sm font-medium transition flex-shrink-0">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
        </svg>
        Portal
    </button>
</div>

<div class="max-w-2xl space-y-5">

<!-- ════════════════════════════════════════════════════════════════════════
     TAB: WhatsApp
═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-whatsapp" class="tab-panel space-y-5">

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Evolution API — WhatsApp</h2>
                <p class="text-xs text-gray-500">Conexão com o seu número de WhatsApp</p>
            </div>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="active_tab" value="whatsapp">
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
            <div class="flex flex-wrap gap-2 pt-1">
                <button type="submit" name="salvar_evolution" class="btn-primary">Salvar</button>
                <button type="submit" name="testar"
                    class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition">
                    Testar Conexão
                </button>
                <button type="button" onclick="abrirModalReconectar()"
                    class="flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0a8 8 0 10-16 0 8 8 0 0016 0z"/>
                    </svg>
                    Reconectar QR Code
                </button>
            </div>
        </form>
    </div>

</div><!-- /tab-whatsapp -->


<!-- ════════════════════════════════════════════════════════════════════════
     TAB: Bot
═══════════════════════════════════════════════════════════════════════════ -->
<?php
function renderBotTab(string $id, string $label, string $prefix, bool $ativo, string $intervalo,
    string $desconto, string $preco, string $int_ofertas, string $max_env,
    string $dias_rev, string $queda, ?string $ultimo_run, ?string $proximo_run): void {
    $csrf = csrfField();
    $checked = $ativo ? 'checked' : '';
    $trackCls = $ativo ? 'bg-emerald-500' : 'bg-gray-300';
    $thumbCls = $ativo ? 'translate-x-6' : 'translate-x-1';
    $toggleId = "toggle-{$id}";
    ?>
<div id="tab-<?= $id ?>" class="tab-panel space-y-5 hidden">
<form method="POST" class="space-y-5">
    <?= $csrf ?>
    <input type="hidden" name="active_tab" value="<?= $id ?>">

    <!-- Status -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Status — <?= htmlspecialchars($label) ?></h2>
        </div>
        <div class="p-5">
            <label id="label-<?= $toggleId ?>" class="flex items-center justify-between p-4 border rounded-xl cursor-pointer
                <?= $ativo ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200 bg-gray-50' ?>">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Rodar <?= htmlspecialchars($label) ?> automaticamente</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <?php if ($ativo && $ultimo_run): ?>
                            Último run: <strong><?= date('d/m H:i', strtotime($ultimo_run)) ?></strong>
                            <?php if ($proximo_run): ?> · Próximo: <strong><?= $proximo_run ?></strong><?php endif; ?>
                        <?php elseif ($ativo): ?>
                            Ativo — aguardando primeiro ciclo do cron
                        <?php else: ?>
                            Pausado — bot só roda manualmente
                        <?php endif; ?>
                    </p>
                </div>
                <div class="relative flex-shrink-0">
                    <input type="checkbox" name="<?= $prefix ?>_ativo" id="<?= $toggleId ?>" value="1"
                        <?= $checked ?> class="sr-only"
                        onchange="document.getElementById('track-<?= $toggleId ?>').className='w-11 h-6 rounded-full transition-colors '+(this.checked?'bg-emerald-500':'bg-gray-300');document.getElementById('thumb-<?= $toggleId ?>').className='absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform '+(this.checked?'translate-x-6':'translate-x-1')">
                    <div id="track-<?= $toggleId ?>" class="w-11 h-6 rounded-full transition-colors <?= $trackCls ?>">
                        <div id="thumb-<?= $toggleId ?>" class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform <?= $thumbCls ?>"></div>
                    </div>
                </div>
            </label>
        </div>
    </div>

    <!-- Agendamento -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Agendamento</h2>
            <p class="text-xs text-gray-500 mt-0.5">Intervalo entre execuções do pipeline</p>
        </div>
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                <?php foreach ([1=>'1h',2=>'2h',3=>'3h',6=>'6h',12=>'12h',24=>'24h'] as $h => $lbl): ?>
                    <label class="flex flex-col items-center justify-center p-3 border rounded-xl cursor-pointer text-sm transition
                        <?= (int)$intervalo === $h ? 'border-emerald-400 bg-emerald-50 font-semibold text-emerald-700' : 'border-gray-200 hover:border-gray-300 text-gray-600' ?>">
                        <input type="radio" name="<?= $prefix ?>_intervalo_horas" value="<?= $h ?>"
                            <?= (int)$intervalo === $h ? 'checked' : '' ?> class="sr-only">
                        <?= $lbl ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400">O cron verifica a cada 30 min e dispara quando o intervalo for atingido.</p>

            <div class="pt-2 border-t border-gray-100 flex flex-wrap gap-2">
                <button type="button" onclick="testarCron('<?= $id === 'bot_ml' ? 'ml' : 'shopee' ?>', false, '<?= $id ?>')"
                    class="flex items-center gap-2 px-4 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Simular Cron
                </button>
                <button type="button" onclick="testarCron('<?= $id === 'bot_ml' ? 'ml' : 'shopee' ?>', true, '<?= $id ?>')"
                    class="flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Forçar Agora
                </button>
                <span class="text-xs text-gray-400 self-center">Simular não executa. Forçar ignora intervalo, mas respeita lock e bot ativo.</span>
            </div>

            <div id="cron-result-<?= $id ?>" class="hidden bg-gray-900 rounded-lg p-3">
                <pre class="text-xs text-emerald-400 font-mono whitespace-pre-wrap" id="cron-result-text-<?= $id ?>"></pre>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Filtros de Oferta</h2>
            <p class="text-xs text-gray-500 mt-0.5">O que o bot deve coletar</p>
        </div>
        <div class="p-5 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Desconto mínimo</label>
                    <div class="relative">
                        <input type="number" name="<?= $prefix ?>_desconto_minimo" value="<?= htmlspecialchars($desconto) ?>"
                            min="1" max="99" class="input pr-8">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                    </div>
                </div>
                <div>
                    <label class="label">Preço máximo</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                        <input type="number" name="<?= $prefix ?>_preco_maximo" value="<?= htmlspecialchars($preco) ?>"
                            min="10" class="input pl-9">
                    </div>
                </div>
            </div>
            <div>
                <label class="label">Intervalo entre ofertas no envio</label>
                <div class="grid grid-cols-4 gap-2 mt-1">
                    <?php foreach ([0=>'Sem pausa',2=>'2 min',5=>'5 min',10=>'10 min',15=>'15 min',30=>'30 min',60=>'1 hora'] as $min => $lbl): ?>
                        <label class="flex items-center gap-2 p-2.5 border rounded-lg cursor-pointer text-xs transition
                            <?= (int)$int_ofertas === $min ? 'border-emerald-400 bg-emerald-50 font-semibold text-emerald-700' : 'border-gray-200 hover:border-gray-300 text-gray-600' ?>">
                            <input type="radio" name="<?= $prefix ?>_intervalo_entre_ofertas" value="<?= $min ?>"
                                <?= (int)$int_ofertas === $min ? 'checked' : '' ?> class="sr-only">
                            <?= $lbl ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Limites & Dedup -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Limites & Dedup</h2>
            <p class="text-xs text-gray-500 mt-0.5">Controle de volume e reenvio</p>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="label">Máx. ofertas / ciclo</label>
                <input type="number" name="<?= $prefix ?>_max_envios_por_ciclo" value="<?= htmlspecialchars($max_env) ?>"
                    min="0" max="100" class="input">
                <p class="text-xs text-gray-400 mt-1">0 = sem limite</p>
            </div>
            <div>
                <label class="label">Dias bloqueio reenvio</label>
                <input type="number" name="<?= $prefix ?>_dias_min_reenvio" value="<?= htmlspecialchars($dias_rev) ?>"
                    min="1" max="365" class="input">
                <p class="text-xs text-gray-400 mt-1">Bloqueia N dias após envio</p>
            </div>
            <div>
                <label class="label">Queda mín. p/ reenvio</label>
                <div class="relative">
                    <input type="number" name="<?= $prefix ?>_queda_minima_pct" value="<?= htmlspecialchars($queda) ?>"
                        min="0" max="100" step="0.5" class="input pr-8">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                </div>
                <p class="text-xs text-gray-400 mt-1">Após o bloqueio, exige essa queda</p>
            </div>
        </div>
    </div>

    <button type="submit" name="salvar_<?= $id ?>" class="btn-primary">Salvar <?= htmlspecialchars($label) ?></button>
</form>
</div><!-- /tab-<?= $id ?> -->
<?php } ?>

<?php renderBotTab(
    'bot_ml', 'Bot ML',       'bot_ml',
    $ml_bot_ativo, $ml_bot_intervalo, $ml_desconto_min, $ml_preco_max,
    $ml_intervalo_ofertas, $ml_max_envios, $ml_dias_reenvio, $ml_queda_pct,
    $ml_ultimo_run, $ml_proximo_run
); ?>

<?php renderBotTab(
    'bot_shopee', 'Bot Shopee', 'bot_shopee',
    $shp_bot_ativo, $shp_bot_intervalo, $shp_desconto_min, $shp_preco_max,
    $shp_intervalo_ofertas, $shp_max_envios, $shp_dias_reenvio, $shp_queda_pct,
    $shp_ultimo_run, $shp_proximo_run
); ?>


<!-- ════════════════════════════════════════════════════════════════════════
     TAB: Fontes
═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-fontes" class="tab-panel space-y-5 hidden">

    <!-- Mercado Livre Credenciais -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-yellow-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Mercado Livre — API</h2>
                    <p class="text-xs text-gray-500">Credenciais do app para busca de produtos</p>
                </div>
            </div>
            <?php if ($ml_token_vivo): ?>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700">✓ Conectado</span>
            <?php elseif ($ml_tem_refresh): ?>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-700">⟳ Expirado</span>
            <?php else: ?>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-red-100 text-red-700">✗ Desconectado</span>
            <?php endif; ?>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="active_tab" value="fontes">
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
            <div>
                <label class="label">Partner ID (Afiliado)</label>
                <input type="text" name="ml_partner_id" value="<?= htmlspecialchars($ml_partner) ?>"
                    placeholder="Seu ID do programa de afiliados ML" class="input">
                <p class="text-xs text-gray-400 mt-1">Em <a href="https://afiliados.mercadolivre.com.br" target="_blank" class="text-emerald-600 hover:underline">afiliados.mercadolivre.com.br</a> → Meu painel</p>
            </div>
            <button type="submit" name="salvar_ml_creds" class="btn-primary">Salvar Credenciais ML</button>
        </form>

        <!-- OAuth ML -->
        <div class="border-t border-gray-100 p-5 space-y-4">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Autorizar Conta ML</h3>

            <?php if ($ml_token_vivo): ?>
            <div class="flex items-center gap-3 p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm text-emerald-800">Token ativo até <strong><?= date('d/m H:i', $ml_expires) ?></strong>. Auto-renovação disponível.</p>
            </div>
            <?php elseif ($ml_tem_refresh): ?>
            <div class="flex items-center gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-sm text-amber-800 flex-1">Token expirado. <button type="button" onclick="renovarToken()" id="btn-ml-refresh" class="font-semibold underline hover:no-underline">Renovar agora</button> sem precisar reconectar.</p>
            </div>
            <?php endif; ?>

            <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800">
                ⚠️ O código de autorização é de <strong>uso único</strong>. Autorize separadamente em cada ambiente (local e VPS).
            </div>

            <div class="space-y-4">
                <div class="flex gap-3">
                    <div class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">1</div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 mb-1">Autorize no Mercado Livre</p>
                        <?php if ($ml_id): ?>
                            <a href="<?= htmlspecialchars($ml_auth_url) ?>" target="_blank"
                               class="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-500 text-yellow-900 font-semibold px-4 py-2 rounded-lg text-sm transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                Autorizar no Mercado Livre
                            </a>
                        <?php else: ?>
                            <p class="text-xs text-red-600">⚠️ Preencha o Client ID acima e salve primeiro.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">2</div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 mb-1">Copie o código da URL do Google</p>
                        <code class="block bg-gray-100 text-gray-600 text-xs px-3 py-2 rounded-lg font-mono">https://www.google.com/?code=<strong class="text-emerald-700">COPIE_ISSO</strong>&state=...</code>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="w-6 h-6 bg-emerald-600 text-white rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">3</div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-800 mb-2">Cole o código e conecte</p>
                        <div class="flex gap-2">
                            <input type="text" id="ml-code-input" placeholder="Cole o código aqui (TG-...)"
                                class="input flex-1 font-mono text-xs">
                            <button onclick="conectarML()" class="btn-primary text-sm whitespace-nowrap" id="btn-ml-connect">Conectar</button>
                        </div>
                        <p id="ml-connect-msg" class="text-xs mt-2 hidden"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Magalu -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Magazine Luiza</h2>
                <p class="text-xs text-gray-500">Coleta de ofertas com link de afiliado</p>
            </div>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="active_tab" value="fontes">
            <label class="flex items-center justify-between p-4 border rounded-xl cursor-pointer
                <?= $magalu_ativo ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-gray-50' ?>">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Coleta Magalu ativa</p>
                    <p class="text-xs text-gray-500 mt-0.5">Busca produtos fitness a cada ciclo do bot</p>
                </div>
                <div class="relative flex-shrink-0">
                    <input type="checkbox" name="magalu_ativo" id="magalu_ativo" value="1"
                        <?= $magalu_ativo ? 'checked' : '' ?> class="sr-only">
                    <div id="track-magalu-ativo" class="w-11 h-6 rounded-full transition-colors <?= $magalu_ativo ? 'bg-blue-500' : 'bg-gray-300' ?>">
                        <div id="thumb-magalu-ativo" class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform <?= $magalu_ativo ? 'translate-x-6' : 'translate-x-1' ?>"></div>
                    </div>
                </div>
            </label>
            <div>
                <label class="label">smttag (ID de parceiro Magalu)</label>
                <input type="text" name="magalu_smttag" value="<?= htmlspecialchars($magalu_smttag) ?>"
                    placeholder="seu-smttag-aqui" class="input">
                <p class="text-xs text-gray-400 mt-1">Em <strong>parceiromagalu.com.br</strong> após aprovação. Comissão 17–19% em fitness.</p>
            </div>
            <button type="submit" name="salvar_magalu" class="btn-primary">Salvar Magalu</button>
        </form>
    </div>

    <!-- Shopee -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
            <div class="w-8 h-8 bg-orange-50 rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zm3.5 12h-7a.5.5 0 010-1h2.5v-5H9.5a.5.5 0 010-1h3a.5.5 0 01.5.5v5.5h2.5a.5.5 0 010 1z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h2 class="text-sm font-semibold text-gray-900">Shopee</h2>
                <p class="text-xs text-gray-500">Coleta via API GraphQL de afiliados</p>
            </div>
            <?php if ($shopee_configurado): ?>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-orange-100 text-orange-700">Configurada</span>
            <?php else: ?>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">Não configurada</span>
            <?php endif; ?>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="active_tab" value="fontes">
            <label id="label-shopee-ativo" class="flex items-center justify-between p-4 border rounded-xl cursor-pointer
                <?= $shopee_ativo ? 'border-orange-400 bg-orange-50' : 'border-gray-200 bg-gray-50' ?>">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Coleta Shopee ativa</p>
                    <p class="text-xs text-gray-500 mt-0.5">Busca produtos fitness a cada ciclo do bot</p>
                </div>
                <div class="relative flex-shrink-0">
                    <input type="checkbox" name="shopee_ativo" id="shopee_ativo" value="1"
                        <?= $shopee_ativo ? 'checked' : '' ?> class="sr-only">
                    <div id="track-shopee-ativo" class="w-11 h-6 rounded-full transition-colors <?= $shopee_ativo ? 'bg-orange-500' : 'bg-gray-300' ?>">
                        <div id="thumb-shopee-ativo" class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform <?= $shopee_ativo ? 'translate-x-6' : 'translate-x-1' ?>"></div>
                    </div>
                </div>
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label">App ID</label>
                    <input type="text" name="shopee_app_id" value="<?= htmlspecialchars($shopee_app_id) ?>"
                        placeholder="Ex: 18345678" class="input">
                </div>
                <div>
                    <label class="label">App Secret</label>
                    <input type="password" name="shopee_app_secret" value="<?= htmlspecialchars($shopee_app_secret) ?>"
                        placeholder="Seu App Secret Shopee" class="input">
                </div>
            </div>
            <p class="text-xs text-gray-400">Em <strong>affiliate.shopee.com.br → Open API</strong> após aprovação (~2 semanas).</p>
            <div>
                <label class="label">Limite por passada</label>
                <input type="number" name="shopee_limite_por_passada" value="<?= htmlspecialchars($shopee_limite) ?>"
                    min="1" max="1000" class="input w-28">
                <p class="text-xs text-gray-400 mt-1">Produtos por ciclo (padrão: 50)</p>
            </div>
            <button type="submit" name="salvar_shopee" class="btn-primary">Salvar Shopee</button>
        </form>
    </div>

</div><!-- /tab-fontes -->


<!-- ════════════════════════════════════════════════════════════════════════
     TAB: IA & Texto
═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-ia" class="tab-panel space-y-5 hidden">
<form method="POST" class="space-y-5">
    <?= csrfField() ?>
    <input type="hidden" name="active_tab" value="ia">

    <!-- URL do Site -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">URL de Produção</h2>
            <p class="text-xs text-gray-500 mt-0.5">Usada para rastrear cliques enviados no WhatsApp e portal</p>
        </div>
        <div class="p-5">
            <input type="url" name="site_url" value="<?= htmlspecialchars($site_url) ?>"
                placeholder="https://seusite.easypanel.host" class="input">
            <p class="text-xs text-gray-400 mt-2">Os links no WhatsApp passarão por <code class="bg-gray-100 px-1 rounded">/api/click.php?id=X</code> antes de redirecionar.</p>
        </div>
    </div>

    <!-- Toggle IA / Template -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Modo de Geração de Texto</h2>
        </div>
        <div class="p-5 space-y-4">
            <label class="flex items-center justify-between p-4 border rounded-xl cursor-pointer
                <?= $usar_ia ? 'border-emerald-400 bg-emerald-50' : 'border-gray-200 bg-gray-50' ?>">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Usar IA para criar mensagens</p>
                    <p class="text-xs text-gray-500 mt-0.5">Desligado: usa o modelo de mensagem padrão abaixo</p>
                </div>
                <div class="relative flex-shrink-0">
                    <input type="checkbox" name="usar_ia" id="usar_ia" value="1"
                        <?= $usar_ia ? 'checked' : '' ?> onchange="toggleIA(this.checked)" class="sr-only">
                    <div id="toggle-track" class="w-11 h-6 rounded-full transition-colors <?= $usar_ia ? 'bg-emerald-500' : 'bg-gray-300' ?>">
                        <div id="toggle-thumb" class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform <?= $usar_ia ? 'translate-x-6' : 'translate-x-1' ?>"></div>
                    </div>
                </div>
            </label>

            <!-- Template -->
            <div id="bloco-template" class="<?= $usar_ia ? 'hidden' : '' ?>">
                <label class="label">Modelo de mensagem padrão</label>
                <textarea name="mensagem_padrao" rows="6" class="input font-mono text-xs"
                    placeholder="{EMOJI} *{NOME}*&#10;~~R$ {PRECO_DE}~~ por *R$ {PRECO_POR}* — {DESCONTO}% OFF&#10;👉 {LINK}"><?= htmlspecialchars($msg_padrao) ?></textarea>
                <p class="text-xs text-gray-400 mt-1">Variáveis: <code>{NOME}</code> <code>{PRECO_DE}</code> <code>{PRECO_POR}</code> <code>{DESCONTO}</code> <code>{LINK}</code> <code>{EMOJI}</code></p>
            </div>
        </div>
    </div>

    <!-- OpenRouter -->
    <div id="bloco-ia" class="bg-white rounded-xl border border-gray-200 overflow-hidden <?= $usar_ia ? '' : 'hidden' ?>">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">OpenRouter — Modelo de IA</h2>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="label">API Key</label>
                <div class="flex gap-2">
                    <input type="password" name="openrouter_apikey" value="<?= htmlspecialchars($or_key) ?>"
                        placeholder="sk-or-..." class="input flex-1">
                    <button type="button" onclick="testarIA()"
                        class="flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition whitespace-nowrap">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Testar IA
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-1">Crie em <a href="https://openrouter.ai/keys" target="_blank" class="text-emerald-600 hover:underline">openrouter.ai/keys</a> — gratuito</p>
                <div id="ia-test-result" class="hidden mt-2 p-3 rounded-lg text-xs font-medium"></div>
            </div>
            <div>
                <label class="label">Modelo</label>
                <div class="space-y-1 mt-1">
                    <?php
                    $grupos_modelo = ['Gratuitos' => [], 'Baratos' => [], 'Premium' => []];
                    foreach ($modelos as $id => $m) $grupos_modelo[$m['grupo']][$id] = $m;
                    foreach ($grupos_modelo as $grupo_nome => $grupo_modelos):
                    ?>
                        <p class="text-xs font-semibold text-gray-400 mt-2 mb-1"><?= $grupo_nome ?></p>
                        <?php foreach ($grupo_modelos as $model_id => $m): ?>
                            <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition
                                <?= $or_model === $model_id ? 'border-emerald-400 bg-emerald-50' : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50' ?>">
                                <input type="radio" name="openrouter_model" value="<?= $model_id ?>"
                                    <?= $or_model === $model_id ? 'checked' : '' ?> class="accent-emerald-600 flex-shrink-0">
                                <span class="flex-1 text-sm text-gray-700"><?= $m['label'] ?></span>
                                <?php if (str_contains($model_id, ':free')): ?>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 flex-shrink-0">GRÁTIS</span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" name="salvar_ia" class="btn-primary">Salvar IA & Texto</button>
</form>
</div><!-- /tab-ia -->


<!-- ════════════════════════════════════════════════════════════════════════
     TAB: Portal
═══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-portal" class="tab-panel space-y-5 hidden">

    <!-- Logo -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Logo do Sistema</h2>
            <p class="text-xs text-gray-500 mt-0.5">Exibido na barra lateral e no portal</p>
        </div>
        <div class="p-5">
            <div class="flex items-center gap-5">
                <div class="w-20 h-20 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0" id="logo-preview-container">
                    <?php if ($tem_logo_sistema): ?>
                        <img src="<?= htmlspecialchars($system_logo_url) ?>" alt="Logo" class="w-full h-full object-contain" id="logo-preview-img">
                    <?php else: ?>
                        <div class="w-full h-full bg-emerald-600 flex items-center justify-center text-white font-black text-3xl" id="logo-preview-fallback">V</div>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <input type="file" id="logo-input" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" onchange="previewLogo(this)">
                    <div class="flex items-center gap-2 mb-2">
                        <label for="logo-input" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg cursor-pointer transition">
                            Escolher imagem…
                        </label>
                        <button type="button" id="btn-salvar-logo" onclick="uploadLogo()" class="btn-primary py-2 hidden">
                            Salvar Logo
                        </button>
                    </div>
                    <p class="text-xs text-gray-500">JPG, PNG, WebP, SVG — máx. 2 MB</p>
                    <p id="logo-upload-msg" class="text-xs font-semibold hidden mt-1"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Banner -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Banner do Portal</h2>
                <p class="text-xs text-gray-500 mt-0.5">Texto no topo de <a href="<?= BASE ?>/portal" target="_blank" class="text-emerald-600 hover:underline">/portal</a></p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="portal_banner_ativo" form="form-portal" id="portal_banner_ativo" value="1"
                    <?= $portal_banner_ativo ? 'checked' : '' ?> class="sr-only peer">
                <div class="w-10 h-5 rounded-full transition-colors peer-checked:bg-emerald-500 bg-gray-300
                    after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-transform peer-checked:after:translate-x-5"></div>
            </label>
        </div>
        <form id="form-portal" method="POST" class="p-5 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="active_tab" value="portal">
            <div>
                <label class="label">Título</label>
                <input type="text" name="portal_banner_titulo"
                    value="<?= htmlspecialchars($portal_banner_titulo, ENT_QUOTES, 'UTF-8') ?>"
                    class="input" placeholder="Melhores Ofertas Fitness">
            </div>
            <div>
                <label class="label">Subtítulo</label>
                <input type="text" name="portal_banner_subtitulo"
                    value="<?= htmlspecialchars($portal_banner_subtitulo, ENT_QUOTES, 'UTF-8') ?>"
                    class="input" placeholder="Suplementos, roupas e equipamentos com descontos todo dia">
            </div>
            <button type="submit" name="salvar_portal" class="btn-primary">Salvar Portal</button>
        </form>
    </div>

</div><!-- /tab-portal -->

</div><!-- /max-w-2xl -->


<!-- ── Modal: Reconectar WhatsApp ──────────────────────────────────────── -->
<div id="modal-reconectar" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Reconectar WhatsApp</h3>
            <button onclick="fecharModalReconectar()" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div id="qr-tela-confirmar" class="p-6">
            <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-lg p-4 mb-5">
                <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-sm text-amber-800">Isso vai <strong>desconectar o número atual</strong>. Você precisará escanear um novo QR code.</p>
            </div>
            <div class="flex gap-3">
                <button onclick="fecharModalReconectar()" class="flex-1 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium py-2 rounded-lg transition">Cancelar</button>
                <button onclick="executarLogout()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2 rounded-lg transition">Continuar</button>
            </div>
        </div>
        <div id="qr-tela-loading" class="p-6 text-center hidden">
            <div class="w-16 h-16 border-4 border-emerald-200 border-t-emerald-600 rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-sm text-gray-600" id="qr-loading-msg">Desconectando número atual...</p>
        </div>
        <div id="qr-tela-qrcode" class="p-6 text-center hidden">
            <p class="text-sm font-semibold text-gray-800 mb-1">Escaneie com o WhatsApp</p>
            <p class="text-xs text-gray-500 mb-4">WhatsApp → Menu → Aparelhos conectados → Conectar aparelho</p>
            <div class="inline-block p-3 bg-white border-2 border-gray-200 rounded-xl shadow-inner mb-4">
                <img id="qr-img" src="" alt="QR Code" class="w-48 h-48 object-contain">
            </div>
            <p class="text-xs text-gray-400" id="qr-timer">Atualizando QR code...</p>
        </div>
        <div id="qr-tela-ok" class="p-6 text-center hidden">
            <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-base font-semibold text-gray-800 mb-1">WhatsApp Conectado!</p>
            <p class="text-sm text-gray-500 mb-5">Número reconectado com sucesso.</p>
            <button onclick="fecharModalReconectar()" class="btn-primary w-full">Fechar</button>
        </div>
        <div id="qr-tela-erro" class="p-6 text-center hidden">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <p class="text-base font-semibold text-gray-800 mb-1">Erro</p>
            <p class="text-sm text-red-600 mb-5" id="qr-erro-msg"></p>
            <div class="flex gap-3">
                <button onclick="fecharModalReconectar()" class="flex-1 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium py-2 rounded-lg transition">Fechar</button>
                <button onclick="iniciarQRFlow()" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2 rounded-lg transition">Tentar novamente</button>
            </div>
        </div>
    </div>
</div>

<style>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.tab-btn { color: #6b7280; }
.tab-btn.active { background: #059669; color: #fff; }
.tab-btn:not(.active):hover { background: #f3f4f6; }
</style>

<script>
// ── Tab navigation ─────────────────────────────────────────────────────────
function showTab(name) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.remove('hidden');
    document.getElementById('tab-btn-' + name).classList.add('active');
    sessionStorage.setItem('config_tab', name);
}
// Restore active tab (POST sets it via PHP, otherwise sessionStorage, otherwise default)
const _initTab = <?= json_encode($active_tab) ?>;
showTab(_initTab || sessionStorage.getItem('config_tab') || 'whatsapp');

// ── ML OAuth ───────────────────────────────────────────────────────────────
function conectarML() {
    let raw = document.getElementById('ml-code-input').value.trim();
    const msg = document.getElementById('ml-connect-msg');
    const btn = document.getElementById('btn-ml-connect');
    if (!raw) { alert('Cole o código ou a URL completa primeiro!'); return; }
    let code = raw;
    if (raw.includes('code=')) {
        const match = raw.match(/[?&]code=([^&]+)/);
        code = match ? match[1] : raw;
    }
    btn.disabled = true; btn.textContent = 'Conectando...';
    msg.className = 'text-xs mt-2 text-gray-500'; msg.textContent = '⏳ Trocando código por token...';
    fetch(BASE + '/api/ml_auth.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({code})
    }).then(r => r.json()).then(data => {
        btn.disabled = false; btn.textContent = 'Conectar';
        if (data.ok) {
            msg.className = 'text-xs mt-2 text-emerald-700 font-semibold';
            msg.textContent = '✅ ' + data.message;
            setTimeout(() => location.reload(), 2000);
        } else {
            msg.className = 'text-xs mt-2 text-red-600';
            msg.textContent = '❌ ' + data.error;
        }
    }).catch(() => { btn.disabled = false; btn.textContent = 'Conectar'; msg.className = 'text-xs mt-2 text-red-600'; msg.textContent = '❌ Erro de rede.'; });
}

function renovarToken() {
    const btn = document.getElementById('btn-ml-refresh');
    if (btn) { btn.disabled = true; btn.textContent = 'Renovando...'; }
    fetch(BASE + '/api/ml_refresh.php', {method: 'POST'}).then(r => r.json()).then(data => {
        if (data.ok) { alert('✅ ' + data.message); location.reload(); }
        else { alert('❌ ' + data.error); if (btn) { btn.disabled = false; btn.textContent = 'Renovar agora'; } }
    }).catch(() => { alert('❌ Erro de rede.'); if (btn) { btn.disabled = false; btn.textContent = 'Renovar agora'; } });
}

// ── IA toggle ──────────────────────────────────────────────────────────────
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
    box.textContent = '⏳ Testando...';
    fetch(BASE + '/api/testar_ia.php', {method: 'POST'}).then(r => r.json()).then(data => {
        if (data.ok) {
            box.className = 'mt-2 p-3 rounded-lg text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200';
            box.textContent = `✅ ${data.message} (modelo: ${data.modelo})`;
        } else {
            box.className = 'mt-2 p-3 rounded-lg text-xs font-medium bg-red-50 text-red-700 border border-red-200';
            box.textContent = '❌ ' + (data.error || 'Erro desconhecido');
        }
    }).catch(() => { box.className = 'mt-2 p-3 rounded-lg text-xs font-medium bg-red-50 text-red-700 border border-red-200'; box.textContent = '❌ Erro de rede.'; });
}

// ── Cron ───────────────────────────────────────────────────────────────────
function testarCron(fonte, force, panelId) {
    const box = document.getElementById('cron-result-' + panelId);
    const pre = document.getElementById('cron-result-text-' + panelId);
    box.classList.remove('hidden');
    pre.textContent = force ? `Forçando Bot ${fonte.toUpperCase()}...` : `Simulando cron do Bot ${fonte.toUpperCase()}...`;
    fetch(BASE + '/api/cron_test.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({fonte, force})
    })
        .then(r => r.json()).then(data => { pre.textContent = data.linhas.join('\n'); })
        .catch(() => { pre.textContent = '❌ Erro de rede.'; });
}

// ── Toggle visuais (Shopee, Magalu, Bot) ───────────────────────────────────
function _setupToggle(checkboxId, trackId, thumbId, colorOn) {
    const cb = document.getElementById(checkboxId);
    const track = document.getElementById(trackId);
    const thumb = document.getElementById(thumbId);
    if (!cb || !track || !thumb) return;
    cb.addEventListener('change', () => {
        const on = cb.checked;
        track.className = `w-11 h-6 rounded-full transition-colors ${on ? colorOn : 'bg-gray-300'}`;
        thumb.className = `absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${on ? 'translate-x-6' : 'translate-x-1'}`;
    });
}
_setupToggle('shopee_ativo', 'track-shopee-ativo', 'thumb-shopee-ativo', 'bg-orange-500');
_setupToggle('magalu_ativo', 'track-magalu-ativo', 'thumb-magalu-ativo', 'bg-blue-500');


// ── Logo upload ────────────────────────────────────────────────────────────
let selectedLogoFile = null;
function previewLogo(input) {
    const file = input.files[0];
    if (!file) return;
    selectedLogoFile = file;
    document.getElementById('btn-salvar-logo').classList.remove('hidden');
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('logo-preview-container').innerHTML =
            `<img src="${e.target.result}" alt="Preview" class="w-full h-full object-contain">`;
    };
    reader.readAsDataURL(file);
}
async function uploadLogo() {
    if (!selectedLogoFile) return;
    const btn = document.getElementById('btn-salvar-logo');
    const msg = document.getElementById('logo-upload-msg');
    btn.disabled = true; btn.textContent = 'Salvando...';
    msg.classList.remove('hidden', 'text-emerald-600', 'text-red-600');
    msg.classList.add('text-gray-500'); msg.textContent = '⏳ Fazendo upload...';
    const fd = new FormData(); fd.append('logo', selectedLogoFile);
    try {
        const data = await fetch(BASE + '/api/upload_logo.php', {method: 'POST', body: fd}).then(r => r.json());
        if (data.ok) {
            msg.classList.replace('text-gray-500', 'text-emerald-600'); msg.textContent = '✅ Logo atualizado!';
            btn.classList.add('hidden');
            setTimeout(() => location.reload(), 1000);
        } else {
            msg.classList.replace('text-gray-500', 'text-red-600'); msg.textContent = '❌ ' + (data.error || 'Erro');
            btn.disabled = false; btn.textContent = 'Salvar Logo';
        }
    } catch(e) {
        msg.classList.replace('text-gray-500', 'text-red-600'); msg.textContent = '❌ Erro de rede.';
        btn.disabled = false; btn.textContent = 'Salvar Logo';
    }
}

// ── QR Code / Reconectar ───────────────────────────────────────────────────
let _qrPollTimer = null, _qrRefreshTimer = null;

function mostrarTela(id) {
    ['confirmar','loading','qrcode','ok','erro'].forEach(t =>
        document.getElementById('qr-tela-' + t).classList.add('hidden'));
    document.getElementById('qr-tela-' + id).classList.remove('hidden');
}
function abrirModalReconectar() { mostrarTela('confirmar'); document.getElementById('modal-reconectar').classList.remove('hidden'); }
function fecharModalReconectar() {
    clearTimeout(_qrPollTimer); clearTimeout(_qrRefreshTimer);
    _qrPollTimer = _qrRefreshTimer = null;
    document.getElementById('modal-reconectar').classList.add('hidden');
}
function qrPost(action) {
    return fetch(BASE + '/api/whatsapp_reconectar.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('input[name=csrf_token]')?.value ?? ''},
        body: JSON.stringify({action})
    }).then(r => r.json());
}
async function executarLogout() {
    mostrarTela('loading'); document.getElementById('qr-loading-msg').textContent = 'Desconectando número atual...';
    try { const r = await qrPost('logout'); if (!r.ok) { mostrarErro(r.error); return; } await iniciarQRFlow(); }
    catch(e) { mostrarErro('Erro de rede ao desconectar.'); }
}
async function iniciarQRFlow() {
    clearTimeout(_qrPollTimer); clearTimeout(_qrRefreshTimer);
    mostrarTela('loading'); document.getElementById('qr-loading-msg').textContent = 'Gerando QR code...';
    try {
        const r = await qrPost('qrcode');
        if (r.connected) { mostrarTela('ok'); return; }
        if (!r.ok) { mostrarErro(r.error); return; }
        document.getElementById('qr-img').src = r.base64;
        mostrarTela('qrcode'); iniciarPolling(); agendarRefreshQR();
    } catch(e) { mostrarErro('Erro de rede ao gerar QR.'); }
}
function iniciarPolling() {
    _qrPollTimer = setTimeout(async () => {
        try {
            const r = await qrPost('status');
            if (r.ok && r.state === 'open') { clearTimeout(_qrRefreshTimer); mostrarTela('ok'); }
            else iniciarPolling();
        } catch(e) { iniciarPolling(); }
    }, 3000);
}
function agendarRefreshQR(segundos = 30) {
    let restante = segundos;
    const tick = () => {
        if (document.getElementById('qr-tela-qrcode').classList.contains('hidden')) return;
        restante--;
        const el = document.getElementById('qr-timer');
        if (el) el.textContent = restante > 0 ? `QR code expira em ${restante}s` : 'Atualizando...';
        if (restante <= 0) { iniciarQRFlow(); return; }
        _qrRefreshTimer = setTimeout(tick, 1000);
    };
    _qrRefreshTimer = setTimeout(tick, 1000);
}
function mostrarErro(msg) { document.getElementById('qr-erro-msg').textContent = msg; mostrarTela('erro'); }
document.getElementById('modal-reconectar').addEventListener('click', function(e) {
    if (e.target === this) fecharModalReconectar();
});
</script>

<?php layoutEnd(); ?>
