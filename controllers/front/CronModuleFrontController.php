<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ErliintegrationCronModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        header('Content-Type: text/plain; charset=utf-8');

        $token    = (string) Tools::getValue('token');
        $expected = (string) Configuration::get('ERLI_CRON_TOKEN');

        if (!$expected || !hash_equals($expected, $token)) {
            header('HTTP/1.1 403 Forbidden');
            die('Invalid token');
        }

        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/OrderSync.php';
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/ProductSync.php';
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/StockSync.php';
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/StatusSync.php';

        $report = [];

        // 1) Orders inbox
        try {
            $orderSync = new OrderSync();
            $orderSync->processInbox();
            $report[] = 'OrderSync: OK';
        } catch (Throwable $e) {
            $report[] = 'OrderSync: ERROR - ' . $e->getMessage();
        }

        // 2) Heavy product sync (pending)
        try {
            $productSync = new ProductSync();
            $cnt = $productSync->syncAllPending(200);
            $report[] = 'ProductSync(pending): OK (' . (int) $cnt . ')';
        } catch (Throwable $e) {
            $report[] = 'ProductSync: ERROR - ' . $e->getMessage();
        }

        // 3) Stock/status sync (all)
        try {
            $stockSync = new StockSync();
            $cnt = $stockSync->syncAll(300);
            $report[] = 'StockSync(all): OK (' . (int) $cnt . ')';
        } catch (Throwable $e) {
            $report[] = 'StockSync: ERROR - ' . $e->getMessage();
        }

        // 4) Order status sync (all)
        try {
            $statusSync = new StatusSync();
            $cnt = $statusSync->syncAll(200);
            $report[] = 'StatusSync(all): OK (' . (int) $cnt . ')';
        } catch (Throwable $e) {
            $report[] = 'StatusSync: ERROR - ' . $e->getMessage();
        }

        die(implode("\n", $report));
    }
}
