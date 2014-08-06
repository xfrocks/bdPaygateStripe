<?php

class bdPaygateStripe_XenForo_Model_Option extends XFCP_bdPaygateStripe_XenForo_Model_Option
{
	// this property must be static because
	// XenForo_ControllerAdmin_UserUpgrade::actionIndex
	// for no apparent reason use XenForo_Model::create to create the optionModel
	// (instead of using XenForo_Controller::getModelFromCache)
	private static $_bdPaygateStripe_hijackOptions = false;

	public function getOptionsByIds(array $optionIds, array $fetchOptions = array())
	{
		if (self::$_bdPaygateStripe_hijackOptions === true)
		{
			$optionIds[] = 'bdPaygateStripe_secretKey';
			$optionIds[] = 'bdPaygateStripe_publicKey';
		}

		$options = parent::getOptionsByIds($optionIds, $fetchOptions);

		self::$_bdPaygateStripe_hijackOptions = false;

		return $options;
	}

	public function bdPaygateStripe_hijackOptions()
	{
		self::$_bdPaygateStripe_hijackOptions = true;
	}

}
