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
        $this->version = '1.2.2'; // ✅ spójnie z paczką
        $this->author = 'Adrian';
        $this->need_instance = 0;
        $this->bootstrap = true;  // ✅ BO

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

        // ✅ rejestracja hooków (raz, poprawnie)
        if (
            !$this->registerHook('actionProductSave') ||
            !$this->registerHook('actionUpdateQuantity')
        ) {
            return false;
        }

        Configuration::updateValue('ERLI_API_KEY', '');
        Configuration::updateValue('ERLI_CRON_TOKEN', Tools::passwdGen(32));

        Configuration::updateValue('ERLI_DEFAULT_CARRIER', 0);
        Configuration::updateValue('ERLI_DEFAULT_ORDER_STATE', (int) Configuration::get('PS_OS_PAYMENT'));

        Configuration::updateValue('ERLI_STATE_PENDING', (int) Configuration::get('PS_OS_BANKWIRE'));
        Configuration::updateValue('ERLI_STATE_PAID', (int) Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('ERLI_STATE_CANCELLED', (int) Configuration::get('PS_OS_CANCELED'));

        return true;
    }

    public function uninstall()
    {
        // sprzątanie taba (nie blokuj uninstall)
        $this->removeAdminTab();

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

        return parent::uninstall();
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

        // bezpieczniejsze cięcie po średnikach
        $queries = preg_split("/;\s*[\r\n]+/", $sqlContent);

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query !== '') {
                if (!Db::getInstance()->execute($query)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function registerAdminTab()
    {
        // 1) Parent (sekcja ERLI)
        $parentClass = 'AdminErli';
        $parentId = (int) Tab::getIdFromClassName($parentClass);

        if (!$parentId) {
            $parent = new Tab();
            $parent->active = 1;
            $parent->class_name = $parentClass;
            $parent->module = $this->name;

            // 0 = root menu (osobna sekcja w lewym panelu)
            $parent->id_parent = 0;

            foreach (Language::getLanguages(false) as $lang) {
                $parent->name[(int)$lang['id_lang']] = 'ERLI';
            }

            // Spróbuj wymusić "na dole"
            $parent->position = 9999;

            if (!$parent->add()) {
                return false;
            }

            $parentId = (int) Tab::getIdFromClassName($parentClass);
        }

        // 2) Child (Twoja strona)
        $childClass = 'AdminErliIntegration';
        $childId = (int) Tab::getIdFromClassName($childClass);

        if ($childId) {
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $childClass;
        $tab->module = $this->name;
        $tab->id_parent = (int) $parentId;

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int)$lang['id_lang']] = 'Integracja';
        }

        // też na końcu sekcji
        $tab->position = 9999;

        return (bool) $tab->add();
    }

    protected function removeAdminTab()
    {
        // usuń child
        $childId = (int) Tab::getIdFromClassName('AdminErliIntegration');
        if ($childId) {
            (new Tab($childId))->delete();
        }

        // usuń parent
        $parentId = (int) Tab::getIdFromClassName('AdminErli');
        if ($parentId) {
            (new Tab($parentId))->delete();
        }

        return true;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitErliIntegration')) {
            $apiKey          = (string) Tools::getValue('ERLI_API_KEY');
            $cronToken       = (string) Tools::getValue('ERLI_CRON_TOKEN');
            $defaultCarrier  = (int) Tools::getValue('ERLI_DEFAULT_CARRIER');
            $defaultOrderSt  = (int) Tools::getValue('ERLI_DEFAULT_ORDER_STATE');
            $statePending    = (int) Tools::getValue('ERLI_STATE_PENDING');
            $statePaid       = (int) Tools::getValue('ERLI_STATE_PAID');
            $stateCancelled  = (int) Tools::getValue('ERLI_STATE_CANCELLED');

            Configuration::updateValue('ERLI_API_KEY', trim($apiKey));
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

        $linkToPanel = $this->context->link->getAdminLink('AdminErliIntegration');

        $output .= '
        <div class="panel">
            <h3><i class="icon-refresh"></i> ' . $this->l('Panel integracji Erli.pl') . '</h3>
            <p>' . $this->l('Przejdź do panelu integracji, aby zobaczyć dashboard, logi i narzędzia synchronizacji.') . '</p>
            <a href="' . htmlspecialchars($linkToPanel, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">
                <i class="icon-external-link"></i> ' . $this->l('Otwórz panel Erli') . '
            </a>
        </div>';

        $cronToken = Configuration::get('ERLI_CRON_TOKEN');
        $cronUrl   = $this->context->link->getModuleLink('erliintegration', 'cron', ['token' => $cronToken]);

        $output .= '
        <div class="panel">
            <h3><i class="icon-clock-o"></i> ' . $this->l('CRON – automatyczna synchronizacja') . '</h3>
            <p>' . $this->l('Wywołuj cyklicznie ten URL:') . '</p>
            <pre style="user-select:all;">' . htmlspecialchars($cronUrl, ENT_QUOTES, 'UTF-8') . '</pre>
            <p>' . $this->l('Przykład (co 5 minut, Linux cron):') . '</p>
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
            $apiKey = (string) Configuration::get('ERLI_API_KEY');
            if ($apiKey === '') {
                throw new Exception($this->l('Brak ustawionego API key.'));
            }

            // ✅ poprawna klasa/plik
            require_once __DIR__ . '/classes/Api/ErliApiClient.php';
            $client = new ErliApiClient($apiKey);

            $response = $client->get('/inbox', ['limit' => 1]);

            if ((int)$response['code'] >= 200 && (int)$response['code'] < 300) {
                return $this->displayConfirmation(
                    $this->l('Połączenie z Erli.pl działa. Kod HTTP: ') . (int) $response['code']
                );
            }

            return $this->displayError(
                $this->l('Błąd połączenia z Erli.pl. Kod HTTP: ') . (int) $response['code']
            );
        } catch (Exception $e) {
            return $this->displayError($e->getMessage());
        }
    }

    public function hookActionProductSave($params)
    {
        if (empty($params['id_product'])) {
            return;
        }

        $apiKey = (string) Configuration::get('ERLI_API_KEY');
        if ($apiKey === '') {
            return;
        }

        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/ProductSync.php';

        // ✅ nie ma syncSingle() – używamy batch pending (1 rekord) lub syncAllPending
        $sync = new ProductSync();
        $sync->syncAllPending(1);
    }

    public function hookActionUpdateQuantity($params)
    {
        if (empty($params['id_product'])) {
            return;
        }

        $apiKey = (string) Configuration::get('ERLI_API_KEY');
        if ($apiKey === '') {
            return;
        }

        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Sync/ProductSync.php';

        // To też odpalamy lekko (1 rekord). Jeśli chcesz precyzyjnie po id_product, dopiszemy syncSingle().
        $sync = new ProductSync();
        $sync->syncAllPending(1);
    }
}
