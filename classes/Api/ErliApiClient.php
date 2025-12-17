<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ErliApiClient
{
    const BASE_URL = 'https://erli.pl/svc/shop-api';

    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = trim((string) $apiKey);
        if ($this->apiKey === '') {
            throw new Exception('ERLI API key is empty');
        }
    }

    public function get($path, array $query = [])
    {
        $url = $this->buildUrl($path, $query);
        return $this->request('GET', $url);
    }

    public function post($path, array $payload = [])
    {
        $url = $this->buildUrl($path);
        return $this->request('POST', $url, $payload);
    }

    public function patch($path, array $payload = [])
    {
        $url = $this->buildUrl($path);
        return $this->request('PATCH', $url, $payload);
    }

    private function buildUrl($path, array $query = [])
    {
        $url = rtrim(self::BASE_URL, '/') . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    protected function request($method, $url, array $payload = null)
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: ' . $this->buildUserAgent(),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method === 'POST' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload ?: []));
        }

        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($raw === false) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        return [
            'code' => $code,
            'body' => json_decode($raw, true),
            'raw'  => $raw,
        ];
    }

    protected function buildUserAgent()
    {
        return 'PrestaShop-ErliIntegration/1.2.2';
    }
}
