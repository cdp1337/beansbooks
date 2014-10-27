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

/*
---BEANSAPISPEC---
@action Beans_Account_Closebooks_Check
@description Check if the books are ready to be closed for the previous financial period.
@required auth_uid
@required auth_key
@required auth_expiration
@returns ready BOOL 
---BEANSENDSPEC---
*/
class Beans_Account_Closebooks_Check extends Beans_Account {

	protected $_auth_role_perm = "account_write";

	protected $_date;
	
	public function __construct($data = NULL)
	{
		parent::__construct($data);

		$this->_date = ( isset($data->date) )
					 ? $data->date
					 : NULL;
	}

	protected function _execute()
	{
		// TODO - Allow specifying the exact year to check for close.
		$fye = ( $this->_beans_setting_get('company_fye') )
			 ? $this->_beans_setting_get('company_fye')
			 : "12-31";
		
		// Determine the appropriate year to check.
		$last_closebooks = ORM::Factory('transaction')->where('close_books','IS NOT',NULL)->order_by('close_books','desc')->find();

		$fye_date = NULL;
		if( $last_closebooks->loaded() )
		{
			// Next Year FYE
			$fye_date = (intval(substr($last_closebooks->close_books,0,4))+1).'-'.$fye;
		}
		else
		{
			// Oldest Year
			$first_transaction = ORM::Factory('transaction')->order_by('date','asc')->find();

			if( ! $first_transaction->loaded() )
				return (object)array(
					'ready' => FALSE,
					'fye_date' => FALSE,
				);

			$fye_date = substr($first_transaction->date,0,4).'-'.$fye;
		}

		if( $fye_date === NULL )
			return (object)array(
				'ready' => FALSE,
				'fye_date' => FALSE,
			);

		// Prevent from showing on last date of the fiscal year.
		if( $fye_date == date("Y-m-d") )
			return (object)array(
				'ready' => FALSE,
				'fye_date' => FALSE,
			);

		if( strtotime($fye_date) > time() )
			$fye_date = date("Y",strtotime("-1 Year")).'-'.$fye;
		
		$fye_date_next_day = date("Y-m-d",strtotime($fye_date.' +1 Day'));
		$close_books = substr($fye_date,0,7).'-00';

		$transactions_exist = ORM::Factory('transaction')->where('date','<=',$fye_date)->order_by('date','DESC')->find();

		if( ! $transactions_exist->loaded() )
			return (object)array(
				'ready' => FALSE,
				'fye_date' => FALSE,
			);

		$closebooks_exist = ORM::Factory('transaction')->where('close_books','=',$close_books)->find();

		if( $closebooks_exist->loaded() )
			return (object)array(
				'ready' => FALSE,
				'fye_date' => FALSE,
			);

		return (object)array(
			'ready' => TRUE,
			'fye_date' => $fye_date
		);
	}

}