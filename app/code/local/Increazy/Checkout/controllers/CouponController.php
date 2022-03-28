<?php
require __DIR__ . '/CartController.php';

class Increazy_Checkout_CouponController extends Mage_Core_Controller_Front_Action
{

    public function addAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $quote = Mage::getModel('sales/quote')->load($body['quote_id']);
            $coupon = Mage::getModel('salesrule/coupon')->load(trim($body['coupon']), 'code');

            $rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
			if (!$rule->getId()) {
				throw new \Exception('Cupom inválido');
			}

            $quote->setCouponCode($body['coupon']);
            return Increazy_Checkout_CartController::getQuote($quote);
        }, function ($body) {
            return isset($body['coupon']) && isset($body['quote_id']);
        });
    }

    public function removeAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $quote = Mage::getModel('sales/quote')->load($body['quote_id']);
            $quote->setCouponCode('');

            return Increazy_Checkout_CartController::getQuote($quote);
        }, function ($body) {
            return isset($body['quote_id']);
        });
    }
}