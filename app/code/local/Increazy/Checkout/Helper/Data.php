<?php
class Increazy_Checkout_Helper_Data extends Mage_Core_Helper_Abstract
{
    const HASH = '{token}';

    public function run($action, $callback, $validate)
    {
        try {
            header('Content-Type:application/json');
            $data = json_decode($action->getRequest()->getRawBody(), true);
            Mage::getSingleton('core/cookie')->setLifetime(0);
            Mage::app()->setCurrentStore($data['store']);

            if (isset($data['token'])) {
                $customerID = Mage::helper('increazy_checkout')->hashDecode($data['token']);
                $customer = Mage::getModel('customer/customer')->load($customerID);

                $session = Mage::getSingleton('customer/session');
                $session->setCustomerAsLoggedIn($customer);
            }

            if (!$validate($data)) {
                throw new \Exception('Invalid request');
            }

            $response = $callback($data);
            die(json_encode($response));
        } catch (\Exception $e) {
            http_response_code(400);
            die(json_encode([
                'message' => $e->getMessage(),
            ]));
        }
    }

    public static function hashEncode($str)
    {
        if ($str == '') return '';
        $token = base64_decode(self::HASH);
        $parts = explode(':', $token);
        $key = substr(hash('sha256', $parts[0]), 0, 32);
        $iv = substr(hash('sha256', $parts[1]), 0, 16);

        $encrypted_data = openssl_encrypt($str, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypted_data);
    }

    public static function hashDecode($str)
    {
        if ($str == '') return '';
        $token = base64_decode(self::HASH);
        $parts = explode(':', $token);
        $key = substr(hash('sha256', $parts[0]), 0, 32);
        $iv = substr(hash('sha256', $parts[1]), 0, 16);

        $str = base64_decode($str);
        $data = openssl_decrypt($str, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $data;
    }
}