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
            ('bot_ativo', '1'),
            ('bot_intervalo_horas', '6'),
            ('bot_ultimo_run', ''),
            ('bot_intervalo_entre_ofertas', '0'),
            ('portal_banner_ativo', '1'),
            ('portal_banner_titulo', 'Melhores Ofertas Fitness'),
            ('portal_banner_subtitulo', 'Suplementos, roupas e equipamentos com descontos todo dia'),
            ('system_logo_url', ''),
            ('system_logo_path', '');
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

    // Migração: nome normalizado para dedup de variações (sabor/cor/tamanho)
    try { $pdo->exec("ALTER TABLE ofertas ADD COLUMN nome_norm TEXT NOT NULL DEFAULT ''"); } catch (\PDOException) {}

    // Migração: categoria do produto (proteinas, creatina, roupas, etc.)
    try { $pdo->exec("ALTER TABLE ofertas ADD COLUMN categoria TEXT NOT NULL DEFAULT 'outros'"); } catch (\PDOException) {}

    // Índices compostos para acelerar dedup (produto_id+data, nome_norm+data)
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ofertas_prodext_data ON ofertas(produto_id_externo, coletado_em)"); } catch (\PDOException) {}
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ofertas_nomenorm_data ON ofertas(nome_norm, coletado_em)"); } catch (\PDOException) {}

    // Rastreamento de cliques no portal
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clicks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            oferta_id INTEGER REFERENCES ofertas(id) ON DELETE SET NULL,
            clicado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clicks_oferta ON clicks(oferta_id)"); } catch (\PDOException) {}
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clicks_data   ON clicks(clicado_em)"); } catch (\PDOException) {}

    // Migração: tabela de slides do portal
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS slides (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL DEFAULT '',
            subtitulo TEXT NOT NULL DEFAULT '',
            imagem_path TEXT NOT NULL DEFAULT '',
            link_url TEXT NOT NULL DEFAULT '',
            ordem INTEGER NOT NULL DEFAULT 0,
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");

    // Migração: tabela de links do bio (linktree)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bio_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo TEXT NOT NULL DEFAULT '',
            url TEXT NOT NULL DEFAULT '',
            icone TEXT NOT NULL DEFAULT 'link',
            cor TEXT NOT NULL DEFAULT '#059669',
            ordem INTEGER NOT NULL DEFAULT 0,
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");

    // Config keys do bio
    $pdo->exec("
        INSERT OR IGNORE INTO config (chave, valor) VALUES
            ('bio_nome', 'CasaFit Ofertas by Rede de Ofertas Viana'),
            ('bio_descricao', 'As melhores ofertas fitness do Brasil 💪'),
            ('bio_avatar_path', '')
    ");

    // Config keys do logo do sistema
    $pdo->exec("
        INSERT OR IGNORE INTO config (chave, valor) VALUES
            ('system_logo_path', ''),
            ('system_logo_url',  '')
    ");

    // Config keys do Magalu
    $pdo->exec("
        INSERT OR IGNORE INTO config (chave, valor) VALUES
            ('magalu_smttag', ''),
            ('magalu_ativo',  '0'),
            ('site_url',      '')
    ");

    // Config keys da Shopee
    $pdo->exec("
        INSERT OR IGNORE INTO config (chave, valor) VALUES
            ('shopee_app_id',            ''),
            ('shopee_app_secret',        ''),
            ('shopee_ativo',             '0'),
            ('shopee_limite_por_passada','50')
    ");

    // Config keys de controle do bot (pausa, limites, dedup avançado)
    $pdo->exec("
        INSERT OR IGNORE INTO config (chave, valor) VALUES
            ('bot_ativo',                '1'),
            ('bot_max_envios_por_ciclo', '0'),
            ('bot_dias_min_reenvio',     '30'),
            ('bot_queda_minima_pct',     '5')
    ");

    // Configs separadas por bot/fonte
    $pdo->exec("
        INSERT OR IGNORE INTO config (chave, valor) VALUES
            ('bot_ml_ativo',                       '1'),
            ('bot_ml_intervalo_horas',             '6'),
            ('bot_ml_ultimo_run',                  ''),
            ('bot_ml_desconto_minimo',             '10'),
            ('bot_ml_preco_maximo',                '500'),
            ('bot_ml_intervalo_entre_ofertas',     '0'),
            ('bot_ml_max_envios_por_ciclo',        '0'),
            ('bot_ml_dias_min_reenvio',            '30'),
            ('bot_ml_queda_minima_pct',            '5'),
            ('bot_shopee_ativo',                   '1'),
            ('bot_shopee_intervalo_horas',         '12'),
            ('bot_shopee_ultimo_run',              ''),
            ('bot_shopee_desconto_minimo',         '10'),
            ('bot_shopee_preco_maximo',            '500'),
            ('bot_shopee_intervalo_entre_ofertas', '0'),
            ('bot_shopee_max_envios_por_ciclo',    '0'),
            ('bot_shopee_dias_min_reenvio',        '30'),
            ('bot_shopee_queda_minima_pct',        '5'),
            ('ml_token_last_refresh_at',           ''),
            ('ml_token_last_refresh_status',       ''),
            ('ml_token_last_refresh_message',      '')
    ");

    // Inserir usuário padrão se não existir nenhum (senha via env ADMIN_PASSWORD)
    $total = $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ((int)$total === 0) {
        $adminPass = getenv('ADMIN_PASSWORD') ?: 'marley123';
        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)')
            ->execute(['Miguel Viana', 'lmiguelviana@hotmail.com', $hash]);
    }

    // ── Tabela de alertas do bot ────────────────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bot_alertas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo TEXT NOT NULL DEFAULT 'erro',
            fonte TEXT NOT NULL DEFAULT 'bot',
            mensagem TEXT NOT NULL DEFAULT '',
            lido INTEGER NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime'))
        )
    ");
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_alertas_lido ON bot_alertas(lido, criado_em)"); } catch (\PDOException) {}

    // ── Tabela de keywords gerenciáveis ────────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS keywords (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fonte TEXT NOT NULL DEFAULT 'ML',
            keyword TEXT NOT NULL,
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT (datetime('now','localtime')),
            UNIQUE(fonte, keyword)
        )
    ");
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS idx_keywords_fonte_ativo ON keywords(fonte, ativo)"); } catch (\PDOException) {}

    // ── Seed das keywords hardcoded (INSERT OR IGNORE — idempotente) ────────────
    $keywordsML = [
        'whey protein','whey isolado','creatina','pre treino','bcaa aminoacido',
        'colageno hidrolisado','vitamina d3','omega 3','glutamina','proteina vegana',
        'hipercalorico massa muscular','albumina proteina','termogenico emagrecedor',
        'multivitaminico esportivo','cafeina anidra','haltere musculacao',
        'anilha musculacao','barra de supino','kettlebell','faixa elastica treino',
        'elastico resistencia musculacao','luva musculacao','cinto musculacao',
        'munhequeira musculacao','joelheira esportiva','corda pular fitness',
        'step aerobico','roda abdominal exercicio','suporte paralela dip',
        'legging fitness feminina','legging academia cintura alta',
        'calca legging compressao','conjunto academia feminino',
        'shorts academia masculino','bermuda treino masculino','calca jogger masculino',
        'top academia feminino','sutia esportivo academia','camiseta dry fit masculino',
        'regata masculina academia','camiseta compressao masculino',
        'blusa moletom treino feminino','jaqueta corta vento corrida',
        'tenis corrida masculino','tenis corrida feminino','tenis academia feminino',
        'tenis crossfit masculino','meia esportiva cano longo','kit roupa academia feminina',
        'shakeira coqueteleira','garrafa termica esportiva','balanca bioimpedancia',
        'monitor frequencia cardiaca','tapete yoga pilates','foam roller massagem',
        'cinto corrida hidratacao','bolsa academia fitness','esteira ergometrica',
        'bicicleta ergometrica','bicicleta spinning','eliptico ergometrico',
        'remo ergometrico','escada ergometrica','banco supino musculacao',
        'banco de exercicios dobravel','rack barra musculacao','suporte haltere academia',
        'torre de musculacao','barra olimpica','barra reta musculacao',
        'placa peso olimpica','caneleira de peso','colete de peso',
        'tornozeleira academia','cotoveleira esportiva','bandagem elastica esportiva',
        'bola pilates suica','mini band circulo elastico','bosu fitness',
        'escada agilidade funcional','cones treino funcional','slam ball medicine ball',
        'pasta amendoim proteica','barra proteica','aveia flocos','whey bar proteica',
        'suporte celular bicicleta','relogio smartwatch esportivo','fone ouvido esporte',
    ];
    $keywordsSHP = [
        'roupa para malhar feminina','roupa para malhar masculina',
        'roupa fitness feminina','roupa fitness masculina',
        'conjunto fitness feminino','conjunto fitness masculino',
        'conjunto academia feminino','conjunto academia masculino',
        'legging academia feminina','calca legging cintura alta',
        'shorts fitness feminino','short saia fitness',
        'bermuda fitness masculina','bermuda academia masculina',
        'camiseta dry fit academia','camiseta dry fit masculina',
        'regata academia masculina','top academia feminino',
        'top fitness feminino','macacao fitness feminino',
        'blusa dry fit feminina','jaqueta corta vento fitness',
        'roupa para pedalar','roupa ciclismo masculina','roupa ciclismo feminina',
        'camisa ciclismo masculina','camisa ciclismo feminina',
        'bermuda ciclismo acolchoada','short ciclismo feminino',
        'bretelle ciclismo masculino','macaquinho ciclismo feminino',
        'calca ciclismo feminina','luva ciclismo','oculos ciclismo',
        'capacete ciclismo','meia ciclismo',
        'whey protein','whey isolado','proteina isolada','albumina proteina',
        'hipercalorico massa','caseina proteina','whey concentrado',
        'creatina monohidratada','creatina pura','creatina suplemento',
        'pre treino','pre workout','termogenico academia',
        'bcaa aminoacido','glutamina pura','aminoacido esportivo',
        'omega 3 capsulas','vitamina d suplemento','multivitaminico esportivo',
        'colageno hidrolisado','magnesio quelato','zinco suplemento',
        'pasta amendoim proteica','barra proteica','snack proteico',
        'haltere musculacao','anilha musculacao','kettlebell academia',
        'faixa elastica musculacao','corda pular fitness','step aerobico',
        'roda abdominal','bola pilates','caneleira academia',
        'bicicleta ergometrica','esteira ergometrica','shorts musculacao',
        'tenis academia','tenis crossfit',
        'coqueteleira academia','garrafa termica esportiva','luva musculacao',
        'munhequeira academia','cinto musculacao','bolsa academia',
        'smartwatch esportivo','balanca bioimpedancia',
    ];
    $keywordsMGZ = [
        'whey protein','whey isolado','creatina','pre treino','bcaa',
        'colageno hidrolisado','vitamina d','omega 3','termogenico',
        'multivitaminico','proteina isolada','glutamina','proteina vegana',
        'hipercalorico','pasta amendoim proteica','barra proteica',
        'haltere','anilha','kettlebell','faixa elastica','luva musculacao',
        'corda pular','tapete yoga','roda abdominal','banco supino',
        'banco exercicios','barra musculacao','placa de peso','caneleira peso',
        'cinto musculacao','munhequeira musculacao','joelheira esportiva',
        'tornozeleira academia','esteira ergometrica','bicicleta ergometrica',
        'bicicleta spinning','eliptico ergometrico','bola pilates','mini band',
        'step aerobico','foam roller','medicine ball','escada agilidade',
        'legging fitness','legging academia','conjunto academia','shorts academia',
        'bermuda treino','calca jogger','top academia','sutia esportivo',
        'camiseta dry fit','regata academia','camiseta compressao',
        'kit academia feminino','tenis academia','tenis corrida','tenis crossfit',
        'coqueteleira','garrafa termica esportiva','balanca bioimpedancia',
        'smartwatch esportivo','bolsa academia','monitor cardiaco',
    ];
    $seedStmt2 = $pdo->prepare('INSERT OR IGNORE INTO keywords (fonte, keyword) VALUES (?, ?)');
    foreach ($keywordsML  as $kw) { $seedStmt2->execute(['ML',  $kw]); }
    foreach ($keywordsSHP as $kw) { $seedStmt2->execute(['SHP', $kw]); }
    foreach ($keywordsMGZ as $kw) { $seedStmt2->execute(['MGZ', $kw]); }

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
