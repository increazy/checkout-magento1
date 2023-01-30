<?php
class Increazy_Checkout_CartController extends Mage_Core_Controller_Front_Action
{
    public function getOrCreateAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $quote = Mage::getModel('sales/quote')->setStore(Mage::app()->getStore());
            $customerID = !isset($data['token']) ? null : Mage::helper('increazy_checkout')->hashDecode($data['token']);
    
            if (isset($body['quote_id'])) {
                $quote = $quote->load($body['quote_id']);
                if ($customerID != null) {
                    $quote->assignCustomer($customerID);
                    $quote->save();
                }

                return $this->getQuote($quote);
            } else if (isset($body['token'])) {
                $quote = $quote
                    ->loadByCustomer(Mage::getSingleton('customer/session')->getCustomer());

                return $this->getQuote($quote);
            }

            if ($customerID != null) {
                $quote->assignCustomer($customerID);
                $quote->save();
            }

            return $this->getQuote($quote);
        }, function ($body) {
            return true;
        });
    }

    public function addAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $quote = Mage::getModel('sales/quote')->load($body['quote_id']);
            $product = Mage::getModel('catalog/product')->load($body['product_id']);

            $varien = [
                'qty'        => $body['qty'],
                'product_id' => $body['product_id'],
            ];

            if (isset($body['super_attribute'])) {
                $varien['super_attribute'] = $body['super_attribute'];
            }

            if (isset($body['options'])) {
                $varien['options'] = $body['options'];
            }

            $quote->addProduct($product, new Varien_Object($varien));

            return $this->getQuote($quote);
        }, function ($body) {
            return isset($body['product_id']) && isset($body['quote_id']) &&
            isset($body['qty']);
        });
    }

    public function removeAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $quote = Mage::getModel('sales/quote')->load($body['quote_id']);

            $items = $quote->getAllVisibleItems();
            foreach ($items as $item) {
                if ($item->getProductId() == $body['product_id']) {
                    $quote->removeItem($item->getId());
                }
            }

            return $this->getQuote($quote);
        }, function ($body) {
            return isset($body['product_id']) && isset($body['quote_id']);
        });
    }

    public function changeAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $quote = Mage::getModel('sales/quote')->load($body['quote_id']);

            $items = $quote->getAllVisibleItems();
            foreach ($items as $item) {
                if ($item->getId() == $body['item_id']) {
                    $item->setQty($body['quantity']);
                    $item->save();
                }
            }

            return $this->getQuote($quote);
        }, function ($body) {
            return isset($body['item_id']) && isset($body['quote_id']) && isset($body['quantity']);
        });
    }

    public function setMethodAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $quote = Mage::getModel('sales/quote')->load($body['quote_id']);

            $quote->getPayment()->importData(array('method' => 'increazy-' . $body['method']));

			$quote->save();

            return $this->getQuote($quote);
        }, function ($body) {
            return isset($body['quote_id']) && isset($body['method']);
        });
    }

    public function setDeliveryAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $quote = Mage::getModel('sales/quote')->load($body['quote_id']);

            $address = Mage::getModel('customer/address')->load($body['address_id']);
            $quoteShippingAddress = new Mage_Sales_Model_Quote_Address();
			$quoteShippingAddress->setData($address->toArray());

            $quote->setBillingAddress($quoteShippingAddress);
			$quote->setShippingAddress($quoteShippingAddress);
			$quote->save();

            $rate = $quote->getShippingAddress()->getShippingRateByCode($body['shipping_method']);

            if(!$rate) {
                throw new Exception('Shipping rate not found');
            }

            $quote->getShippingAddress()->setShippingMethod($body['shipping_method']);

            return $this->getQuote($quote);
        }, function ($body) {
            return isset($body['quote_id']) && isset($body['address_id']) &&
            isset($body['shipping_method']);
        });
    }

    public static function getQuote($quote)
    {
        $quote->setIsActive(true)->setIsMultiShipping(false);
        $quote->getBillingAddress();
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
        $quote->save();

        return array_merge($quote->toArray(), [
			'quote_id'			=> $quote->getId(),
			'coupon_code'		=> $quote->getCouponCode(),
			'reserved_order_id' => $quote->getReservedOrderId(),
			'items_count'		=> $quote->getItemsCount(),
			'items_qty'			=> $quote->getItemsQty(),
			'customer'			=> $quote->getCustomer()->toArray(),
			'shipping'			=> [
                'method'      => $quote->getShippingAddress()->getShippingMethod(),
                'description' => $quote->getShippingAddress()->getShippingDescription(),
            ],
			'address'           => $quote->getShippingAddress()->toArray(),
            'totals'            => [
                'subtotal'		=> $quote->getSubtotalWithDiscount(),
                'discount'		=> $quote->getSubtotal() - $quote->getSubtotalWithDiscount(),
                'shipping'		=> $quote->getShippingAddress()->getShippingAmount(),
                'total'			=> $quote->getGrandTotal() - ($quote->getSubtotal() - $quote->getSubtotalWithDiscount())
            ],
			'items'				=> array_map(function($item) {
                $image = Mage::getResourceModel('catalog/product')
                    ->getAttributeRawValue($item->getProduct()->getId(), 'image', Mage::app()->getStore()->getId());

                return [
                    'item_id'	   	  => $item->getId(),
                    'product_id'	  => $item->getProduct()->getId(),
                    'sku'			  => $item->getProduct()->getSku(),
                    'name'			  => $item->getProduct()->getName(),
                    'url'			  => $item->getProduct()->getUrlPath(),
                    'image'     	  => Mage::getModel('catalog/product_media_config')->getMediaUrl($image),
                    'price'			  => $item->getPrice(),
                    'discount_amount' => $item->getDiscountAmount(),
                    'qty'			  => $item->getQty(),
                    'total'			  => ($item->getRowTotal() - $item->getDiscountAmount()),
                    'options'         => $item->getProduct()->getTypeId() == Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE ? Mage::helper('catalog/product_configuration')->getConfigurableOptions($item) : Mage::helper('catalog/product_configuration')->getCustomOptions($item),
                ];
            }, $quote->getAllVisibleItems()),
        ]);
    }

}
