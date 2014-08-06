<?php

class bdPaygateStripe_Helper_Sub
{
	public static function doPack($customerId, $subscriptionId)
	{
		return sprintf('%s|%s', $customerId, $subscriptionId);
	}

	public static function doUnpack($sub)
	{
		list($customerId, $subscriptionId) = explode('|', $sub);

		return compact('customerId', 'subscriptionId');
	}

}
