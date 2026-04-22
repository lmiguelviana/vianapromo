<?php

require_once __DIR__ . '/db.php';

class EvolutionAPI {
    private string $url;
    private string $apikey;
    private string $instance;

    public function __construct() {
        $this->url      = rtrim(getConfig('evolution_url'), '/');
        $this->apikey   = getConfig('evolution_apikey');
        $this->instance = getConfig('evolution_instance');
    }

    private function request(string $method, string $endpoint, array $body = []): array {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'apikey: ' . $this->apikey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->url . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['ok' => false, 'error' => 'cURL: ' . $error];
        }

        $data = json_decode($response, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $data['message'] ?? $data['error'] ?? $response;
            return ['ok' => false, 'error' => "HTTP $httpCode: $msg"];
        }

        return ['ok' => true, 'data' => $data];
    }

    public function testConnection(): array {
        if (!$this->url || !$this->instance) {
            return ['ok' => false, 'error' => 'URL ou instância não configurados.'];
        }
        return $this->request('GET', "/instance/connectionState/{$this->instance}");
    }

    public function getGroups(): array {
        return $this->request('GET', "/group/fetchAllGroups/{$this->instance}?getParticipants=false");
    }

    public function sendText(string $groupJid, string $text): array {
        return $this->request('POST', "/message/sendText/{$this->instance}", [
            'number' => $groupJid,
            'text'   => $text,
        ]);
    }

    public function sendMedia(string $groupJid, string $caption, string $media, string $mimetype = 'image/jpeg'): array {
        // $media pode ser URL externa ou base64 puro (sem prefixo data:)
        $isUrl = str_starts_with($media, 'http');

        return $this->request('POST', "/message/sendMedia/{$this->instance}", [
            'number'    => $groupJid,
            'mediatype' => 'image',
            'mimetype'  => $mimetype,
            'caption'   => $caption,
            'media'     => $isUrl ? $media : base64_encode(file_get_contents($media)),
            'fileName'  => 'produto.jpg',
        ]);
    }

    public function logout(): array {
        return $this->request('DELETE', "/instance/logout/{$this->instance}");
    }

    public function getQRCode(): array {
        return $this->request('GET', "/instance/connect/{$this->instance}");
    }

    public function isConfigured(): bool {
        return $this->url !== '' && $this->apikey !== '' && $this->instance !== '';
    }
}
