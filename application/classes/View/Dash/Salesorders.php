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


class View_Dash_Salesorders extends View_Template {
	
	public function report_date()
	{
		if( ! isset($this->report_salesorders_result) )
			return FALSE;

		return $this->report_salesorders_result->data->date;
	}

	public function report_customer_name()
	{
		if( ! isset($this->report_salesorders_result) )
			return FALSE;

		if( ! $this->report_salesorders_result->data->customer_id OR 
		 	count($this->report_salesorders_result->data->customers) > 1 )
			return FALSE;

		foreach( $this->report_salesorders_result->data->customers as $customer_id => $customer )
			return $customer->customer_name;
	}

	public function balance_filter_options()
	{
		$return_array = array();

		$return_array[] = array(
			'name' => "All",
			'value' => "",
			'selected' => ( ! $this->report_salesorders_result->data->balance_filter ? TRUE : FALSE ),
		);

		$return_array[] = array(
			'name' => "Unpaid",
			'value' => "unpaid",
			'selected' => ( $this->report_salesorders_result->data->balance_filter == "unpaid" ? TRUE : FALSE ),
		);

		$return_array[] = array(
			'name' => "With Payment",
			'value' => "paid",
			'selected' => ( $this->report_salesorders_result->data->balance_filter == "paid" ? TRUE : FALSE ),
		);

		return $return_array;
	}

	public function days_old_minimum_options()
	{
		$return_array = array();

		$return_array[] = array(
			'days' => 15,
		);

		$return_array[] = array(
			'days' => 30,
		);

		$return_array[] = array(
			'days' => 45,
		);

		$return_array[] = array(
			'days' => 60,
		);

		$return_array[] = array(
			'days' => 90,
		);

		if( ! isset($this->report_salesorders_result) )
			return $return_array;

		foreach( $return_array as $index => $option )
			$return_array[$index]['selected'] = ( $option['days'] == $this->report_salesorders_result->data->days_old_minimum ) ? TRUE : FALSE;

		return $return_array;
	}

	public function report_customer_id()
	{
		if( ! isset($this->report_salesorders_result) )
			return FALSE;

		if( ! $this->report_salesorders_result->data->customer_id OR 
		 	count($this->report_salesorders_result->data->customers) > 1 )
			return FALSE;

		foreach( $this->report_salesorders_result->data->customers as $customer_id => $customer )
			return $customer_id;
	}

	public function report_days_late_minimum()
	{
		if( ! isset($this->report_salesorders_result) )
			return FALSE;

		return $this->report_salesorders_result->data->days_late_minimum;
	}

	public function report_totals()
	{
		$return_array = array();

		$return_array['total_total_formatted'] = 
			( $this->report_salesorders_result->data->total_total < 0 ? '<span class="text-red">-' : '' ).
			number_format(abs($this->report_salesorders_result->data->total_total),2,'.',',').
			( $this->report_salesorders_result->data->total_total < 0 ? '</span>' : '' );

		$return_array['paid_total_formatted'] = 
			( $this->report_salesorders_result->data->paid_total < 0 ? '<span class="text-red">-' : '' ).
			number_format(abs($this->report_salesorders_result->data->paid_total),2,'.',',').
			( $this->report_salesorders_result->data->paid_total < 0 ? '</span>' : '' );

		$return_array['balance_total_formatted'] = 
			( $this->report_salesorders_result->data->balance_total < 0 ? '<span class="text-red">-' : '' ).
			number_format(abs($this->report_salesorders_result->data->balance_total),2,'.',',').
			( $this->report_salesorders_result->data->balance_total < 0 ? '</span>' : '' );

		$return_array['customer_count'] = count($this->report_salesorders_result->data->customers);

		return $return_array;
	}

	public function customer_reports()
	{
		if( ! isset($this->report_salesorders_result) )
			return FALSE;

		$return_array = array();

		foreach( $this->report_salesorders_result->data->customers as $customer_report )
		{
			$return_array[] = $this->_customer_report_array($customer_report);
		}

		return $return_array;
	}

	public function _customer_report_array($customer_report)
	{
		$settings = $this->beans_settings();

		$return_array = array();

		$return_array['company_name'] = $customer_report->customer_company_name;
		$return_array['customer_name'] = $customer_report->customer_name;
		$return_array['phone_number'] = $customer_report->customer_phone_number;
		
		$return_array['customer_total_total_formatted'] = 
			( $customer_report->total_total < 0 ? '<span class="text-red">-' : '' ).
			number_format(abs($customer_report->total_total),2,'.',',').
			( $customer_report->total_total < 0 ? '</span>' : '' );

		$return_array['customer_paid_total_formatted'] = 
			( $customer_report->paid_total < 0 ? '<span class="text-red">-' : '' ).
			number_format(abs($customer_report->paid_total),2,'.',',').
			( $customer_report->paid_total < 0 ? '</span>' : '' );

		$return_array['customer_balance_total_formatted'] = 
			( $customer_report->balance_total < 0 ? '<span class="text-red">-' : '' ).
			number_format(abs($customer_report->balance_total),2,'.',',').
			( $customer_report->balance_total < 0 ? '</span>' : '' );

		$return_array['sales'] = array();

		foreach( $customer_report->sales as $sale )
			$return_array['sales'][] = $this->_customer_report_sale_array($sale);

		return $return_array;
	}

	public function _customer_report_sale_array($sale)
	{
		$return_array = array();

		$return_array['date_created'] = $sale->date_created;
		$return_array['sale_id'] = $sale->id;
		$return_array['sale_number'] = $sale->sale_number;
		$return_array['date_due'] = $sale->date_due;
		$return_array['days_late'] = ( $sale->days_late > 0 ) ? $sale->days_late : FALSE;
		
		$return_array['total_formatted'] = 
			( $sale->total < 0 ? '<span class="text-red">-' : '' ).
			number_format(abs($sale->total),2,'.',',').
			( $sale->total < 0 ? '</span>' : '' );

		$return_array['paid_formatted'] = 
			( $sale->paid < 0 ? '<span class="text-red">-' : '' ).
			number_format(abs($sale->paid),2,'.',',').
			( $sale->paid < 0 ? '</span>' : '' );
			
		$return_array['balance_formatted'] = 
			( $sale->balance < 0 ? '<span class="text-red">-' : '' ).
			number_format(abs($sale->balance),2,'.',',').
			( $sale->balance < 0 ? '</span>' : '' );

		return $return_array;		
	}


}