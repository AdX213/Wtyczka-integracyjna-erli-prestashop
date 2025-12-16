<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductSync
{
    public static function syncSingle($idProduct, $idAttribute = null)
    {
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/ProductLinkRepository.php';
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/LogRepository.php';
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Mapper/ProductMapper.php';
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Api/ErlProductApi.php';
        require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Api/ErlApiClient.php';

        $product = new Product($idProduct);
        $idLang  = (int) Configuration::get('PS_LANG_DEFAULT');

        $mapperData = ProductMapper::map($product, $idLang);
        $payload    = json_encode($mapperData);

        $repo    = new ProductLinkRepository();
        $logRepo = new LogRepository();

        $client = new ErlApiClient(Configuration::get('ERLI_API_KEY'));
        $api    = new ErlProductApi($client);

        $existing = $repo->findByProduct($idProduct, $idAttribute);

        try {
            if (!$existing) {
                $response = $api->createProduct($mapperData['externalId'], $mapperData);
            } else {
                if ($existing['last_payload'] === $payload) {
                    return 'Brak zmian — pomijam synchronizację.';
                }
                $response = $api->updateProduct($mapperData['externalId'], $mapperData);
            }

            if ($response['code'] >= 200 && $response['code'] < 300) {
                $repo->save($idProduct, $idAttribute, $mapperData['externalId'], $payload);
                $logRepo->addLog('product_sync', $idProduct, 'SUKCES', $payload);
                return 'OK – zsynchronizowano produkt ID ' . (int) $idProduct;
            }

            $logRepo->addLog('product_error', $idProduct, 'Błąd: ' . $response['code'], $response['raw']);
            return 'Błąd API: ' . $response['code'];

        } catch (Exception $e) {
            $repo->markError($idProduct, $e->getMessage());
            $logRepo->addLog('product_exception', $idProduct, $e->getMessage(), $payload);
            return 'WYJĄTEK: ' . $e->getMessage();
        }
    }
}
