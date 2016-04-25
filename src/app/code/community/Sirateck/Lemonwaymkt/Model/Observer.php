<?php
class Sirateck_Lemonwaymkt_Model_Observer{
	
	public function createWallet($observer){
		/* @var $userprofile Webkul_Marketplace_Model_Userprofile */
		$userprofile = $observer->getObject();

		if(!($userprofile instanceof Webkul_Marketplace_Model_Userprofile))
			return $this;
		
			if(!Mage::getStoreConfigFlag('sirateck_lemonway/lemonwaymkt/create_wallet'))
				return $this;
		
		//Check if customer already have a wallet
		$wallet = Mage::getModel('sirateck_lemonway/wallet')->load($userprofile->getMageuserid(),'customer_id');
		if($wallet->getId())
			return $this;
		
		if((int)$userprofile->getWantpartner() == 1)
		{
			$customer = Mage::getModel('customer/customer')->load($userprofile->getMageuserid());
			
			if($customer->getId()){
				try {
					
					$this->getHelper()->registerWallet($customer, $userprofile);
				} catch (Exception $e) {
					Mage::logException($e);
				}
			}
		}
		
	}
	
	
	public function sendPaymentToWallet($observer) {
		
		/* @var $saleslist Webkul_Marketplace_Model_Saleslist */
		$saleslist = $observer->getObject();
		
		if(!($saleslist instanceof Webkul_Marketplace_Model_Saleslist))
			return $this;
		
		if((int)$saleslist->getData('paidstatus') == 0)
			return $this;
			
		
		//Check if the new state is paid
		if((int)$saleslist->getOrigData('paidstatus') == 0 && (int)$saleslist->getData('paidstatus') == 1 &&
				(int)$saleslist->getOrigData('transid') == 0 && (int)$saleslist->getData('transid') > 0)
		{
			
			
			/* @var $order Mage_Sales_Model_Order */
			$order = Mage::getModel('sales/order')->load((int)$saleslist->getMageorderid());
			if(!$order->getId())
				return $this;
			
			
			if($order->getPayment()->getMethod() != 'lemonway_webkit')
				return $this;
			
			//Check if customer already have a wallet
			$wallet = Mage::getModel('sirateck_lemonway/wallet')->load($saleslist->getMageproownerid(),'customer_id');
			if(!$wallet->getId())
				return $this;
			
			
			$params = array(
					"debitWallet"	=> Mage::getSingleton('sirateck_lemonway/config')->getWalletMerchantId(),
					"creditWallet"	=> $wallet->getWalletId(),
					"amount"		=> number_format((float)$saleslist->getActualparterprocost(), 2, '.', ''),
					"message"		=> Mage::helper('lemonwaymkt')->__('Send payment for product %s in order %s',$saleslist->getMageproname(),$order->getIncrementId()),
					//"scheduledDate" => "",
					//"privateData"	=> "",
			);
		
			//Init APi kit
	    	/* @var $kit Sirateck_Lemonway_Model_Apikit_Kit */
	    	$kit = Mage::getSingleton('sirateck_lemonway/apikit_kit');
		
			try {
				$res = $kit->SendPayment($params);
				
				if(isset($res->lwError))
				{
					throw new Exception($res->lwError->getMessage(), (int)$res->lwError->getCode(),null);
				}
				
				if(count($res->operations))
				{
					/* @var $op Sirateck_Lemonway_Model_Apikit_Apimodels_Operation */
					$op = $res->operations[0];
				
					if($op->getHpayId())
					{
						//change transaction informations;
						$transaction = Mage::getModel('marketplace/sellertransaction')->load($saleslist->getTransid(),'transid');
						
						if($transaction->getTransid())
						{
							$transaction->setType('Manual');
							$transaction->setMethod('Lemonway');
							$transaction->save();
							
							$params = array(
									"debitWallet"	=> Mage::getSingleton('sirateck_lemonway/config')->getWalletMerchantId(),
									"creditWallet"	=> "SC",
									"amount"		=> number_format((float)$saleslist->getTotalcommision(), 2, '.', ''),
									"message"		=> Mage::helper('lemonwaymkt')->__('Send payment commision for order %s',$order->getIncrementId()),
							);
							
							$res = $kit->SendPayment($params);
							
							if(isset($res->lwError))
							{
								throw new Exception($res->lwError->getMessage(), (int)$res->lwError->getCode(),null);
							}
							
						}
							
					}
					else {
						Mage::throwException($this->__("An error occurred. Please contact support."));
					}
				}
				 
				} catch (Exception $e) {
					 
					Mage::logException($e);
					 
				}
				 
		}
	}
	/**
	 * 
	 * @return Sirateck_Lemonwaymkt_Helper_Data
	 */
	protected function getHelper(){
		return Mage::helper('lemonwaymkt');
	}
}