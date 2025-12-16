<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/CategoryMapRepository.php';

class CategoryMapper
{
    /**
     * Zwraca listę kategorii Erli dla danego produktu,
     * w formacie zbliżonym do externalCategories Erli.
     *
     * @param Product $product
     * @param int     $idLang
     *
     * @return array
     */
    public static function mapProductCategories(Product $product, $idLang)
    {
        if (!Validate::isLoadedObject($product)) {
            return [];
        }

        $repo = new CategoryMapRepository();
        $idCategories = $product->getCategories();
        $result = [];

        foreach ($idCategories as $idCategory) {
            $map = $repo->findByCategoryId($idCategory);
            if (!$map) {
                continue;
            }

            $category = new Category($idCategory, (int) $idLang);

            $result[] = [
                'source'     => 'marketplace',
                'breadcrumb' => [
                    [
                        'id'   => (string) $map['erli_category_id'],
                        'name' => $map['erli_category_name'] ?: $category->name,
                    ],
                ],
            ];
        }

        return $result;
    }
}
