<?php
// logs_shopee.php — redireciona para a página de logs unificada com aba Shopee
require_once __DIR__ . '/app/helpers.php';
header('Location: ' . BASE . '/logs-ml?bot=shopee');
exit;
