<?php

class bdPaygateStripe_XenForo_ControllerPublic_Misc extends XFCP_bdPaygateStripe_XenForo_ControllerPublic_Misc
{
    public function actionStripe()
    {
        $this->_assertPostOnly();

        $input = $this->_input->filter(array(
            'stripeToken' => XenForo_Input::STRING,
            'stripeTokenType' => XenForo_Input::STRING,
            'stripeEmail' => XenForo_Input::STRING,

            'itemId' => XenForo_Input::STRING,
            'cents' => XenForo_Input::UINT,
            'currency' => XenForo_Input::STRING,
            'recurringInterval' => XenForo_Input::UINT,
            'recurringUnit' => XenForo_Input::STRING,
        ));

        if (empty($input['stripeToken'])) {
            return $this->responseNoPermission();
        }
        if (empty($input['cents']) OR empty($input['currency'])) {
            return $this->responseNoPermission();
        }

        $redirectParams = array(
            'itemId' => $input['itemId'],
            'cents' => $input['cents'],
            'currency' => $input['currency'],
        );

        if (empty($input['recurringInterval']) OR empty($input['recurringUnit'])) {
            // one time payment
            $chargeResult = bdPaygateStripe_Helper_Api::charge($input['stripeToken'], $input['cents'],
                $input['currency'], array(
                    'itemId' => $input['itemId'],
                    'email' => $input['stripeEmail'],
                ));

            if ($chargeResult instanceof \Stripe\Charge) {
                $redirectParams['charge_id'] = $chargeResult->id;
                $redirectParams['success'] = 1;
            } elseif ($chargeResult instanceof \Stripe\Error\Base) {
                $redirectParams['error'] = $chargeResult->getMessage();
            }
        } else {
            // recurring payment
            $plan = bdPaygateStripe_Helper_Api::getPlan($input['cents'], $input['currency'],
                $input['recurringInterval'], $input['recurringUnit']);

            if ($plan instanceof \Stripe\Plan) {
                $customer = bdPaygateStripe_Helper_Api::subscribe($input['stripeToken'], $plan, $input['stripeEmail'],
                    array('itemId' => $input['itemId']));
                if ($customer instanceof \Stripe\Customer) {
                    $redirectParams['nop'] = 1;
                } elseif ($customer instanceof \Stripe\Error\Base) {
                    $redirectParams['error'] = $customer->getMessage();
                    $redirectParams['customer_error'] = $redirectParams['error'];
                }
            } elseif ($plan instanceof \Stripe\Error\Base) {
                $redirectParams['error'] = $plan->getMessage();
                $redirectParams['plan_error'] = $redirectParams['error'];
            }
        }

        $redirect = $this->getDynamicRedirect();
        $redirect = bdPaygateStripe_Helper_Url::appendParams($redirect, $redirectParams);
        $redirect = bdPaygateStripe_Helper_Url::sign($redirect);

        return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirect);
    }

    public function actionStripeSubscription()
    {
        $sub = $this->_input->filterSingle('sub', XenForo_Input::STRING);
        $subInfo = bdPaygateStripe_Helper_Sub::doUnpack($sub);
        $templateTitle = 'bdpaygatestripe_misc_subscription';

        /** @var \Stripe\Customer $customer */
        $customer = bdPaygateStripe_Helper_Api::getCustomer($subInfo['customerId']);
        if ($customer instanceof \Stripe\Error\Base) {
            return $this->responseError(new XenForo_Phrase('bdpaygatestripe_customer_not_found'));
        }
        /** @noinspection PhpUndefinedFieldInspection */
        if ($customer->email != XenForo_Visitor::getInstance()->get('email')) {
            return $this->responseError(new XenForo_Phrase('bdpaygatestripe_customer_email_mismatched',
                array('boardTitle' => XenForo_Application::getOptions()->get('boardTitle'))));
        }

        try {
            /** @var \Stripe\Subscription $subscription */
            /** @noinspection PhpUndefinedFieldInspection */
            $subscription = $customer->subscriptions->retrieve($subInfo['subscriptionId']);
        } catch (\Stripe\Error\Base $e) {
            return $this->responseError(new XenForo_Phrase('bdpaygatestripe_subscription_not_found'));
        }

        if (empty($subscription->canceled_at)) {
            $cancel = $this->_input->filterSingle('cancel', XenForo_Input::STRING);
            if (!empty($cancel)) {
                $templateTitle = 'bdpaygatestripe_misc_subscription_cancel';

                if ($this->isConfirmedPost()) {
                    try {
                        $subscription->cancel(array('at_period_end' => true));
                    } catch (\Stripe\Error\Base $e) {
                        // serious problem!
                        throw $e;
                    }

                    return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS,
                        XenForo_Link::buildPublicLink('misc/stripe-subscription', null, array('sub' => $sub)),
                        new XenForo_Phrase('bdpaygatestripe_subscription_canceled'));
                }
            }
        }

        /** @noinspection PhpUndefinedFieldInspection */
        $viewParams = array(
            'sub' => $sub,
            'customer' => $customer,
            'subscription' => $subscription,
            'plan' => $subscription->plan,
        );

        return $this->responseView('bdPaygateStripe_ViewPublic_Misc_Subscription', $templateTitle, $viewParams);
    }

}
