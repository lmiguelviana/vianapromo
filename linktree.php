<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db    = getDB();
$links = $db->query("SELECT * FROM bio_links ORDER BY ordem ASC, id ASC")->fetchAll();

layoutStart('linktree', 'LinkTree');
toast();
?>

<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        <a href="<?= BASE ?>/bio" target="_blank" rel="noopener"
           class="flex items-center gap-1.5 text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-lg hover:bg-emerald-100 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            Ver página
        </a>
    </div>
    <button onclick="abrirModal()"
        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Novo Link
    </button>
</div>

<!-- Perfil -->
<div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-4">Perfil da página</h2>
    <div class="flex items-center gap-4">
        <!-- Avatar preview -->
        <div class="relative flex-shrink-0">
            <?php
            $avatar = getConfig('bio_avatar_path');
            $avatarUrl = $avatar && file_exists(__DIR__ . '/' . ltrim($avatar, '/'))
                ? BASE . '/' . ltrim($avatar, '/') : '';
            ?>
            <div id="avatar-wrap" class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center overflow-hidden border-2 border-emerald-200 cursor-pointer hover:opacity-80 transition"
                 onclick="document.getElementById('avatar-input').click()" title="Clique para trocar foto">
                <?php if ($avatarUrl): ?>
                <img id="avatar-img" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" class="w-full h-full object-cover">
                <?php else: ?>
                <svg id="avatar-icon" class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <?php endif ?>
            </div>
            <input type="file" id="avatar-input" accept="image/*" class="hidden">
            <input type="hidden" id="bio-avatar-path" value="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="flex-1 space-y-2">
            <input type="text" id="bio-nome" value="<?= htmlspecialchars(getConfig('bio_nome'), ENT_QUOTES, 'UTF-8') ?>"
                   class="input w-full" placeholder="Nome da página">
            <input type="text" id="bio-desc" value="<?= htmlspecialchars(getConfig('bio_descricao'), ENT_QUOTES, 'UTF-8') ?>"
                   class="input w-full" placeholder="Descrição / bio">
        </div>
        <button onclick="salvarPerfil()" class="btn-primary text-sm px-4 py-2 rounded-lg flex-shrink-0">Salvar</button>
    </div>
</div>

<!-- Lista de links -->
<?php if (empty($links)): ?>
<div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
    <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
    </svg>
    <p class="text-gray-500 font-medium">Nenhum link cadastrado</p>
    <p class="text-sm text-gray-400 mt-1">Clique em "Novo Link" para adicionar</p>
</div>
<?php else: ?>
<div class="space-y-2">
    <?php foreach ($links as $l): ?>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 flex items-center gap-3" id="bio-link-<?= $l['id'] ?>">
        <div class="w-4 h-4 rounded-full flex-shrink-0" style="background-color: <?= htmlspecialchars($l['cor'] ?: '#059669', ENT_QUOTES, 'UTF-8') ?>"></div>
        <span class="text-xs font-bold text-gray-500 w-24 truncate uppercase tracking-wide"><?= htmlspecialchars($l['icone'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="flex-1 text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($l['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="text-xs text-gray-400 truncate max-w-[160px]"><?= htmlspecialchars($l['url'], ENT_QUOTES, 'UTF-8') ?></span>
        <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $l['ativo'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
            <?= $l['ativo'] ? 'Ativo' : 'Inativo' ?>
        </span>
        <div class="flex gap-1 ml-1">
            <button onclick="editarLink(<?= htmlspecialchars(json_encode($l), ENT_QUOTES, 'UTF-8') ?>)"
                class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:border-emerald-400 hover:text-emerald-700 transition">Editar</button>
            <button onclick="toggleLink(<?= $l['id'] ?>, this)"
                class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:border-emerald-400 hover:text-emerald-700 transition">
                <?= $l['ativo'] ? 'Desativar' : 'Ativar' ?>
            </button>
            <button onclick="deletarLink(<?= $l['id'] ?>)"
                class="text-xs px-3 py-1.5 rounded-lg border border-red-100 text-red-400 hover:bg-red-50 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>
    </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<!-- Modal -->
<div id="modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 id="modal-titulo" class="font-semibold text-gray-800">Novo Link</h3>
            <button onclick="fecharModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <input type="hidden" id="link-id">
            <div>
                <label class="label">Título <span class="text-red-500">*</span></label>
                <input type="text" id="input-titulo" class="input w-full" placeholder="Ex: Grupo do WhatsApp">
            </div>
            <div>
                <label class="label">URL <span class="text-red-500">*</span></label>
                <input type="url" id="input-url" class="input w-full" placeholder="https://...">
            </div>
            <div>
                <label class="label">Ícone</label>
                <select id="input-icone" class="input w-full">
                    <option value="whatsapp">WhatsApp</option>
                    <option value="instagram">Instagram</option>
                    <option value="tiktok">TikTok</option>
                    <option value="youtube">YouTube</option>
                    <option value="telegram">Telegram</option>
                    <option value="ofertas">⚡ Ofertas</option>
                    <option value="link">Link Genérico</option>
                </select>
            </div>
            <div>
                <label class="label">Cor do botão</label>
                <div class="flex items-center gap-3">
                    <input type="color" id="input-cor" value="#059669" class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5">
                    <div class="flex gap-2 flex-wrap">
                        <?php foreach ([
                            '#25D366' => 'WhatsApp',
                            '#E1306C' => 'Instagram',
                            '#000000' => 'TikTok',
                            '#FF0000' => 'YouTube',
                            '#229ED9' => 'Telegram',
                            '#059669' => 'Emerald',
                            '#2563EB' => 'Azul',
                            '#DC2626' => 'Vermelho',
                        ] as $hex => $label): ?>
                        <button type="button" onclick="document.getElementById('input-cor').value='<?= $hex ?>'"
                            class="w-6 h-6 rounded-full border-2 border-white shadow hover:scale-110 transition-transform"
                            style="background:<?= $hex ?>" title="<?= $label ?>"></button>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
            <div>
                <label class="label">Ordem</label>
                <input type="number" id="input-ordem" class="input w-full" value="0" min="0">
            </div>
        </div>
        <div class="flex gap-3 px-5 pb-5">
            <button onclick="fecharModal()" class="flex-1 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:border-gray-300 transition">Cancelar</button>
            <button onclick="salvarLink()" class="flex-1 btn-primary">Salvar</button>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode(csrfToken()) ?>;

function abrirModal(d = null) {
    document.getElementById('modal-titulo').textContent = d ? 'Editar Link' : 'Novo Link';
    document.getElementById('link-id').value      = d?.id ?? '';
    document.getElementById('input-titulo').value = d?.titulo ?? '';
    document.getElementById('input-url').value    = d?.url ?? '';
    document.getElementById('input-icone').value  = d?.icone ?? 'link';
    document.getElementById('input-cor').value    = d?.cor ?? '#059669';
    document.getElementById('input-ordem').value  = d?.ordem ?? '0';
    document.getElementById('modal').classList.remove('hidden');
}
function editarLink(d) { abrirModal(d); }
function fecharModal() { document.getElementById('modal').classList.add('hidden'); }

async function salvarLink() {
    const id = document.getElementById('link-id').value;
    const titulo = document.getElementById('input-titulo').value.trim();
    const url    = document.getElementById('input-url').value.trim();
    if (!titulo || !url) { alert('Título e URL são obrigatórios.'); return; }
    const body = {
        action: id ? 'editar' : 'criar',
        id: id ? parseInt(id) : undefined,
        titulo, url,
        icone: document.getElementById('input-icone').value,
        cor:   document.getElementById('input-cor').value,
        ordem: parseInt(document.getElementById('input-ordem').value) || 0,
    };
    const r = await fetch(BASE + '/api/bio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    const j = await r.json();
    if (j.ok) location.reload();
    else alert(j.error);
}

async function toggleLink(id, btn) {
    const r = await fetch(BASE + '/api/bio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'toggle', id }),
    });
    const j = await r.json();
    if (j.ok) location.reload();
}

async function deletarLink(id) {
    if (!confirm('Remover este link?')) return;
    const r = await fetch(BASE + '/api/bio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'deletar', id }),
    });
    const j = await r.json();
    if (j.ok) document.getElementById('bio-link-' + id)?.remove();
}

async function salvarPerfil() {
    const r = await fetch(BASE + '/api/bio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
            action: 'perfil',
            nome:        document.getElementById('bio-nome').value.trim(),
            descricao:   document.getElementById('bio-desc').value.trim(),
            avatar_path: document.getElementById('bio-avatar-path').value,
        }),
    });
    const j = await r.json();
    if (j.ok) showToast('Perfil salvo!');
    else alert(j.error);
}

// Upload avatar
document.getElementById('avatar-input').addEventListener('change', async function() {
    const file = this.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('imagem', file);
    fd.append('csrf_token', CSRF);
    const r = await fetch(BASE + '/api/upload.php', { method: 'POST', body: fd });
    const j = await r.json();
    if (!j.ok) { alert(j.error); return; }
    const path = j.url.replace(BASE, '');
    document.getElementById('bio-avatar-path').value = path;
    const wrap = document.getElementById('avatar-wrap');
    wrap.innerHTML = `<img src="${j.url}" class="w-full h-full object-cover">`;
    showToast('Foto atualizada! Clique em Salvar.');
});
</script>

<?php layoutEnd() ?>
