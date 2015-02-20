<?php

abstract class bdPaygateStripe_Processor_Common extends bdPaygate_Processor_Abstract
{
	public function getSupportedCurrencies()
	{
		return array(
			bdPaygate_Processor_Abstract::CURRENCY_USD,
			bdPaygate_Processor_Abstract::CURRENCY_CAD,
			bdPaygate_Processor_Abstract::CURRENCY_AUD,
			bdPaygate_Processor_Abstract::CURRENCY_GBP,
			bdPaygate_Processor_Abstract::CURRENCY_EUR,
		);
	}

	public function isRecurringSupported()
	{
		return true;
	}

	public function isAvailable()
	{
		$secretKey = $this->_getStripeSecretKey();
		$publicKey = $this->_getStripePublicKey();

		return !empty($secretKey) AND !empty($publicKey);
	}

	public function validateCallback(Zend_Controller_Request_Http $request, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId)
	{
		$amount = false;
		$currency = false;

		return $this->validateCallback2($request, $transactionId, $paymentStatus, $transactionDetails, $itemId, $amount, $currency);
	}

	public function validateCallback2(Zend_Controller_Request_Http $request, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId, &$amount, &$currency)
	{
		$input = new XenForo_Input($request);
		$filtered = $input->filter(array(
			'itemId' => XenForo_Input::STRING,
			'cents' => XenForo_Input::UINT,
			'currency' => XenForo_Input::STRING,

			'success' => XenForo_Input::UINT,
			'nop' => XenForo_Input::UINT,
			'charge_id' => XenForo_Input::STRING,
			'error' => XenForo_Input::STRING,
			'signature' => XenForo_Input::STRING,
		));

		if (!empty($filtered['signature']))
		{
			// user was redirected here from misc/stripe
			// let's validate the signature first
			if (!bdPaygateStripe_Helper_Url::verifySignature())
			{
				$this->_setError('Signature is invalid');
				return false;
			}

			$transactionId = (!empty($filtered['charge_id']) ? ('stripe_' . $filtered['charge_id']) : '');
			$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_OTHER;
			$transactionDetails = $filtered;
			$itemId = $filtered['itemId'];
			$currency = $filtered['currency'];
			$amount = bdPaygateStripe_Helper_Api::getAmountFromCent($filtered['cents'], $currency);

			if (!empty($filtered['success']))
			{
				$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_ACCEPTED;
			}
			elseif (!empty($filtered['error']))
			{
				$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_REJECTED;
			}
			elseif (!empty($filtered['nop']))
			{
				// try to redirect asap and avoid logging non-sense paygate log entries
				$redirected = $this->redirectOnCallback($request, $paymentStatus, 'NOP');
				if ($redirected)
				{
					die();
				}
			}

			return true;
		}
		else
		{
			$jsonRaw = file_get_contents('php://input');
			if (empty($jsonRaw))
			{
				$this->_setError('Request not recognized');
				return false;
			}

			$json = @json_decode($jsonRaw, true);
			if (empty($json) OR empty($json['type']))
			{
				$this->_setError('Unable to parse JSON');
				return false;
			}

			switch ($json['type'])
			{
				case 'charge.refunded':
					return $this->_validateChargeRefunded($json, $transactionId, $paymentStatus, $transactionDetails, $itemId, $amount, $currency);
				case 'invoice.payment_succeeded':
					return $this->_validateInvoicePaymentSucceeded($json, $transactionId, $paymentStatus, $transactionDetails, $itemId, $amount, $currency);
				default:
					// stop processing to prevent logging too many paygate log entries
					die('NOP');
			}
		}
	}

	public function redirectOnCallback(Zend_Controller_Request_Http $request, $paymentStatus, $processMessage)
	{
		$input = new XenForo_Input($request);
		$returnUrl = $input->filterSingle('returnUrl', XenForo_Input::STRING);

		if (!empty($returnUrl))
		{
			header('Location: ' . $returnUrl);
			return true;
		}

		return parent::redirectOnCallback($request, $paymentStatus, $processMessage);
	}

	protected function _getStripeSecretKey()
	{
		$secretKey = XenForo_Application::getOptions()->get('bdPaygateStripe_secretKey');
		$isTestKey = strpos($secretKey, 'sk_test_') === 0;

		if ($isTestKey === $this->_sandboxMode())
		{
			return $secretKey;
		}

		return '';
	}

	protected function _getStripePublicKey()
	{
		$publicKey = XenForo_Application::getOptions()->get('bdPaygateStripe_publicKey');
		$isTestKey = strpos($publicKey, 'pk_test_') === 0;

		if ($isTestKey === $this->_sandboxMode())
		{
			return $publicKey;
		}

		return '';
	}

	protected function _getStripeBitcoin()
	{
		$bitcoin = XenForo_Application::getOptions()->get('bdPaygateStripe_zbitcoin');

		if (!empty($bitcoin))
		{
			return 'true';
		}
		else
		{
			return 'false';
		}
	}

	protected function _validateChargeRefunded(array $json, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId, &$amount, &$currency)
	{
		if (empty($json['data']['object']['id']))
		{
			$this->_setError('Unable to extract charge data');
			return false;
		}

		$charge = bdPaygateStripe_Helper_Api::getCharge($json['data']['object']['id']);
		if ($charge instanceof Stripe_Error)
		{
			$this->_setError('Unable to fetch charge from Stripe');
			return false;
		}
		if (!$charge->refunded)
		{
			$this->_setError('Stripe reports charge has not been refunded');
			return false;
		}

		if (!empty($charge->metadata['itemId']))
		{
			$itemId = $charge->metadata['itemId'];
		}
		elseif (!empty($charge->customer))
		{
			$customer = bdPaygateStripe_Helper_Api::getCustomer($charge->customer);
			if ($customer instanceof Stripe_Customer)
			{
				if (!empty($customer->metadata['itemId']))
				{
					$itemId = $customer->metadata['itemId'];
				}
			}
		}

		$transactionId = 'stripe_refunded_' . $charge->id;
		$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_REJECTED;
		$transactionDetails = $json['data']['object'];
		$amount = bdPaygateStripe_Helper_Api::getAmountFromCent($charge->amount, $charge->currency);
		$currency = $charge->currency;
		$transactionDetails[bdPaygate_Processor_Abstract::TRANSACTION_DETAILS_PARENT_TID] = 'stripe_' . $charge->id;

		return true;
	}

	protected function _validateInvoicePaymentSucceeded(array $json, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId, &$amount, &$currency)
	{
		if (empty($json['data']['object']['id']))
		{
			$this->_setError('Unable to extract invoice data');
			return false;
		}

		$invoice = bdPaygateStripe_Helper_Api::getInvoice($json['data']['object']['id']);
		if ($invoice instanceof Stripe_Error)
		{
			$this->_setError('Unable to fetch invoice from Stripe');
			return false;
		}
		if (!$invoice->paid)
		{
			$this->_setError('Stripe reports invoice has not been paid');
			return false;
		}

		if (empty($invoice->charge))
		{
			$this->_setError('Unable to extract charge info');
			return false;
		}

		if (empty($invoice->customer))
		{
			$this->_setError('Unable to extract customer info');
			return false;
		}
		$customer = bdPaygateStripe_Helper_Api::getCustomer($invoice->customer);
		if ($customer instanceof Stripe_Error)
		{
			$this->_setError('Unable to fetch customer from Stripe');
			return false;
		}
		if (empty($customer->metadata) OR empty($customer->metadata['itemId']))
		{
			$this->_setError('Unable to extract item info');
			return false;
		}

		if (empty($invoice->subscription))
		{
			$this->_setError('Unable to extract subscription info');
			return false;
		}

		$transactionId = 'stripe_' . $invoice->charge;
		$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_ACCEPTED;
		$transactionDetails = $json['data']['object'];
		$itemId = $customer->metadata['itemId'];
		$amount = bdPaygateStripe_Helper_Api::getAmountFromCent($invoice->subtotal, $invoice->currency);
		$currency = $invoice->currency;
		$transactionDetails[bdPaygate_Processor_Abstract::TRANSACTION_DETAILS_SUBSCRIPTION_ID] = bdPaygateStripe_Helper_Sub::doPack($invoice->customer, $invoice->subscription);

		return true;
	}

	public static function getSubscriptionLink($sub)
	{
		return XenForo_Link::buildPublicLink('misc/stripe-subscription', null, array('sub' => $sub));
	}

}
