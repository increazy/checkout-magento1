<?php
class Increazy_Checkout_Model_AbstractMethod extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'increazy_checkout';
    protected $_infoBlockType = 'increazy_checkout/info';

    public function assignData($data)
    {
        $data['method'] = $this->getConfigData('title');
        $this->getInfoInstance()->setAdditionalInformation($data->toArray());
    }

}