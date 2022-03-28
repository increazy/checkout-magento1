<?php
require __DIR__ . '/CartController.php';

class Increazy_Checkout_OrderController extends Mage_Core_Controller_Front_Action
{
    public function getAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($body['order_id']);

            $items = array();
            foreach ($order->getAllItems() as $item) {
                $image = Mage::getResourceModel('catalog/product')->getAttributeRawValue($item->getProductId(), 'image', Mage::app()->getStore()->getId());

                if($image) {
                    $image = Mage::getModel('catalog/product_media_config')->getMediaUrl($image);
                }

                $productId    = $item->getProductId();
                $product      = Mage::getModel('catalog/product')->load($productId);
                $categoryIds  = $product->getCategoryIds();

                foreach ($categoryIds as $category_id) {
                    $category = Mage::getModel('catalog/category')->setStoreId(Mage::app()->getStore()->getId())->load($category_id);
                    $categoryNames[] = $category->getName();
                }

                $categoriesNames = implode(', ',$categoryNames);

                $items[] = array_merge($product->getData(), $item->getData(), [
                    'image'         => $image,
                    'categories_names' => $categoriesNames,
                    'categories_ids' => $categoryIds
                ]);
            }

            return array_merge($order->getData(), [
                'items'          => $items,
                'shipping'       => $order->getShippingAddress()->getFormated(true),
                'billing'        => $order->getBillingAddress()->getFormated(true),
                'delivery_name'  => $order->getData()['shipping_description'],
                'delivery_price' => $order->getData()['shipping_amount'],
                'discount'       => $order->getData()['discount_amount'],
                'total'          => $order->getData()['grand_total'],
                'payment_method' => $this->getPaymentData($order),
            ]);
        }, function ($body) {
            return isset($body['order_id']);
        });
    }

    public function listAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customerID = Mage::helper('increazy_checkout')->hashDecode($body['token']);

            $orderCollection = Mage::getResourceModel('sales/order_collection')
                ->addFieldToSelect('*')
                ->addFieldToFilter('customer_id', $customerID)
                ->setOrder('created_at', 'desc')
            ;

            return $orderCollection;
        }, function ($body) {
            return isset($body['token']);
        });
    }

    private function getPaymentData($order) {
        $payment = $order->getPayment();
        $additionalInfo = $payment->getAdditionalInformation();

        return [
            'additional_info' => $additionalInfo,
            'method' => $payment->getMethod(),
        ];
    }

}