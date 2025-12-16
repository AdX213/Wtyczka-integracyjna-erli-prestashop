<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class OrderMapper
{
    /**
     * Tworzy (lub znajduje) klienta na podstawie danych z Erli
     *
     * @param array $orderData
     * @return Customer
     */
    public static function getOrCreateCustomer(array $orderData)
    {
        $email = $orderData['buyer']['email'] ?? null;

        if (!$email) {
            $email = 'erli-' . time() . '@example.com';
        }

        $firstname = $orderData['buyer']['firstName'] ?? 'ERLI';
        $lastname  = $orderData['buyer']['lastName'] ?? 'Customer';

        $customerId = Customer::customerExists($email, true);
        if ($customerId) {
            return new Customer($customerId);
        }

        $customer = new Customer();
        $customer->email      = $email;
        $customer->firstname  = $firstname;
        $customer->lastname   = $lastname;
        $customer->passwd     = Tools::encrypt(Tools::passwdGen());
        $customer->id_lang    = (int) Configuration::get('PS_LANG_DEFAULT');
        $customer->id_shop    = (int) Context::getContext()->shop->id;
        $customer->newsletter = 0;

        $customer->add();

        return $customer;
    }

    /**
     * Buduje adres (dostawa / faktura)
     *
     * @param Customer $customer
     * @param array    $addrData
     * @param string   $alias
     *
     * @return Address
     */
    public static function createAddress(Customer $customer, array $addrData, $alias = 'ERLI')
    {
        $address = new Address();
        $address->id_customer = (int) $customer->id;
        $address->alias       = $alias;

        $address->firstname   = $addrData['firstName'] ?? $customer->firstname;
        $address->lastname    = $addrData['lastName'] ?? $customer->lastname;
        $address->address1    = $addrData['street'] ?? ' ';
        $address->postcode    = $addrData['zipCode'] ?? '';
        $address->city        = $addrData['city'] ?? '';
        $address->phone       = $addrData['phone'] ?? '';

        $isoCountry = $addrData['countryCode'] ?? 'PL';
        $idCountry  = Country::getByIso($isoCountry);
        if (!$idCountry) {
            $idCountry = Country::getByIso('PL');
        }
        $address->id_country = (int) $idCountry;

        $address->add();

        return $address;
    }

    /**
     * Uzupełnia koszyk produktami na podstawie orderData z Erli
     *
     * @param Cart  $cart
     * @param array $orderData
     */
    public static function fillCartWithProducts(Cart $cart, array $orderData)
    {
        if (empty($orderData['items']) || !is_array($orderData['items'])) {
            return;
        }

        foreach ($orderData['items'] as $item) {
            if (empty($item['externalProductId'])) {
                continue;
            }

            $externalId = (string) $item['externalProductId'];
            $quantity   = (int) ($item['quantity'] ?? 1);

            $idProduct   = null;
            $idAttribute = null;

            if (strpos($externalId, '-') !== false) {
                list($idProduct, $idAttribute) = explode('-', $externalId);
            } else {
                $idProduct = $externalId;
            }

            $cart->updateQty(
                $quantity,
                (int) $idProduct,
                $idAttribute ? (int) $idAttribute : null
            );
        }
    }
}
