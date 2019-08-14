
<?php

class Kancart_MobileApi_Model_Language extends Kancart_MobileApi_Model_Abstract {

    public function getLanguages($param) {
        $languages = array();
        $stores = Mage::app()->getStores(false, true);
        $order = 0;
        foreach($stores as $store){
            $locale = Mage::getStoreConfig('general/locale/code',$store->getCode());
            $languages[] = array('language_id'   => $store->getCode(),
                                 'language_code' => $locale ,
                                 'language_name' => $store->getName(),
                                 'sort_order'    => $order++);
        }
        $result = Mage::getModel('mobileapi/Result');
        $result->setResult('0x0000', array('languages' => $languages));
        return $result->returnResult();
    }
}
?>
