<?php

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $path = __DIR__ . '/../database/viana.db';
    $pdo = new PDO("sqlite:{$path}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // busy_timeout ANTES de qualquer escrita — espera até 15s se banco travado
    $pdo->exec('PRAGMA busy_timeout=15000');
    $pdo->exec('PRAGMA journal_mode=WAL');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS config (
            chave TEXT PRIMARY KEY,
            valor TEXT NOT NULL DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plataforma TEXT NOT NULL,
            nome_produto TEXT NOT NULL,
            url_afiliado TEXT NOT NULL,
            mensagem TEXT NOT NULL DEFAULT '',
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS grupos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            group_jid TEXT NOT NULL UNIQUE,
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS agendamentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            link_id INTEGER NOT NULL REFERENCES links(id) ON DELETE CASCADE,
            grupo_id INTEGER NOT NULL REFERENCES grupos(id) ON DELETE CASCADE,
            dias_semana TEXT NOT NULL DEFAULT '[0,1,2,3,4,5,6]',
            horario TEXT NOT NULL,
            ativo INTEGER NOT NULL DEFAULT 1,
            ultimo_envio DATE,
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS historico (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            link_id INTEGER REFERENCES links(id) ON DELETE SET NULL,
            grupo_id INTEGER REFERENCES grupos(id) ON DELETE SET NULL,
            status TEXT NOT NULL,
            mensagem_erro TEXT,
            enviado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            senha TEXT NOT NULL,
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        );

        INSERT OR IGNORE INTO config (chave, valor) VALUES
            ('evolution_url', ''),
            ('evolution_apikey', ''),
            ('evolution_instance', ''),
            ('ml_client_id', ''),
            ('ml_client_secret', ''),
            ('ml_partner_id', ''),
            ('openrouter_apikey', ''),
            ('openrouter_model', 'minimax/minimax-01:free'),
            ('usar_ia', '0'),
            ('mensagem_padrao', ''),
            ('bot_desconto_minimo', '10'),
            ('bot_preco_maximo', '500'),
            ('bot_ativo', '0'),
            ('bot_intervalo_horas', '6'),
            ('bot_ultimo_run', ''),
            ('bot_intervalo_entre_ofertas', '0');
    ");

    // ── Seed via variáveis de ambiente (EasyPanel / Docker) ────────────────
    // Se a variável estiver definida E o campo ainda estiver vazio, sobrescreve.
    // Isso permite configurar tudo pelo painel do EasyPanel sem precisar
    // acessar o painel web no primeiro deploy.
    $envMap = [
        'EVOLUTION_URL'        => 'evolution_url',
        'EVOLUTION_APIKEY'     => 'evolution_apikey',
        'EVOLUTION_INSTANCE'   => 'evolution_instance',
        'ML_CLIENT_ID'        => 'ml_client_id',
        'ML_CLIENT_SECRET'    => 'ml_client_secret',
        'ML_PARTNER_ID'       => 'ml_partner_id',
        'OPENROUTER_APIKEY'   => 'openrouter_apikey',
        'OPENROUTER_MODEL'    => 'openrouter_model',
        'BOT_DESCONTO_MINIMO' => 'bot_desconto_minimo',
        'BOT_PRECO_MAXIMO'    => 'bot_preco_maximo',
        'USAR_IA'             => 'usar_ia',
    ];
    $seedStmt = $pdo->prepare(
        'UPDATE config SET valor = ? WHERE chave = ? AND (valor = \'\' OR valor IS NULL)'
    );
    foreach ($envMap as $envKey => $dbKey) {
        $val = getenv($envKey);
        if ($val !== false && $val !== '') {
            $seedStmt->execute([$val, $dbKey]);
        }
    }

    // Migração: adicionar colunas novas à tabela links (seguro — ignora se já existir)
    foreach ([
        "ALTER TABLE links ADD COLUMN imagem_url  TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE links ADD COLUMN imagem_path TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE links ADD COLUMN preco_de    TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE links ADD COLUMN preco_por   TEXT NOT NULL DEFAULT ''",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (\PDOException) {}
    }

    // Migração: foto do perfil
    try {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN foto_path TEXT NOT NULL DEFAULT ''");
    } catch (\PDOException) {}

    // Migração: tabela de ofertas coletadas pelo bot
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ofertas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fonte TEXT NOT NULL DEFAULT 'ML',
            produto_id_externo TEXT NOT NULL,
            nome TEXT NOT NULL,
            preco_de REAL NOT NULL DEFAULT 0,
            preco_por REAL NOT NULL DEFAULT 0,
            desconto_pct INTEGER NOT NULL DEFAULT 0,
            url_afiliado TEXT NOT NULL DEFAULT '',
            imagem_url TEXT NOT NULL DEFAULT '',
            imagem_path TEXT NOT NULL DEFAULT '',
            mensagem_ia TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'nova',
            coletado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime')),
            enviado_em DATETIME
        );

        CREATE TABLE IF NOT EXISTS fila_envio (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            oferta_id INTEGER NOT NULL REFERENCES ofertas(id) ON DELETE CASCADE,
            grupo_id INTEGER NOT NULL REFERENCES grupos(id) ON DELETE CASCADE,
            agendado_para DATETIME NOT NULL DEFAULT (datetime('now','localtime')),
            status TEXT NOT NULL DEFAULT 'pendente',
            erro TEXT
        );

        CREATE TABLE IF NOT EXISTS blacklist (
            produto_id_externo TEXT PRIMARY KEY,
            motivo TEXT NOT NULL DEFAULT 'rejeitado',
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        );
    ");

    // Inserir usuário padrão se não existir nenhum (senha via env ADMIN_PASSWORD)
    $total = $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ((int)$total === 0) {
        $adminPass = getenv('ADMIN_PASSWORD') ?: 'marley123';
        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)')
            ->execute(['Miguel Viana', 'lmiguelviana@hotmail.com', $hash]);
    }

    return $pdo;
}

function getConfig(string $chave): string {
    $db = getDB();
    $stmt = $db->prepare('SELECT valor FROM config WHERE chave = ?');
    $stmt->execute([$chave]);
    $row = $stmt->fetch();
    return $row ? $row['valor'] : '';
}

function setConfig(string $chave, string $valor): void {
    $db = getDB();
    $db->prepare('INSERT OR REPLACE INTO config (chave, valor) VALUES (?, ?)')
       ->execute([$chave, $valor]);
}
