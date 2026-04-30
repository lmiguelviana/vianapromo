<?php

// BASE_URL: vazio = raiz (VPS/produção), '/viana' = local XAMPP
// Defina APP_BASE='' nas variáveis de ambiente do EasyPanel
define('BASE', rtrim(getenv('APP_BASE') !== false ? (string)getenv('APP_BASE') : '/viana', '/'));

const PLATAFORMAS = [
    'ML'  => ['label' => 'Mercado Livre', 'color' => 'bg-orange-100 text-orange-800 border border-orange-200'],
    'AMZ' => ['label' => 'Amazon',        'color' => 'bg-sky-100 text-sky-800 border border-sky-200'],
    'SHP' => ['label' => 'Shopee',        'color' => 'bg-red-100 text-red-800 border border-red-200'],
    'TKT' => ['label' => 'TikTok Shop',   'color' => 'bg-gray-900 text-white border border-gray-700'],
    'MGZ' => ['label' => 'Magazine Luiza','color' => 'bg-blue-100 text-blue-800 border border-blue-200'],
];

function badgePlataforma(string $codigo): string {
    $p = PLATAFORMAS[$codigo] ?? ['label' => $codigo, 'color' => 'bg-gray-100 text-gray-700 border border-gray-200'];
    return "<span class=\"inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded-md {$p['color']}\">{$p['label']}</span>";
}

function msgTemplate(string $nomeProduto, string $urlAfiliado, string $precoDe = '', string $precoPor = ''): string {
    $preco = '';
    if ($precoDe && $precoPor) {
        $preco = "\n\n~~R$ {$precoDe}~~ → *R$ {$precoPor}*";
    } elseif ($precoPor) {
        $preco = "\n\n*Por apenas R$ {$precoPor}*";
    }
    return "🔥 *OFERTA IMPERDÍVEL* 🔥\n\n*{$nomeProduto}*{$preco}\n\n👉 {$urlAfiliado}\n\n⚡ Aproveite enquanto dura!\n\n_Link de afiliado — se comprar por aqui me ajuda sem custo extra pra você_ 🙌";
}

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfVerify(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.']);
        exit;
    }
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function layoutStart(string $paginaAtiva, string $titulo): void {
    $user    = currentUser();
    $inicial = mb_strtoupper(mb_substr($user['nome'], 0, 1));

    // Logs ML e Shopee são unificados — ambos apontam para /logs
    $logsAtivo = in_array($paginaAtiva, ['logs_ml', 'logs_shopee', 'logs']);
    $nav = [
        'index'     => ['href' => BASE . '/v-admin',  'icon' => 'grid',     'label' => 'Dashboard'],
        'grupos'    => ['href' => BASE . '/grupos',   'icon' => 'users',    'label' => 'Grupos'],
        'historico' => ['href' => BASE . '/historico','icon' => 'clock',    'label' => 'Histórico'],
        'fila'      => ['href' => BASE . '/fila',     'icon' => 'inbox',    'label' => 'Fila de Ofertas'],
        'logs'      => ['href' => BASE . '/logs-ml',  'icon' => 'terminal', 'label' => 'Logs'],
        'keywords'  => ['href' => BASE . '/keywords', 'icon' => 'tag',      'label' => 'Keywords'],
        'usuarios'  => ['href' => BASE . '/usuarios', 'icon' => 'user',     'label' => 'Usuários'],
        'slides'    => ['href' => BASE . '/slides',   'icon' => 'image',    'label' => 'Slides Portal'],
        'linktree'  => ['href' => BASE . '/linktree', 'icon' => 'linktree', 'label' => 'LinkTree'],
        'config'    => ['href' => BASE . '/config',   'icon' => 'settings', 'label' => 'Config'],
    ];

    $icons = [
        'grid'     => '<path d="M3 3h7v7H3V3zm0 11h7v7H3v-7zm11-11h7v7h-7V3zm0 11h7v7h-7v-7z"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'inbox'    => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/>',
        'terminal' => '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'linktree' => '<path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>',
        'tag'      => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
    ];

    echo '<!DOCTYPE html><html lang="pt-BR"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo "<title>{$titulo} — Viana Promo</title>";
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . BASE . '/assets/app.css">';
    // Expõe BASE para os scripts JS inline (fetch URLs)
    echo '<script>const BASE = ' . json_encode(BASE) . ';</script>';
    echo '</head><body class="bg-gray-50 text-gray-800 min-h-screen">';
    echo '<div class="min-h-screen lg:flex">';
    echo '<div id="admin-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-black/50 backdrop-blur-sm lg:hidden" onclick="closeAdminSidebar()" aria-hidden="true"></div>';

    // ── Sidebar verde premium ─────────────────────────────────────────────
    echo '<aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 flex h-full w-64 max-w-[85vw] flex-col transform -translate-x-full transition-transform duration-250 ease-out lg:sticky lg:top-0 lg:z-10 lg:w-60 lg:max-w-none lg:translate-x-0 lg:h-screen" style="background:linear-gradient(180deg,#0d2018 0%,#0f2a1e 60%,#0a1f16 100%)">';

    // Logo
    $logoUrl = getConfig('system_logo_url');
    echo '<div class="px-5 py-4 flex items-center gap-3" style="border-bottom:1px solid rgba(255,255,255,0.08)">';
    if ($logoUrl && file_exists(getConfig('system_logo_path'))) {
        echo "  <img src=\"{$logoUrl}\" alt=\"Logo\" class=\"h-8 max-w-[140px] object-contain brightness-[2] saturate-0 invert\">";
    } else {
        echo '<div style="width:34px;height:34px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:15px;flex-shrink:0;box-shadow:0 4px 12px rgba(34,197,94,0.4)">V</div>';
        echo '<div><span style="font-weight:900;font-size:15px;color:#4ade80">Viana</span><span style="font-weight:600;font-size:15px;color:rgba(255,255,255,0.7)"> Promo</span></div>';
    }
    echo '</div>';

    // Nav
    echo '<nav class="flex-1 py-3 overflow-y-auto" style="padding-left:10px;padding-right:10px">';
    foreach ($nav as $key => $item) {
        $isActive = ($paginaAtiva === $key) || ($key === 'logs' && $logsAtivo);
        if ($isActive) {
            $cls = 'flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold transition-all';
            $style = 'background:rgba(74,222,128,0.18);color:#4ade80;border:1px solid rgba(74,222,128,0.2);margin-bottom:2px';
        } else {
            $cls = 'flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-medium transition-all';
            $style = 'color:rgba(255,255,255,0.55);border:1px solid transparent;margin-bottom:2px';
        }
        $svg = $icons[$item['icon']] ?? '';
        $hoverScript = $isActive ? '' : ' onmouseover="this.style.background=\'rgba(255,255,255,0.07)\';this.style.color=\'rgba(255,255,255,0.9)\'" onmouseout="this.style.background=\'transparent\';this.style.color=\'rgba(255,255,255,0.55)\'"';
        echo "<a href=\"{$item['href']}\" class=\"{$cls}\" style=\"{$style}\"{$hoverScript}>";
        echo "<svg xmlns='http://www.w3.org/2000/svg' class='w-4 h-4 flex-shrink-0' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='1.8' aria-hidden='true'>{$svg}</svg>";
        echo "<span>{$item['label']}</span></a>";
    }
    echo '</nav>';

    // Usuário + Logout
    $db = getDB();
    $u = $db->query("SELECT foto_path FROM usuarios WHERE id = " . (int)$user['id'])->fetch();
    $fotoPath = $u['foto_path'] ?? '';
    $fotoUrl  = $fotoPath && file_exists($fotoPath) ? BASE . '/uploads/' . basename($fotoPath) : '';

    $nome  = htmlspecialchars($user['nome']);
    $email = htmlspecialchars($user['email']);

    // Footer da sidebar — perfil + sair
    echo '<div style="padding:12px;border-top:1px solid rgba(255,255,255,0.08)">';

    echo '<a href="' . BASE . '/perfil" title="Editar Perfil" style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:12px;margin-bottom:8px;transition:background .2s" onmouseover="this.style.background=\'rgba(255,255,255,0.07)\'" onmouseout="this.style.background=\'transparent\'">';
    if ($fotoUrl) {
        echo "<img src=\"{$fotoUrl}\" alt=\"{$nome}\" style=\"width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid rgba(74,222,128,0.4)\">";
    } else {
        echo "<div style=\"width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#166534,#15803d);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#4ade80;flex-shrink:0;border:2px solid rgba(74,222,128,0.3)\">{$inicial}</div>";
    }
    echo '<div style="min-width:0;flex:1">';
    echo "<p style=\"font-size:13px;font-weight:600;color:rgba(255,255,255,0.9);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0\">{$nome}</p>";
    echo "<p style=\"font-size:11px;color:rgba(255,255,255,0.4);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0\">{$email}</p>";
    echo '</div>';
    echo '</a>';

    echo '<a href="' . BASE . '/logout" style="display:flex;align-items:center;gap:8px;font-size:12px;color:rgba(255,255,255,0.4);padding:7px 10px;border-radius:10px;transition:all .2s;text-decoration:none" onmouseover="this.style.background=\'rgba(255,255,255,0.07)\';this.style.color=\'rgba(255,255,255,0.7)\'" onmouseout="this.style.background=\'transparent\';this.style.color=\'rgba(255,255,255,0.4)\'">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" style="width:14px;height:14px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1"/></svg>';
    echo 'Sair';
    echo '</a>';
    echo '</div>';
    echo '</aside>';

    // ── Main ──────────────────────────────────────────────────────────────
    echo '<main class="min-w-0 flex-1 flex flex-col min-h-screen">';
    echo '<header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-6 lg:px-8 lg:py-4">';
    echo '<div class="flex min-w-0 items-center gap-3">';
    echo '<button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 lg:hidden" onclick="openAdminSidebar()" aria-label="Abrir menu">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>';
    echo '</button>';
    echo "<h1 class=\"truncate text-lg font-bold text-gray-900 sm:text-xl\">{$titulo}</h1>";
    echo '</div>';
    echo '<div class="flex items-center gap-2">';
    // Botão de alertas
    echo '<button id="btn-alertas" onclick="toggleAlertasDrawer()" class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors" aria-label="Alertas do bot">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>';
    echo '<span id="alertas-badge" class="absolute -top-1 -right-1 hidden min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1 leading-none">0</span>';
    echo '</button>';
    echo '<a href="' . BASE . '/" target="_blank" rel="noopener" class="inline-flex whitespace-nowrap items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-100 sm:px-3">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>';
    echo '<span class="hidden sm:inline">Ver Portal</span><span class="sm:hidden">Portal</span></a>';
    echo '</div>';
    echo '</header>';
    echo '<div class="flex-1 p-4 sm:p-6 lg:p-8">';
}

/**
 * Renderiza paginação premium com elipses inteligentes.
 *
 * @param int    $paginaAtual   Página atual (1-indexed)
 * @param int    $totalPaginas  Total de páginas
 * @param int    $totalItens    Total de registros para exibição
 * @param string $paramPagina   Nome do parâmetro GET da página (ex: 'pagina' ou 'p')
 * @param array  $extraParams   Outros parâmetros GET a preservar na URL
 */
function paginacao(int $paginaAtual, int $totalPaginas, int $totalItens, string $paramPagina = 'pagina', array $extraParams = []): void {
    if ($totalPaginas <= 1) return;

    $inicio = max(1, $paginaAtual - 2);
    $fim    = min($totalPaginas, $paginaAtual + 2);

    // Garante sempre 5 botões quando há páginas suficientes
    if ($fim - $inicio < 4) {
        if ($inicio === 1) $fim = min($totalPaginas, $inicio + 4);
        else $inicio = max(1, $fim - 4);
    }

    $buildUrl = function(int $p) use ($paramPagina, $extraParams): string {
        return '?' . http_build_query(array_merge($extraParams, [$paramPagina => $p]));
    };

    $itensPorPag = 20; // valor de exibição aproximado — só para o label
    $de  = (($paginaAtual - 1) * $itensPorPag) + 1;
    $ate = min($paginaAtual * $itensPorPag, $totalItens);
    ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-8 pt-6 border-t border-gray-100">

        <!-- Contador -->
        <p class="text-xs text-gray-400 tabular-nums order-2 sm:order-1">
            Página <span class="font-semibold text-gray-600"><?= $paginaAtual ?></span> de
            <span class="font-semibold text-gray-600"><?= $totalPaginas ?></span>
            &mdash;
            <span class="font-semibold text-gray-600"><?= number_format($totalItens, 0, ',', '.') ?></span> registro(s)
        </p>

        <!-- Controles -->
        <nav class="flex items-center gap-1 order-1 sm:order-2" aria-label="Paginação">

            <!-- Anterior -->
            <?php if ($paginaAtual > 1): ?>
            <a href="<?= $buildUrl($paginaAtual - 1) ?>"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm"
               aria-label="Página anterior">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <span class="hidden sm:inline">Anterior</span>
            </a>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-300 bg-gray-50 border border-gray-100 cursor-not-allowed" aria-disabled="true">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <span class="hidden sm:inline">Anterior</span>
            </span>
            <?php endif; ?>

            <!-- Primeira página + elipse -->
            <?php if ($inicio > 1): ?>
                <a href="<?= $buildUrl(1) ?>"
                   class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm">1</a>
                <?php if ($inicio > 2): ?>
                    <span class="w-9 h-9 inline-flex items-center justify-center text-gray-400 text-sm select-none">…</span>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Páginas centrais -->
            <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                <?php if ($i === $paginaAtual): ?>
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-sm font-bold text-white bg-emerald-600 shadow-sm shadow-emerald-200" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= $buildUrl($i) ?>"
                       class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Elipse + última página -->
            <?php if ($fim < $totalPaginas): ?>
                <?php if ($fim < $totalPaginas - 1): ?>
                    <span class="w-9 h-9 inline-flex items-center justify-center text-gray-400 text-sm select-none">…</span>
                <?php endif; ?>
                <a href="<?= $buildUrl($totalPaginas) ?>"
                   class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm"><?= $totalPaginas ?></a>
            <?php endif; ?>

            <!-- Próxima -->
            <?php if ($paginaAtual < $totalPaginas): ?>
            <a href="<?= $buildUrl($paginaAtual + 1) ?>"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm"
               aria-label="Próxima página">
                <span class="hidden sm:inline">Próxima</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-300 bg-gray-50 border border-gray-100 cursor-not-allowed" aria-disabled="true">
                <span class="hidden sm:inline">Próxima</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </span>
            <?php endif; ?>

        </nav>
    </div>
    <?php
}

function layoutEnd(): void {
    echo '</div></main>';
    // Drawer de alertas do bot
    echo <<<'HTML'
    <div id="alertas-backdrop" class="fixed inset-0 z-40 hidden bg-gray-900/30" onclick="closeAlertasDrawer()"></div>
    <aside id="alertas-drawer" class="fixed inset-y-0 right-0 z-50 w-full max-w-sm translate-x-full transform bg-white shadow-2xl transition-transform duration-200 ease-out flex flex-col">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-gray-900">Alertas do Bot</span>
                <span id="drawer-badge" class="hidden min-w-[20px] h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center px-1.5">0</span>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="marcarTodosLidos()" class="text-xs text-emerald-600 hover:underline font-medium">Limpar tudo</button>
                <button onclick="closeAlertasDrawer()" class="p-1 rounded-lg hover:bg-gray-100 text-gray-500" aria-label="Fechar">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div id="alertas-lista" class="flex-1 overflow-y-auto divide-y divide-gray-50"></div>
        <div id="alertas-vazio" class="flex-1 flex flex-col items-center justify-center gap-2 text-gray-400 hidden">
            <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm font-medium">Nenhum alerta pendente</p>
        </div>
    </aside>
    HTML;

    echo <<<'HTML'
    <script>
    // ── Alertas do Bot ───────────────────────────────────────────
    let _alertasIds = [];

    async function carregarAlertas() {
        try {
            const r = await fetch(BASE + '/api/alertas_poll.php');
            if (!r.ok) return;
            const d = await r.json();
            _alertasIds = (d.alertas || []).map(a => a.id);
            atualizarBadge(d.total || 0);
            renderAlertas(d.alertas || []);
        } catch(e) {}
    }

    function atualizarBadge(n) {
        const badge = document.getElementById('alertas-badge');
        const dbadge = document.getElementById('drawer-badge');
        if (!badge || !dbadge) return;
        if (n > 0) {
            badge.textContent = n > 99 ? '99+' : n;
            badge.classList.remove('hidden');
            dbadge.textContent = n > 99 ? '99+' : n;
            dbadge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
            dbadge.classList.add('hidden');
        }
    }

    function renderAlertas(alertas) {
        const lista  = document.getElementById('alertas-lista');
        const vazio  = document.getElementById('alertas-vazio');
        if (!lista || !vazio) return;
        if (!alertas.length) {
            lista.innerHTML = '';
            vazio.classList.remove('hidden');
            return;
        }
        vazio.classList.add('hidden');
        const tipoClass = { erro: 'bg-red-50 text-red-700', aviso: 'bg-amber-50 text-amber-700', info: 'bg-sky-50 text-sky-700' };
        const tipoIco  = { erro: '🔴', aviso: '🟡', info: '🔵' };
        lista.innerHTML = alertas.map(a => `
            <div class="px-5 py-3.5 space-y-1">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm text-gray-800 leading-snug">
                        <span class="mr-1">${tipoIco[a.tipo] || '⚪'}</span>${a.mensagem}
                    </p>
                    <button onclick="marcarLido(${a.id})" class="flex-shrink-0 text-gray-300 hover:text-gray-500 mt-0.5" aria-label="Marcar como lido">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="text-[11px] text-gray-400">${a.fonte.toUpperCase()} &middot; ${a.criado_em}</p>
            </div>`).join('');
    }

    async function marcarLido(id) {
        await fetch(BASE + '/api/alertas_lidos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('[name=csrf_token]')?.value || '' },
            body: JSON.stringify({ ids: [id] })
        });
        await carregarAlertas();
    }

    async function marcarTodosLidos() {
        await fetch(BASE + '/api/alertas_lidos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('[name=csrf_token]')?.value || '' },
            body: JSON.stringify({ ids: [] })
        });
        await carregarAlertas();
    }

    function toggleAlertasDrawer() {
        const drawer = document.getElementById('alertas-drawer');
        const backdrop = document.getElementById('alertas-backdrop');
        if (!drawer) return;
        const aberto = !drawer.classList.contains('translate-x-full');
        if (aberto) { closeAlertasDrawer(); } else { openAlertasDrawer(); }
    }

    function openAlertasDrawer() {
        const drawer = document.getElementById('alertas-drawer');
        const backdrop = document.getElementById('alertas-backdrop');
        drawer?.classList.remove('translate-x-full');
        backdrop?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        carregarAlertas();
    }

    function closeAlertasDrawer() {
        const drawer = document.getElementById('alertas-drawer');
        const backdrop = document.getElementById('alertas-backdrop');
        drawer?.classList.add('translate-x-full');
        backdrop?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    // Polling a cada 30s
    carregarAlertas();
    setInterval(carregarAlertas, 30000);

    // ── Sidebar ───────────────────────────────────────────────────
    function openAdminSidebar() {
        const sidebar = document.getElementById('admin-sidebar');
        const backdrop = document.getElementById('admin-sidebar-backdrop');
        if (!sidebar || !backdrop) return;
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeAdminSidebar() {
        const sidebar = document.getElementById('admin-sidebar');
        const backdrop = document.getElementById('admin-sidebar-backdrop');
        if (!sidebar || !backdrop) return;
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            closeAdminSidebar();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAdminSidebar();
            closeAlertasDrawer();
        }
    });
    </script>
    HTML;
    echo '</div></body></html>';
}

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function toast(): void {
    echo <<<'HTML'
    <div id="toast" class="fixed bottom-6 right-6 z-50 hidden pointer-events-none">
        <div id="toast-inner" class="flex items-center gap-3 px-5 py-3 rounded-xl shadow-2xl text-sm font-semibold text-white min-w-[200px]"></div>
    </div>
    <script>
    function showToast(msg, type = 'success') {
        const t     = document.getElementById('toast');
        const inner = document.getElementById('toast-inner');
        inner.textContent = msg;
        inner.className = 'flex items-center gap-3 px-5 py-3 rounded-xl shadow-2xl text-sm font-semibold text-white ' +
            (type === 'success' ? 'bg-emerald-600' : 'bg-red-600');
        t.classList.remove('hidden');
        clearTimeout(t._timer);
        t._timer = setTimeout(() => t.classList.add('hidden'), 4000);
    }
    </script>
    HTML;
}
