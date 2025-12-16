<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Mapper/CategoryMapper.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Mapper/ShippingMapper.php';

class ProductMapper
{
    /**
     * Mapuje produkt PrestaShop na payload do Erli API
     *
     * @param Product $product
     * @param int     $idLang
     *
     * @return array
     * @throws Exception
     */
    public static function map(Product $product, $idLang)
    {
        if (!Validate::isLoadedObject($product)) {
            throw new Exception('Nie znaleziono produktu o ID: ' . (int) $product->id);
        }

        $idLang   = (int) $idLang;
        $context  = Context::getContext();
        $link     = $context->link;

        // Obrazki - tablica obiektów {"url": "..."}
        $images = [];
        $cover  = Image::getCover((int) $product->id);

        if ($cover && isset($cover['id_image'])) {
            $image = new Image($cover['id_image']);
            $url = $link->getImageLink(
                isset($product->link_rewrite[$idLang]) ? $product->link_rewrite[$idLang] : '',
                $image->id,
                ImageType::getFormattedName('large_default')
            );

            $images[] = [
                'url' => $url,
            ];
        }

        // Stock
        $stock = (int) StockAvailable::getQuantityAvailableByProduct((int) $product->id);

        // Status produktu w Erli
        $status = 'active';
        if (!$product->active || $stock <= 0) {
            $status = 'inactive';
        }

        // VAT – na razie stałe 23% (do rozwinięcia)
        $vatRate = 23;

        // Waga w gramach
        $weightGrams = (float) $product->weight * 1000;

        $categories   = CategoryMapper::mapProductCategories($product, $idLang);
        $shippingTags = ShippingMapper::mapTagsForProduct($product, $idLang);

        return [
            'externalId'  => (string) $product->id,
            'status'      => $status,
            'name'        => isset($product->name[$idLang]) ? (string) $product->name[$idLang] : '',
            'description' => isset($product->description[$idLang]) ? (string) $product->description[$idLang] : '',
            'price'       => (float) $product->price,
            'vat'         => $vatRate,
            'stock'       => $stock,
            'images'      => $images,
            'packaging'   => [
                'weight' => $weightGrams,
                'tags'   => $shippingTags,
            ],
            'externalCategories' => $categories,
        ];
    }
}
