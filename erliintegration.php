<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ErliIntegration extends Module
{
    public function __construct()
    {
        $this->name = 'erliintegration';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Adrian';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Integracja Erli.pl');
        $this->description = $this->l('Integracja PrestaShop z Erli.pl (produkty + zamówienia + CRON).');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->installSql()) {
            return false;
        }

        if (!$this->registerAdminTab()) {
            return false;
        }

        Configuration::updateValue('ERLI_API_KEY', '');
        Configuration::updateValue('ERLI_CRON_TOKEN', Tools::passwdGen(32));

        Configuration::updateValue('ERLI_DEFAULT_CARRIER', 0);
        Configuration::updateValue('ERLI_DEFAULT_ORDER_STATE', (int) Configuration::get('PS_OS_PAYMENT'));

        Configuration::updateValue('ERLI_STATE_PENDING', (int) Configuration::get('PS_OS_BANKWIRE'));
        Configuration::updateValue('ERLI_STATE_PAID', (int) Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('ERLI_STATE_CANCELLED', (int) Configuration::get('PS_OS_CANCELED'));

        $this->registerHook('actionProductSave');
        $this->registerHook('actionUpdateQuantity');

        return true;
    }

    public function uninstall()
    {
        if (!$this->removeAdminTab()) {
            // ignorujemy
        }

        if (!$this->uninstallSql()) {
            return false;
        }

        Configuration::deleteByName('ERLI_API_KEY');
        Configuration::deleteByName('ERLI_CRON_TOKEN');
        Configuration::deleteByName('ERLI_DEFAULT_CARRIER');
        Configuration::deleteByName('ERLI_DEFAULT_ORDER_STATE');
        Configuration::deleteByName('ERLI_STATE_PENDING');
        Configuration::deleteByName('ERLI_STATE_PAID');
        Configuration::deleteByName('ERLI_STATE_CANCELLED');

        if (!parent::uninstall()) {
            return false;
        }

        return true;
    }

    protected function installSql()
    {
        $sqlFile = dirname(__FILE__) . '/Sql/install.sql';
        return $this->executeSqlFile($sqlFile);
    }

    protected function uninstallSql()
    {
        $sqlFile = dirname(__FILE__) . '/Sql/uninstall.sql';
        return $this->executeSqlFile($sqlFile);
    }

    protected function executeSqlFile($file)
    {
        if (!file_exists($file)) {
            return true;
        }

        $sqlContent = file_get_contents($file);
        $sqlContent = str_replace(['PREFIX_'], [_DB_PREFIX_], $sqlContent);
        $queries = preg_split("/;\s*[\r\n]+/", $sqlContent);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query) {
                if (!Db::getInstance()->execute($query)) {
                    return false;
                }
            }
        }
        return true;
    }

    protected function registerAdminTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminErliIntegration');
        if ($idTab) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminErliIntegration';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentModulesSf');
        $tab->active = 1;

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Erli';
        }

        return (bool) $tab->add();
    }

    protected function removeAdminTab()
    {
        $idTab = (int) Tab::getIdFromClassName('AdminErliIntegration');
        if ($idTab) {
            $tab = new Tab($idTab);
            return (bool) $tab->delete();
        }

        return true;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitErliIntegration')) {
            $apiKey          = Tools::getValue('ERLI_API_KEY');
            $cronToken       = Tools::getValue('ERLI_CRON_TOKEN');
            $defaultCarrier  = (int) Tools::getValue('ERLI_DEFAULT_CARRIER');
            $defaultOrderSt  = (int) Tools::getValue('ERLI_DEFAULT_ORDER_STATE');
            $statePending    = (int) Tools::getValue('ERLI_STATE_PENDING');
            $statePaid       = (int) Tools::getValue('ERLI_STATE_PAID');
            $stateCancelled  = (int) Tools::getValue('ERLI_STATE_CANCELLED');

            Configuration::updateValue('ERLI_API_KEY', $apiKey);
            Configuration::updateValue('ERLI_CRON_TOKEN', $cronToken ?: Tools::passwdGen(32));
            Configuration::updateValue('ERLI_DEFAULT_CARRIER', $defaultCarrier);
            Configuration::updateValue('ERLI_DEFAULT_ORDER_STATE', $defaultOrderSt);
            Configuration::updateValue('ERLI_STATE_PENDING', $statePending);
            Configuration::updateValue('ERLI_STATE_PAID', $statePaid);
            Configuration::updateValue('ERLI_STATE_CANCELLED', $stateCancelled);

            $output .= $this->displayConfirmation($this->l('Ustawienia zapisane.'));
        }

        if (Tools::isSubmit('submitErliTestConnection')) {
            $output .= $this->testConnection();
        }

        $output .= $this->renderForm();

        $linkToProductsSync = $this->context->link->getAdminLink('AdminErliIntegration');

        $output .= '
        <div class="panel">
            <h3><i class="icon-refresh"></i> ' . $this->l('Ręczna synchronizacja produktów z Erli.pl') . '</h3>
            <p>' . $this->l('Przejdź do panelu ręcznej synchronizacji produktów, aby wysłać wybrany produkt do Erli lub sprawdzić ostatnie logi.') . '</p>
            <a href="' . htmlspecialchars($linkToProductsSync, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">
                <i class="icon-external-link"></i> ' . $this->l('Otwórz panel synchronizacji produktów') . '
            </a>
        </div>';

        $cronToken = Configuration::get('ERLI_CRON_TOKEN');
        $cronUrl   = $this->context->link->getModuleLink('erliintegration', 'cron', ['token' => $cronToken]);

        $output .= '
        <div class="panel">
            <h3><i class="icon-clock-o"></i> ' . $this->l('CRON – automatyczna synchronizacja zamówień') . '</h3>
            <p>' . $this->l('Skonfiguruj zadanie CRON na serwerze, aby cyklicznie wywoływać ten URL:') . '</p>
            <pre style="user-select:all;">' . htmlspecialchars($cronUrl, ENT_QUOTES, 'UTF-8') . '</pre>
            <p>' . $this->l('Przykład (co 5 minut, cron Linux):') . '</p>
            <pre>*/5 * * * * curl -s "' . htmlspecialchars($cronUrl, ENT_QUOTES, 'UTF-8') . '" >/dev/null 2>&1</pre>
        </div>';

        return $output;
    }

    public function renderForm()
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $carriers = Carrier::getCarriers(
            $defaultLang,
            true,
            false,
            false,
            null,
            Carrier::ALL_CARRIERS
        );
        $orderStates = OrderState::getOrderStates($defaultLang);

        $fieldsForm[0]['form'] = [
            'legend' => [
                'title' => $this->l('Ustawienia Erli'),
            ],
            'input'  => [
                [
                    'type'     => 'text',
                    'label'    => $this->l('API key'),
                    'name'     => 'ERLI_API_KEY',
                    'size'     => 80,
                    'required' => true,
                    'desc'     => $this->l('Klucz API wygenerowany w panelu Erli.'),
                ],
                [
                    'type'     => 'text',
                    'label'    => $this->l('CRON token'),
                    'name'     => 'ERLI_CRON_TOKEN',
                    'size'     => 64,
                    'required' => true,
                    'desc'     => $this->l('Losowy token zabezpieczający URL CRON.'),
                ],
                [
                    'type'   => 'select',
                    'label'  => $this->l('Domyślny przewoźnik dla zamówień z Erli'),
                    'name'   => 'ERLI_DEFAULT_CARRIER',
                    'options'=> [
                        'query' => $carriers,
                        'id'    => 'id_carrier',
                        'name'  => 'name',
                    ],
                    'desc'   => $this->l('Przewoźnik ustawiany na zamówieniach importowanych z Erli (jeśli nie ma mapowania).'),
                ],
                [
                    'type'   => 'select',
                    'label'  => $this->l('Domyślny status zamówienia z Erli'),
                    'name'   => 'ERLI_DEFAULT_ORDER_STATE',
                    'options'=> [
                        'query' => $orderStates,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ],
                ],
                [
                    'type'   => 'select',
                    'label'  => $this->l('Status dla Erli: pending'),
                    'name'   => 'ERLI_STATE_PENDING',
                    'options'=> [
                        'query' => $orderStates,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ],
                ],
                [
                    'type'   => 'select',
                    'label'  => $this->l('Status dla Erli: purchased'),
                    'name'   => 'ERLI_STATE_PAID',
                    'options'=> [
                        'query' => $orderStates,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ],
                ],
                [
                    'type'   => 'select',
                    'label'  => $this->l('Status dla Erli: cancelled'),
                    'name'   => 'ERLI_STATE_CANCELLED',
                    'options'=> [
                        'query' => $orderStates,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Zapisz'),
                'class' => 'btn btn-default pull-right',
            ],
            'buttons' => [
                [
                    'title' => $this->l('Test połączenia'),
                    'icon'  => 'process-icon-refresh',
                    'type'  => 'submit',
                    'name'  => 'submitErliTestConnection',
                    'class' => 'btn btn-primary',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module                     = $this;
        $helper->name_controller            = $this->name;
        $helper->token                      = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex               = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language      = $defaultLang;
        $helper->allow_employee_form_lang   = $defaultLang;
        $helper->title                      = $this->displayName;
        $helper->show_toolbar               = false;
        $helper->toolbar_scroll             = false;
        $helper->submit_action              = 'submitErliIntegration';

        $helper->fields_value['ERLI_API_KEY']             = Configuration::get('ERLI_API_KEY');
        $helper->fields_value['ERLI_CRON_TOKEN']          = Configuration::get('ERLI_CRON_TOKEN');
        $helper->fields_value['ERLI_DEFAULT_CARRIER']     = Configuration::get('ERLI_DEFAULT_CARRIER');
        $helper->fields_value['ERLI_DEFAULT_ORDER_STATE'] = Configuration::get('ERLI_DEFAULT_ORDER_STATE');
        $helper->fields_value['ERLI_STATE_PENDING']       = Configuration::get('ERLI_STATE_PENDING');
        $helper->fields_value['ERLI_STATE_PAID']          = Configuration::get('ERLI_STATE_PAID');
        $helper->fields_value['ERLI_STATE_CANCELLED']     = Configuration::get('ERLI_STATE_CANCELLED');

        return $helper->generateForm($fieldsForm);
    }

    protected function testConnection()
    {
        try {
            $apiKey = Configuration::get('ERLI_API_KEY');

            if (!$apiKey) {
                throw new Exception($this->l('Brak ustawionego API key.'));
            }

            require_once __DIR__ . '/classes/Api/ErlApiClient.php';
            $client = new ErlApiClient($apiKey);

            $response = $client->get('/inbox', ['limit' => 1]);

            if ($response['code'] >= 200 && $response['code'] < 300) {
                return $this->displayConfirmation(
                    $this->l('Połączenie z Erli.pl działa. Kod HTTP: ') . $response['code']
                );
            }

            return $this->displayError(
                $this->l('Błąd połączenia z Erli.pl. Kod HTTP: ') . $response['code']
            );
        } catch (Exception $e) {
            return $this->displayError($e->getMessage());
        }
    }

    public function hookActionProductSave($params)
    {
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/ProductSync.php';

        if (empty($params['id_product'])) {
            return;
        }

        $apiKey = Configuration::get('ERLI_API_KEY');
        if (!$apiKey) {
            return;
        }

        ProductSync::syncSingle((int) $params['id_product']);
    }

    public function hookActionUpdateQuantity($params)
    {
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/ProductSync.php';

        if (empty($params['id_product'])) {
            return;
        }

        $apiKey = Configuration::get('ERLI_API_KEY');
        if (!$apiKey) {
            return;
        }

        $idProduct   = (int) $params['id_product'];
        $idAttribute = !empty($params['id_product_attribute'])
            ? (int) $params['id_product_attribute']
            : null;

        ProductSync::syncSingle($idProduct, $idAttribute);
    }
}
