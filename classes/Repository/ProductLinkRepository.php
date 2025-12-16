<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductLinkRepository
{
    public function findByProduct($idProduct, $idProductAttribute = null)
    {
        $sql = new DbQuery();
        $sql->select('*')
            ->from('erli_product_link')
            ->where('id_product = ' . (int) $idProduct);

        if ($idProductAttribute !== null) {
            $sql->where('id_product_attribute = ' . (int) $idProductAttribute);
        }

        return Db::getInstance()->getRow($sql);
    }

    public function save($idProduct, $idCombination, $externalId, $payload)
    {
        $existing = $this->findByProduct($idProduct, $idCombination);

        if ($existing) {
            return Db::getInstance()->update(
                'erli_product_link',
                [
                    'external_id'    => pSQL($externalId),
                    'last_payload'   => pSQL($payload, true),
                    'last_synced_at' => date('Y-m-d H:i:s'),
                    'last_error'     => null,
                ],
                'id_erli_product_link = ' . (int) $existing['id_erli_product_link']
            );
        }

        return Db::getInstance()->insert('erli_product_link', [
            'id_product'           => (int) $idProduct,
            'id_product_attribute' => $idCombination ? (int) $idCombination : null,
            'external_id'          => pSQL($externalId),
            'last_payload'         => pSQL($payload, true),
            'last_synced_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    public function markError($idProduct, $message)
    {
        Db::getInstance()->update(
            'erli_product_link',
            ['last_error' => pSQL($message)],
            'id_product = ' . (int) $idProduct
        );
    }
}
