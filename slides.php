<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db     = getDB();
$slides = $db->query("SELECT * FROM slides ORDER BY ordem ASC, id ASC")->fetchAll();

layoutStart('slides', 'Slides do Portal');
toast();
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500">Imagens exibidas no slider do portal público.</p>
    <button onclick="abrirModal()"
        class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Novo Slide
    </button>
</div>

<?php if (empty($slides)): ?>
<div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
    <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
    <p class="text-gray-500 font-medium">Nenhum slide cadastrado</p>
    <p class="text-sm text-gray-400 mt-1">Clique em "Novo Slide" para adicionar</p>
</div>
<?php else: ?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($slides as $s): ?>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden flex flex-col" id="slide-<?= $s['id'] ?>">
        <!-- Imagem preview -->
        <div class="relative aspect-video bg-gray-100">
            <?php if ($s['imagem_path']): ?>
            <img src="<?= BASE . '/' . ltrim(htmlspecialchars($s['imagem_path'], ENT_QUOTES, 'UTF-8'), '/') ?>"
                 alt="" class="w-full h-full object-cover">
            <?php else: ?>
            <div class="w-full h-full flex items-center justify-center text-gray-300">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <?php endif ?>
            <!-- Badge ativo -->
            <span class="absolute top-2 right-2 text-xs font-semibold px-2 py-0.5 rounded-full <?= $s['ativo'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                <?= $s['ativo'] ? 'Ativo' : 'Inativo' ?>
            </span>
            <!-- Ordem -->
            <span class="absolute top-2 left-2 bg-black/50 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                #<?= (int)$s['ordem'] ?>
            </span>
        </div>

        <div class="p-3 flex flex-col gap-2 flex-1">
            <div>
                <p class="font-semibold text-sm text-gray-800 truncate"><?= htmlspecialchars($s['titulo'] ?: '(sem título)', ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($s['subtitulo']): ?>
                <p class="text-xs text-gray-500 truncate mt-0.5"><?= htmlspecialchars($s['subtitulo'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif ?>
                <?php if ($s['link_url']): ?>
                <p class="text-xs text-emerald-600 truncate mt-0.5">🔗 <?= htmlspecialchars($s['link_url'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif ?>
            </div>
            <div class="flex gap-2 mt-auto">
                <button onclick="editarSlide(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)"
                    class="flex-1 text-xs font-medium py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-emerald-400 hover:text-emerald-700 transition">
                    Editar
                </button>
                <button onclick="toggleSlide(<?= $s['id'] ?>, this)"
                    class="flex-1 text-xs font-medium py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-emerald-400 hover:text-emerald-700 transition">
                    <?= $s['ativo'] ? 'Desativar' : 'Ativar' ?>
                </button>
                <button onclick="deletarSlide(<?= $s['id'] ?>)"
                    class="px-3 text-xs font-medium py-1.5 rounded-lg border border-red-100 text-red-500 hover:bg-red-50 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    <?php endforeach ?>
</div>
<?php endif ?>

<!-- Modal criar/editar -->
<div id="modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 id="modal-titulo" class="font-semibold text-gray-800">Novo Slide</h3>
            <button onclick="fecharModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <input type="hidden" id="slide-id" value="">

            <!-- Upload de imagem -->
            <div>
                <label class="label">Imagem <span class="text-red-500">*</span></label>
                <div id="drop-zone"
                     onclick="document.getElementById('file-input').click()"
                     class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-emerald-400 transition relative">
                    <div id="drop-placeholder">
                        <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm text-gray-400">Clique para selecionar</p>
                        <p class="text-xs text-gray-300 mt-0.5">JPG, PNG ou WebP — máx 5 MB</p>
                    </div>
                    <img id="img-preview" src="" alt="" class="hidden w-full rounded-lg max-h-40 object-cover">
                    <div id="upload-spinner" class="hidden absolute inset-0 bg-white/80 rounded-xl flex items-center justify-center">
                        <div class="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                </div>
                <input type="file" id="file-input" accept="image/*" class="hidden">
                <input type="hidden" id="imagem-path" value="">
            </div>

            <div>
                <label class="label">Título</label>
                <input type="text" id="input-titulo" class="input w-full" placeholder="Ex: Black Friday Fitness">
            </div>
            <div>
                <label class="label">Subtítulo</label>
                <input type="text" id="input-subtitulo" class="input w-full" placeholder="Ex: Até 60% OFF em suplementos">
            </div>
            <div>
                <label class="label">Link (ao clicar no slide)</label>
                <input type="url" id="input-link" class="input w-full" placeholder="https://...">
            </div>
            <div>
                <label class="label">Ordem</label>
                <input type="number" id="input-ordem" class="input w-full" value="0" min="0">
            </div>
        </div>
        <div class="flex gap-3 px-5 pb-5">
            <button onclick="fecharModal()" class="flex-1 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:border-gray-300 transition">Cancelar</button>
            <button onclick="salvarSlide()" class="flex-1 btn-primary">Salvar</button>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode(csrfToken()) ?>;

function abrirModal(dados = null) {
    document.getElementById('modal-titulo').textContent = dados ? 'Editar Slide' : 'Novo Slide';
    document.getElementById('slide-id').value       = dados?.id ?? '';
    document.getElementById('input-titulo').value   = dados?.titulo ?? '';
    document.getElementById('input-subtitulo').value= dados?.subtitulo ?? '';
    document.getElementById('input-link').value     = dados?.link_url ?? '';
    document.getElementById('input-ordem').value    = dados?.ordem ?? '0';
    document.getElementById('imagem-path').value    = dados?.imagem_path ?? '';

    const preview = document.getElementById('img-preview');
    const placeholder = document.getElementById('drop-placeholder');
    if (dados?.imagem_path) {
        preview.src = BASE + '/' + dados.imagem_path.replace(/^\//, '');
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
    } else {
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');
    }
    document.getElementById('modal').classList.remove('hidden');
}
function editarSlide(dados) { abrirModal(dados); }
function fecharModal() { document.getElementById('modal').classList.add('hidden'); }

// Upload de imagem
document.getElementById('file-input').addEventListener('change', async function() {
    const file = this.files[0];
    if (!file) return;
    const spinner = document.getElementById('upload-spinner');
    spinner.classList.remove('hidden');
    const fd = new FormData();
    fd.append('imagem', file);
    fd.append('csrf_token', CSRF);
    try {
        const r = await fetch(BASE + '/api/upload.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (!j.ok) { alert(j.error); return; }
        document.getElementById('imagem-path').value = j.url.replace(BASE, '');
        const preview = document.getElementById('img-preview');
        preview.src = j.url;
        preview.classList.remove('hidden');
        document.getElementById('drop-placeholder').classList.add('hidden');
    } catch(e) { alert('Erro no upload.'); }
    finally { spinner.classList.add('hidden'); }
});

async function salvarSlide() {
    const id          = document.getElementById('slide-id').value;
    const imagem_path = document.getElementById('imagem-path').value;
    if (!imagem_path) { alert('Selecione uma imagem.'); return; }
    const body = {
        action:       id ? 'editar' : 'criar',
        id:           id ? parseInt(id) : undefined,
        titulo:       document.getElementById('input-titulo').value.trim(),
        subtitulo:    document.getElementById('input-subtitulo').value.trim(),
        imagem_path,
        link_url:     document.getElementById('input-link').value.trim(),
        ordem:        parseInt(document.getElementById('input-ordem').value) || 0,
    };
    const r = await fetch(BASE + '/api/slides.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify(body),
    });
    const j = await r.json();
    if (j.ok) location.reload();
    else alert(j.error);
}

async function toggleSlide(id, btn) {
    const r = await fetch(BASE + '/api/slides.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'toggle', id }),
    });
    const j = await r.json();
    if (j.ok) location.reload();
}

async function deletarSlide(id) {
    if (!confirm('Remover este slide?')) return;
    const r = await fetch(BASE + '/api/slides.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action: 'deletar', id }),
    });
    const j = await r.json();
    if (j.ok) document.getElementById('slide-' + id)?.remove();
}
</script>

<?php layoutEnd() ?>
