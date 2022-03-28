<?php
class Increazy_Checkout_Model_Observer
{

    public function registerTransactionId(Varien_Event_Observer $observer) {
        $order = $observer->getOrder();

        if (!$order->getIncreazyTransactionId()) {
            $payment = $order->getPayment();
            $additionalData = $payment->getAdditionalInformation();

            if (isset($additionalData['order'])) {
                $order->setIncreazyTransactionId($additionalData['order']);
                $order->save();
            }
        }
    }

}
