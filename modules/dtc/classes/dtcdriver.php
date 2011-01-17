<?php

defined('SYSPATH') or die('No direct script access.');

abstract class DTCDriver
{
	protected $db;

	function __construct()
	{
		$this->db = Database::instance(DTC::config()->db_driver);
	}

	abstract public function insert($table, array $values, $primary_column, $primary_autoincrement);

	abstract public function delete($table, $condition, $primary_column, $used_tables = array());

	abstract public function update($table, array $values, $condition, $primary_column);

	abstract public function select(DTCQuery $query, $used_tables);

	abstract public function select_init($table, $condition, array $columns, $primary_column);

	abstract public function quote($value);

	abstract public function count_all_items(DTCQuery $query);

	abstract public function validate_rule($parameters);

	public function list_columns($table)
	{
		return $this->db->list_columns($table);
	}

	public function last_query()
	{
		return $this->db->last_query;
	}

	public function quote_identifier($value, $quote_table = false)
	{
		if (is_object($value))
		{
			if ($value instanceof DTCQuery)
			{
				return ' (' . $value->get_query() . ') ';
			}
			elseif ($value instanceof DTCExpression)
			{
				return $value->get();
			}
			else
			{
				return self::quote_identifier((string) $value);
			}
		}
		elseif (is_array($value) || stripos($value, ' as ') !== false)
		{
			if (is_string($value))
			{
				$value = explode(' as ', strtolower($value));
			}

			list($identifier, $alias) = $value;
			return self::quote_identifier($identifier) . ($quote_table === true ? ' ' : ' as ') . self::quote_identifier($alias);
		}

		if (strpos($value, '"') !== false)
		{
			return preg_replace('/"(.+?)"/e', '$this->quote_identifier("$1")', $value);
		}
		elseif (strpos($value, '.') !== false)
		{
			return implode('.', array_map(array($this, 'quote_identifier'), explode('.', $value)));
		}
		else
		{
			return self::$quote_char . strtolower($value) . self::$quote_char;
		}

		return $value;
	}
	
	static public $quote_char = '';

}

?>
