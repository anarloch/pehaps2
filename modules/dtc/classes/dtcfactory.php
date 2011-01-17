<?php

defined('SYSPATH') or die('No direct script access.');

class DTCFactory extends DTCCore implements Iterator, Countable
{
	protected $pagination = array();
	protected $select_query = null;
	private $results = null;
	private $result_count = 0;

	public function __construct($table_name)
	{
		$reflection_object = new ReflectionClass('Model_' . $table_name);
		foreach ($reflection_object->getDefaultProperties() as $property_name => $property_value)
		{
			$this->$property_name = $property_value;
		}

		parent::__construct($table_name);

		$this->select_query = new DTCQuery($this->table_name, $this->primary_key, array_keys($this->columns));
	}

	public function __call($method_name, $parameters)
	{
		if (in_array($method_name, DTCQuery::$allowed_segments) || in_array(substr($method_name, 4), DTCQuery::$allowed_segments))
		{
			if (count($parameters) == 1)
			{
				$this->select_query->$method_name($parameters[0]);
			}
			else
			{
				$this->select_query->$method_name($parameters);
			}

			return $this;
		}
		else
			throw new DatabaseTableCore_Exception ('Method >>:method<< does not exist in class >>:class<<', array(':method' => $method_name, ':class' => __CLASS__));
	}

	public function as_array()
	{
		return $this->results;
	}

	public function as_tree($parent_field_name)
	{
		if (empty($parent_field_name))
			throw new DatabaseTableCore_Exception ('Parameter >>parent_field_name<< is required');

		$lookup = array();
		foreach ($this as $item)
		{
			$lookup[$item->get_pk()] = $item->get() + array('children' => array());
		}

		for ($tree = array();list($id, ) = each($lookup); )
		{
			$item = &$lookup[$id];

			if ($item[$parent_field_name] == null)
			{
				$tree[$id] = &$item;
			}
			elseif (isset($lookup[$item[$parent_field_name]]))
			{
				$lookup[$item[$parent_field_name]]['children'][$id] = &$item;
			}
			else
			{
				$tree['_orphans_'][$id] = &$item;
			}
		}

		return $tree;
	}

	public function clear_query()
	{
		$this->select_query->clear();
		return $this;
	}

	public function count()
	{
		return $this->result_count;
	}

	public function count_all()
	{
		return $this->pagination instanceof Pagination ? $this->pagination->total_items : $this->count_all_items();
	}

	public function current()
	{
		return current($this->results);
	}

	public function debug_query()
	{
		var_dump($this->select_query->get_query());
		return $this;
	}

	public function delete()
	{
		$state = false;

		if (func_num_args())
		{
			$record_ids = is_array(func_get_arg(0)) ? func_get_arg(0) : func_get_args();
			$state = ! $this->driver->delete($this->table_name, $record_ids, $this->primary_key, $this->get_tables_list());
		}

		return $state;
	}

	public function delete_all()
	{
		$this->fields($this->primary_key);
		$records = $this->driver->select($this->select_query, $this->get_tables_list());

		for ($results = array(); list(, $record) = each($records); )
		{
			$record = (array) $record;
			$results[] = $record[$this->primary_key];
		}

		if (count($results))
		{
			return $this->delete($results);
		}
		
		return false;
	}

	public function each()
	{
		return each($this->results);
	}

	public function is_empty()
	{
		return ! $this->result_count;
	}

	public function end()
	{
		return end($this->results);
	}

	public function find()
	{
		if ($this->pagination instanceof Pagination)
		{
			$this->pagination->total_items = $this->count_all_items();
			$records = $this->driver->select($this->limit($this->pagination->items_per_page, $this->pagination->offset)->select_query, $this->get_tables_list());
		}
		else
		{
			$records = $this->driver->select($this->select_query, $this->get_tables_list());
		}

		$this->results = array();
		while (list(, $record) = each($records))
		{
			$this->results[] = new $this->model_name(null, $record);
		}

		$this->result_count = count($this->results);

		return $this;
	}

	public function find_all()
	{
		$copy_of_query = clone $this->select_query;

		$this->where()->having()->find();
		$this->select_query = $copy_of_query;

		return $this;
	}

	public function find_first()
	{
		$copy_of_query = clone $this->select_query;

		$this->limit(1)->find();
		$this->select_query = $copy_of_query;

		return Arr::get($this->results, 0);
	}

	public function get_id($id)
	{
		return new $this->model_name($id);
	}

	public function get_column($column_name)
	{
		if ($this->result_count == 0)
		{
			$this->find();
		}

		$results_list = array();
		foreach ($this as $item)
		{
			$results_list[] = $item->get($column_name);
		}

		return $results_list;
	}

	public function get_columns()
	{
		if ($this->result_count == 0)
		{
			$this->find();
		}

		$results_list = array();
		foreach ($this as $item)
		{
			$results_list[$item->get_pk()] = $item->get(func_get_args());
		}

		return $results_list;
	}

	public function key()
	{
		return key($this->results);
	}

	public function next()
	{
		return next($this->results);
	}

	public function pagination(array $settings = array())
	{
		if ( ! $this->pagination instanceof Pagination || count($settings))
		{
			$this->pagination = Pagination::factory($settings);
			return $this;
		}

		return $this->pagination;
	}

	public function prev()
	{
		return prev($this->results);
	}

	public function rewind()
	{
		return reset($this->results);
	}

	public function select_list($key_column, $value_column, $first_position = '')
	{
		if ($this->result_count == 0)
		{
			$this->find();
		}

		$select_list = ! empty($first_position) ? array(0 => $first_position) : array();

		foreach ($this as $return)
		{
			$select_list[$return->$key_column] = $return->$value_column;
		}

		return $select_list;
	}

	public function set_query(DTCQuery $query)
	{
		$this->select_query = clone $query;
		return $this;
	}

	public function valid()
	{
		return isset($this->results[key($this->results)]);
	}

	private function count_all_items()
	{
		return $this->driver->count_all_items(clone $this->select_query);
	}

	private function get_tables_list(array $tables_list = null)
	{
		if ($this->cache_on)
		{
			return array_merge($this->used_tables, is_null($tables_list) ? $this->select_query->get_tables_list() : $tables_list);
		}
		else
		{
			return null;
		}
	}

	public static function create($table_name, $init_result = null)
	{
		$model_name = 'Model_' . $table_name;
		return new $model_name(null, $init_result, true);
	}

	public static function instance($table_name, $id = null)
	{
		$model_name = 'Model_' . $table_name;
		return new $model_name($id instanceof DTCModel ? $id->get_pk() : $id);
	}

	public static function table($table_name, $id = null)
	{
		$model_name = 'Model_' . $table_name;
		return is_null($id) ? new DTCFactory($table_name) : new $model_name($id instanceof DTCModel ? $id->get_pk() : $id);
	}

	public static function view($view_name, $id = null)
	{
		$model_name = 'Model_View_' . $view_name;
		return is_null($id) ? new DTCFactory('view_' . $view_name) : new $model_name($id instanceof  DTCModel ? $id->get_pk() : $id);
	}
}

?>