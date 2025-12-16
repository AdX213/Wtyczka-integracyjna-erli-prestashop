<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/Logrepository.php'; 
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/OrderRepository.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/ProductSync.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/OrderSync.php';


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

        $view = Tools::getValue('view','configure');
    
        switch ($view) {
            case 'dashboard':
                $this->renderDashboard();
                $this->template = 'dashboard.tpl';
                break;

            case 'products':
                $this->renderProducts();
                $this->template = 'products.tpl';
                break;

            case 'orders':
                $this->renderOrders();
                $this->template = 'order.tpl';
                break;

            case 'mapping':
                $this->renderMapping();
                $this->template = 'mapping.tpl';
                break;

            case 'logs':
                $this->renderLogs();
                $this->template = 'logs.tpl';
                break;

            case 'configure':
            default:
                $this->renderConfigure();
                $this->template = 'configure.tpl';
                break;
        }

    }

    // ----------- Contigure.tpl ----------- 
    protected function renderConfigure()
    {
        $modules = Module::getInstanceByName('erliintegration');

        $formHtml = $module->renderForm();
        
        $link = $this->context->link;
        $token = Tools::getAdminTokenLite('AdminErliIntegration');
        
        $base = 'index.php?controller=AdminErliIntegration&token=' .$token;
        
        $linkDashboard = $base .  '&view=dashboard';
        $linkProduct = $base . '&view=product';
        $linkOrders = $base . '&view=orders'; 
        $linkMapping = $base . '&view=mapping';
        $linkLogs = $base . '&view=logs';
        
        $cronToken = Configuration::get('ERLI_CRON_TOKEN');
        $cronUrl = $link->getModuleLink(
            'erliintegration',
            'cron',
            ['token' => $cronToken]
        );
        
        $this->context->smarty->assign([
            'form_html'      => $formHtml,
            'cron_url'       => $cronUrl,
            'link_dashboard' => $linkDashboard,
            'link_products'  => $linkProducts,
            'link_orders'    => $linkOrders,
            'link_mapping'   => $linkMapping,
            'link_logs'      => $linkLogs,
        ]);
        
    }
    //------ DASHBOARD.tpl ---------------- 
    public function renderDashboard()
    {
        $logRepo = new LogRepository();

        $totalProducts = (int)Db::getInstance()->getValue(
            'Select COUNT(*) FROM '. _DB_PREFIX_ . 'products');
    
        $syncedProducts = (int)Db::getInstance()->getValue(
            'Select Count(*) FROM'. _DB_PREFIX_ .'erli_product_sync');
        
        $toralOrders = (int)Db::getInstance()->getValue(
            'Select Count(*) FROM'. _DB_PREFIX_ .'orders');
        
        $syncedOrders = (int)Db::getInstance()->getValue(
            'Select Count(*) FROM'. _DB_PREFIX_ .'erli_product_sync');
        
        $lastSync = Db::getInstance()->getValue(
            'Select MAX(lasted_synced_at) FROM '. _DB_PREFIX_ .'erli_product_link'
        );
        
        $lastLogs= $logRepo->getLastLops(20);

        $this->context->smarty->assign([
            'total_products'  => $totalProducts,
            'synced_products' => $syncedProducts,
            'total_orders'    => $totalOrders,
            'erli_orders'     => $erliOrders,
            'last_sync'       => $lastSync,
            'last_logs'       => $lastLogs,

        ]);
    }
    // ----- products.tpl -----------
    protected function renderProducts()
    {

    }
    // ----- orders.tpl ----------
    protected function renderOrders()
    {

    }
    // ------ mapping.tpl
    protected function renderMapping()
    {

    }
    // --- logs.tpl ------
    protected function renderLogs()
    {
        $logRepo = new LogRepository();
        $logs = $logRepo->getLastLogs(100);

        $this -> context->smarty->assign([
            'logs' => $logs,
        ]);
    }
}
