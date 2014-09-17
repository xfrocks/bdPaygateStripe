<?php

class bdPaygateStripe_Helper_Api
{
	/**
	 * @return int
	 */
	public static function getAmountInCent($amount, $currency)
	{
		if (self::isZeroDecimalCurrency($currency))
		{
			return intval($amount);
		}

		if (function_exists('bcmul'))
		{
			return intval(bcmul(strval($amount), '100'));
		}
		else
		{
			return floor(doubleval($amount) * 100);
		}
	}

	/**
	 * @return double
	 */
	public static function getAmountFromCent($amount, $currency)
	{
		if (self::isZeroDecimalCurrency($currency))
		{
			return doubleval($amount);
		}

		if (function_exists('bcdiv'))
		{
			return doubleval(bcdiv(strval($amount), '100', 2));
		}
		else
		{
			return intval($amount) / 100.0;
		}
	}

	public static function isZeroDecimalCurrency($currency)
	{
		// list from Stripe Zero-decimal currencies page
		// https://support.stripe.com/questions/which-zero-decimal-currencies-does-stripe-support
		// these currencies doesn't need their amount in cents (no multiple by 100)
		static $currencies = array(
			'bif',
			'djf',
			'jpy',
			'krw',
			'pyg',
			'vnd',
			'xaf',
			'xpf',
			'clp',
			'gnf',
			'kmf',
			'mga',
			'rwf',
			'vuv',
			'xof',
		);

		$currency = strtolower($currency);
		return in_array($currency, $currencies, true);
	}

	public static function charge($token, $amountInCents, $currency, array $metadata = array())
	{
		$result = null;
		self::loadLib();

		try
		{
			$result = Stripe_Charge::create(array(
				'amount' => $amountInCents,
				'currency' => $currency,
				'card' => $token,
				'metadata' => $metadata,
			));
		}
		catch (Stripe_Error $e)
		{
			$result = $e;
		}

		return $result;
	}

	public static function getPlan($cents, $currency, $interval, $unit)
	{
		$plan = null;
		self::loadLib();

		$planId = sprintf('%d%s%d%s', $cents, strtolower($currency), $interval, strtolower($unit));

		try
		{
			$plan = Stripe_Plan::retrieve($planId);

			if ($plan->amount != $cents OR strtolower($plan->currency) != strtolower($currency) OR strtolower($plan->interval) != strtolower($unit) OR $plan->interval_count != $interval)
			{
				// found a plan but its information is not matched
				// do not use it + generate a new plan id for temporary usage
				$plan = null;
				$planId .= XenForo_Application::$time;
			}
		}
		catch (Stripe_Error $e)
		{
			// ignore
		}

		if (empty($plan))
		{
			try
			{
				$plan = Stripe_Plan::create(array(
					'id' => $planId,
					'amount' => $cents,
					'currency' => $currency,
					'interval' => $unit,
					'interval_count' => $interval,
					'name' => sprintf('%.2f %s every %d %ss at %s', self::getAmountFromCent($cents, $currency), strtoupper($currency), $interval, $unit, XenForo_Application::getOptions()->get('boardTitle')),
				));
			}
			catch (Stripe_Error $e)
			{
				$plan = $e;
			}
		}

		return $plan;
	}

	public static function subscribe($token, Stripe_Plan $plan, $email, array $metadata = array())
	{
		$result = null;
		self::loadLib();

		try
		{
			$result = Stripe_Customer::create(array(
				'card' => $token,
				'plan' => $plan->id,
				'email' => $email,
				'metadata' => $metadata,
			));
		}
		catch (Stripe_Error $e)
		{
			$result = $e;
		}

		return $result;
	}

	public static function getCustomer($customerId)
	{
		$customer = null;
		self::loadLib();

		try
		{
			$customer = Stripe_Customer::retrieve(array('id' => $customerId));
		}
		catch (Stripe_Error $e)
		{
			$customer = $e;
		}

		return $customer;
	}

	public static function getInvoice($invoiceId)
	{
		$invoice = null;
		self::loadLib();

		try
		{
			$invoice = Stripe_Invoice::retrieve(array('id' => $invoiceId));
		}
		catch (Stripe_Error $e)
		{
			$invoice = $e;
		}

		return $invoice;
	}

	public static function getCharge($chargeId)
	{
		$charge = null;
		self::loadLib();

		try
		{
			$charge = Stripe_Charge::retrieve($chargeId);
		}
		catch (Stripe_Error $e)
		{
			$charge = $e;
		}

		return $charge;
	}

	public static function loadLib()
	{
		require_once (dirname(dirname(__FILE__)) . '/3rdparty/lib/Stripe.php');
		Stripe::setApiKey(XenForo_Application::getOptions()->get('bdPaygateStripe_secretKey'));
	}

}
