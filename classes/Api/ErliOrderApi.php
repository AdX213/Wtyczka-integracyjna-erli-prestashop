<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Api/ErlApiClient.php';

class ErlOrderApi
{
    /** @var ErlApiClient */
    private $client;

    public function __construct()
    {
        $apiKey = Configuration::get('ERLI_API_KEY');
        $this->client = new ErlApiClient($apiKey);
    }

    /**
     * Pobiera inbox z Erli
     */
    public function getInbox($limit = 100)
    {
        return $this->client->get('/inbox', [
            'limit' => (int) $limit,
        ]);
    }

    /**
     * Oznacza wiadomości jako przetworzone
     */
    public function ackInbox($lastMessageId)
    {
        return $this->client->post('/inbox/ack', [
            'lastMessageId' => (string) $lastMessageId,
        ]);
    }

    /**
     * Pobiera szczegóły zamówienia
     */
    public function getOrder($orderId)
    {
        return $this->client->get('/orders/' . urlencode($orderId));
    }
}
