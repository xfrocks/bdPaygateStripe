<?php

class bdPaygateStripe_XenForo_ControllerAdmin_UserUpgrade extends XFCP_bdPaygateStripe_XenForo_ControllerAdmin_UserUpgrade
{
	public function actionIndex()
	{
		$optionModel = $this->getModelFromCache('XenForo_Model_Option');
		$optionModel->bdPaygateStripe_hijackOptions();

		return parent::actionIndex();
	}

}
