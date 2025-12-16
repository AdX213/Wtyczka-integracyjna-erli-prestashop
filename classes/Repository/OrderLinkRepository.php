<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderLinkRepository
{
    public function findByErliOrderId($erliOrderId)
    {
        $sql = new DbQuery();
        $sql->select('*')
            ->from('erli_order_link')
            ->where('erli_order_id = "' . pSQL($erliOrderId) . '"');

        return Db::getInstance()->getRow($sql);
    }

    public function findByPrestashopOrderId($idOrder)
    {
        $sql = new DbQuery();
        $sql->select('*')
            ->from('erli_order_link')
            ->where('id_order = ' . (int) $idOrder);

        return Db::getInstance()->getRow($sql);
    }

    public function save($idOrder, $erliOrderId, $status)
    {
        $existing = $this->findByErliOrderId($erliOrderId);

        if ($existing) {
            return Db::getInstance()->update(
                'erli_order_link',
                [
                    'id_order'    => (int) $idOrder,
                    'last_status' => pSQL($status),
                ],
                'id_erli_order_link = ' . (int) $existing['id_erli_order_link']
            );
        }

        return Db::getInstance()->insert('erli_order_link', [
            'id_order'      => (int) $idOrder,
            'erli_order_id' => pSQL($erliOrderId),
            'last_status'   => pSQL($status),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
