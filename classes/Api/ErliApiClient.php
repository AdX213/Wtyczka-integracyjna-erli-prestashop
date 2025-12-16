<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ErlApiClient
{
    const BASE_URL = 'https://erli.pl/svc/shop-api';

    /** @var string */
    private $apiKey;

    /**
     * @param string $apiKey Klucz API z panelu Erli
     */
    public function __construct($apiKey)
    {
        $this->apiKey = trim($apiKey);
    }

    /**
     * Wykonuje żądanie GET
     *
     * @param string $path
     * @param array  $query
     *
     * @return array ['code' => int, 'body' => mixed, 'raw' => string]
     * @throws Exception
     */
    public function get($path, array $query = [])
    {
        $url = rtrim(self::BASE_URL, '/') . '/' . ltrim($path, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $this->request('GET', $url);
    }

    /**
     * Wykonuje żądanie POST
     *
     * @param string $path
     * @param array  $payload
     *
     * @return array
     * @throws Exception
     */
    public function post($path, array $payload = [])
    {
        $url = rtrim(self::BASE_URL, '/') . '/' . ltrim($path, '/');

        return $this->request('POST', $url, $payload);
    }

    /**
     * Wykonuje żądanie PATCH
     *
     * @param string $path
     * @param array  $payload
     *
     * @return array
     * @throws Exception
     */
    public function patch($path, array $payload = [])
    {
        $url = rtrim(self::BASE_URL, '/') . '/' . ltrim($path, '/');

        return $this->request('PATCH', $url, $payload);
    }

    /**
     * Wspólna metoda do obsługi requestów
     *
     * @param string     $method  GET|POST|PATCH
     * @param string     $url
     * @param array|null $payload
     *
     * @return array
     * @throws Exception
     */
    protected function request($method, $url, array $payload = null)
    {
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: ' . $this->buildUserAgent(),
        ];

        $options = [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($payload ?: []);
                break;

            case 'PATCH':
                $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
                $options[CURLOPT_POSTFIELDS] = json_encode($payload ?: []);
                break;

            case 'GET':
            default:
                break;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Błąd połączenia z Erli.pl: ' . $error);
        }

        curl_close($ch);

        $data = json_decode($response, true);

        return [
            'code' => $httpCode,
            'body' => $data,
            'raw'  => $response,
        ];
    }

    /**
     * User-Agent widoczny po stronie Erli
     *
     * @return string
     */
    protected function buildUserAgent()
    {
        return 'PrestaShop-ErliIntegration/1.0';
    }
}
