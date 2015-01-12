<?php
#------------------------------
#------ PHP CLIENT FOR TAKASCOIN PAYMENT API ----
#------------------------------

class Takascoin {
	const MINAMOUNT = 0.0005;

	#----------------------------------------------------------------
	# Create new payment
	# Required : $amount		# Billed amount
	# Required : $apiKey		# Merchant ApiKey (merchant email)
	# Optional : $options 	# Payment options : currency # Billed currency - defaults to "TRY"
	#											orderID,
	#                                           secret, 
	#                                           callback,
	#                                           item,
	#                                           description,
	#                                           minconf
	# Returns   : JSON object
	#----------------------------------------------------------------
	public function payment($amount, $apiKey='', $options=array()) 
	{
		if (floatval($amount) < self::MINAMOUNT)
        	return $this->error('Amount cannot be less than ' . self::MINAMOUNT);

		if (!$this->validEmail($apiKey)) {
		 	return $this->error('Invalid apiKey');  		
		}

		$params = array_merge($options, array( 'amount'   => $amount,
		                                       'apiKey'   => $apiKey));

		try {
			$res = $this->apiRequest('/api/takas/payment', $params);
		} catch (Exception $e) {
			return $this->error('An error occured: '.$e->getMessage());
		}

		return $res;
	}
	
	#----------------------------------------------------------------
	# Create new payment template to use in client side
	# Required : $amount		# Billed amount
	# Required : $apiKey		# Merchant ApiKey (merchant email)
	# Optional : $options 	# Payment options : currency # Billed currency - defaults to "TRY"
	#											orderID,
	#                                           secret, 
	#                                           callback,
	#                                           item,
	#                                           description,
	#                                           minconf
	# Returns   : JSON object
	#----------------------------------------------------------------
	public function button($amount, $apiKey='', $options=array())
	{

		if (floatval($amount) < self::MINAMOUNT)
        	return $this->error('Amount cannot be less than ' . self::MINAMOUNT);

		if (!$this->validEmail($apiKey)) {
		 	return $this->error('Invalid apiKey');  		
		}

		$params = array_merge($options, array('amount'   => $amount,
		                                      'apiKey'   => $apiKey));

		try {
			$res = $this->apiRequest('/api/takas/button', $params);
		} catch (Exception $e) {
			return $this->error("An error occured: ".$e->getMessage());
		}

		return $res;
	}

	#----------------------------------------------
	# Validates received payment notification (IPN)
	# Required : $hash      # provided by IPN call
	# Required : $orderID   # provided by IPN call
	# Required : $invoiceID # provided by IPN call
	# Required : $secret    # secret used while creating payment
	# Returns  : True/False
	#----------------------------------------------
	public function validateNotification($hash, $orderID, $invoiceID, $secret)
	{
		try {
			return $hash == hash_hmac('sha256', $orderID.":".$invoiceID, $secret, FALSE);
		} catch (Exception $e) {
			return false;
		}
	}


	#---------------------------------------------------
	# Required : $invoiceID
	# Returns  : JSON object
	#---------------------------------------------------
	public function status($invoiceID)
	{
		try {
			if($invoiceID) {
				$res = $this->apiRequest('/api/status', array("invoiceID" => $invoiceID));
				return $res;
			} else {
				return $this->error("Please supply an invoice id");
			}
		} catch (Exception $e) {
			return $this->error("An error occured: ".$e->getMessage());
		}
	}


	#---------------------------------------------------
	# Required : $invoiceID
	# Returns  : JSON object
	#---------------------------------------------------
	public function invoice($invoiceID)
	{
		try {
			if($invoiceID) {
				$res = $this->apiRequest('/api/invoice', array("invoiceID" => $invoiceID));
				return $res;
			} else {
				return $this->error("Please supply an invoice id");
			}
		} catch (Exception $e) {
			return $this->error("An error occured: ".$e->getMessage());
		}
	}


	#---------------------------------------
	# Basic request
	#---------------------------------------
	private function apiRequest($url, $postArray=array())
	{
		# Filter false elements
		foreach ($postArray as $var => $value) {
			if ($value === false) {
				unset($postArray[$var]);
			}
		}
		# Fill post string
		$postString = json_encode($postArray);

		$url = "https://coinvoy.net" . $url;
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		# Get result object
		$httpres = curl_exec($ch);
		# Close curl
		curl_close ($ch);

		try {
			$res = json_decode($httpres, true);
		} catch (Exception $e) {
			$res = $httpres;
		}

		return $res;
	}

	private function error($message = "") {
		$res = new stdClass;
		$res->success = false;
		$res->error   = $message;
		return $res;
	}
	
	private function validEmail($email) {
		return strlen($email) > 3 
		   && preg_match('/^[^\W][a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*@[a-zA-Z0-9_]+(\.[a-zA-Z0-9_]+)*\.[a-zA-Z]{2,4}$/', $email);
	}
}
