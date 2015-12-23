<?php
class Sirateck_Lemonwaymkt_Block_Account extends Mage_Core_Block_Template{
	
	protected $_walletDetails = null;
	
	protected $_ibans = null;
	
	protected $_moneyouts = null;
	
	protected $_wallet = null;
	
	/**
	 * @return Sirateck_Lemonway_Model_Apikit_Apiresponse
	 */
	public function getWalletDetails(){
		if(is_null($this->_walletDetails))
		{
			$this->_walletDetails = $this->_getHelper()->getWalletDetails($this->getCustomer()->getId());
		}
	
		return $this->_walletDetails;
	
	}
	
	public function getCustomerWallet(){
		if(is_null($this->_wallet)){
			
			$this->_wallet = Mage::getModel('sirateck_lemonway/wallet')->load($this->getCustomer()->getId(),'customer_id');
		}
		
		return $this->_wallet;
		
	}
	
	/**
	 * @return Sirateck_Lemonwaymkt_Helper_Data
	 */
	protected function _getHelper(){
		return $this->helper('lemonwaymkt');
	}
	
	/**
	 * @return Mage_Customer_Model_Session
	 */
	protected function _getCustomerSession(){
		return Mage::getSingleton('customer/session');
	}
	
	/**
	 * @return Mage_Customer_Model_Customer
	 */
	protected function getCustomer(){
		return $this->_getCustomerSession()->getCustomer();
	}
	
	/**
	 * Check if main wallet is configured and if Iban is present
	 * @TODO check validation (wallet exist and have a positive balance,iban exist and status is ok)
	 * @return bool
	 */
	public function canPayMoneyOut()
	{
		return $this->hasWallet() && count($this->getWalletDetails()->wallet->getIbans());
	}
	
	public function hasWallet(){
		return !is_null($this->getCustomerWallet()->getId());
	}
	
	public function formatPrice($price){
	
		return Mage::helper('core')->formatPrice($price);
	}
	
	public function getIbans(){
		
		if(is_null($this->_ibans))
		{
			$this->_ibans = Mage::getModel('sirateck_lemonway/iban')->getCollection()
							->addFieldToFilter('customer_id',$this->getCustomer()->getId())
							->setPageSize(5);
		}
		
		return $this->_ibans;
		
	}
	
	public function getMoneyouts(){
		
		if(is_null($this->_moneyouts)){
			
			$this->_moneyouts = Mage::getModel('sirateck_lemonway/moneyout')->getCollection()
										->addFieldToFilter('customer_id',$this->getCustomer()->getId())
										->setPageSize(5)
										->setOrder('created_at', 'desc');
			
		}
		
		return $this->_moneyouts;
		
	}
	
	public function getWalletStatusLabel($status_id){
		$status_id = (int)$status_id;
		$statuesLabel = Sirateck_Lemonway_Model_Wallet::$statuesLabel;
		if(isset($statuesLabel[$status_id]))
			return $this->__($statuesLabel[$status_id]);
		return $this->__("N/A");
	}
	
	public function getDocumentsType(){
		return Sirateck_Lemonway_Model_Wallet::$docsType;
	}
	
	
	
	
}