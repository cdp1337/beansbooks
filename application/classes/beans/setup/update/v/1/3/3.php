<?php defined('SYSPATH') or die('No direct script access.');
/*
BeansBooks
Copyright (C) System76, Inc.

This file is part of BeansBooks.

BeansBooks is free software; you can redistribute it and/or modify
it under the terms of the BeansBooks Public License.

BeansBooks is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the BeansBooks Public License for more details.

You should have received a copy of the BeansBooks Public License
along with BeansBooks; if not, email info@beansbooks.com.	
*/

class Beans_Setup_Update_V_1_3_3 extends Beans_Setup_Update_V {

	public function __construct($data = NULL)
	{
		parent::__construct($data);
	}
	
	protected function _execute()
	{
		// Increase the maximum width of reference, alt_reference, and aux_reference from 16 to 32 characters.
		// This is partially because Amazon uses 19-character invoice numbers.
		$this->_db_update_table_column('forms', 'reference', ' `reference` VARCHAR(32) NULL DEFAULT NULL');
		$this->_db_update_table_column('forms', 'alt_reference', ' `alt_reference` VARCHAR(32) NULL DEFAULT NULL');
		$this->_db_update_table_column('forms', 'aux_reference', ' `aux_reference` VARCHAR(32) NULL DEFAULT NULL');

		// Increase precision of lines to 4 to support > $0.01 prices
		$this->_db_update_table_column('form_lines', 'amount', '`amount` decimal(15,4) DEFAULT NULL');

		return (object)array();
	}
}

