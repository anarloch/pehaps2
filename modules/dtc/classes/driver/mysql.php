<?php

defined('SYSPATH') or die('No direct script access.');

class Driver_Mysql extends DTCDriver
{
	public function insert($table, array $values, $primary_column, $primary_autoincrement)
	{
		return $this->update($table, $values, null, $primary_column);
	}

	public function delete($table, $condition, $primary_column, $used_tables = array())
	{
		if (is_array($condition))
		{
			return $this->db->query(Database::DELETE, 'delete from ' . DTCQuery::quote_table($table) . ' where ' . DTCQuery::quote_identifier($primary_column) . ' in (' . implode(',', $condition) . ') limit ' . count($condition), true, $used_tables);
		}

		return $this->db->query(Database::DELETE, 'delete from ' . DTCQuery::quote_table($table) . ' where ' . DTCQuery::quote_identifier($primary_column) . ' = ' . $condition . ' limit 1', true, array($table));
	}

	public function select(DTCQuery $query, $used_tables)
	{
		return $this->db->query(Database::SELECT, $query->get_query(), true, $used_tables);
	}

	public function select_init($table, $condition, array $columns, $primary_column)
	{
		if (is_scalar($condition))
		{
			$init_query = 'select ' . DTCQuery::quote_array(array_keys($columns)) . ' from ' . DTCQuery::quote_table($table) . ' where ' . DTCQuery::quote_identifier($primary_column) . ' = ' . $condition . ' limit 1';
		}
		elseif (is_array($condition))
		{
			for ($conditions = array(); list($field_name, $field_value) = each($condition); array_push($conditions, DTCQuery::quote_identifier($field_name) . ' = ' . DTCQuery::quote($field_value)))
				;

			$init_query = 'select ' . DTCQuery::quote_array(array_keys($columns)) . ' from ' . DTCQuery::quote_table($table) . ' where ' . implode(' and ', $conditions) . ' limit 1';
		}

		return Arr::get($this->db->query(Database::SELECT, $init_query, true, array($table)), 0);
	}

	public function update($table, array $values, $condition, $primary_column)
	{
		foreach ($values as $query_column_name => $query_value_name)
		{
			$query_set_segment_array[$query_column_name] = $query_column_name . ' = ' . $query_value_name;
		}

		$query_set_segment = implode(',', $query_set_segment_array);

		return $this->db->query(Database::INSERT, 'insert into ' . DTCQuery::quote_table($table) . ' set ' . $query_set_segment . ' on duplicate key update ' . $query_set_segment, true, array($table));
	}

	public function quote($value)
	{
		switch(gettype($value))
		{

			default:
			case 'string':
				if (($value = mysql_real_escape_string((string) $value)) === false)
					throw new Database_Exception(':error', array(':error' => mysql_errno()));

				return "'$value'";
			break;

			case 'integer':
			case 'boolean':
				return (int) $value;
			break;

			case 'double':
				return sprintf('%F', $value);
			break;

			case 'NULL':
				return 'null';
			break;

			case 'array':
				return '(' . implode(',', array_map(array($this, 'quote'), $value)) . ')';
			break;

			case 'object':
				if ($value instanceof DTCQuery)
				{
					return '(' . $value->get_query() . ')';
				}
				elseif ($value instanceof DTCExpression)
				{
					return $value->get();
				}
				else
				{
					return $this->quote((string) $value);
				}
			break;

		}

		return $value;
	}

	public function count_all_items(DTCQuery $query)
	{
		$query->fields('count(*) as count')->order_by()->limit()->unset_field()->params('SQL_NO_CACHE');
		return Arr::get($this->select($query, $query->get_tables_list()), 0)->count;
	}

	public function validate_rule($parameters)
	{
		$column_rules = array();
		$range = array('min' => 0, 'max' => 0);
		$decimal = array('numeric_precision' => 0, 'numeric_scale' => 0);

		foreach ($parameters as $type => $value)
		{
			switch ($type)
			{
				case 'display':
				case 'character_maximum_length':
					if ($value)
					{
						$column_rules['max_length'] = array($value);
					}
				break;

				case 'is_nullable':
					if ( ! $value)
					{
						$column_rules['not_empty'] = array();
					}
				break;

				case 'min':
				case 'max':
					$range[$type] = $value;
				break;

				case 'numeric_precision':
				case 'numeric_scale':
					$decimal[$type] = $value;
				break;

				case 'type':
					if ($value == 'int')
					{
						$column_rules['numeric'] = array();
					}
					elseif ($value == 'float')
					{
//						$column_rules['decimal'] = array();
					}
				break;
			}
		}

		if ($range['min'] || $range['max'])
		{
			$column_rules['range'] = array_values($range);
		}
		elseif ($decimal['numeric_precision'] || $decimal['numeric_scale'])
		{
//			$column_rules['decimal'] = array_reverse(array_values($decimal));
		}

		return $column_rules;
	}

	static public $quote_char = '`';
}

?>
