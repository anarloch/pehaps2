<?php

defined('SYSPATH') or die('No direct script access.');

abstract class DTCViewModel extends DTCModel
{
	
	public function delete()
	{
		throw new DatabaseTableCore_Exception('The view >>:view<< is readonly - you cannot delete this instance.', array(':view' => $this->table_name));		
	}

	public function save()
	{
		throw new DatabaseTableCore_Exception('The view >>:view<< is readonly - you cannot save this instance.', array(':view' => $this->table_name));
	}

}

?>
