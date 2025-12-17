<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/LogRepository.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/ProductSync.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/OrderSync.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/StockSync.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/StatusSync.php';

class AdminErliIntegrationController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();

        // ✅ zawsze ustawione – żeby tpl nie sypały warningami
        $this->context->smarty->assign([
            'output' => '',
        ]);

        $this->assignNavigationLinks();
        $this->handlePostActions();

        $view = Tools::getValue('view', 'configure');

        switch ($view) {
            case 'dashboard':
                $this->renderDashboard();
                $this->setTemplate('dashboard.tpl');
                break;

            case 'products':
                $this->renderProducts();
                $this->setTemplate('products.tpl');
                break;

            case 'orders':
                $this->renderOrders();
                $this->setTemplate('orders.tpl');
                break;

            case 'mapping':
                $this->renderMapping();
                $this->setTemplate('mapping.tpl');
                break;

            case 'logs':
                $this->renderLogs();
                $this->setTemplate('logs.tpl');
                break;

            case 'configure':
            default:
                $this->renderConfigure();
                $this->setTemplate('configure.tpl');
                break;
        }
    }

    private function assignNavigationLinks()
    {
        $token = Tools::getAdminTokenLite('AdminErliIntegration');
        $base  = 'index.php?controller=AdminErliIntegration&token=' . $token;

        $this->context->smarty->assign([
            'link_dashboard' => $base . '&view=dashboard',
            'link_products'  => $base . '&view=products',
            'link_orders'    => $base . '&view=orders',
            'link_mapping'   => $base . '&view=mapping',
            'link_logs'      => $base . '&view=logs',
            'link_configure' => $base . '&view=configure',
        ]);
    }

    /**
     * ✅ komunikat OK dla BO (bez displayConfirmation na kontrolerze)
     */
    private function msgOk($text)
    {
        if ($this->module && method_exists($this->module, 'displayConfirmation')) {
            return $this->module->displayConfirmation($text);
        }
        return '<div class="alert alert-success">' . Tools::safeOutput($text) . '</div>';
    }

    /**
     * ✅ komunikat ERROR dla BO (bez displayError na kontrolerze)
     */
    private function msgErr($text)
    {
        if ($this->module && method_exists($this->module, 'displayError')) {
            return $this->module->displayError($text);
        }
        return '<div class="alert alert-danger">' . Tools::safeOutput($text) . '</div>';
    }

    /**
     * Obsługa formularzy POST z tpl
     */
    private function handlePostActions()
    {
        try {
            // 1) Import zamówień
            if (Tools::isSubmit('submitErliImportOrders')) {
                $sync = new OrderSync();
                $sync->processInbox();

                $this->context->smarty->assign([
                    'output' => $this->msgOk('Pobrano inbox z ERLI.'),
                ]);
            }

            // 2) Ręczna synchronizacja produktu po ID
            if (Tools::isSubmit('submitSyncProduct')) {
                $idProduct = (int) Tools::getValue('ERLI_PRODUCT_ID');
                if ($idProduct <= 0) {
                    $this->context->smarty->assign([
                        'output' => $this->msgErr('Podaj poprawne ID produktu.'),
                    ]);
                    return;
                }

                // Na razie: batch pending (jak masz wpisy w erli_product_link)
                $sync = new ProductSync();
                $cnt  = $sync->syncAllPending(200);

                $this->context->smarty->assign([
                    'output' => $this->msgOk('ProductSync pending uruchomiony. Przetworzono: ' . (int) $cnt),
                ]);
            }
        } catch (Throwable $e) {
            $this->context->smarty->assign([
                'output' => $this->msgErr($e->getMessage()),
            ]);
        }
    }

    // -------- configure.tpl --------
    protected function renderConfigure()
    {
        /** @var ErliIntegration $module */
        $module = Module::getInstanceByName('erliintegration');

        $formHtml = '';
        if ($module && method_exists($module, 'renderForm')) {
            $formHtml = $module->renderForm();
        }

        $cronToken = (string) Configuration::get('ERLI_CRON_TOKEN');
        $cronUrl = $this->context->link->getModuleLink('erliintegration', 'cron', ['token' => $cronToken]);

        $this->context->smarty->assign([
            'form_html' => $formHtml,
            'cron_url'  => $cronUrl,
        ]);
    }

    // -------- dashboard.tpl --------
    protected function renderDashboard()
    {
        $logRepo = new LogRepository();

        $totalProducts  = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product`');
        $syncedProducts = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'erli_product_link`');

        $totalOrders = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders`');
        $erliOrders  = (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'erli_order_link`');

        $lastSync = Db::getInstance()->getValue(
            'SELECT MAX(`last_synced_at`) FROM `' . _DB_PREFIX_ . 'erli_product_link`'
        );

        $this->context->smarty->assign([
            'total_products'  => $totalProducts,
            'synced_products' => $syncedProducts,
            'total_orders'    => $totalOrders,
            'erli_orders'     => $erliOrders,
            'last_sync'       => $lastSync,
            'last_logs'       => $logRepo->getLastLogs(20),
        ]);
    }

    // -------- products.tpl --------
    protected function renderProducts()
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        $rows = Db::getInstance()->executeS(
            'SELECT
                p.id_product,
                pl.name,
                pl.link_rewrite,
                sa.quantity,
                i.id_image,
                epl.external_id,
                epl.last_synced_at,
                epl.last_error
             FROM `' . _DB_PREFIX_ . 'product` p
             INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                ON (pl.id_product = p.id_product AND pl.id_lang = ' . (int)$idLang . ' AND pl.id_shop = ' . (int)$idShop . ')
             LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                ON (sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = ' . (int)$idShop . ')
             LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` ish
                ON (ish.id_product = p.id_product AND ish.cover = 1 AND ish.id_shop = ' . (int)$idShop . ')
             LEFT JOIN `' . _DB_PREFIX_ . 'image` i
                ON (i.id_image = ish.id_image)
             LEFT JOIN `' . _DB_PREFIX_ . 'erli_product_link` epl
                ON (epl.id_product = p.id_product)
             ORDER BY p.id_product DESC
             LIMIT 200'
        );

        $products = [];
        foreach ($rows ?: [] as $r) {
            $imgUrl = '';
            if (!empty($r['id_image'])) {
                $imgUrl = $this->context->link->getImageLink(
                    (string) $r['link_rewrite'],
                    (int) $r['id_image'],
                    ImageType::getFormattedName('small_default')
                );
            }

            $priceGross = (float) Product::getPriceStatic((int)$r['id_product'], true);

            $products[] = [
                'id_product'     => (int) $r['id_product'],
                'name'           => (string) $r['name'],
                'price'          => $priceGross,
                'quantity'       => (int) ($r['quantity'] ?? 0),
                'image'          => $imgUrl,
                'external_id'    => (string) ($r['external_id'] ?? ''),
                'last_synced_at' => (string) ($r['last_synced_at'] ?? ''),
                'last_error'     => (string) ($r['last_error'] ?? ''),
            ];
        }

        $this->context->smarty->assign([
            'products' => $products,
        ]);
    }

    // -------- orders.tpl --------
    protected function renderOrders()
    {
        $adminOrdersToken = Tools::getAdminTokenLite('AdminOrders');

        $orders = Db::getInstance()->executeS(
            'SELECT
                eol.id_order,
                eol.erli_order_id,
                eol.last_status,
                eol.created_at
             FROM `' . _DB_PREFIX_ . 'erli_order_link` eol
             ORDER BY eol.id_erli_order_link DESC
             LIMIT 200'
        );

        $this->context->smarty->assign([
            'orders' => $orders ?: [],
            'admin_orders_token' => $adminOrdersToken,
        ]);
    }

    // -------- mapping.tpl --------
    protected function renderMapping()
    {
        $this->context->smarty->assign([]);
    }

    // -------- logs.tpl --------
    protected function renderLogs()
    {
        $logRepo = new LogRepository();
        $this->context->smarty->assign([
            'logs' => $logRepo->getLastLogs(200),
        ]);
    }
}
