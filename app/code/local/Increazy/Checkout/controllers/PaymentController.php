<?php
require __DIR__ . '/CartController.php';

class Increazy_Checkout_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function finishAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customerID = Mage::helper('increazy_checkout')->hashDecode($body['token']);
            $customer = Mage::getModel('customer/customer')->load($customerID);
    
            $quote = Mage::getModel('sales/quote')->load($body['quote_id']);
            $quote->setCustomer($customer);
            $quote->save();

            if (!$quote->isVirtual()) {
                $quote->getShippingAddress()->setPaymentMethod($body['payment_data']['method']);
            } else {
                $quote->getBillingAddress()->setPaymentMethod($body['payment_data']['method']);
            }

            $_POST['payment'] = $body['payment_data'];
            $quote->getPayment()->importData(array_merge($body['payment_data'], [
                'payment_method' => $body['payment_data']['method'],
            ]));
            $quote->assignCustomer($customer);

            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            $checkoutSession = Mage::getSingleton('checkout/session');
            $checkoutSession->setLastQuoteId($quote->getId())
                ->setLastSuccessQuoteId($quote->getId())
            ->clearHelperData();

            $order = $service->getOrder();
            $order->setCustomer($customer);
            $order->setCustomerId($customer->getId());
            $order->setCustomerFirstname($customer->getFirstname());
            $order->setCustomerLastname($customer->getLastname());
            $order->setCustomerEmail($customer->getEmail());
            $order->save();

            if ($order) {
                Mage::dispatchEvent('checkout_type_onepage_save_order_after',
                    array('order'=>$order, 'quote'=>$quote));
                $redirectUrl = $quote->getPayment()->getOrderPlaceRedirectUrl();
                $checkoutSession->setLastOrderId($order->getId())
                    ->setRedirectUrl($redirectUrl)
                ->setLastRealOrderId($order->getIncrementId());

                $agreement = $order->getPayment()->getBillingAgreement();
                if ($agreement) {
                    $checkoutSession->setLastBillingAgreementId($agreement->getId());
                }
            }

            $profiles = $service->getRecurringPaymentProfiles();
            if ($profiles) {
                $ids = array();
                foreach ($profiles as $profile) {
                    $ids[] = $profile->getId();
                }
                $checkoutSession->setLastRecurringProfileIds($ids);
            }

            Mage::dispatchEvent(
                'checkout_submit_all_after',
                array('order' => $order, 'quote' => $quote, 'recurring_profiles' => $profiles)
            );

            $quote->setIsActive(0)->save();
            
            return $order->getData();
        }, function ($body) {
            return isset($body['token']) && isset($body['payment_data']) &&
                isset($body['quote_id']) && isset($body['tax'])
            ;
        });
    }

    public function methodsAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $payments = Mage::getSingleton('payment/config')->getActiveMethods();
            $methods = array(array('value'=>'','label'=>Mage::helper('adminhtml')->__('–Please Select–')));

            foreach ($payments as $paymentCode=>$paymentModel) {
                $paymentTitle = Mage::getStoreConfig('payment/'.$paymentCode.'/title');
                $methods[$paymentCode] = array(
                    'label'   => $paymentTitle,
                    'value' => $paymentCode,
                );
            }
            return $methods;
        }, function ($body) {
            return isset($body['quote_id']);
        });
    }

}