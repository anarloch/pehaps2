<?php

defined('SYSPATH') or die('No direct script access.');

abstract class DTCModel extends DTCCore
{

	protected $error_file;
	protected $errors = array();
	protected $new_record = null;
	protected $string_column;
	protected $validate = array();
	protected $values;
	protected $update = array();
	private $affected_rows = 0;
	private $loaded = false;

	public function __construct($id = null, $init_result = null, $new_record = false)
	{
		parent::__construct();

		if ( ! is_null($init_result))
		{
			$this->update_result($init_result, is_array($init_result));
			$this->new_record = $new_record;
		}
		elseif ( ! (int) $id)
		{
			$this->update_result(null);
		}
		else
		{
			if (is_object($result = $this->driver->select_init($this->table_name, $id, $this->columns, $this->primary_key)))
			{
				$this->update_result($result);
			}
			else
			{
				$this->update_result(null);
				$this->values[$this->primary_key] = is_scalar($id) ? $id : null;
			}
		}

		foreach ($this->values as $column_name => &$value)
		{
			if ($value === null)
			{
				$value = $this->params($column_name, 'column_default');
			}
		}

		if (empty($this->error_file))
		{
			$this->error_file = $this->table_name;
		}

		$this->validate = array_merge(array('callbacks' => array(), 'filters' => array(), 'labels' => array(), 'rules' => array()), $this->validate);
		$this->set_validate_rules();

		unset($this->validate['rules'][$this->primary_key]['not_empty']);
	}

	public function __destruct()
	{
		parent::__destruct();
	}

	public function __call($method_name, $parameters)
	{
		$function_type = strtolower(substr($method_name, 0, 4));
		$field_name = strtolower(substr($method_name, 4));

		if ($function_type  == 'get_')
		{
			return $this->$field_name;
		}
		elseif ($function_type == 'set_')
		{
			$this->$field_name = array_shift($parameters);
		}
		elseif ($function_type == 'can_')
		{
			return $this->checkPermission(substr($method_name, 4), Auth::instance()->get_user());
		}
		elseif (strtolower(substr($method_name, 0, 8)) == 'require_')
		{
			if ( ! $this->checkPermission(substr($method_name, 8), Auth::instance()->get_user()))
				throw new Exception($message);

			return true;
		}
	}

	public function __get($field_name)
	{
		return $this->get($field_name);
	}

	public function __isset($field_name)
	{
		return isset($this->values[$field_name]);
	}

	public function __set($field_name, $new_value)
	{
		$this->set($field_name, $new_value);
	}

	public function __toString()
	{
		if (empty($this->string_column))
		{
			return nl2br('Values: ' . var_export($this->values, true) . "\nErrors: " . var_export($this->errors, true) . "\n");
		}
		else
		{
			return strlen($out_string = $this->get($this->string_column)) ? $out_string : '';
		}
	}

	public function __unset($fieldname)
	{
		unset($this->values[$fieldname]);
	}

	public function affected_rows()
	{
		return $this->affected_rows;
	}

	public function as_array()
	{
		return $this->values;
	}

	public function check()
	{
		if ($this->validate instanceof Validate)
		{
			$this->validate->exchangeArray($this->values);
		}
		else
		{
			$this->init_validate();
		}

		if ($check = $this->validate->check())
		{
			$this->values = array_merge($this->values, $this->validate->getArrayCopy());
		}
		else
		{
			$this->errors = $this->validate->errors($this->error_file);
		}

		return $check;
	}

	public function checkPermission($permission, &$user = null)
	{
		if ($user && $user->admin == 'super')
			return true;

		if ($this->{$permission.'_access'} == 'everyone' || ($this->{$permission.'_access'} == 'registered' && $user))
			return true;

		if ($user)
		{
			$mapAdmins = DTC::table('map_administrators')->where('map_id', '=', $this->get_pk())->andwhere('user_id', '=', $user->id);
			if ($mapAdmins && $mapAdmins->{'can'.$permission}) return true;
		}

		return false;
	}

	public function delete()
	{
		if ($this->loaded())
		{
			$this->driver->delete($this->table_name, $this->get_pk(), $this->primary_key);
		}

		unset($this);
	}

	public function errors($other_errors_file = null)
	{
		return ( ! empty($other_errors_file) && $this->validate instanceof Validate) ? $this->validate->errors($other_errors_file) : $this->errors;
	}

	public function get($field_name = null, $ignore_errors = false)
	{
		if (is_array($field_name))
		{
			for ($values = array(); list(, $field) = each($field_name); $values[$field] = $this->get($field, true))
				;

			return $values;
		}
		elseif ( ! empty($field_name))
		{
			if (method_exists($this, $method_name = 'get_' . $field_name))
			{
				return call_user_func(array($this, $method_name), @$this->values[$field_name]);
			}
			elseif ( ! array_key_exists($field_name, $this->values))
			{
				if (array_key_exists($field_name . self::$suffix_id, $this->values))
				{
					$value = $this->values[$field_name .= self::$suffix_id];

					if ($model_name = @$this->params($field_name, 'model'))
					{
						$model_name = 'Model_' . $model_name;
					}

					return ( ! empty($model_name)) ? new $model_name($value) : $value;
				}
				elseif (in_array($field_name, $this->ignored_columns))
				{
					return @$this->values[$field_name];
				}
				else
				{
					if ($ignore_errors)
						return $this;

					throw new DatabaseTableCore_Exception('Field >:field< doesn\'t exist at this instance of >>:object<< object', array(':field' => $field_name, ':object' => $this->model_name));
				}
			}
			else
			{
				return $this->values[$field_name];
			}
		}
		else
			return $this->get_all();
	}

	public function get_all()
	{
		return $this->get(array_keys($this->values));
	}

	public function get_pk()
	{
		return @$this->values[$this->primary_key];
	}

	public function is_correct()
	{
		return ! count($this->errors);
	}

	public function is_update($field_name)
	{
		return isset($this->update[$field_name]) && $this->update[$field_name];
	}

	public function load($load_values)
	{
		if ($load_values instanceof DTCModel)
		{
			$load_values = $load_values->get_all();
		}

		$this->loaded = false;
		return $this->set($load_values);
	}

	public function loaded()
	{
		return (bool) $this->loaded;
	}

	public function save()
	{
		if ( ! empty($this->update) || $this->new_record)
		{
			ksort($this->values);

			$save_values = $this->values;

			foreach ($this->ignored_columns as $column_name)
			{
				unset($save_values[$column_name]);
			}

			for ($query_values = array(); list($column_name, $value) = each($save_values);)
			{
				if ( ! is_null($value))
				{
					$query_values[DTCQuery::quote_identifier($column_name)] =  DTCQuery::quote($value);
				}
				elseif ( ! is_null($this->params($column_name, 'column_default')))
				{
					$query_values[DTCQuery::quote_identifier($column_name)] = 'default';
				}
				elseif ($this->params($column_name, 'is_nullable'))
				{
					$query_values[DTCQuery::quote_identifier($column_name)] =  'null';
				}
				elseif ($column_name != $this->primary_key)
					throw new DatabaseTableCore_Exception ('Required field >>:field<< in model >>:model<< is empty', array(':field' => $column_name, ':model' => $this->model_name));
			}

			if ($this->new_record)
			{
				list($this->values[$this->primary_key], $this->affected_rows) = $this->driver->insert($this->table_name, $query_values, $this->primary_key, (bool) @$this->primary_autoincrement);
			}
			else
			{
				$this->affected_rows = $this->driver->update($this->table_name, $query_values, $this->get_pk(), $this->primary_key);
			}

			$this->update = (array) $this->new_record = false;
		}

		return $this;
	}

	public function set($field_name, $new_value = null, $ignore_errors = false)
	{
		if (is_array($values = $field_name))
		{
			foreach ($values as $field_name => $value)
			{
				$this->set($field_name, $value, true);
			}
		}
		else
		{
			if (method_exists($this, $method_name = 'set_' . $field_name))
			{
				return $this->set_field($field_name, call_user_func(array($this, $method_name), $new_value));
			}
			elseif ( ! array_key_exists($field_name, $this->values))
			{
				if (array_key_exists($field_name . self::$suffix_id, $this->values))
				{
					$this->set($field_name . self::$suffix_id, $new_value, $ignore_errors);
					return $this;
				}
				elseif (in_array($field_name, $this->ignored_columns))
				{
					return $this->set_field($field_name, $new_value);
				}
				else
				{
					if ($ignore_errors)
						return $this;

					throw new DatabaseTableCore_Exception('Field >:field< doesn\'t exists at this instance of >>:object<< object', array(':field' => $field_name, ':object' => $this->model_name));
				}
			}
			else
			{
				if ($this->params($field_name, 'readonly') && ! is_null($this->values[$field_name]))
				{
					if ($ignore_errors)
						return $this;

					throw new DatabaseTableCore_Exception('You cannot to write to readonly field >:field< in table >>:table<<', array(':field' => $field_name, ':table' => $this->table_name));
				}

				if ($new_value instanceof DTCModel)
				{
					$new_value = ( ! $new_value->get_pk()) ? $new_value->save()->get_pk() : $new_value->get_pk();
				}

				return $this->set_field($field_name, $new_value);
			}
		}
		
		return $this;
	}

	public function validate()
	{
		if ( ! $this->validate instanceof  Validate)
		{
			$this->init_validate();
		}

		return $this->validate;
	}

	public function validate_save()
	{
		if ($this->check())
		{
			$this->save();
		}

		return $this;
	}

	protected function init_validate()
	{
		$validate = Validate::factory($this->values);

		$columns = array_keys(array_merge($this->columns, $this->validate['labels']));
		$labels = array_merge(array_combine($columns, $columns), $this->validate['labels']);

		foreach ($columns as $column)
		{
			if (is_array($rules = @$this->validate['rules'][$column]))
			{
				$validate->rules($column, $rules);
			}

			if (is_array($filters = @$this->validate['filters'][$column]))
			{
				$validate->filters($column, $filters);
			}
		}

		$validate->labels($this->validate['labels']);

		foreach ($this->validate['callbacks'] as $field => $callbacks)
		{
			foreach ($callbacks as $callback)
			{
				if (is_string($callback) && method_exists($this, $callback))
				{
					$validate->callback($field, array($this, $callback));
				}
				else
				{
					$validate->callback($field, $callback);
				}
			}
		}

		$this->validate = $validate;
	}

	protected function set_validate_rules()
	{
		foreach ($this->columns as $column_name => $column_parameters)
		{
			$this->set_rule($this->driver->validate_rule($column_parameters), $column_name);
		}
	}

	protected function update_result($result, $init_all_columns = false)
	{
		if (is_object($result) || is_array($result))
		{
			$result = (array) $result;
			$this->values = array_fill_keys($init_all_columns ? array_keys($this->columns) : array_keys($result), null);
			$this->set($result);

			$this->new_record = false;
		}
		elseif (is_scalar($result) || is_null($result))
		{
			$this->values = array_fill_keys(array_keys($this->columns), $result);
			$this->new_record = true;
		}
		else
			throw new DatabaseTableCore_Exception('Result is incorrect - values are not updated');

		foreach ($this->ignored_columns as $ignored_column_name)
		{
			$this->get($ignored_column_name);
		}

		$this->loaded = true;
	}
	
	private function set_field($field_name, $value)
	{
		$this->values[$field_name] = $value;
		$this->update[$field_name] = true;

		return $this;
	}

	private function set_rule($rules, $column_name)
	{
		$this->validate['rules'] = array_merge_recursive($this->validate['rules'], array($column_name => $rules));
	}

	static public $suffix_id = '';

}

?>