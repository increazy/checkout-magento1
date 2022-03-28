<?php
class Increazy_Checkout_CustomerController extends Mage_Core_Controller_Front_Action
{
    public function existsAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customer = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($body['email']);

            if ($customer->getId()) {
                return [
                    'email' => $body['email'],
                ];
            }

            throw new \Exception('Customer does not exist');
        }, function ($body) {
            return isset($body['email']);
        });
    }

    public function newsletterAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customer = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getWebsite()->getId())
            ->loadByEmail($body['email']);


            if ($customer->getId()) {
                Mage::getModel('newsletter/subscriber')->subscribe($body['email']);
                return [ 'message' => 'Newsletter subscribed' ];
            }

            throw new \Exception('Newsletter subscribe error');
        }, function ($body) {
            return isset($body['email']);
        });
    }

    public function registerAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customer = Mage::getModel("customer/customer");
            $customer->setWebsiteId(Mage::app()->getWebsite()->getId())
                ->setStore(Mage::app()->getStore())
                ->setFirstname($body['firstname'])
                ->setLastname($body['lastname'])
                ->setEmail($body['email'])
                ->setTaxvat($body['taxvat'])
            ->setPassword($body['password']);

            $customer->save();

            if ($customer->getId()) {
                return $customer->toArray();
            }

            throw new \Exception('Customer creation error');
        }, function ($body) {
            return isset($body['email']) && isset($body['password']) &&
            isset($body['firstname']) && isset($body['lastname']) &&
            isset($body['taxvat']);
        });
    }

    public function updateAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customer = Mage::getModel('customer/customer')->load($body['entity_id']);

            $customer->setWebsiteId(Mage::app()->getWebsite()->getId())
                ->setStore(Mage::app()->getStore())
                ->setFirstname($body['firstname'])
                ->setLastname($body['lastname'])
                ->setEmail($body['email'])
                ->setTaxvat($body['taxvat']);

            $customer->save();

            if ($customer->getId()) {
                return $customer->toArray();
            }

            throw new \Exception('Customer creation error');
        }, function ($body) {
            return isset($body['email']) &&
            isset($body['firstname']) && isset($body['lastname']) &&
            isset($body['taxvat']) && isset($body['entity_id']);
        });
    }
}