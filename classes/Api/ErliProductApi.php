<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Api/ErliApiClient.php';

class ErliProductApi
{
    private $client;

    public function __construct()
    {
        $this->client = new ErliApiClient(Configuration::get('ERLI_API_KEY'));
    }

    public function createProduct($externalId, array $payload)
    {
        return $this->client->post('/products/' . rawurlencode($externalId), $payload);
    }

    public function updateProduct($externalId, array $payload)
    {
        return $this->client->patch('/products/' . rawurlencode($externalId), $payload);
    }

    // alias dla StockSync
    public function patchProduct($externalId, array $payload)
    {
        return $this->updateProduct($externalId, $payload);
    }

    public function getProduct($externalId)
    {
        return $this->client->get('/products/' . rawurlencode($externalId));
    }
}
