<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ErliintegrationCronModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $token    = Tools::getValue('token');
        $expected = Configuration::get('ERLI_CRON_TOKEN');

        if (!$expected || $token !== $expected) {
            header('HTTP/1.1 403 Forbidden');
            die('Invalid token');
        }

        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/OrderSync.php';
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/ProductSync.php';

        $orderSync = new OrderSync();
        $orderSync->processInbox();

        // (później) można dodać ProductSync::syncAllPending();

        die('OK');
    }
}
