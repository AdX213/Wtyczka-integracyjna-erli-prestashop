<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Api/ErliApiClient.php';

class ErliOrderApi
{
    private $client;

    public function __construct()
    {
        $this->client = new ErliApiClient(Configuration::get('ERLI_API_KEY'));
    }

    public function getInbox($limit = 100)
    {
        return $this->client->get('/inbox', ['limit' => (int) $limit]);
    }

    public function ackInbox($lastMessageId)
    {
        return $this->client->post('/inbox/ack', [
            'lastMessageId' => (string) $lastMessageId,
        ]);
    }

    public function getOrder($orderId)
    {
        return $this->client->get('/orders/' . rawurlencode($orderId));
    }
}
