<?php

class bdPaygateStripe_ViewPublic_Misc_Subscription extends XenForo_ViewPublic_Base
{
	public function prepareParams()
	{
		$plan = $this->_params['plan'];

		$cost = sprintf('%.2f %s', bdPaygateStripe_Helper_Api::getAmountFromCent($plan->amount, $plan->currency), strtoupper($plan->currency));

		if ($plan->interval_count > 1)
		{
			$this->_params['planExplain'] = new XenForo_Phrase('bdpaygatestripe_subscribe_x_every_y_' . $plan->interval . 's', array(
				'cost' => $cost,
				'length' => $plan->interval_count,
			));
		}
		else
		{
			$this->_params['planExplain'] = new XenForo_Phrase('bdpaygatestripe_subscribe_x_every_' . $plan->interval, array('cost' => $cost));
		}

		return parent::prepareParams();
	}

}
