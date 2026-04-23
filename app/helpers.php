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

    $nav = [
        'index'     => ['href' => BASE . '/v-admin',      'icon' => 'grid',     'label' => 'Dashboard'],
        'grupos'    => ['href' => BASE . '/grupos',    'icon' => 'users',    'label' => 'Grupos'],
        'historico' => ['href' => BASE . '/historico', 'icon' => 'clock',    'label' => 'Histórico'],
        'fila'      => ['href' => BASE . '/fila',      'icon' => 'inbox',    'label' => 'Fila de Ofertas'],
        'logs'      => ['href' => BASE . '/logs',      'icon' => 'terminal', 'label' => 'Logs do Sistema'],
        'usuarios'  => ['href' => BASE . '/usuarios',  'icon' => 'user',     'label' => 'Usuários'],
        'slides'    => ['href' => BASE . '/slides',    'icon' => 'image',    'label' => 'Slides Portal'],
        'config'    => ['href' => BASE . '/config',    'icon' => 'settings', 'label' => 'Config'],
    ];

    $icons = [
        'grid'     => '<path d="M3 3h7v7H3V3zm0 11h7v7H3v-7zm11-11h7v7h-7V3zm0 11h7v7h-7v-7z"/>',
        'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'inbox'    => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/>',
        'terminal' => '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 4.93 19.07 10 10 0 0 0 19.07 4.93z"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
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
    echo '</head><body class="bg-gray-50 text-gray-800 flex min-h-screen">';

    // ── Sidebar ──────────────────────────────────────────────────────────
    echo '<aside class="w-56 bg-white border-r border-gray-200 flex flex-col fixed top-0 left-0 h-full z-10">';

    // Logo
    echo '<div class="px-4 py-4 border-b border-gray-100 flex items-center gap-2.5">';
    echo '  <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center text-white font-black text-sm flex-shrink-0">V</div>';
    echo '  <div><span class="font-black text-base text-emerald-600">Viana</span><span class="font-bold text-base text-gray-700"> Promo</span></div>';
    echo '</div>';

    // Nav
    echo '<nav class="flex-1 py-3 space-y-0.5 px-2 overflow-y-auto">';
    foreach ($nav as $key => $item) {
        $active = $paginaAtiva === $key
            ? 'bg-emerald-50 text-emerald-700 font-semibold'
            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800';
        $svg = $icons[$item['icon']] ?? '';
        echo "<a href=\"{$item['href']}\" class=\"flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-all {$active}\">";
        echo "<svg xmlns='http://www.w3.org/2000/svg' class='w-4 h-4 flex-shrink-0' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2' aria-hidden='true'>{$svg}</svg>";
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
    
    echo "<div class=\"px-3 py-3 border-t border-gray-100\">";
    
    // Perfil Hover
    echo "  <a href=\"" . BASE . "/perfil\" title=\"Editar Perfil\" class=\"flex items-center gap-2.5 mb-2.5 p-1 -mx-1 rounded-lg hover:bg-gray-50 transition-colors group\">";
    
    if ($fotoUrl) {
        echo "    <img src=\"{$fotoUrl}\" alt=\"{$nome}\" class=\"w-8 h-8 rounded-full object-cover flex-shrink-0 border border-gray-200\">";
    } else {
        echo "    <div class=\"w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center text-sm font-bold text-emerald-700 flex-shrink-0\">{$inicial}</div>";
    }
    
    echo "    <div class=\"min-w-0 flex-1\">";
    echo "      <p class=\"text-sm font-semibold text-gray-800 truncate group-hover:text-emerald-700 transition-colors\">{$nome}</p>";
    echo "      <p class=\"text-xs text-gray-400 truncate\">{$email}</p>";
    echo "    </div>";
    echo "  </a>";
    
    echo "  <a href=\"" . BASE . "/logout\" class=\"flex items-center gap-2 text-xs text-gray-500 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 px-3 py-2 rounded-lg transition-all\">";
    echo "    <svg xmlns='http://www.w3.org/2000/svg' class='w-3.5 h-3.5' fill='none' viewBox='0 0 24 24' stroke='currentColor' stroke-width='2'><path stroke-linecap='round' stroke-linejoin='round' d='M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1'/></svg>";
    echo "    Sair";
    echo "  </a>";
    echo "</div>";
    echo '</aside>';

    // ── Main ──────────────────────────────────────────────────────────────
    echo '<main class="ml-56 flex-1 flex flex-col min-h-screen">';
    echo '<header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between">';
    echo "<h1 class=\"text-xl font-bold text-gray-900\">{$titulo}</h1>";
    echo '<a href="' . BASE . '/" target="_blank" rel="noopener" class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3 py-1.5 rounded-lg transition-colors">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>';
    echo 'Ver Portal</a>';
    echo '</header>';
    echo '<div class="p-6 lg:p-8 flex-1">';
}

function layoutEnd(): void {
    echo '</div></main>';
    echo '</body></html>';
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
