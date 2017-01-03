<?php
/**
 * Sirateck_Lemonwaymkt extension
 * 
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/mit-license.php
 * 
 * @category       Sirateck
 * @package        Sirateck_Lemonwaymkt
 * @copyright      Copyright (c) 2015
 * @license        http://opensource.org/licenses/mit-license.php MIT License
 */
/**
 * Lemonwaymkt default helper
 *
 * @category    Sirateck
 * @package     Sirateck_Lemonwaymkt
 * @author Kassim Belghait kassim@sirateck.com
 */
class Sirateck_Lemonwaymkt_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * 
     * @param Mage_Customer_Model_Customer $customer
     * @param Webkul_Marketplace_Model_Userprofile $userinfo
     * @throws Exception
     * @return Sirateck_Lemonway_Model_Wallet
     */
    public function registerWallet($customer,$userinfo){
        
        
        $wallet = Mage::getModel('sirateck_lemonway/wallet');
        $wallet->setIsAdmin(false);
        $wallet->setIsDefault(true);
        $wallet->setCustomerId($customer->getId());
        
        //Init APi kit
        /* @var $kit Sirateck_Lemonway_Model_Apikit_Kit */
        $kit = Mage::getSingleton('sirateck_lemonway/apikit_kit');

        $params = array();
        
        $params['wallet'] = "wallet-" .$userinfo->getAutoid(). "-" . $customer->getId();
        $wallet->setWalletId($params['wallet']);
        
        $params['clientMail'] = $customer->getEmail();
        $wallet->setCustomerEmail($params['clientMail']);

        //@TODO add prefix

        $params['clientFirstName'] = $customer->getFirstname();
        $wallet->setCustomerFirstname($params['clientFirstName']);
        
        $params['clientLastName'] = $customer->getLastname();
        $wallet->setCustomerLastname($params['clientLastName']);
        
        $params['street'] = ''; //@TODO 
        $params['postCode'] = '';//@TODO
        $params['city'] = '';//@TODO
        $params['ctry'] = '';//@TODO
        
        $params['birthdate'] = '';//@TODO
        
        $params['phoneNumber'] = '';//@TODO
        
        $params['mobileNumber'] = '';//@TODO
        
        $params['isCompany'] = 1;
        $wallet->setIsCompany(1);
        
        $params['companyName'] = $userinfo->getShoptitle();;
        $wallet->setCompanyName( $params['companyName'] );
        
        $params['companyWebsite'] = '';//@TODO
        
        //$params['companyDescription'] = '';//@TODO
        
        if($customer->getTaxvat())
        {       
            $params['companyIdentificationNumber'] = $customer->getTaxvat();
            $wallet->setCompanyIdNumber($params['companyIdentificationNumber']);
        }
        
        $params['isDebtor'] = 0;
        $wallet->setIsDebtor($params['isDebtor'] );
        
        /*$params['nationality'] = '';
        $params['birthcity'] = '';
        $params['birthcountry'] = '';*/
        $params['payerOrBeneficiary'] = 2; //1 for payer, 2 for beneficiary
        $wallet->setPayerOrBeneficiary( $params['payerOrBeneficiary'] );
        
        $params['isOneTimeCustomer'] = 0;
        $wallet->setIsOnetimeCustomer( $params['isOneTimeCustomer'] );
    
        try {
            
            $res = $kit->RegisterWallet($params);
            if(isset($res->lwError) && (int)$res->lwError->getCode() != 152)
            {
                throw new Exception($res->lwError->getMessage(), (int)$res->lwError->getCode(),null);
            }
            elseif(!isset($res->lwError)){
                $wallet->setStatus($res->wallet->getStatus());
                
            }
            
            $wallet->save();

        } catch (Exception $e) {
            
            throw $e;
                
        }
        
        return $wallet;
    }
    
    /**
     * @return Sirateck_Lemonway_Model_Apikit_Apiresponse
     */
    public function getWalletDetails($customer_id){

            $wallet = Mage::getModel('sirateck_lemonway/wallet')->load($customer_id,'customer_id');
    
            if(!$wallet->getId())
                return null;
                    
            $params = array("wallet"=>$wallet->getWalletId());

            return Mage::getSingleton('sirateck_lemonway/apikit_kit')->GetWalletDetails($params);
    }
    
    /**
     * 
     * @param Mage_Sales_Model_Order $order
     */
    public function getOrderCommissionDetails($order){
        
        /*
         * Get Global Commission Rate for Admin
         */
        $percent = Mage::helper('marketplace')->getConfigCommissionRate();
        
        /*
         * Get Current Store Currency Rate
         */
        $currentCurrencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();
        $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        $allowedCurrencies = Mage::getModel('directory/currency')->getConfigAllowCurrencies();
        $rates = Mage::getModel('directory/currency')->getCurrencyRates($baseCurrencyCode, array_values($allowedCurrencies));
        if(!$rates[$currentCurrencyCode]){
            $rates[$currentCurrencyCode] = 1;
        }
        
        /*
         * Marketplace Credit Management module Observer
         */
        Mage::dispatchEvent('mp_discount_manager',array("order"=>$order));
        /*
         * Marketplace Credit discount data
         */
        $discountDetails = array();
        $discountDetails = Mage::getSingleton('core/session')->getData('salelistdata');
        
        Mage::dispatchEvent('mp_advance_commission_rule',array("order"=>$order));
        $advanceCommissionRule = Mage::getSingleton('core/session')->getData('advancecommissionrule');
        
        
        $total_seller_amt = 0;
        $total_commision = 0;
        foreach ($order->getAllItems() as $item){
            $item_data = $item->getData();
            $attrselection = unserialize($item_data['product_options']);
            $bundle_selection_attributes = array();
            if(isset($attrselection['bundle_selection_attributes'])){
                $bundle_selection_attributes = unserialize($attrselection['bundle_selection_attributes']);
            }else{
                $bundle_selection_attributes['option_id']=0;
            }
            if(!$bundle_selection_attributes['option_id']){
                $temp=$item->getProductOptions();
                if (array_key_exists('seller_id', $temp['info_buyRequest'])) {
                    $seller_id= $temp['info_buyRequest']['seller_id'];
                }
                else {
                    $seller_id='';
                }
                if($discountDetails[$item->getProductId()])
                    $price = $discountDetails[$item->getProductId()]['price']/$rates[$currentCurrencyCode];
                    else
                        $price = $item->getPrice()/$rates[$currentCurrencyCode];
                        if($seller_id==''){
                            $collection_product = Mage::getModel('marketplace/product')->getCollection();
                            $collection_product->addFieldToFilter('mageproductid',array('eq'=>$item->getProductId()));
                            foreach($collection_product as $selid){
                                $seller_id=$selid->getuserid();
                            }
                        }
                        if($seller_id==''){$seller_id=0;}
                        $collection1 = Mage::getModel('marketplace/saleperpartner')->getCollection();
                        $collection1->addFieldToFilter('mageuserid',array('eq'=>$seller_id));
                        $taxamount=$item_data['tax_amount'];
                        $qty=$item->getQtyOrdered();
                        $totalamount=$qty*$price;
        
                        if(count($collection1)!=0){
                            foreach($collection1 as $rowdatasale) {
                                $commision=($totalamount*$rowdatasale->getcommision())/100;
                            }
                        }
                        else{
                            $commision=($totalamount*$percent)/100;
                        }
        
                        if(!Mage::helper('marketplace')->getUseCommissionRule()) {
                            $wholedata['id'] = $item->getProductId();
                            Mage::dispatchEvent('mp_advance_commission', $wholedata);
                            $advancecommission = Mage::getSingleton('core/session')->getData('commission');
                            if($advancecommission!=''){
                                $percent=$advancecommission;
                                $commType = Mage::helper('marketplace')->getCommissionType();
                                if($commType=='fixed')
                                {
                                    $commision=$percent;
                                }
                                else
                                {
                                    $commision=($totalamount*$advancecommission)/100;
                                }
                                if($commision>$totalamount){ $commision= $totalamount*Mage::helper('marketplace')->getConfigCommissionRate()/100; }
                            }
                        } else {
                            if(count($advanceCommissionRule)) {
                                if($advanceCommissionRule[$item->getId()]['type'] == 'fixed') {
                                    $commision = $advanceCommissionRule[$item->getId()]['amount'];
                                } else {
                                    $commision = ($totalamount * $advanceCommissionRule[$item->getId()]['amount']) / 100;
                                }
                            }
                        }

                        $actparterprocost=$totalamount-$commision;

                        if (Mage::helper('marketplace')->getConfigTaxMange()) {
                            $actparterprocost += $taxamount;
                        }

                        $total_seller_amt += $actparterprocost;
                        $total_commision += $commision;
            }
            
        
        }
        return new Varien_Object(array('total_seller_amount'=>$total_seller_amt,'total_commision'=>$total_commision));
    }
}
