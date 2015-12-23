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
 * Vendor Account Controller
 *
 * @category    Sirateck
 * @package     Sirateck_Lemonwaymkt
 * @author Kassim Belghait kassim@sirateck.com
 */
require_once 'Mage/Customer/controllers/AccountController.php';
class Sirateck_Lemonwaymkt_AccountController extends Mage_Customer_AccountController {

	/**
	 * Action predispatch
	 *
	 * Check customer authentication for some actions
	 */
	public function preDispatch()
	{
		// a brute-force protection here would be nice
	
		parent::preDispatch();
	
		$action = $this->getRequest()->getActionName();
		$openActions = array(
				'index',
				'createWallet',
		);
		$pattern = '/^(' . implode('|', $openActions) . ')/i';
	
		if (!preg_match($pattern, $action)) {
			if (is_null($this->getCustomerWallet()->getId())) {
				$this->setFlag('', 'no-dispatch', true);
				throw $e;
			}
		} else {
			$this->_getSession()->setNoReferer(true);
		}
	}
	
	public function indexAction(){
		
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->renderLayout();
		
			return $this;
		
	}
	
	public function uploadDocAction(){
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->renderLayout();
		
		return $this;
	}
	
	public function createWalletAction(){
		
		
		$wallet = $this->getCustomerWallet();
		$this->_redirect('*/*/index');
		
		if($wallet->getId())
		{
			$this->_getSession()->addError($this->__('You already have a wallet!'));
			
		}
		else{
			$customer = $this->_getSession()->getCustomer();
			$userProfile = Mage::getModel('marketplace/userprofile')->load($customer->getId(),'mageuserid');
			
			if($userProfile->getId()){
				
				if((int)$userProfile->getWantpartner() == 1)
				{
					try {
							
						$this->_getHelper()->registerWallet($customer, $userProfile);
						$this->_getSession()->addSuccess($this->__('Wallet created.'));
						return $this;
					} catch (Exception $e) {
						$this->_getSession()->addError($e->getMessage());
						
					}
					
				}
				else{
					$this->_getSession()->addError($this->__("Your account is not validated!"));
				}
				
			}
			else{
				$this->_getSession()->addError($this->__('You are not a merchant partner!'));
			}

		}
		
	}
	
	public function uploadDocPostAction(){
		if($data = $this->getRequest()->getPost()){
			$input = 'document_file';
	        if (isset($_FILES[$input]['name']) && !empty($_FILES[$input]['name']) && !empty($_FILES[$input]['tmp_name'])) {

	           $content = file_get_contents($_FILES[$input]['tmp_name']);
	           
	           if(!Zend_Validate::is($data['doc_type'], 'NotEmpty')){
	           		$this->_getSession()->addError($this->__('Please select a document type'));
	           		$this->_redirect('*/*/uploadDoc');
	           }
	           else{
	           	
		           	$params = array(
		           			'wallet'=>$this->getCustomerWallet()->getWalletId(),
		           			'fileName'=>$_FILES[$input]['name'],
		           			'type'=>$data['doc_type'],
		           			'buffer'=>base64_encode($content),
		           	);
		           	

		           	$kit = Mage::getSingleton('sirateck_lemonway/apikit_kit');
		           		
		           	$res = $kit->UploadFile($params);
		           		
		           	if(isset($res->lwError))
		           	{
		           		$this->_getSession()->addError($res->lwError->getMessage());
		           		$this->_redirect('*/*/uploadDoc');
		           	}
		           	else
		           	{
			           	$this->_getSession()->addSuccess($this->__('Document uploaded.'));
			           	$this->_redirect('*/*');
		           	}
		           		
	           }
	          
	        }
	        else{
	        	$uploadErrors = array(
	        			0 => $this->__('There is no error, the file uploaded with success'),
	        			1 => $this->__('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
	        			2 => $this->__('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
	        			3 => $this->__('The uploaded file was only partially uploaded'),
	        			4 => $this->__('No file was uploaded'),
	        			6 => $this->__('Missing a temporary folder'),
	        			7 => $this->__('Failed to write file to disk.'),
	        			8 => $this->__('A PHP extension stopped the file upload.'),
	        	);
	        	$errorId = (int)$_FILES[$input]['error'];
	        	$this->_getSession()->addError($uploadErrors[$errorId]);
	        	$this->_redirect('*/*/uploadDoc');
	        }
	        
		}
		
		return $this;
	}
	
	public function addIbanAction(){
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->renderLayout();
		
		return $this;
	}
	
	public function ibanPostAction(){
		
		if($data = $this->getRequest()->getPost('iban_data'))
		{
			
			$wallet = $this->getCustomerWallet();
				
			if($wallet->getId()){
				/* @var $iban Sirateck_Lemonway_Model_Iban*/
				$iban = Mage::getModel('sirateck_lemonway/iban');
				$customer = $this->_getSession()->getCustomer();
				$data['customer_id'] = $customer->getId();
				$data['wallet_id'] = $wallet->getWalletId();
				
				$iban->setData($data);

				//@TODO validate datas
					try {
							
						$params = array(
								'wallet'	=>	$wallet->getWalletId(),
								'holder'	=> 	$iban->getHolder(),
								'iban'		=>	$iban->getIban(),
								'bic'		=>	$iban->getBic(),
								'dom1'		=>  $iban->getDom1(),
								'dom2'		=>	$iban->getDom2(),
						);
			
						$kit = Mage::getSingleton('sirateck_lemonway/apikit_kit');
			
						$res = $kit->RegisterIBAN($params);
			
						if(isset($res->lwError))
						{
							throw new Exception($res->lwError->getMessage(), (int)$res->lwError->getCode(),null);
						}
			
						$iban->setLwIbanId($res->iban->getIbanId());
						$iban->setStatusId($res->iban->getStatus());
			
						$iban->save();
			
						$this->_getSession()->addSuccess($this->__('Iban saved.'));
						$this->_redirect('*/*');
			
					} catch (Exception $e) {
			
						$this->_getSession()->addError($e->getMessage());
						$this->_redirect('*/*/addIban');
					}
		
			}
		}
		return $this;
	}
	
	public function doMoneyoutAction(){
		
		$this->loadLayout();
		$this->_initLayoutMessages('customer/session');
		$this->renderLayout();
		
		return $this;
		
	}
	
	public function doMoneyoutPostAction(){
		
		if($data = $this->getRequest()->getPost('moneyout_data')){
			
			$walletdetails = $this->getCustomerWalletDetails();
			if(!isset($walletdetails->lwError))
			{
				
				$bal = $walletdetails->wallet->getBal();
				$balFormated = Mage::helper("core")->formatPrice((float)$bal);
				$ibanId = $data['iban_id'];
				
				$ibanObj = Mage::getModel('sirateck_lemonway/iban')->load((int)$ibanId);
				if(!$ibanObj->getId())
				{
					$this->_getSession()->addError($this->__('Iban not found'));
					$this->_redirect("*/*/doMoneyout");
					return $this;
				}
				elseif ($ibanObj->getCustomerId() != $this->_getSession()->getCustomer()->getId()){
					$this->_getSession()->addError($this->__('Is not your Iban!'));
					$this->_redirect("*/*/doMoneyout");
					return $this;
				}
				
				$amountToPay = (float)str_replace(",", ".",$data['amount_to_pay']);
				$amountFormated = Mage::helper("core")->formatPrice((float)$amountToPay);
				
				if($amountToPay > $bal)
				{
					$this->_getSession()->addError($this->__("You can't paid amount upper of your balance amount: %s",$balFormated));
					$this->_redirect("*/*/doMoneyout");
					return $this;
				}
				
				if($ibanObj->getId() && $amountToPay > 0 && $bal > 0)
				{
					$walletId = (string)$walletdetails->wallet->getWalletId();
					try {
							$params = array(
									"wallet"=>$walletId,
									"amountTot"=>sprintf("%.2f" ,$amountToPay),
									"amountCom"=>sprintf("%.2f" ,(float)str_replace(",",".",Mage::getStoreConfig('sirateck_lemonway/lemonwaymkt/commission_amount'))),
									"message"=>$this->__("Moneyout from Magento module by cutomer %s",$this->_getSession()->getCustomer()->getName()),
									"ibanId"=>$ibanObj->getLwIbanId(),
									"autoCommission" => Mage::getStoreConfig('sirateck_lemonway/lemonwaymkt/autocommission'),
							);
							
							//Init APi kit
							/* @var $kit Sirateck_Lemonway_Model_Apikit_Kit */
							$kit = Mage::getSingleton('sirateck_lemonway/apikit_kit');
							
							$apiResponse = $kit->MoneyOut($params);
							
							if($apiResponse->lwError)
								Mage::throwException($apiResponse->lwError->getMessage());
						
							if(count($apiResponse->operations))
							{
								/* @var $op Sirateck_Lemonway_Model_Apikit_Apimodels_Operation */
								$op = $apiResponse->operations[0];
								if($op->getHpayId())
								{
									$moneyout = Mage::getModel('sirateck_lemonway/moneyout');
									
									$moneyout->setWalletId($walletId);
									$moneyout->setCustomerId($this->_getSession()->getCustomer()->getId());
									$moneyout->setIsAdmin(0);
									$moneyout->setlwIbanId($ibanObj->getLwIbanId());
									$moneyout->setPrevBal($bal);
									$moneyout->setNewBal($bal - $amountToPay);
									$moneyout->setIban($ibanObj->getIban());
									$moneyout->setAmountToPay($amountToPay);
									
									$moneyout->save();
					
									$this->_getSession()->addSuccess($this->__("You paid %s to your Iban %s from your wallet <b>%s</b>",$amountFormated,$ibanObj->getIban(),$walletId));
								}
								else {
									Mage::throwException($this->__("An error occurred. Please contact support."));
								}
							}
					
					
					} catch (Exception $e) {
					
						$this->_getSession()->addError($e->getMessage());
						$this->_redirect('*/*/doMoneyout');
						return $this;
					
					}
				}
				
			}
			else{
				$this->_getSession()->addError($walletdetails->lwError->getMessage());
				$this->_redirect('*/*/doMoneyout');
				return $this;
			}
			
		}
		
		$this->_redirect('*/*/index');
		
	}
	
	protected function getCustomerWallet(){
		$customer = $this->_getSession()->getCustomer();
		return Mage::getModel('sirateck_lemonway/wallet')->load($customer->getId(),'customer_id');
	}
	
	/**
	 * @return Sirateck_Lemonway_Model_Apikit_Apimodels_Wallet
	 */
	protected function getCustomerWalletDetails(){
		$customer = $this->_getSession()->getCustomer();
		$wallet = $this->_getHelper()->getWalletDetails($customer->getId());
		
		return $wallet;
	}
	
	/**
	 * @return Sirateck_Lemonwaymkt_Helper_Data
	 */
	protected function _getHelper(){
		return Mage::helper('lemonwaymkt');
	}
		
	
	
}