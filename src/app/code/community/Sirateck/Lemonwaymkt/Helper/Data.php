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
			if(isset($res->lwError) && (int)$res->lwError->CODE != 152)
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
}
