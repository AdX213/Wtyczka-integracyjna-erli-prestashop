<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Api/ErlApiClient.php';

class ErlProductApi
{
    /** @var ErlApiClient */
    private $client;

    public function __construct(ErlApiClient $client)
    {
        $this->client = $client;
    }

    public function createProduct($externalId, array $payload)
    {
        return $this->client->post('/products/' . urlencode($externalId), $payload);
    }

    public function updateProduct($externalId, array $payload)
    {
        return $this->client->patch('/products/' . urlencode($externalId), $payload);
    }

    public function getProduct($externalId)
    {
        return $this->client->get('/products/' . urlencode($externalId));
    }
}
