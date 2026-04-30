<?php
/**
 * Helpers de token do Mercado Livre.
 *
 * Centraliza status e renovacao para o painel, cron e API manual.
 */

function mlTokenInfo(): array {
    $clientId     = getConfig('ml_client_id');
    $clientSecret = getConfig('ml_client_secret');
    $access       = getConfig('ml_access_token');
    $refresh      = getConfig('ml_refresh_token');
    $expiresAt    = (int)getConfig('ml_token_expires');
    $now          = time();

    if ($clientId === '' || $clientSecret === '') {
        $status = 'sem_credenciais';
        $label  = 'Client ID/Secret ausentes';
    } elseif ($refresh === '') {
        $status = 'sem_refresh';
        $label  = 'Conta ML nao conectada';
    } elseif ($access === '' || $expiresAt <= $now) {
        $status = 'expirado';
        $label  = 'Token expirado';
    } elseif ($expiresAt <= $now + 3600) {
        $status = 'vence_logo';
        $label  = 'Token vence em breve';
    } else {
        $status = 'valido';
        $label  = 'Token valido';
    }

    return [
        'status' => $status,
        'label' => $label,
        'has_client' => $clientId !== '' && $clientSecret !== '',
        'has_access' => $access !== '',
        'has_refresh' => $refresh !== '',
        'expires_at' => $expiresAt,
        'expires_in' => $expiresAt ? ($expiresAt - $now) : null,
        'last_refresh_at' => getConfig('ml_token_last_refresh_at'),
        'last_refresh_status' => getConfig('ml_token_last_refresh_status'),
        'last_refresh_message' => getConfig('ml_token_last_refresh_message'),
    ];
}

function mlTokenNeedsRefresh(int $bufferSeconds = 3600): bool {
    $info = mlTokenInfo();
    if (!$info['has_client'] || !$info['has_refresh']) return false;
    if (!$info['has_access']) return true;
    return (int)$info['expires_at'] <= time() + $bufferSeconds;
}

function mlRefreshTokenAuto(int $bufferSeconds = 3600, bool $force = false): array {
    $info = mlTokenInfo();

    if (!$info['has_client']) {
        $msg = 'Client ID ou Secret ML nao configurados.';
        _mlRefreshStatus('erro', $msg);
        return ['ok' => false, 'skipped' => false, 'error' => $msg, 'info' => $info];
    }

    if (!$info['has_refresh']) {
        $msg = 'Nenhum refresh_token salvo. Reconecte a conta ML.';
        _mlRefreshStatus('erro', $msg);
        return ['ok' => false, 'skipped' => false, 'error' => $msg, 'info' => $info];
    }

    if (!$force && !mlTokenNeedsRefresh($bufferSeconds)) {
        $msg = 'Token ML ainda valido; refresh pulado.';
        _mlRefreshStatus('ok', $msg);
        return ['ok' => true, 'skipped' => true, 'message' => $msg, 'info' => $info];
    }

    $clientId     = getConfig('ml_client_id');
    $clientSecret = getConfig('ml_client_secret');
    $refreshToken = getConfig('ml_refresh_token');

    $ch = curl_init('https://api.mercadolibre.com/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type'    => 'refresh_token',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]),
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 20,
    ]);

    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $resp === '') {
        $msg = 'Falha de rede ao renovar token ML: ' . ($curlErr ?: 'sem resposta');
        _mlRefreshStatus('erro', $msg);
        return ['ok' => false, 'skipped' => false, 'error' => $msg, 'http_status' => $status];
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        $msg = 'Resposta invalida do ML ao renovar token.';
        _mlRefreshStatus('erro', $msg);
        return ['ok' => false, 'skipped' => false, 'error' => $msg, 'http_status' => $status];
    }

    if ($status === 200 && !empty($data['access_token'])) {
        $expiresIn = (int)($data['expires_in'] ?? 21600);
        $newRefresh = $data['refresh_token'] ?? $refreshToken;
        $expiresAt = time() + $expiresIn;

        setConfig('ml_access_token', $data['access_token']);
        setConfig('ml_refresh_token', $newRefresh);
        setConfig('ml_token_expires', (string)$expiresAt);

        $msg = 'Token ML renovado automaticamente. Valido ate ' . date('d/m H:i', $expiresAt) . '.';
        _mlRefreshStatus('ok', $msg);

        return [
            'ok' => true,
            'skipped' => false,
            'message' => $msg,
            'valid_until' => $expiresAt,
            'expires_in' => $expiresIn,
        ];
    }

    $err = $data['message'] ?? $data['error_description'] ?? $data['error'] ?? 'erro desconhecido';
    $msg = "ML retornou erro ao renovar token: {$err} (HTTP {$status})";
    if (in_array($data['error'] ?? '', ['invalid_grant', 'invalid_token'], true)) {
        $msg = "Refresh token expirado ou revogado. Reconecte a conta ML. (ML: {$err})";
    }
    _mlRefreshStatus('erro', $msg);
    return ['ok' => false, 'skipped' => false, 'error' => $msg, 'http_status' => $status];
}

function _mlRefreshStatus(string $status, string $message): void {
    setConfig('ml_token_last_refresh_at', date('Y-m-d H:i:s'));
    setConfig('ml_token_last_refresh_status', $status);
    setConfig('ml_token_last_refresh_message', $message);
}
