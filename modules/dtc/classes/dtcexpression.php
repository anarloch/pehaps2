<?php

defined('SYSPATH') or die('No direct script access.');

class DTCExpression extends Database_Expression
{

	protected $expression;

	public function __construct($value)
	{
		$this->expression = $value;
	}

	public function get()
	{
		return $this->value();
	}

	public function value()
	{
		return (string) $this->expression;
	}

	public function __toString()
	{
		return $this->value();
	}

}