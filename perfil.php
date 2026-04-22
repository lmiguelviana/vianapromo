<?php
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/helpers.php';

$db   = getDB();
$user = currentUser();
$uid  = (int)$user['id'];

$msg     = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfVerify();

// Handle Upload de Foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['foto'])) {
    $file = $_FILES['foto'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $maxBytes = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $maxBytes) {
            $msg = 'A foto deve ter no máximo 2MB.';
            $msgType = 'error';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $mimesValidos = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $mimesValidos)) {
                $msg = 'Formato inválido. Use JPG, PNG ou WebP.';
                $msgType = 'error';
            } else {
                $ext = match($mime) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
                $nomeArq = "avatar_{$uid}_" . time() . ".{$ext}";
                $destDir = __DIR__ . '/uploads/';
                
                if (!is_dir($destDir)) mkdir($destDir, 0777, true);
                
                $destPath = $destDir . $nomeArq;

                // Apaga foto antiga se existir
                $antiga = $db->query("SELECT foto_path FROM usuarios WHERE id = {$uid}")->fetchColumn();
                if ($antiga && file_exists($antiga)) {
                    unlink($antiga);
                }

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $db->prepare("UPDATE usuarios SET foto_path = ? WHERE id = ?")->execute([$destPath, $uid]);
                    $msg = 'Foto de perfil atualizada!';
                } else {
                    $msg = 'Erro ao salvar a imagem.';
                    $msgType = 'error';
                }
            }
        }
    }
}

// Handle Edição de Dados (Nome e Senha)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_dados'])) {
    $nome  = trim($_POST['nome'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (empty($nome)) {
        $msg = 'O nome não pode ficar vazio.';
        $msgType = 'error';
    } else {
        if (!empty($senha)) {
            // Atualiza nome e senha
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $db->prepare("UPDATE usuarios SET nome = ?, senha = ? WHERE id = ?")->execute([$nome, $hash, $uid]);
            $msg = 'Perfil e senha atualizados!';
        } else {
            // Atualiza só o nome
            $db->prepare("UPDATE usuarios SET nome = ? WHERE id = ?")->execute([$nome, $uid]);
            $msg = 'Nome atualizado!';
        }
        
        // Atualiza a sessão para refletir o novo nome imediatamente
        $_SESSION['usuario_nome'] = $nome;
        $user['nome'] = $nome;
    }
}

// Carrega os dados atuais do banco
$dadosUser = $db->query("SELECT * FROM usuarios WHERE id = {$uid}")->fetch();
$fotoPath  = $dadosUser['foto_path'] ?? '';
$fotoUrl   = $fotoPath && file_exists($fotoPath) ? BASE . '/uploads/' . basename($fotoPath) : '';
$inicial   = mb_strtoupper(mb_substr($dadosUser['nome'], 0, 1));

layoutStart('perfil', 'Meu Perfil');
?>

<div class="max-w-2xl space-y-6">

    <?php if ($msg): ?>
        <div class="px-4 py-3 rounded-lg text-sm font-medium <?= $msgType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <!-- Foto de Perfil -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 flex flex-col sm:flex-row items-center gap-6">
        <div class="flex-shrink-0">
            <?php if ($fotoUrl): ?>
                <img src="<?= htmlspecialchars($fotoUrl) ?>?t=<?= time() ?>" alt="Foto" class="w-24 h-24 rounded-full object-cover border-4 border-gray-50 shadow-sm">
            <?php else: ?>
                <div class="w-24 h-24 bg-emerald-100 rounded-full flex items-center justify-center text-4xl font-bold text-emerald-700 border-4 border-gray-50 shadow-sm">
                    <?= $inicial ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="flex-1 text-center sm:text-left">
            <h3 class="text-base font-semibold text-gray-900 mb-1">Foto do Perfil</h3>
            <p class="text-sm text-gray-500 mb-4">Recomendado: imagem quadrada (JPG, PNG). Máx 2MB.</p>
            
            <form method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-center gap-3 justify-center sm:justify-start">
                <?= csrfField() ?>
                <input type="file" name="foto" accept="image/jpeg, image/png, image/webp" class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100 focus:outline-none cursor-pointer">
                <button type="submit" class="bg-gray-900 hover:bg-black text-white text-sm font-medium px-4 py-2 rounded-lg transition shadow-sm">
                    Upload
                </button>
            </form>
        </div>
    </div>

    <!-- Dados Pessoais -->
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-5">Dados Pessoais</h3>
        
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div>
                <label class="label">Nome Completo</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($dadosUser['nome']) ?>" required class="input">
            </div>
            
            <div>
                <label class="label">E-mail</label>
                <input type="email" value="<?= htmlspecialchars($dadosUser['email']) ?>" disabled class="input bg-gray-50 text-gray-500 cursor-not-allowed">
                <p class="text-xs text-gray-400 mt-1">O e-mail não pode ser alterado por aqui.</p>
            </div>
            
            <hr class="border-gray-100 my-4">
            
            <div>
                <label class="label">Nova Senha</label>
                <input type="password" name="senha" placeholder="••••••••" class="input">
                <p class="text-xs text-gray-400 mt-1">Deixe em branco se não quiser alterar a senha atual.</p>
            </div>
            
            <div class="pt-2">
                <button type="submit" name="salvar_dados" class="btn-primary">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>

</div>

<?php layoutEnd(); ?>

