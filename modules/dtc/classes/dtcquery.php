<?php

defined('SYSPATH') or die('No direct script access.');

class DTCQuery
{
	const WHERE_AND = 'and';
	const WHERE_OR = 'or';

	protected $columns;
	protected $const_query;
	protected $elements;
	protected $id_column;
	protected $last_query;
	protected $query;
	protected $table_name;
	protected $update;
	protected $used_tables = array();

	public function __construct($from, $id_column = 'id', array $columns = array())
	{
		$this->id_column = $id_column;
		$this->columns = $columns;
		$this->table_name = $from;

		$this->clear();
	}

	public function __call($method_name, $parameters)
	{
		if (method_exists($this, $method_name) || ($use_add_method = strpos($method_name, 'add_') === 0))
		{
			if (count($parameters) == 1)
			{
				if (is_string($parameters[0]))
				{
					if (in_array($method_name, array('where', 'having', 'add_where', 'add_having')))
					{
						$parameters = preg_split('/[\s,]*\"([^\"]+)\"[\s,]*|[\s,]*\'([^\']+)\'[\s,]*|[\s,]+/', $parameters[0] , 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
					}
					else
					{
						$parameters = preg_split('/[\s]*[,][\s]*/', $parameters[0]);
					}
				}
				elseif (is_array($parameters[0]))
				{
					$parameters = $parameters[0];
				}
			}

			if ( ! empty($use_add_method))
			{
				$this->append(substr($method_name, 4), $parameters);
			}
			else
			{
				$this->set($method_name, $parameters);
			}

			$this->update[$method_name] = true;
		}

		return $this;
	}

	public function __toString()
	{
		return $this->get_query();
	}

	public function clear()
	{
		$this->elements = array_fill_keys(self::$allowed_segments, array());
		$this->query = array_fill_keys(self::$allowed_segments, '');
		$this->update = array_fill_keys(self::$allowed_segments, true);

		$this->elements['fields'] = $this->split_simple($this->columns);
		$this->elements['from'] = $this->split_simple(array($this->table_name));

		$this->used_tables = array();

		$this->const_query = '';
	}

	public function distinct()
	{
		return $this->add_params('DISTINCT');
	}

	public function get_tables_list()
	{
		$all_tables = array_merge($this->elements['from'], $this->used_tables);

		foreach ($all_tables as & $table_name)
		{
			if (($space_pos = strpos($table_name, ' ')) !== false)
			{
				$table_name = substr($table_name, 0, $space_pos);
			}
		}

		return $all_tables;
	}

	public function get_query()
	{
		if ( ! empty($this->const_query))
			return $this->const_query;

		if ( ! array_sum($this->update))
			return $this->last_query;

		foreach ($this->elements as $segment_name => $segment_values)
		{
			if (empty($segment_values) || ! $this->update[$segment_name])
				continue;

			switch ($segment_name)
			{
				case 'having':
				case 'where':
					$this->query[$segment_name] = $this->join_where_style_query($segment_values, $segment_name);
				break;

				case 'fields':
					$this->query[$segment_name] = $this->join_query($segment_values, '', ',');
				break;

				case 'from':
					$this->query[$segment_name] = $this->join_query($segment_values, $segment_name, ',', 'quote_table');
				break;

				case 'limit':
					$this->query[$segment_name] = $this->join_query($segment_values, $segment_name, ' offset ', '');
				break;

				case 'params':
					$this->query[$segment_name] = $this->join_query($segment_values, '', ' ', 'strtolower');
				break;

				case 'order_by':
					$this->query[$segment_name] = $this->join_query($segment_values, 'order by', ',', 'quote_order');
				break;

				case 'group_by':
					$this->query[$segment_name] = $this->join_query($segment_values, 'group by', ',');
				break;

				case 'join':
					$this->query[$segment_name] = $this->join_join_style_query($segment_values);
				break;

				default:
					$this->query[$segment_name] = $this->join_query($segment_values, $segment_name, ',');
				break;

			}

			$this->update[$segment_name] = false;
		}

		return $this->last_query = 'select' . join('', $this->query);
	}

	public function set_query($query_string)
	{
		$this->const_query = $query_string;
	}

	public function unset_field($field_name = null)
	{
		if (is_null($field_name))
		{
			$field_name = $this->id_column;
		}

		if (($index = array_search($field_name, $this->elements['fields'])) !==  false)
		{
			unset($this->elements['fields'][$index]);
		}

		return $this;
	}

	protected function append($segment_name, $parameters)
	{
		$this->elements[$segment_name] = array_merge($this->elements[$segment_name], $this->$segment_name($parameters));
	}

	protected function set($segment_name, $parameters)
	{
		$this->elements[$segment_name] = $this->$segment_name($parameters);
	}

	protected function fields(array $parameters)
	{
		return $this->split_simple($parameters, $this->id_column);
	}

	protected function from(array $parameters)
	{
		return $this->split_simple($parameters, $this->table_name, true);
	}

	protected function having(array $parameters)
	{
		return $this->split_advanced($parameters, 3, array(self::WHERE_AND, self::WHERE_OR), self::WHERE_AND);
	}

	protected function join(array $parameters)
	{
		$join_type = & $parameters[0]; $join_table = & $parameters[1];
		$join_condition_column = & $parameters[2]; $join_condition_operator = & $parameters[3]; $join_condition_value = & $parameters[4];

		array_push($this->used_tables, $join_table);

		$join_type = strtolower($join_type);
		$join_table = $this->quote_table($join_table);
		$join_condition_column = $this->quote_identifier($join_condition_column);
		$join_condition_operator = strtolower($join_condition_operator);
		$join_condition_value = $this->quote_identifier($join_condition_value);

		return array($parameters);
	}

	protected function limit(array $parameters)
	{
		return $parameters;
	}

	protected function group_by(array $parameters)
	{
		return $this->split_simple($parameters, '', false);
	}

	protected function params(array $parameters)
	{
		return $this->split_simple($parameters, '', true);
	}

	protected function order_by(array $parameters)
	{
		return $this->split_simple($parameters, '', false);
	}

	protected function where(array $parameters)
	{
		return $this->split_advanced($parameters, 3, array(self::WHERE_AND, self::WHERE_OR), self::WHERE_AND);
	}

	protected function sub_where($parameters)
	{
		array_push($this->elements['where'], $this->split_sub_segment($parameters, 3, array(self::WHERE_AND, self::WHERE_OR), self::WHERE_AND));
	}

	protected function sub_having($parameters)
	{
		array_push($this->elements['having'], $this->split_sub_segment($parameters, 3, array(self::WHERE_AND, self::WHERE_OR), self::WHERE_AND));
	}

	private function join_query($segment, $segment_preffix, $glue, $quote_func = 'quote_identifier')
	{
		if ( ! empty($quote_func))
		{
			foreach ($segment as & $parameter)
			{
				$parameter = $this->$quote_func($parameter);
			}
		}

		return $segment_preffix . ' ' . implode($glue, $segment) . ' ';
	}

	private function join_where_style_query($segment, $segment_preffix)
	{
		$first_segment_item = array_shift($segment);
		array_shift($first_segment_item);

		$compile_query_item = $segment_preffix . ' ' . implode(' ', $first_segment_item) . ' ';

		foreach ($segment as $segment_item)
		{
			if (is_array($segment_item[1]))
			{
				$compile_query_item .= $segment_item[0] . ' (' . $this->join_where_style_query($segment_item[1], '') . ') ';
			}
			else
			{
				$compile_query_item .= implode(' ', $segment_item) . ' ';
			}
		}

		return $compile_query_item;
	}

	private function join_join_style_query($segment)
	{
		$compile_query_item = '';

		foreach ($segment as $segment_item)
		{
			$segment_item[2] = 'on ' . $segment_item[2];
			$compile_query_item .= implode(' ', $segment_item) . ' ';
		}

		return $compile_query_item;
	}

	private function get_condition($condition_block, $log_op)
	{
		array_unshift($condition_block, $log_op);

		$condition_column = & $condition_block[1]; $condition_operator = & $condition_block[2]; $condition_value = & $condition_block[3];

		$condition_column = $this->quote_identifier($condition_column);
		$condition_operator = strtolower($condition_operator);
		$condition_value = $this->quote($condition_value);

		return $condition_block;
	}

	private function split_advanced($parameters, $condition_block_size, array $log_op_list = array(), $default_log_op = '')
	{
		$conditions = array();

		while (count($parameters))
		{
			$log_op = array_shift($parameters);
			if ( ! in_array($log_op, $log_op_list))
			{
				array_unshift($parameters, $log_op);
				$log_op = $default_log_op;
			}

			$conditions[] = $this->get_condition(array_splice($parameters, 0, $condition_block_size), $log_op);
		}

		return $conditions;
	}

	private function split_sub_segment($parameters, $condition_block_size, array $log_op_list = array(), $default_log_op = '')
	{
		$log_op = array_shift($parameters);
		if ( ! in_array($log_op, $log_op_list))
		{
			array_unshift($parameters, $log_op);
			$log_op = $default_log_op;
		}

		return array($log_op, $this->split_advanced($parameters, $condition_block_size, $log_op_list, $default_log_op));
	}

	private function split_simple($parameters, $add_item = '', $sorting = true)
	{
		if ( ! empty($add_item) && ! in_array($add_item, $parameters))
		{
			array_push($parameters, $add_item);
		}

		if ($sorting)
		{
			sort($parameters);
		}

		return $parameters;
	}

	static public $allowed_segments = array('params', 'fields', 'from', 'join', 'where', 'group_by', 'having', 'order_by', 'limit');

	static public function strtolower($value)
	{
		return strtolower($value);
	}

	static public function quote($value)
	{
		return DTC::driver()->quote($value);
	}

	static public function quote_identifier($value, $quote_table = false)
	{
		return DTC::driver()->quote_identifier($value, $quote_table);
	}

	static public function quote_array(array $value, $glue = ',')
	{
		sort($value);

		foreach ($value as & $value_item)
		{
			$value_item = self::quote_identifier($value_item);
		}

		return implode($glue, $value);
	}

	static public function quote_table($value)
	{
		if (str_word_count($value, 0, '_') == 2)
		{
			$value = str_word_count($value, 1, '_');
		}

		return self::quote_identifier($value, true);
	}

	static public function quote_order($value)
	{
		if (str_word_count($value, 0, '_') == 2)
		{
			list($column, $order) =  str_word_count($value, 1, '_');
			return self::quote_identifier($column) . ' ' . strtolower($order);
		}
		else
		{
			return self::quote_identifier($value);
		}
	}

}

?>
