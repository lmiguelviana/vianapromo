<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/helpers.php';

$db    = getDB();
$nome  = getConfig('bio_nome') ?: 'CasaFit Ofertas';
$desc  = getConfig('bio_descricao') ?: '';
$avatar= getConfig('bio_avatar_path');
$avatarUrl = $avatar && file_exists(__DIR__ . '/' . ltrim($avatar, '/'))
    ? BASE . '/' . ltrim($avatar, '/')
    : '';

$links = $db->query("SELECT * FROM bio_links WHERE ativo=1 ORDER BY ordem ASC, id ASC")->fetchAll();

$icones = [
    'whatsapp'  => '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12 0C5.373 0 0 5.373 0 12c0 2.132.558 4.13 1.535 5.865L.057 23.854a.5.5 0 00.61.61l6.058-1.474A11.952 11.952 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.907 0-3.693-.504-5.234-1.384l-.374-.222-3.878.942.962-3.808-.242-.386A9.96 9.96 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>',
    'instagram' => '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>',
    'tiktok'    => '<path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.32 6.32 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.76a4.85 4.85 0 01-1.01-.07z"/>',
    'youtube'   => '<path d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/>',
    'telegram'  => '<path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>',
    'link'      => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>',
    'ofertas'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col items-center py-12 px-4">

    <div class="w-full max-w-sm">

        <!-- Avatar + Nome -->
        <div class="text-center mb-8">
            <?php if ($avatarUrl): ?>
            <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"
                 class="w-24 h-24 rounded-full object-cover mx-auto mb-4 border-4 border-white shadow-lg">
            <?php else: ?>
            <div class="w-24 h-24 rounded-full bg-emerald-600 flex items-center justify-center mx-auto mb-4 shadow-lg border-4 border-white">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <?php endif ?>
            <h1 class="text-xl font-extrabold text-gray-900"><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if ($desc): ?>
            <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif ?>
        </div>

        <!-- Links -->
        <div class="space-y-3">
            <?php foreach ($links as $l):
                $icone = $icones[$l['icone']] ?? $icones['link'];
                $cor   = htmlspecialchars($l['cor'] ?: '#059669', ENT_QUOTES, 'UTF-8');
                $isFilled = !in_array($l['icone'], ['link', 'ofertas']);
            ?>
            <a href="<?= htmlspecialchars($l['url'], ENT_QUOTES, 'UTF-8') ?>"
               target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-3 w-full px-5 py-4 rounded-2xl text-white font-semibold text-sm shadow-sm hover:opacity-90 active:scale-95 transition-all"
               style="background-color: <?= $cor ?>">
                <svg class="w-5 h-5 flex-shrink-0" fill="<?= $isFilled ? 'currentColor' : 'none' ?>" viewBox="0 0 24 24"
                     <?= !$isFilled ? 'stroke="currentColor"' : '' ?>>
                    <?= $icone ?>
                </svg>
                <span class="flex-1 text-center"><?= htmlspecialchars($l['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <?php endforeach ?>

            <?php if (empty($links)): ?>
            <p class="text-center text-sm text-gray-400 py-8">Nenhum link cadastrado ainda.</p>
            <?php endif ?>
        </div>

        <!-- Footer -->
        <div class="mt-10 text-center">
            <a href="<?= BASE ?>/" class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-emerald-600 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                CasaFit Ofertas
            </a>
        </div>
    </div>

</body>
</html>
