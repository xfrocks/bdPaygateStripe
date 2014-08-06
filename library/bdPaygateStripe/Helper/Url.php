<?php

class bdPaygateStripe_Helper_Url
{
	public static function appendParams($url, array $params)
	{
		if (strpos($url, '?') === false)
		{
			$url .= '?';
		}
		else
		{
			$url .= '&';
		}

		foreach ($params as $key => $value)
		{
			$url .= sprintf('%s=%s&', $key, rawurlencode($value));
		}

		$url = substr($url, 0, -1);

		return $url;
	}

	public static function sign($url)
	{
		$parts = parse_url($url);

		if (!empty($parts['query']))
		{
			parse_str($parts['query'], $query);

			ksort($query);

			$signature = md5(implode('', $query) . XenForo_Application::getConfig()->get('globalSalt'));

			$url = self::appendParams($url, array('signature' => $signature));
		}

		return $url;
	}

	public static function verifySignature()
	{
		$query = $_GET;

		if (!isset($query['signature']))
		{
			return false;
		}
		$signature = $query['signature'];
		unset($query['signature']);

		return $signature === self::calculateSignature($query);
	}

	public static function calculateSignature(array $query)
	{
		ksort($query);
		return md5(implode('', $query) . XenForo_Application::getConfig()->get('globalSalt'));
	}

}
