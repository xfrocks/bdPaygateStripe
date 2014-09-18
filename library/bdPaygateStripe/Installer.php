<?php

class bdPaygateStripe_Installer
{
	/* Start auto-generated lines of code. Change made will be overwriten... */

	protected static $_tables = array();
	protected static $_patches = array();

	public static function install($existingAddOn, $addOnData)
	{
		$db = XenForo_Application::get('db');

		foreach (self::$_tables as $table)
		{
			$db->query($table['createQuery']);
		}

		foreach (self::$_patches as $patch)
		{
			$tableExisted = $db->fetchOne($patch['showTablesQuery']);
			if (empty($tableExisted))
			{
				continue;
			}

			$existed = $db->fetchOne($patch['showColumnsQuery']);
			if (empty($existed))
			{
				$db->query($patch['alterTableAddColumnQuery']);
			}
		}
		
		self::installCustomized($existingAddOn, $addOnData);
	}

	public static function uninstall()
	{
		$db = XenForo_Application::get('db');

		foreach (self::$_patches as $patch)
		{
			$tableExisted = $db->fetchOne($patch['showTablesQuery']);
			if (empty($tableExisted))
			{
				continue;
			}

			$existed = $db->fetchOne($patch['showColumnsQuery']);
			if (!empty($existed))
			{
				$db->query($patch['alterTableDropColumnQuery']);
			}
		}

		foreach (self::$_tables as $table)
		{
			$db->query($table['dropQuery']);
		}

		self::uninstallCustomized();
	}

	/* End auto-generated lines of code. Feel free to make changes below */

	public static function installCustomized($existingAddOn, $addOnData)
	{
		if (XenForo_Application::$versionId < 1020000)
		{
			throw new XenForo_Exception('[bd] Paygate: STRIPE requires XenForo 1.2.0+');
		}

		$addOns = XenForo_Application::get('addOns');
		if (empty($addOns['bdPaygate']) OR $addOns['bdPaygate'] < 28)
		{
			throw new XenForo_Exception('[bd] Paygate: STRIPE requires [bd] Paygates v1.4.4+');
		}
	}

	public static function uninstallCustomized()
	{
		// customized uninstall script goes here
	}

}
