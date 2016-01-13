<?php

class bdPaygateStripe_Helper_Api
{
    /**
     * @param float $amount
     * @param string $currency
     * @return int
     */
    public static function getAmountInCent($amount, $currency)
    {
        if (self::isZeroDecimalCurrency($currency)) {
            return intval($amount);
        }

        if (function_exists('bcmul')) {
            return intval(bcmul(strval($amount), '100'));
        } else {
            return floor(doubleval($amount) * 100);
        }
    }

    /**
     * @param int $amount
     * @param string $currency
     * @return float
     */
    public static function getAmountFromCent($amount, $currency)
    {
        if (self::isZeroDecimalCurrency($currency)) {
            return doubleval($amount);
        }

        if (function_exists('bcdiv')) {
            return doubleval(bcdiv(strval($amount), '100', 2));
        } else {
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

        $chargeParams = array(
            'amount' => $amountInCents,
            'currency' => $currency,
            'source' => $token,
            'metadata' => $metadata,
        );

        $xenOptions = XenForo_Application::getOptions();
        if ($xenOptions->get('bdPaygateStripe_receiptEmail')
            && !empty($metadata['email'])
        ) {
            $chargeParams['receipt_email'] = $metadata['email'];
        }

        try {
            $result = \Stripe\Charge::create($chargeParams);
        } catch (\Stripe\Error\Base $e) {
            $result = $e;
        }

        return $result;
    }

    public static function getPlan($cents, $currency, $interval, $unit)
    {
        $plan = null;
        self::loadLib();

        $planId = sprintf('%d%s%d%s', $cents, strtolower($currency), $interval, strtolower($unit));

        try {
            $plan = \Stripe\Plan::retrieve($planId);

            /** @noinspection PhpUndefinedFieldInspection */
            if ($plan->amount != $cents
                || strtolower($plan->currency) != strtolower($currency)
                || strtolower($plan->interval) != strtolower($unit)
                || $plan->interval_count != $interval
            ) {
                // found a plan but its information is not matched
                // do not use it + generate a new plan id for temporary usage
                $plan = null;
                $planId .= XenForo_Application::$time;
            }
        } catch (\Stripe\Error\Base $e) {
            // ignore
        }

        if (empty($plan)) {
            try {
                $plan = \Stripe\Plan::create(array(
                    'id' => $planId,
                    'amount' => $cents,
                    'currency' => $currency,
                    'interval' => $unit,
                    'interval_count' => $interval,
                    'name' => sprintf('%.2f %s every %d %ss at %s', self::getAmountFromCent($cents, $currency),
                        strtoupper($currency), $interval, $unit, XenForo_Application::getOptions()->get('boardTitle')),
                ));
            } catch (\Stripe\Error\Base $e) {
                $plan = $e;
            }
        }

        return $plan;
    }

    public static function subscribe($token, \Stripe\Plan $plan, $email, array $metadata = array())
    {
        $result = null;
        self::loadLib();

        try {
            $result = \Stripe\Customer::create(array(
                'source' => $token,
                'plan' => $plan->id,
                'email' => $email,
                'metadata' => $metadata,
            ));
        } catch (\Stripe\Error\Base $e) {
            $result = $e;
        }

        return $result;
    }

    public static function getCustomer($customerId)
    {
        $customer = null;
        self::loadLib();

        try {
            $customer = \Stripe\Customer::retrieve(array('id' => $customerId));
        } catch (\Stripe\Error\Base $e) {
            $customer = $e;
        }

        return $customer;
    }

    public static function getInvoice($invoiceId)
    {
        $invoice = null;
        self::loadLib();

        try {
            $invoice = \Stripe\Invoice::retrieve(array('id' => $invoiceId));
        } catch (\Stripe\Error\Base $e) {
            $invoice = $e;
        }

        return $invoice;
    }

    public static function getCharge($chargeId)
    {
        $charge = null;
        self::loadLib();

        try {
            $charge = \Stripe\Charge::retrieve($chargeId);
        } catch (\Stripe\Error\Base $e) {
            $charge = $e;
        }

        return $charge;
    }

    public static function loadLib()
    {
        require_once(dirname(dirname(__FILE__)) . '/3rdparty/init.php');
        \Stripe\Stripe::setApiKey(XenForo_Application::getOptions()->get('bdPaygateStripe_secretKey'));
    }

}
