<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Api/ErliOrderApi.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Mapper/OrderMapper.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/OrderLinkRepository.php';
require_once _PS_MODULE_DIR_ . 'erliintegration/classes/Repository/LogRepository.php';

class OrderSync
{
    public function processInbox()
    {
        $orderApi = new ErlOrderApi();
        $logRepo  = new LogRepository();
        $linkRepo = new OrderLinkRepository();

        $response = $orderApi->getInbox(100);

        if ($response['code'] < 200 || $response['code'] >= 300) {
            $logRepo->addLog(
                'order_inbox_error',
                '',
                'Błąd pobierania inbox: ' . $response['code'],
                $response['raw']
            );
            return;
        }

        $body = $response['body'];
        if (empty($body['items']) || !is_array($body['items'])) {
            return;
        }

        $lastMessageId = null;

        foreach ($body['items'] as $event) {
            $lastMessageId = $event['messageId'] ?? $lastMessageId;

            $type = $event['type'] ?? '';
            if (!in_array($type, ['ORDER_CREATED', 'newOrder', 'orderCreated'])) {
                continue;
            }

            $erliOrderId = $event['orderId'] ?? null;
            if (!$erliOrderId) {
                continue;
            }

            $orderResp = $orderApi->getOrder($erliOrderId);
            if ($orderResp['code'] < 200 || $orderResp['code'] >= 300) {
                $logRepo->addLog(
                    'order_fetch_error',
                    $erliOrderId,
                    'Błąd pobierania zamówienia: ' . $orderResp['code'],
                    $orderResp['raw']
                );
                continue;
            }

            $orderData = $orderResp['body'];

            $existing = $linkRepo->findByErliOrderId($erliOrderId);
            if ($existing) {
                $status = $orderData['status'] ?? '';
                $this->updateOrderStatus((int) $existing['id_order'], $status);
                $linkRepo->save((int) $existing['id_order'], $erliOrderId, $status);
                continue;
            }

            $idOrder = $this->createOrderFromErliData($orderData);
            if ($idOrder) {
                $status = $orderData['status'] ?? '';
                $linkRepo->save($idOrder, $erliOrderId, $status);
                $logRepo->addLog(
                    'order_created',
                    $idOrder,
                    'Zamówienie utworzone z Erli',
                    json_encode($orderData)
                );
            }
        }

        if ($lastMessageId) {
            $orderApi->ackInbox($lastMessageId);
        }
    }

    protected function createOrderFromErliData(array $orderData)
    {
        $logRepo = new LogRepository();
        $context = Context::getContext();

        $customer = OrderMapper::getOrCreateCustomer($orderData);

        $shippingAddrData = $orderData['shippingAddress'] ?? [];
        $billingAddrData  = $orderData['billingAddress'] ?? $shippingAddrData;

        $deliveryAddress = OrderMapper::createAddress($customer, $shippingAddrData, 'ERLI Delivery');
        $invoiceAddress  = OrderMapper::createAddress($customer, $billingAddrData, 'ERLI Invoice');

        $cart = new Cart();
        $cart->id_lang      = (int) Configuration::get('PS_LANG_DEFAULT');
        $cart->id_currency  = (int) Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_customer  = (int) $customer->id;
        $cart->id_address_delivery = (int) $deliveryAddress->id;
        $cart->id_address_invoice  = (int) $invoiceAddress->id;
        $cart->id_carrier   = (int) Configuration::get('ERLI_DEFAULT_CARRIER');
        $cart->secure_key   = $customer->secure_key;
        $cart->add();

        OrderMapper::fillCartWithProducts($cart, $orderData);

        $totalPaid   = (float) ($orderData['summary']['total'] ?? $cart->getOrderTotal(true, Cart::BOTH));
        $orderStatus = (int) Configuration::get('ERLI_DEFAULT_ORDER_STATE');

        $paymentModuleName = 'Erli';
        $paymentMethod     = 'Erli Payment';

        $extraVars = [
            'transaction_id' => $orderData['orderId'] ?? '',
        ];

        $order = new Order();
        $order->payment = $paymentMethod;

        $result = $order->validateOrder(
            $cart->id,
            $orderStatus,
            $totalPaid,
            $paymentModuleName,
            null,
            $extraVars,
            $cart->id_currency,
            false,
            $customer->secure_key
        );

        if (!$result) {
            $logRepo->addLog(
                'order_create_error',
                '',
                'Nie udało się utworzyć zamówienia w Preście',
                json_encode($orderData)
            );
            return null;
        }

        return (int) Order::getOrderByCartId($cart->id);
    }

    protected function updateOrderStatus($idOrder, $erliStatus)
    {
        if (!$erliStatus) {
            return;
        }

        $map = [
            'pending'   => (int) Configuration::get('ERLI_STATE_PENDING'),
            'purchased' => (int) Configuration::get('ERLI_STATE_PAID'),
            'cancelled' => (int) Configuration::get('ERLI_STATE_CANCELLED'),
        ];

        if (!isset($map[$erliStatus])) {
            return;
        }

        $newState = (int) $map[$erliStatus];
        if ($newState <= 0) {
            return;
        }

        $history = new OrderHistory();
        $history->id_order = (int) $idOrder;
        $history->changeIdOrderState($newState, $idOrder);
        $history->add();
    }
}
