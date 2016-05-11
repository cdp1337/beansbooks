<?php defined('SYSPATH') or die('No direct access allowed.');
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

class Controller_Dash_Json extends Controller_Json {

	public function action_incomeexpense()
	{
		$date_start = $this->request->post('date_start');
		$date_end = $this->request->post('date_end');

		if( ! $date_start )
		{
			if( Session::instance()->get('dash_incomeexpense_date_start') )
				$date_start = Session::instance()->get('dash_incomeexpense_date_start');
			else
				$date_start = date("Y-m",strtotime("-5 Months")).'-01';
		}

		if( ! $date_end )
		{
			if( Session::instance()->get('dash_incomeexpense_date_end') )
				$date_end = Session::instance()->get('dash_incomeexpense_date_end');
			else
				$date_end = date("Y-m-d");
		}

		Session::instance()->set('dash_incomeexpense_date_start',$date_start);
		Session::instance()->set('dash_incomeexpense_date_end',$date_end);

		$report_budget = new Beans_Report_Budget($this->_beans_data_auth((object)array(
			'date_start' => $date_start,
			'date_end' => $date_end,
		)));
		$report_budget_result = $report_budget->execute();

		if( ! $report_budget_result->success )
			return $this->_return_error($this->_beans_result_get_error($report_budget_result));
		
		// Format $report_budget_result
		$this->_return_object->data->date_ranges = array();
		foreach( $report_budget_result->data->date_ranges as $date_range )
			$this->_return_object->data->date_ranges[] = date("M",strtotime($date_range->date_start));

		$this->_return_object->data->expense_data = array();
		foreach( $report_budget_result->data->account_types['expense_total']->date_ranges as $index => $amount )
			$this->_return_object->data->expense_data[$index] = round($amount - $report_budget_result->data->account_types['costofgoods_subtotal']->date_ranges[$index],2,PHP_ROUND_HALF_UP);

		$this->_return_object->data->income_data = $report_budget_result->data->account_types['income_subtotal']->date_ranges;
		//$this->_return_object->data->expense_data = $report_budget_result->data->account_types['expense_total']->date_ranges;
	}

	public function action_incomedaterange()
	{
		$date_start = $this->request->post('date_start');
		$date_end = $this->request->post('date_end');

		if( ! $date_start )
		{
			if( Session::instance()->get('dash_income_date_start') )
				$date_start = Session::instance()->get('dash_income_date_start');
			else
				$date_start = date("Y-m",strtotime("-11 Months")).'-01';
		}

		if( ! $date_end )
		{
			if( Session::instance()->get('dash_income_date_end') )
				$date_end = Session::instance()->get('dash_income_date_end');
			else
				$date_end = date("Y-m-d");
		}

		if( strtotime($date_end) < strtotime($date_start) )
		{
			$date_start = date("Y-m",strtotime("-11 Months")).'-01';
			$date_end = date("Y-m-d");
		}

		Session::instance()->set('dash_income_date_start',$date_start);
		Session::instance()->set('dash_income_date_end',$date_end);

		$date_counter = $date_start;

		$this->_return_object->data->date_ranges = array();
		$this->_return_object->data->income = array();
		$this->_return_object->data->gross_income = array();
		$this->_return_object->data->expenses = array();
		$this->_return_object->data->net_income = array();

		while( strtotime($date_counter) < strtotime($date_end) )
		{
			$report_date_start = date("Y-m",strtotime($date_counter))."-01";
			$report_date_end = date("Y-m-t",strtotime($date_counter));
			
			if( strtotime($report_date_end) > strtotime($date_end) )
				$report_date_end = $date_end;

			$report_income = new Beans_Report_Income($this->_beans_data_auth((object)array(
				'date_start' => $report_date_start,
				'date_end' => $report_date_end,
			)));
			$report_income_result = $report_income->execute();

			if( ! $report_income_result->success )
				return $this->_return_error($this->_beans_result_get_error($report_income_result));
			
			$this->_return_object->data->date_ranges[] = date("M",strtotime($report_date_start));
			$this->_return_object->data->income[] = $report_income_result->data->account_types['income']->balance;
			$this->_return_object->data->gross_income[] = $report_income_result->data->account_types['gross']->balance;
			$this->_return_object->data->expense[] = $report_income_result->data->account_types['expense']->balance;
			$this->_return_object->data->net_income[] = $report_income_result->data->account_types['net']->balance;

			$date_counter = date("Y-m",strtotime($date_counter." +1 Month"))."-01";
		}

		if( count($this->_return_object->data->date_ranges) == 1 ) 
		{
			$this->_return_object->data->date_ranges[] = $this->_return_object->data->date_ranges[0];
			$this->_return_object->data->income[] = $this->_return_object->data->income[0];
			$this->_return_object->data->gross_income[] = $this->_return_object->data->gross_income[0];
			$this->_return_object->data->expense[] = $this->_return_object->data->expense[0];
			$this->_return_object->data->net_income[] = $this->_return_object->data->net_income[0];
		}
	}

	public function action_monthlyexpenses()
	{
		$date = $this->request->post('date');

		if( ! $date )
		{
			if( Session::instance()->get('dash_expense_date') )
				$date = Session::instance()->get('dash_expense_date');
			else
				$date = date("Y-m-d");
		}

		Session::instance()->set('dash_expense_date',$date);

		$report_budget = new Beans_Report_Budget($this->_beans_data_auth((object)array(
			'date_start' => date("Y-m",strtotime($date))."-01",
			'date_end' => date("Y-m-t",strtotime($date)),
		)));
		$report_budget_result = $report_budget->execute();

		if( ! $report_budget_result->success )
			return $this->_return_error($this->_beans_result_get_error($report_budget_result));
		
		$this->_return_object->data->expense_data = array();

		$other_total = 0.00;
		$other_cutoff = round($report_budget_result->data->account_types['expense_total']->date_ranges[0] / 20,0,PHP_ROUND_HALF_UP);

		foreach( $report_budget_result->data->account_types['expense'] as $account )
		{
			if( $account->date_ranges[0] AND 
				$other_cutoff > $account->date_ranges[0] )
				$other_total = round( $other_total + $account->date_ranges[0] );
			else if( $account->date_ranges[0] )
				$this->_return_object->data->expense_data[] = (object)array(
					'name' => $account->name,
					'amount' => $account->date_ranges[0],
				);
		}

		if( $other_total )
			$this->_return_object->data->expense_data[] = (object)array(
				'name' => "Other",
				'amount' => $other_total,
			);
	}

	public function action_closebooks()
	{
		$include_account_ids = array();
		if( $this->request->post('include_account_ids') )
		{
			foreach( explode(',', $this->request->post('include_account_ids')) as $include_account_id )
			{
				if( trim($include_account_id) )
					$include_account_ids[] = $include_account_id;
			}
		}

		$account_closebooks = new Beans_Account_Closebooks($this->_beans_data_auth((object)array(
			'date' => $this->request->post('date'),
			'transfer_account_id' => $this->request->post('transfer_account_id'),
			'include_account_ids' => $include_account_ids
		)));
		$account_closebooks_result = $account_closebooks->execute();

		if( ! $account_closebooks_result->success )
			return $this->_return_error($this->_beans_result_get_error($account_closebooks_result));

		$message = new View_Partials_Dash_Message;

		$message->title = "Year End - Close Books";
		$message->text = "The books have been closed for all transactions before ".$account_closebooks_result->data->transaction->date.'.';

		$this->_return_object->data->message = $message->render();
	}

	public function action_loadtaxpayments()
	{
		$tax_payment_search_data = new stdClass;
		$tax_payment_search_data->sort_by = 'newest';
		$tax_payment_search_data->page_size = 24;
		$tax_payment_search_data->search_tax_id = $this->request->post('tax_id');

		if( ! $tax_payment_search_data->search_tax_id )
			return $this->_return_error("Please select a tax.");
		
		$tax_payment_search = new Beans_Tax_Payment_Search($this->_beans_data_auth($tax_payment_search_data));

		$tax_payment_search_result = $tax_payment_search->execute();

		if( ! $tax_payment_search_result->success )
			return $this->_return_error($tax_payment_search_result->error);

		$this->_return_object = $tax_payment_search_result;
	}

}