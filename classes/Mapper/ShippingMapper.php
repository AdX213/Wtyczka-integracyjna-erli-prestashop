<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/ShippingMapRepository.php';

class ShippingMapper
{
    /**
     * Zwraca tablicę tagów Erli dla produktu:
     * 
     *
     * @param Product $product
     * @param int     $idLang
     *
     * @return string[]
     */
    public static function mapTagsForProduct(Product $product, $idLang)
    {
        if (!Validate::isLoadedObject($product)) {
            return [];
        }

        $idLang = (int) $idLang;

        $repo = new ShippingMapRepository();
        $shippingMap = $repo->getAllAsArray(); // [id_carrier => erli_tag]

        if (empty($shippingMap)) {
            return [];
        }

        $carriers = Carrier::getCarriers(
            $idLang,
            true,
            false,
            false,
            null,
            Carrier::ALL_CARRIERS
        );

        $tags = [];

        foreach ($carriers as $carrier) {
            $idCarrier = (int) $carrier['id_carrier'];
            if (isset($shippingMap[$idCarrier])) {
                $tags[] = $shippingMap[$idCarrier];
            }
        }

        return array_values(array_unique($tags));
    }
}
