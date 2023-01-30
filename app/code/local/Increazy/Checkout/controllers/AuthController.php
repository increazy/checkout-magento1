<?php
class Increazy_Checkout_AuthController extends Mage_Core_Controller_Front_Action
{
    public function connectAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customerID = Mage::helper('increazy_checkout')->hashDecode($body['token']);
            $customer = Mage::getModel('customer/customer')->load($customerID);

            if ($customer->getId()) {
                return [
                    'customer' => $customer->toArray(),
                    'token'    => Mage::helper('increazy_checkout')->hashEncode($customer->getId()),
                ];
            }

            throw new \Exception('Customer login error');
        }, function ($body) {
            return isset($body['token']);
        });
    }

    public function bridgeAction()
    {
        Mage::getSingleton('core/session', array('name' => 'frontend'));

        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customerID = Mage::helper('increazy_checkout')->hashDecode(str_replace(' ', '+', $body['token']));
            $customer = Mage::getModel('customer/customer')->load($customerID);

            if ($customer->getId()) {
                Mage::app('default');
                !@umask(0);
                Mage::getSingleton('core/session', array('name' => 'frontend'));

                $session = Mage::getSingleton('customer/session');
                // $session->start();


                $session->loginById($customer->getId());

                // return [
                //     'id' => $customerID,
                //     'data' => Mage::getSingleton('customer/session')->getCustomer()->getData(),
                // ];


                $this->_redirect('customer/account');
            }

            // throw new \Exception('Customer login error');

            // return [
            //     'token' => $body,
            //     'cdo' => $customer->getData()
            // ];
        }, function ($body) {
            return isset($body['token']);
        });
    }

    public function loginAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $session = Mage::getSingleton('customer/session');
            $session->login($body['email'], $body['password']);
            $session->setCustomerAsLoggedIn($session->getCustomer());

            $customer = Mage::getModel('customer/customer')->load($session->getCustomer()->getId());

            if ($customer->getId()) {
                return [
                    'customer' => $customer->toArray(),
                    'id'       => $customer->getId(),
                    'token'    => Mage::helper('increazy_checkout')->hashEncode($customer->getId()),
                ];
            }

            throw new \Exception('Customer login error');
        }, function ($body) {
            return isset($body['email']) && isset($body['password']);
        });
    }

    // TODO: corrigir
    public function recoveryAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customerEmail = Mage::helper('increazy_checkout')->hashDecode($body['email']);

            $customer = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($customerEmail);

            if ($customer->getId()) {
                $customer->setPassword($body['password']);
                $customer->save();

                return [ true  ];
            }

            throw new \Exception('Customer recovery error');
        }, function ($body) {
            return isset($body['email']) && isset($body['password']);
        });
    }
}
