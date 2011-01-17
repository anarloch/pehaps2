<?php

defined('SYSPATH') or die('No direct script access.');

abstract class DTCCore
{
	protected $cache_on = true;
	protected $columns = array();
	protected $driver = null;
	protected $driver_name = null;
	protected $ignored_columns = array();
	protected $models = array();
	protected $model_name;
	protected $primary_key = '';
	protected $primary_autoincrement = true;
	protected $table_name = '';
	protected $used_tables = array();

	public function __construct($table_name = null)
	{
		$this->model_name = empty($table_name) ? get_class($this) : 'Model_' . $table_name;

		if (empty($this->table_name))
		{
			$this->table_name = strtolower(str_replace('Model_', '', $this->model_name));
		}

		if (empty($this->table_name))
			throw new DatabaseTableCore_Exception('You should set table_name property before you construct this model');

		$this->driver = DTC::driver($this->driver_name);
		$this->columns = Arr::merge($this->driver->list_columns($this->table_name), $this->columns);

		if (empty($this->columns))
			throw new DatabaseTableCore_Exception('You should to set array of table columns before you construct this model');

		if (empty($this->primary_key))
		{
/*			if ( ! empty(DTC::config()->primary_key))
			{
				$this->primary_key = DTC::config()->primary_key;
			}
			else
			{*/
				foreach ($this->columns as $column_parameters)
				{
					if (isset($column_parameters['key']) && $column_parameters['key'] == 'PRI')
					{
						$this->primary_key = $column_parameters['column_name'];
						break;
					}
				}
//			}
		}

		if (empty($this->primary_key))
			throw new DatabaseTableCore_Exception('You should to set primary_key property before you construct this model');

		$this->set_params($this->primary_key, 'readonly', true);

		foreach ($this->models as $column_name => $model_name)
		{
			$this->add_model($column_name, $model_name);
		}
	}

	public function __destruct()
	{}

	public function cache_on($status)
	{
		$this->cache_on = $status;
		return $this;
	}

	public function column_list()
	{
		return $this->columns;
	}

	public function last_query()
	{
		return $this->diver->last_query();
	}

	public function params($column_name = null, $parameter_name = null, $parameter_value = null)
	{
		if (is_null($column_name))
		{
			return $this->columns;
		}
		elseif (is_null($parameter_name))
		{
			return Arr::get($this->columns, $column_name, array());
		}
		elseif (is_null($parameter_value))
		{
			return Arr::get(Arr::get($this->columns, $column_name, array($parameter_name => null)), $parameter_name, null);
		}
		else
		{
			return $this->set_params($column_name, $parameter_name, $parameter_value);
		}
	}

	public function set_used_tables(array $used_tables_list)
	{
		$this->used_tables = array_merge($this->used_tables, $used_tables_list);
	}

	protected function add_model($column_name, $model_name)
	{
		$this->set_params($column_name, 'model', $model_name);
	}

	protected function set_params($column_name, $parameter_name, $parameter_value)
	{
		$this->columns[$column_name][$parameter_name] = $parameter_value;
		return $this->columns;
	}

}

class DatabaseTableCore_Exception extends Database_Exception { }

?>
