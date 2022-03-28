<?php
require __DIR__ . '/CartController.php';

class Increazy_Checkout_StatusController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $order = Mage::getModel('sales/order')->load($body['conversion']['external_order'], 'increment_id');;

            if ($order->getId()) {
                switch ($body['status']) {
                    case 'waiting':
                        if ($order->canHold()) {
                            $order->hold();
                        }
                        break;
                    case 'validate':
                        if ($order->canUnhold()) {
                            $order->unhold();
                        }

                        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
                        $order->setState($state)->setStatus($state);
                        break;
                    case 'error':
                    case 'canceled':
                        if ($order->canUnhold()) {
                            $order->unhold();
                        }

                        if ($order->canCancel()) {
                            $order->cancel();
                        }
                        break;
                    case 'success':
                        if ($order->canUnhold()) {
                            $order->unhold();
                        }

                        if ($order->canInvoice()) {
                            $status = 'ccadyennovo';
                            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);

                            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
                            $invoice->register();
                            $transactionSave = Mage::getModel('core/resource_transaction')
                                ->addObject($invoice)
                                ->addObject($invoice->getOrder());
                            $transactionSave->save();
                        }
                        break;
                }

                $order->save();
            }

            return true;
        }, function ($body) {
            return isset($body['conversion']['external_order']);
        });
    }
}