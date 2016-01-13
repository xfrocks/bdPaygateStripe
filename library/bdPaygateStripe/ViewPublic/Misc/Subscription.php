<?php

class bdPaygateStripe_ViewPublic_Misc_Subscription extends XenForo_ViewPublic_Base
{
    public function prepareParams()
    {
        $plan = $this->_params['plan'];

        $cost = sprintf('%.2f %s', bdPaygateStripe_Helper_Api::getAmountFromCent($plan->amount, $plan->currency),
            strtoupper($plan->currency));

        if ($plan->interval_count > 1) {
            // new XenForo_Phrase('bdpaygatestripe_subscribe_x_every_y_days')
            // new XenForo_Phrase('bdpaygatestripe_subscribe_x_every_y_months')
            // new XenForo_Phrase('bdpaygatestripe_subscribe_x_every_y_years')
            $this->_params['planExplain'] = new XenForo_Phrase(sprintf('bdpaygatestripe_subscribe_x_every_y_%ss',
                $plan->interval),
                array(
                    'cost' => $cost,
                    'length' => $plan->interval_count,
                ));
        } else {
            // new XenForo_Phrase('bdpaygatestripe_subscribe_x_every_day')
            // new XenForo_Phrase('bdpaygatestripe_subscribe_x_every_month')
            // new XenForo_Phrase('bdpaygatestripe_subscribe_x_every_year')
            $this->_params['planExplain'] = new XenForo_Phrase(sprintf('bdpaygatestripe_subscribe_x_every_%s',
                $plan->interval), array('cost' => $cost));
        }

        parent::prepareParams();
    }

}
