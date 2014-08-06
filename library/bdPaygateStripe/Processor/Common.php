<?php

abstract class bdPaygateStripe_Processor_Common extends bdPaygate_Processor_Abstract
{
	public function getSupportedCurrencies()
	{
		return array(bdPaygate_Processor_Abstract::CURRENCY_USD);
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

			'success' => XenForo_Input::STRING,
			'charge_id' => XenForo_Input::STRING,
			'customer_id' => XenForo_Input::STRING,
			'subscription_id' => XenForo_Input::STRING,
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
			$amount = $filtered['cents'] / 100;
			$currency = $filtered['currency'];

			if (!empty($filtered['success']))
			{
				$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_ACCEPTED;

				if (!empty($filtered['customer_id']) AND !empty($filtered['subscription_id']))
				{
					$transactionDetails[bdPaygate_Processor_Abstract::TRANSACTION_DETAILS_SUBSCRIPTION_ID] = bdPaygateStripe_Helper_Sub::doPack($filtered['customer_id'], $filtered['subscription_id']);
				}
			}
			elseif (!empty($filtered['error']))
			{
				$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_REJECTED;
			}

			return true;
		}
		else
		{
			$this->_setError('Request unrecognized');
			return false;
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

	public static function getSubscriptionLink($sub)
	{
		return XenForo_Link::buildPublicLink('misc/stripe-subscription', null, array('sub' => $sub));
	}

}
