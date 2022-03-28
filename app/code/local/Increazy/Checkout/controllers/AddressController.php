<?php
class Increazy_Checkout_AddressController extends Mage_Core_Controller_Front_Action
{
    public function allAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            return $this->getCustomerAddresses($body);
        }, function ($body) {
            return isset($body['token']);
        });
    }

    public function createAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customerID = Mage::helper('increazy_checkout')->hashDecode($body['token']);
            $region = Mage::getModel('directory/region')->loadByName($body['state'], 'BR');

            $address = Mage::getModel('customer/address');
            $address->setCustomerId($customerID)
                ->setRegionId($region->getId())
                ->setFirstname($body['firstname'])
                ->setLastname($body['lastname'])
                ->setPostcode(str_replace('-', '', trim($body['postcode'])))
                ->setCity($body['city'])
                ->setTelephone($body['telephone'])
                ->setCompany(isset($body['company']) ? $body['company'] : '')
                ->setStreet($body['street'])
                ->setCountryId('BR')
                ->setIsDefaultBilling('1')
                ->setIsDefaultShipping('1')
           ->setSaveInAddressBook('1');

            $address->save();

            return $this->getCustomerAddresses($body);
        }, function ($body) {
            return isset($body['state']) && isset($body['firstname']) &&
            isset($body['lastname']) && isset($body['postcode']) &&
            isset($body['street']) && isset($body['city']) &&
            isset($body['telephone']) && isset($body['token']);
        });
    }

    public function removeAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $customerID = Mage::helper('increazy_checkout')->hashDecode($body['token']);

            $address = Mage::getModel('customer/address')->load($body['address_id']);
            if ($address->getCustomerId() == $customerID) {
                $address->delete();
            }

            return $this->getCustomerAddresses($body);
        }, function ($body) {
            return isset($body['address_id']) && isset($body['token']);
        });
    }

    public function getFreightAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $quote = Mage::getModel('sales/quote')->load($body['quote_id']);
            $customerID = Mage::helper('increazy_checkout')->hashDecode($body['token']);

            $address = Mage::getModel('customer/address')->load($body['address_id']);
            if ($address->getCustomerId() != $customerID) {
                throw new Exception('Address not found');
            }

            $address->setIsDefaultBilling('1');
            $address->setIsDefaultShipping('1');
            $address->setSaveInAddressBook('1');
            $address->save();

            $quoteShippingAddress = new Mage_Sales_Model_Quote_Address();
			$quoteShippingAddress->setData($address->toArray());
			// $quoteShippingAddress->setQuoteId($quote->getId());
            // $quoteShippingAddress->save();

            $quote->setBillingAddress($quoteShippingAddress);
			$quote->setShippingAddress($quoteShippingAddress);
			$quote->save();

            $address = $quote->getShippingAddress()->setCollectShippingRates(true);
			$quote->collectTotals()->save();

            $rates = [];
			foreach($address->getGroupedAllShippingRates() as $carrier) {
				foreach($carrier as $method) {
					$rates[] = [
						'code'    => $method->getCode(),
						'carrier' => $method->getCarrierTitle(),
						'method'  => $method->getMethodTitle(),
						'error'   => $method->getErrorMessage(),
						'price'   => $method->getPrice(),
						'order'   => $method->getRateId()
					];
				}
			}

            return $rates;
        }, function ($body) {
            return isset($body['quote_id']) && isset($body['address_id']);
        });
    }

    private function getCustomerAddresses($body)
    {
        $customerID = Mage::helper('increazy_checkout')->hashDecode($body['token']);
        $customer = Mage::getModel('customer/customer')->load($customerID);

        $result = [];
        foreach($customer->getAddresses() as $address) {
            $address = $address->toArray();
            $regionId = $address['region_id'];

            $regionModel = Mage::getModel('directory/region')->load($regionId);
            $address['region'] = $regionModel->getName();
            $result[] = $address;
        }

        return $result;
    }
}