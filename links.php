<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db    = getDB();
$links  = $db->query('SELECT * FROM links ORDER BY criado_em DESC')->fetchAll();
$grupos = $db->query('SELECT * FROM grupos WHERE ativo = 1 ORDER BY nome')->fetchAll();

layoutStart('links', 'Links de Afiliado');
toast();
?>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500">Cadastre seus links de afiliado com imagem e preço para envio no WhatsApp.</p>
    <button onclick="abrirModal()"
        class="btn-primary flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Novo Link
    </button>
</div>

<?php if (empty($links)): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-16 text-center">
        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
        </div>
        <p class="text-gray-500 text-sm font-medium mb-1">Nenhum link cadastrado ainda</p>
        <p class="text-gray-400 text-xs">Clique em "Novo Link" para adicionar seu primeiro link de afiliado.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($links as $l): ?>
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col" id="row-<?= $l['id'] ?>">
                <!-- Imagem do produto -->
                <?php
                    $imgSrc = '';
                    if (!empty($l['imagem_path']) && file_exists($l['imagem_path'])) {
                        $imgSrc = '/viana/uploads/' . basename($l['imagem_path']);
                    } elseif (!empty($l['imagem_url'])) {
                        $imgSrc = htmlspecialchars($l['imagem_url']);
                    }
                ?>
                <?php if ($imgSrc): ?>
                    <div class="h-40 bg-gray-100 overflow-hidden">
                        <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($l['nome_produto']) ?>"
                            class="w-full h-full object-cover">
                    </div>
                <?php else: ?>
                    <div class="h-40 bg-gray-50 flex items-center justify-center">
                        <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/>
                            <path d="M21 15l-5-5L5 21"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <?= badgePlataforma($l['plataforma']) ?>
                        <?php if ($l['ativo']): ?>
                            <span class="badge-success text-xs">Ativo</span>
                        <?php else: ?>
                            <span class="badge-muted text-xs">Inativo</span>
                        <?php endif; ?>
                    </div>

                    <p class="text-sm font-semibold text-gray-800 leading-snug mb-1 line-clamp-2"><?= htmlspecialchars($l['nome_produto']) ?></p>

                    <?php if (!empty($l['preco_por'])): ?>
                        <div class="flex items-center gap-2 mb-2">
                            <?php if (!empty($l['preco_de'])): ?>
                                <span class="text-xs text-gray-400 line-through">R$ <?= htmlspecialchars($l['preco_de']) ?></span>
                            <?php endif; ?>
                            <span class="text-base font-bold text-emerald-600">R$ <?= htmlspecialchars($l['preco_por']) ?></span>
                        </div>
                    <?php endif; ?>

                    <a href="<?= htmlspecialchars($l['url_afiliado']) ?>" target="_blank"
                        class="text-xs text-emerald-600 hover:underline font-mono truncate mb-3 block">
                        <?= htmlspecialchars($l['url_afiliado']) ?>
                    </a>

                    <div class="flex items-center gap-2 mt-auto pt-3 border-t border-gray-100">
                        <button onclick="enviarAgora(<?= $l['id'] ?>, '<?= htmlspecialchars($l['nome_produto'], ENT_QUOTES) ?>')"
                            class="flex-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold px-3 py-2 rounded-lg transition flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            Enviar
                        </button>
                        <button onclick="editarLink(<?= $l['id'] ?>)"
                            class="p-2 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button onclick="excluirLink(<?= $l['id'] ?>, '<?= htmlspecialchars($l['nome_produto'], ENT_QUOTES) ?>')"
                            class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ========== Modal Criar/Editar Link ========== -->
<div id="modal-link" class="modal-backdrop hidden" onclick="fecharSeClicarFora(event,'modal-link')">
    <div class="modal-box" style="max-width:560px;">
        <div class="flex items-center justify-between mb-5">
            <h2 id="modal-titulo" class="text-base font-semibold text-gray-900">Novo Link</h2>
            <button onclick="fecharModal('modal-link')" class="text-gray-400 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100 transition">&times;</button>
        </div>

        <form id="form-link" class="space-y-4" onsubmit="salvarLink(event)">
            <input type="hidden" id="link-id">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Plataforma</label>
                    <select id="link-plataforma" class="input" required>
                        <?php foreach (PLATAFORMAS as $cod => $p): ?>
                            <option value="<?= $cod ?>"><?= $p['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label">Nome do Produto</label>
                    <input type="text" id="link-nome" placeholder="Ex: Tênis Nike Air Max" required class="input">
                </div>
            </div>

            <div>
                <label class="label">URL de Afiliado <span class="text-red-500">*</span></label>
                <input type="url" id="link-url" placeholder="https://..." required class="input">
            </div>

            <!-- Preços -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Preço De <span class="text-gray-400">(opcional)</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                        <input type="text" id="link-preco-de" placeholder="199,90" class="input pl-9">
                    </div>
                </div>
                <div>
                    <label class="label">Preço Por <span class="text-gray-400">(opcional)</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">R$</span>
                        <input type="text" id="link-preco-por" placeholder="149,90" class="input pl-9">
                    </div>
                </div>
            </div>

            <!-- Imagem -->
            <div>
                <label class="label">Imagem do Produto <span class="text-gray-400">(opcional)</span></label>
                <div class="border-2 border-dashed border-gray-200 rounded-xl p-4" id="upload-area">
                    <!-- Preview -->
                    <div id="img-preview-wrap" class="hidden mb-3">
                        <img id="img-preview" src="" alt="" class="w-full h-32 object-cover rounded-lg">
                        <button type="button" onclick="limparImagem()" class="mt-2 text-xs text-red-500 hover:underline">Remover imagem</button>
                    </div>

                    <div id="upload-options">
                        <!-- Upload arquivo -->
                        <label class="flex items-center gap-3 cursor-pointer p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition mb-2">
                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-700">Fazer upload da imagem</p>
                                <p class="text-xs text-gray-400">JPG, PNG ou WebP · máx 5 MB</p>
                            </div>
                            <input type="file" id="link-imagem-file" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="handleUpload(this)">
                        </label>

                        <!-- Separador -->
                        <div class="flex items-center gap-2 my-2">
                            <div class="flex-1 h-px bg-gray-200"></div>
                            <span class="text-xs text-gray-400">ou</span>
                            <div class="flex-1 h-px bg-gray-200"></div>
                        </div>

                        <!-- URL externa -->
                        <input type="url" id="link-imagem-url" placeholder="https://imagem-do-produto.jpg"
                            class="input text-sm" oninput="handleUrlImagem(this.value)">
                        <p class="text-xs text-gray-400 mt-1">Cole a URL da imagem do produto (do site da loja)</p>
                    </div>
                </div>
                <input type="hidden" id="link-imagem-path">
                <input type="hidden" id="link-imagem-url-final">
            </div>

            <!-- Mensagem personalizada -->
            <div>
                <label class="label">Mensagem Personalizada <span class="text-gray-400">(opcional)</span></label>
                <textarea id="link-mensagem" rows="3" placeholder="Deixe vazio para usar o template padrão com emojis..." class="input resize-none"></textarea>
                <p class="text-xs text-gray-400 mt-1">Se preenchido, ignora o template automático.</p>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit" id="btn-salvar" class="btn-primary">
                    Salvar Link
                </button>
                <button type="button" onclick="fecharModal('modal-link')" class="text-sm text-gray-500 hover:text-gray-700">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ========== Modal Enviar Agora ========== -->
<div id="modal-enviar" class="modal-backdrop hidden" onclick="fecharSeClicarFora(event,'modal-enviar')">
    <div class="modal-box" style="max-width:400px;">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-900">Enviar para WhatsApp</h2>
            <button onclick="fecharModal('modal-enviar')" class="text-gray-400 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100 transition">&times;</button>
        </div>

        <div id="enviar-info" class="bg-gray-50 rounded-lg px-4 py-3 mb-4 text-sm text-gray-600"></div>
        <input type="hidden" id="enviar-link-id">

        <div>
            <label class="label">Grupo WhatsApp</label>
            <select id="enviar-grupo" class="input">
                <?php if (empty($grupos)): ?>
                    <option value="">Nenhum grupo ativo — configure em Grupos</option>
                <?php else: ?>
                    <?php foreach ($grupos as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nome']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="flex gap-3 mt-5">
            <button onclick="confirmarEnvio()" id="btn-enviar"
                class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                Enviar Agora
            </button>
            <button onclick="fecharModal('modal-enviar')" class="text-sm text-gray-500 hover:text-gray-700 px-4">Cancelar</button>
        </div>
    </div>
</div>

<script>
// ── Upload de imagem ──────────────────────────────────────────────
let uploadEmAndamento = false;

function handleUpload(input) {
    const file = input.files[0];
    if (!file) return;

    // Preview local imediato
    const reader = new FileReader();
    reader.onload = e => mostrarPreview(e.target.result);
    reader.readAsDataURL(file);

    // Upload ao servidor
    uploadEmAndamento = true;
    const btn = document.getElementById('btn-salvar');
    btn.disabled = true;
    btn.textContent = 'Enviando imagem...';

    const fd = new FormData();
    fd.append('imagem', file);

    fetch('/viana/api/upload.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            uploadEmAndamento = false;
            btn.disabled = false;
            btn.textContent = 'Salvar Link';

            if (data.ok) {
                document.getElementById('link-imagem-path').value = data.path;
                document.getElementById('link-imagem-url-final').value = '';
            } else {
                showToast(data.error, 'error');
                limparImagem();
            }
        })
        .catch(() => {
            uploadEmAndamento = false;
            btn.disabled = false;
            btn.textContent = 'Salvar Link';
            showToast('Erro ao fazer upload da imagem.', 'error');
            limparImagem();
        });
}

function handleUrlImagem(url) {
    document.getElementById('link-imagem-url-final').value = url;
    document.getElementById('link-imagem-path').value = '';
    if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
        mostrarPreview(url);
    }
}

function mostrarPreview(src) {
    document.getElementById('img-preview').src = src;
    document.getElementById('img-preview-wrap').classList.remove('hidden');
    document.getElementById('upload-options').classList.add('hidden');
}

function limparImagem() {
    document.getElementById('img-preview').src = '';
    document.getElementById('img-preview-wrap').classList.add('hidden');
    document.getElementById('upload-options').classList.remove('hidden');
    document.getElementById('link-imagem-path').value = '';
    document.getElementById('link-imagem-url-final').value = '';
    document.getElementById('link-imagem-file').value = '';
    document.getElementById('link-imagem-url').value = '';
}

// ── Modais ─────────────────────────────────────────────────────────
function abrirModal() {
    document.getElementById('modal-titulo').textContent = 'Novo Link';
    document.getElementById('link-id').value = '';
    document.getElementById('link-plataforma').value = 'ML';
    document.getElementById('link-nome').value = '';
    document.getElementById('link-url').value = '';
    document.getElementById('link-mensagem').value = '';
    document.getElementById('link-preco-de').value = '';
    document.getElementById('link-preco-por').value = '';
    document.getElementById('btn-salvar').textContent = 'Salvar Link';
    document.getElementById('btn-salvar').disabled = false;
    limparImagem();
    document.getElementById('modal-link').classList.remove('hidden');
}

function fecharModal(id) {
    document.getElementById(id).classList.add('hidden');
}

function fecharSeClicarFora(e, id) {
    if (e.target.id === id) fecharModal(id);
}

function editarLink(id) {
    fetch('/viana/api/links.php?action=buscar&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return showToast(data.error, 'error');
            const l = data.link;
            document.getElementById('modal-titulo').textContent = 'Editar Link';
            document.getElementById('link-id').value = l.id;
            document.getElementById('link-plataforma').value = l.plataforma;
            document.getElementById('link-nome').value = l.nome_produto;
            document.getElementById('link-url').value = l.url_afiliado;
            document.getElementById('link-mensagem').value = l.mensagem || '';
            document.getElementById('link-preco-de').value = l.preco_de || '';
            document.getElementById('link-preco-por').value = l.preco_por || '';
            document.getElementById('btn-salvar').textContent = 'Salvar Link';
            document.getElementById('btn-salvar').disabled = false;

            limparImagem();
            if (l.imagem_path) {
                document.getElementById('link-imagem-path').value = l.imagem_path;
                mostrarPreview('/viana/uploads/' + l.imagem_path.split('/').pop());
            } else if (l.imagem_url) {
                document.getElementById('link-imagem-url').value = l.imagem_url;
                document.getElementById('link-imagem-url-final').value = l.imagem_url;
                mostrarPreview(l.imagem_url);
            }

            document.getElementById('modal-link').classList.remove('hidden');
        });
}

// ── CRUD ──────────────────────────────────────────────────────────
function salvarLink(e) {
    e.preventDefault();
    if (uploadEmAndamento) { showToast('Aguarde o upload da imagem terminar.', 'error'); return; }

    const id = document.getElementById('link-id').value;
    const body = {
        id:           id ? parseInt(id) : undefined,
        plataforma:   document.getElementById('link-plataforma').value,
        nome_produto: document.getElementById('link-nome').value,
        url_afiliado: document.getElementById('link-url').value,
        mensagem:     document.getElementById('link-mensagem').value,
        preco_de:     document.getElementById('link-preco-de').value,
        preco_por:    document.getElementById('link-preco-por').value,
        imagem_path:  document.getElementById('link-imagem-path').value,
        imagem_url:   document.getElementById('link-imagem-url-final').value,
    };

    fetch('/viana/api/links.php?action=' + (id ? 'editar' : 'criar'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            showToast(id ? 'Link atualizado!' : 'Link criado com sucesso!');
            fecharModal('modal-link');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.error, 'error');
        }
    });
}

function excluirLink(id, nome) {
    if (!confirm(`Excluir "${nome}"?\nAgendamentos vinculados também serão removidos.`)) return;
    fetch('/viana/api/links.php?action=excluir', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    }).then(r => r.json()).then(data => {
        if (data.ok) { document.getElementById('row-' + id)?.remove(); showToast('Link excluído.'); }
        else showToast(data.error, 'error');
    });
}

// ── Enviar ────────────────────────────────────────────────────────
function enviarAgora(id, nome) {
    document.getElementById('enviar-link-id').value = id;
    document.getElementById('enviar-info').textContent = `📦 ${nome}`;
    document.getElementById('modal-enviar').classList.remove('hidden');
}

function confirmarEnvio() {
    const linkId  = parseInt(document.getElementById('enviar-link-id').value);
    const grupoId = parseInt(document.getElementById('enviar-grupo').value);
    if (!grupoId) { showToast('Selecione um grupo.', 'error'); return; }

    const btn = document.getElementById('btn-enviar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Enviando...';

    fetch('/viana/api/enviar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ link_id: linkId, grupo_id: grupoId })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Enviar Agora`;
        fecharModal('modal-enviar');
        if (data.ok) showToast('✅ Mensagem enviada para o WhatsApp!');
        else showToast('Erro: ' + data.error, 'error');
    });
}
</script>

<?php layoutEnd(); ?>
