<?php

defined('SYSPATH') or die('No direct script access.');

class DTC extends DTCFactory
{

	static function config($config_file = 'dtc')
	{
		static $dtc_config = array();

		if ( ! isset($dtc_config[$config_file]))
		{
			$dtc_config[$config_file] = Kohana::config($config_file);

			DTCModel::$suffix_id = $dtc_config[$config_file]->suffix_id;
		}

		return $dtc_config[$config_file];
	}

	static function driver($driver_name = null)
	{
		static $dtc_driver = array();
		static $dtc_driver_name;

		if (($driver_name != $dtc_driver_name) || (empty($dtc_driver_name)))
		{
			$dtc_driver_name = DTC::config()->driver;
		}

		if (isset($dtc_driver[$dtc_driver_name]))
		{
			return $dtc_driver[$dtc_driver_name];
		}

		$driver_name = 'Driver_' . ucfirst($dtc_driver_name);
		
		try
		{
			return $dtc_driver[$dtc_driver_name] = new $driver_name;
		}
		catch (Exception $e)
		{
			throw new DatabaseTableCore_Exception('The DTC driver class is incorrect - try another settings');
		}
	}

	static function expr($expression)
	{
		return new DTCExpression($expression);
	}

	static function query($tables_list = null, $columns = array())
	{
		$query = new DTCQuery('', '', is_string($columns) ? explode(',', $columns) : $columns);
		return $query->from($tables_list);
	}
}

?>
