<?php
/**
 * portal.php — Portal público de achadinhos fitness.
 * Sem login. Lista ofertas enviadas com busca, banner e filtro por categoria.
 */
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/helpers.php';

$db  = getDB();
$q   = trim($_GET['q'] ?? '');

$por_pag = 24;
$pagina  = max(1, (int)($_GET['p'] ?? 1));
$offset  = ($pagina - 1) * $por_pag;

$where  = "WHERE status = 'enviada'";
$params = [];
if ($q !== '') {
    $where   .= " AND nome LIKE ?";
    $params[] = "%{$q}%";
}

$stCount = $db->prepare("SELECT COUNT(*) FROM ofertas {$where}");
$stCount->execute($params);
$total = (int)$stCount->fetchColumn();
$total_paginas = max(1, ceil($total / $por_pag));
$maxId = (int)($db->query("SELECT COALESCE(MAX(id),0) FROM ofertas WHERE status='enviada'")->fetchColumn());

$stmt = $db->prepare(
    "SELECT * FROM ofertas {$where} ORDER BY enviado_em DESC LIMIT {$por_pag} OFFSET {$offset}"
);
$stmt->execute($params);
$ofertas = $stmt->fetchAll();

// Banner
$banner_ativo     = getConfig('portal_banner_ativo') !== '0';

// Slides
$slides_stmt = $db->query("SELECT * FROM slides WHERE ativo=1 ORDER BY ordem ASC, id ASC");
$slides = $slides_stmt ? $slides_stmt->fetchAll() : [];
$banner_titulo    = getConfig('portal_banner_titulo') ?: 'Melhores Ofertas Fitness';
$banner_subtitulo = getConfig('portal_banner_subtitulo') ?: 'Suplementos, roupas e equipamentos com descontos todo dia';

function detectarCat(string $nome): string {
    $n = mb_strtolower($nome, 'UTF-8');
    if (preg_match('/whey|creatina|prote[ií]na|bcaa|col[aá]geno|vitamina|glutamina|termog[eê]nico|pr[eé].treino|hipercal[oó]rico|albumina|cafe[ií]na|[oô]mega|multivitamin|suplemento/u', $n)) return 'suplementos';
    if (preg_match('/legging|shorts|top |camiseta|bermuda|regata|conjunto|suti[aã]|jogger|moletom|jaqueta|corta.vento|roupa|blusa|cal[çc]a/u', $n)) return 'roupas';
    if (preg_match('/t[eê]nis|meia esportiva|cal[çc]ado/u', $n)) return 'calcados';
    if (preg_match('/haltere|anilha|barra|kettlebell|faixa|el[áa]stico|corda pular|step|roda abdominal|paralela|supino|muscula[çc][aã]o/u', $n)) return 'equipamentos';
    return 'acessorios';
}

function tempoRelativo(?string $dt): string {
    if (!$dt) return '';
    $diff = time() - strtotime($dt);
    if ($diff < 3600)  return 'há ' . max(1, (int)($diff / 60)) . ' min';
    if ($diff < 86400) return 'há ' . (int)($diff / 3600) . 'h';
    return 'há ' . (int)($diff / 86400) . ' dia(s)';
}

function imgUrl(array $o): string {
    if ($o['imagem_path'] && file_exists(__DIR__ . '/' . ltrim($o['imagem_path'], '/'))) {
        return BASE . '/' . ltrim($o['imagem_path'], '/');
    }
    if ($o['imagem_url']) return $o['imagem_url'];
    return '';
}

// 3 tiers de cor por faixa de desconto
function badgeClasses(int $pct): string {
    if ($pct >= 50) return 'bg-rose-500 text-white';
    if ($pct >= 25) return 'bg-amber-400 text-amber-900';
    return 'bg-emerald-600 text-white';
}

$cats = [
    'todas'        => 'Todas',
    'suplementos'  => 'Suplementos',
    'roupas'       => 'Roupas',
    'calcados'     => 'Calçados',
    'equipamentos' => 'Equipamentos',
    'acessorios'   => 'Acessórios',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CasaFit Ofertas — by Rede de Ofertas Viana | Melhores Promoções Fitness</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .card-img { aspect-ratio: 1/1; object-fit: contain; background: #f0fdf4; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .cat-btn { transition: all .15s; }
        .cat-btn.active { background: #059669; color: #fff; border-color: #059669; }
        .card { transition: box-shadow .2s, transform .15s; }
        .card:hover { box-shadow: 0 6px 24px rgba(5,150,105,.12); transform: translateY(-1px); }
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Header -->
<header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 h-14 flex items-center gap-4">

        <a href="<?= BASE ?>/" class="flex items-center gap-2 flex-shrink-0">
            <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div class="leading-none">
                <span class="block text-sm font-bold text-gray-900 tracking-tight">CasaFit</span>
                <span class="block text-[9px] font-medium text-gray-400 tracking-wide leading-none">by Rede de Ofertas Viana</span>
            </div>
        </a>

        <div class="flex items-center gap-2 flex-shrink-0">
            <a href="https://chat.whatsapp.com/CgfC84iFsQf1j5x6sqgJFz?mode=gi_t" target="_blank" rel="noopener noreferrer"
               class="hidden sm:flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.132.558 4.13 1.535 5.865L.057 23.854a.5.5 0 00.61.61l6.058-1.474A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.907 0-3.693-.504-5.234-1.384l-.374-.222-3.878.942.962-3.808-.242-.386A9.96 9.96 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                </svg>
                Entrar no Grupo
            </a>
            <a href="https://www.instagram.com/casafit_ofertas/" target="_blank" rel="noopener noreferrer"
               class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:text-pink-500 hover:border-pink-200 transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                </svg>
            </a>
        </div>

        <form method="get" class="flex-1 max-w-md">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="q"
                    value="<?= htmlspecialchars($q, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
                    placeholder="Buscar produto..."
                    class="w-full pl-9 pr-8 py-2 rounded-lg border border-gray-200 text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-colors placeholder:text-gray-400">
                <?php if ($q): ?>
                <button type="button" onclick="location.href='<?= BASE ?>/portal'"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <?php endif ?>
            </div>
        </form>

    </div>
</header>


<!-- Slider -->
<?php if (!empty($slides)): ?>
<div class="relative overflow-hidden bg-black" id="slider">
    <div id="slider-track" class="flex transition-transform duration-500 ease-in-out">
        <?php foreach ($slides as $sl):
            $sImg  = BASE . '/' . ltrim(htmlspecialchars($sl['imagem_path'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'), '/');
            $sHref = htmlspecialchars($sl['link_url'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        ?>
        <div class="min-w-full relative" style="aspect-ratio:16/5;">
            <?php if ($sl['link_url']): ?><a href="<?= $sHref ?>" target="_blank" rel="noopener noreferrer nofollow"><?php endif ?>
            <img src="<?= $sImg ?>" alt="<?= htmlspecialchars($sl['titulo'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
                 class="w-full h-full object-cover" loading="lazy">
            <?php if ($sl['titulo'] || $sl['subtitulo']): ?>
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent flex flex-col justify-end px-6 pb-5">
                <?php if ($sl['titulo']): ?>
                <p class="text-white font-bold text-lg sm:text-2xl leading-tight drop-shadow"><?= htmlspecialchars($sl['titulo'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <?php endif ?>
                <?php if ($sl['subtitulo']): ?>
                <p class="text-white/80 text-sm sm:text-base mt-1 drop-shadow"><?= htmlspecialchars($sl['subtitulo'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <?php endif ?>
            </div>
            <?php endif ?>
            <?php if ($sl['link_url']): ?></a><?php endif ?>
        </div>
        <?php endforeach ?>
    </div>

    <?php if (count($slides) > 1): ?>
    <!-- Setas -->
    <button onclick="sliderMove(-1)" class="absolute left-3 top-1/2 -translate-y-1/2 w-8 h-8 bg-black/40 hover:bg-black/60 text-white rounded-full flex items-center justify-center transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <button onclick="sliderMove(1)" class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 bg-black/40 hover:bg-black/60 text-white rounded-full flex items-center justify-center transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
    </button>
    <!-- Dots -->
    <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
        <?php foreach ($slides as $i => $_): ?>
        <button onclick="sliderGo(<?= $i ?>)" data-dot="<?= $i ?>"
            class="w-2 h-2 rounded-full transition-all <?= $i === 0 ? 'bg-white scale-125' : 'bg-white/50' ?>"></button>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>
<?php endif ?>

<!-- Filtros -->
<div class="bg-white border-b border-gray-100 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 py-2.5 flex gap-1.5 overflow-x-auto">
        <?php foreach ($cats as $slug => $label): ?>
        <button data-cat="<?= $slug ?>" onclick="filtrarCat(this)"
            class="cat-btn flex-shrink-0 px-4 py-1.5 rounded-full border border-gray-200 text-sm font-medium text-gray-500 hover:border-emerald-400 hover:text-emerald-700 <?= $slug === 'todas' ? 'active' : '' ?>">
            <?= $label ?>
        </button>
        <?php endforeach ?>
    </div>
</div>

<!-- Grid -->
<main class="max-w-7xl mx-auto px-4 py-5">

<!-- Pill de polling: aparece quando chegam novas ofertas -->
<div id="poll-pill" class="hidden fixed top-5 left-1/2 z-50"
     style="transform:translateX(-50%)">
    <button onclick="smoothReload()"
        class="flex items-center gap-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold pl-3.5 pr-4 py-2.5 rounded-full shadow-2xl transition-all duration-200 hover:scale-105 active:scale-95"
        style="animation:pillSlideDown .4s cubic-bezier(.34,1.56,.64,1) both">
        <span class="relative flex h-2.5 w-2.5 flex-shrink-0">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-50"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-white/90"></span>
        </span>
        <span id="poll-count">Novas ofertas chegaram</span>
        <svg class="w-3.5 h-3.5 opacity-75" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
    </button>
</div>
<style>
@keyframes pillSlideDown {
    from { opacity:0; transform:translateX(-50%) translateY(-16px) scale(.92); }
    to   { opacity:1; transform:translateX(-50%) translateY(0)      scale(1);   }
}
</style>

    <?php if (empty($ofertas)): ?>
    <div class="text-center py-24 text-gray-400">
        <svg class="w-12 h-12 mx-auto mb-4 opacity-25" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <p class="text-base font-medium text-gray-600">Nenhuma oferta encontrada</p>
        <?php if ($q): ?><p class="text-sm mt-1">Tente outro termo de busca</p><?php endif ?>
    </div>
    <?php else: ?>

    <div id="grid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
        <?php foreach ($ofertas as $o):
            $cat      = detectarCat($o['nome']);
            $img      = imgUrl($o);
            $tempo    = tempoRelativo($o['enviado_em']);
            $desconto = (int)$o['desconto_pct'];
            $precoPor = (float)$o['preco_por'];
            $precoDe  = (float)$o['preco_de'];
            $badgeCls = badgeClasses($desconto);
            $href     = htmlspecialchars($o['url_afiliado'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
            $nomeEsc  = htmlspecialchars($o['nome'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        ?>
        <div class="card bg-white rounded-xl border border-gray-100 overflow-hidden flex flex-col"
             data-cat="<?= $cat ?>">

            <a href="<?= $href ?>" target="_blank" rel="noopener noreferrer nofollow" class="block relative">
                <?php if ($img): ?>
                    <img src="<?= htmlspecialchars($img, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?>"
                         alt="<?= $nomeEsc ?>"
                         class="card-img w-full"
                         loading="lazy"
                         onerror="this.parentElement.innerHTML='<div class=\'card-img w-full flex items-center justify-center bg-emerald-50\'><svg class=\'w-10 h-10 text-emerald-200\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4\'/></svg></div>'">
                <?php else: ?>
                    <div class="card-img w-full flex items-center justify-center bg-emerald-50">
                        <svg class="w-10 h-10 text-emerald-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                <?php endif ?>

                <?php if ($desconto >= 5): ?>
                <span class="absolute top-2 left-2 text-xs font-bold px-2 py-0.5 rounded-md <?= $badgeCls ?>">
                    -<?= $desconto ?>%
                </span>
                <?php endif ?>
            </a>

            <div class="p-2.5 flex flex-col flex-1">
                <p class="text-xs text-gray-700 line-clamp-2 font-medium leading-snug mb-2">
                    <?= $nomeEsc ?>
                </p>
                <div class="mt-auto space-y-0.5">
                    <?php if ($precoDe > 0 && $precoDe > $precoPor): ?>
                    <p class="text-[11px] text-gray-400 line-through">R$ <?= number_format($precoDe, 2, ',', '.') ?></p>
                    <?php endif ?>
                    <?php if ($precoPor > 0): ?>
                    <p class="text-sm font-bold text-emerald-700">R$ <?= number_format($precoPor, 2, ',', '.') ?></p>
                    <?php endif ?>
                    <?php if ($tempo): ?>
                    <p class="text-[10px] text-gray-400"><?= $tempo ?></p>
                    <?php endif ?>
                    <a href="<?= $href ?>" target="_blank" rel="noopener noreferrer nofollow"
                       class="mt-2 block w-full text-center bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-1.5 rounded-lg transition-colors">
                        Ver oferta
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach ?>
    </div>

    <?php if ($total_paginas > 1):
        $inicio = max(1, $pagina - 2);
        $fim    = min($total_paginas, $pagina + 2);
        if ($fim - $inicio < 4) {
            if ($inicio === 1) $fim = min($total_paginas, $inicio + 4);
            else $inicio = max(1, $fim - 4);
        }
        $mkUrl = fn(int $p) => '?' . http_build_query(array_merge($_GET, ['p' => $p]));
    ?>
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-10 pt-6 border-t border-gray-200">

        <!-- Contador -->
        <p class="text-xs text-gray-400 tabular-nums order-2 sm:order-1">
            Página <span class="font-semibold text-gray-600"><?= $pagina ?></span> de
            <span class="font-semibold text-gray-600"><?= $total_paginas ?></span>
            &mdash;
            <span class="font-semibold text-gray-600"><?= number_format($total, 0, ',', '.') ?></span> oferta(s)
        </p>

        <!-- Controles -->
        <nav class="flex items-center gap-1 order-1 sm:order-2" aria-label="Paginação">

            <!-- Anterior -->
            <?php if ($pagina > 1): ?>
            <a href="<?= $mkUrl($pagina - 1) ?>"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <span class="hidden sm:inline">Anterior</span>
            </a>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-300 bg-gray-50 border border-gray-100 cursor-not-allowed">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <span class="hidden sm:inline">Anterior</span>
            </span>
            <?php endif; ?>

            <!-- Primeira + elipse -->
            <?php if ($inicio > 1): ?>
                <a href="<?= $mkUrl(1) ?>" class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm">1</a>
                <?php if ($inicio > 2): ?>
                    <span class="w-9 h-9 inline-flex items-center justify-center text-gray-400 text-sm select-none">…</span>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Páginas centrais -->
            <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                <?php if ($i === $pagina): ?>
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-sm font-bold text-white bg-emerald-600 shadow-sm shadow-emerald-200" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= $mkUrl($i) ?>" class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- Elipse + última -->
            <?php if ($fim < $total_paginas): ?>
                <?php if ($fim < $total_paginas - 1): ?>
                    <span class="w-9 h-9 inline-flex items-center justify-center text-gray-400 text-sm select-none">…</span>
                <?php endif; ?>
                <a href="<?= $mkUrl($total_paginas) ?>" class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm"><?= $total_paginas ?></a>
            <?php endif; ?>

            <!-- Próxima -->
            <?php if ($pagina < $total_paginas): ?>
            <a href="<?= $mkUrl($pagina + 1) ?>"
               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-600 bg-white border border-gray-200 hover:border-emerald-400 hover:text-emerald-700 hover:bg-emerald-50 transition-all duration-150 shadow-sm">
                <span class="hidden sm:inline">Próxima</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-gray-300 bg-gray-50 border border-gray-100 cursor-not-allowed">
                <span class="hidden sm:inline">Próxima</span>
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </span>
            <?php endif; ?>

        </nav>
    </div>
    <?php endif; ?>

    <?php endif ?>
</main>

<footer class="bg-emerald-700 mt-8">
    <div class="max-w-7xl mx-auto px-4 py-8 text-center">
        <div class="flex items-center justify-center gap-2 mb-2">
            <div class="w-6 h-6 bg-white/20 rounded-md flex items-center justify-center">
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <span class="text-white font-bold text-sm tracking-tight">CasaFit Ofertas</span>
            <span class="text-emerald-300/70 text-[10px] font-medium tracking-wide">by Rede de Ofertas Viana</span>
        </div>
        <p class="text-emerald-200 text-xs mb-4">Os melhores preços em suplementos, roupas e equipamentos</p>
        <div class="flex items-center justify-center gap-3 mb-4">
            <a href="https://chat.whatsapp.com/CgfC84iFsQf1j5x6sqgJFz?mode=gi_t" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.132.558 4.13 1.535 5.865L.057 23.854a.5.5 0 00.61.61l6.058-1.474A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.907 0-3.693-.504-5.234-1.384l-.374-.222-3.878.942.962-3.808-.242-.386A9.96 9.96 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                </svg>
                Grupo do WhatsApp
            </a>
            <a href="https://www.instagram.com/casafit_ofertas/" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                </svg>
                @casafit_ofertas
            </a>
        </div>
        <a href="<?= BASE ?>/bio" class="text-emerald-300/70 hover:text-emerald-200 text-xs transition-colors">Links</a>
        <span class="text-emerald-600 text-xs">·</span>
        <a href="<?= BASE ?>/termos" class="text-emerald-300/70 hover:text-emerald-200 text-xs transition-colors">Termos de Uso &amp; Privacidade</a>
        <span class="text-emerald-600 text-xs">·</span>
        <a href="https://github.com/lmiguelviana" target="_blank" rel="noopener noreferrer"
           class="text-emerald-300/70 hover:text-emerald-200 text-xs transition-colors">Dev by lmiguelviana</a>
    </div>
</footer>

<script>
// Slider
(function() {
    const total = <?= count($slides) ?>;
    if (total <= 1) return;
    let cur = 0, timer;
    const track = document.getElementById('slider-track');
    const dots  = document.querySelectorAll('[data-dot]');

    function go(n) {
        cur = (n + total) % total;
        track.style.transform = `translateX(-${cur * 100}%)`;
        dots.forEach((d, i) => {
            d.classList.toggle('bg-white', i === cur);
            d.classList.toggle('scale-125', i === cur);
            d.classList.toggle('bg-white/50', i !== cur);
        });
        clearTimeout(timer);
        timer = setTimeout(() => go(cur + 1), 5000);
    }
    window.sliderMove = n => go(cur + n);
    window.sliderGo   = n => go(n);
    timer = setTimeout(() => go(1), 5000);
})();

function filtrarCat(btn) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const cat = btn.dataset.cat;
    document.querySelectorAll('#grid > [data-cat]').forEach(card => {
        card.style.display = (cat === 'todas' || card.dataset.cat === cat) ? '' : 'none';
    });
}
</script>
<!-- Notificação social proof -->
<div id="social-notif"
     class="hidden fixed bottom-5 left-5 z-50 bg-white border border-gray-200 rounded-2xl shadow-xl px-4 py-3 flex items-center gap-3 max-w-xs"
     style="animation: slideIn .4s ease">
    <div class="w-9 h-9 bg-emerald-100 rounded-full flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
        </svg>
    </div>
    <div class="min-w-0">
        <p class="text-xs font-bold text-gray-800 truncate" id="notif-nome"></p>
        <p class="text-xs text-gray-500 truncate" id="notif-produto"></p>
        <p class="text-[10px] text-emerald-600 font-semibold mt-0.5">acabou de pegar essa oferta ✓</p>
    </div>
    <button onclick="document.getElementById('social-notif').classList.add('hidden')"
        class="text-gray-300 hover:text-gray-500 flex-shrink-0 ml-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

<style>
@keyframes slideIn { from { transform: translateX(-110%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(-110%); opacity: 0; } }
</style>

<script>
(function() {
    const nomes = [
        'Ana Lima','Carlos Oliveira','Fernanda Silva','Lucas Souza','Juliana Santos',
        'Rodrigo Costa','Mariana Ferreira','Bruno Pereira','Camila Alves','Diego Rocha',
        'Gabriela Mendes','Felipe Carvalho','Letícia Gomes','Mateus Ribeiro','Priscila Martins',
        'Rafael Araujo','Tatiane Nunes','Eduardo Barros','Vanessa Lopes','André Machado',
        'Aline Freitas','Thiago Monteiro','Bianca Rodrigues','Leandro Vieira','Sabrina Castro',
        'Paulo Melo','Natalia Cardoso','Gustavo Teixeira','Renata Correia','Igor Pinto',
        'Elaine Borges','Marcelo Moreira','Viviane Dias','Flavio Cunha','Simone Nascimento',
        'Fábio Batista','Cláudia Ramos','Sérgio Fernandes','Andréa Leal','Mauricio Azevedo',
        'Luciana Campos','Alessandro Faria','Patrícia Cavalcanti','Henrique Andrade','Denise Cruz',
        'Vinicius Torres','Rosana Braga','Caio Lima','Mônica Vargas','Danilo Pires',
        'Adriana Queiroz','Marcela Guimarães','Wellington Figueiredo','Cristiane Duarte','Samuel Medeiros',
        'Larissa Tavares','Jonas Brito','Amanda Sousa','Roberto Amaral','Sheila Nogueira',
        'Tiago Bezerra','Karina Peixoto','Alan Vasconcelos','Vera Neto','Charles Paiva',
        'Josiane Moura','Evandro Rezende','Débora Matos','Gilberto Marques','Jéssica Bastos',
        'Nathalia Coelho','Alexsandro Moraes','Miriam Fonseca','Nilson Macedo','Daiana Leite',
        'Leonardo Queiroz','Solange Xavier','Edson Miranda','Paloma Siqueira','Rinaldo Assis',
        'Tatiana Aguiar','Clayton Bittencourt','Mônica Veloso','Renan Loyola','Ângela Zambon',
        'Luciano Petri','Ivone Bueno','Walmir Godoi','Sueli Padilha','Fabrício Luz',
        'Rosilene Esteves','Celso Meirelles','Adriele Fontes','Hermes Salgado','Marilene Cunha',
        'Tarcísio Duarte','Elielma Santos','Niraldo Gama','Geovana Britto','Arlindo Ferraz',
    ];

    <?php
    $produtosJs = [];
    if (!empty($ofertas)) {
        foreach (array_slice($ofertas, 0, 40) as $o) {
            $nome = mb_substr($o['nome'], 0, 50, 'UTF-8');
            $produtosJs[] = $nome;
        }
    }
    if (empty($produtosJs)) {
        $produtosJs = ['Whey Protein 900g','Legging Fitness','Tênis de Academia','Creatina 300g','Coqueteleira'];
    }
    ?>
    const produtos = <?= json_encode($produtosJs, JSON_UNESCAPED_UNICODE) ?>;

    function notifAleatorio() {
        const nome    = nomes[Math.floor(Math.random() * nomes.length)];
        const produto = produtos[Math.floor(Math.random() * produtos.length)];
        const el      = document.getElementById('social-notif');

        document.getElementById('notif-nome').textContent    = nome;
        document.getElementById('notif-produto').textContent = produto;

        el.style.animation = 'slideIn .4s ease';
        el.classList.remove('hidden');

        setTimeout(() => {
            el.style.animation = 'slideOut .4s ease forwards';
            setTimeout(() => el.classList.add('hidden'), 400);
        }, 6000);
    }

    // Primeira notificação após 10s, depois a cada 45s
    setTimeout(function loop() {
        notifAleatorio();
        setTimeout(loop, 45000);
    }, 10000);
})();
</script>
<script>
// ── Silent Polling — Portal Público ────────────────────────────────────────
(function() {
    const MAX_ID   = <?= $maxId ?>;
    const ENDPOINT = '<?= BASE ?>/api/portal_poll.php';
    let   timer;

    // Fade-in suave ao carregar a página
    const grid = document.getElementById('grid');
    if (grid) {
        grid.style.opacity = '0';
        grid.style.transition = 'opacity .4s ease';
        requestAnimationFrame(() => requestAnimationFrame(() => { grid.style.opacity = '1'; }));
    }

    async function check() {
        try {
            const res  = await fetch(`${ENDPOINT}?last_id=${MAX_ID}`, { cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();
            if (data.ok && data.novas > 0) {
                const pill  = document.getElementById('poll-pill');
                const label = document.getElementById('poll-count');
                label.textContent = data.novas === 1
                    ? '1 nova oferta chegou — Ver agora'
                    : `${data.novas} novas ofertas chegaram — Ver agora`;
                pill.classList.remove('hidden');
                clearInterval(timer);
            }
        } catch (_) { /* silencioso */ }
    }

    // Primeira verificação após 12s, depois a cada 30s
    setTimeout(check, 12000);
    timer = setInterval(check, 30000);

    // Reload suave: fade-out → reload → fade-in
    window.smoothReload = function() {
        document.getElementById('poll-pill').classList.add('hidden');
        const el = document.getElementById('grid') || document.querySelector('main');
        if (el) { el.style.transition = 'opacity .22s ease'; el.style.opacity = '0'; }
        setTimeout(() => location.reload(), 240);
    };
})();
</script>
</body>
</html>
