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
@action Beans_Vendor_Purchase_Invoice
@description Convert a vendor purchase into an invoice ( age an invoice ).
@required auth_uid
@required auth_key
@required auth_expiration
@required id INTEGER The ID for the #Beans_Vendor_Purchase#.
@required date_billed STRING The bill date in YYYY-MM-DD for the invoice.
@optional invoice_number STRING
@optional so_number STRING
@optional invoice_amount DECIMAL The amount on the invoice ( if it differs from the purchase order total ).
@optional adjustment_description STRING An explanation for the discrepency and adjusting entry.
@optional adjustment_account_id INTEGER The ID of the #Beans_Account# that will handle the balance of the adjustment.
@returns purchase OBJECT The updated #Beans_Vendor_Purchase#.
---BEANSENDSPEC---
*/
class Beans_Vendor_Purchase_Invoice extends Beans_Vendor_Purchase {

	protected $_auth_role_perm = "vendor_purchase_write";

	protected $_id;
	protected $_purchase;
	protected $_date_billed;
	protected $_invoice_number;
	protected $_so_number;						// Optional
	protected $_invoice_amount;					// Optional - if not provided assume the full amount.
	protected $_invoice_adjustment_description;	// Required if invoice amount != the purchase total. 
	protected $_invoice_adjustment_account_id;	// Same

	protected $_validate_only;

	protected $_transaction_purchase_account_id;
	protected $_transaction_purchase_line_account_id;

	public function __construct($data = NULL)
	{
		parent::__construct($data);

		$this->_validate_only = ( isset($data->validate_only) AND 
								  $data->validate_only )
							  ? TRUE
							  : FALSE;
		
		$this->_id = ( isset($data->id) ) 
				   ? (int)$data->id
				   : 0;

		$this->_purchase = $this->_load_vendor_purchase($this->_id);

		$this->_date_billed = ( isset($data->date_billed) )
							? $data->date_billed
							: date("Y-m-d");

		$this->_invoice_number = ( isset($data->invoice_number) AND 
								   $data->invoice_number )
							   ? $data->invoice_number
							   : FALSE;

		$this->_so_number = ( isset($data->so_number) )
						  ? $data->so_number
						  : FALSE;

		$this->_invoice_amount = ( isset($data->invoice_amount) )
							   ? $data->invoice_amount
							   : FALSE;

		$this->_invoice_adjustment_description = ( isset($data->adjustment_description) )
											   ? $data->adjustment_description
											   : FALSE;

		$this->_invoice_adjustment_account_id = ( isset($data->adjustment_account_id) )
											  ? $data->adjustment_account_id
											  : FALSE;

		$this->_transaction_purchase_account_id = $this->_beans_setting_get('purchase_default_account_id');
		$this->_transaction_purchase_line_account_id = $this->_beans_setting_get('purchase_default_line_account_id');
	}

	protected function _execute()
	{
		if( ! $this->_purchase->loaded() )
			throw new Exception("That purchase could not be found.");

		if( $this->_purchase->date_cancelled )
			throw new Exception("A purchase cannot be converted to an invoice after it has been cancelled.");

		if( $this->_purchase->date_billed )
			throw new Exception("That purchase has already been converted to an invoice.");

		if( ! $this->_transaction_purchase_account_id )
			throw new Exception("INTERNAL ERROR: Could not find default PO account.");

		if( ! $this->_transaction_purchase_line_account_id )
			throw new Exception("INTERNAL ERROR: Could not find default PO Line account.");

		if( $this->_date_billed != date("Y-m-d",strtotime($this->_date_billed)) )
			throw new Exception("Invalid invoice date: must be in YYYY-MM-DD format.");

		if( strtotime($this->_date_billed) < strtotime($this->_purchase->date_created) )
			throw new Exception("Invalid invoice date: must be on or after the creation date of ".$this->_purchase->date_created.".");

		if( $this->_invoice_number AND 
			strlen($this->_invoice_number) > 16 )
			throw new Exception("Invalid invoice number: maximum of 16 characters.");

		if( $this->_so_number AND 
			strlen($this->_so_number) > 16 )
			throw new Exception("Invalid SO number: maximum of 16 characters.");

		// Figure out if we need to create an adjusting entry.
		if( $this->_invoice_amount AND 
			$this->_purchase->total != $this->_invoice_amount )
		{
			if( ! $this->_invoice_adjustment_description )
				throw new Exception("Invalid invoice adjustment description: none provided.");

			if( ! $this->_invoice_adjustment_account_id )
				throw new Exception("Invalid invoice adjustment writeoff account: none provided.");

			// Add line to purchase.
			$purchase_lines = array();
			foreach( $this->_purchase->form_lines->find_all() as $line )
			{
				$purchase_lines[] = (object)array(
					'description' => $line->description,
					'account_id' => $line->account_id,
					'quantity' => $line->quantity,
					'amount' => $line->amount,
				);
			}

			$purchase_lines[] = (object)array(
				'description' => $this->_invoice_adjustment_description,
				'account_id' => $this->_invoice_adjustment_account_id,
				'quantity' => 1,
				'amount' => $this->_beans_round( $this->_invoice_amount - $this->_purchase->total ),
				'adjustment' => TRUE,
			);

			if( ! $this->_validate_only )
			{
				$vendor_purchase_update = new Beans_Vendor_Purchase_Update($this->_beans_data_auth((object)array(
					'id' => $this->_purchase->id,
					'lines' => $purchase_lines,
				)));
				$vendor_purchase_update_result = $vendor_purchase_update->execute();

				if( ! $vendor_purchase_update_result->success )
					throw new Exception("Could not adjust purchase: ".
										$vendor_purchase_update_result->error);

				// Re-load purchase.
				$this->_purchase = $this->_load_vendor_purchase($this->_purchase->id);
			}
		}

		if( $this->_validate_only )
			return (object)array();

		$this->_purchase->date_billed = $this->_date_billed;
		$this->_purchase->date_due = date("Y-m-d",strtotime($this->_purchase->date_billed.' +'.$this->_purchase->account->terms.' Days'));
		
		if( strlen($this->_invoice_number) )
			$this->_purchase->aux_reference = $this->_invoice_number;
		
		if( $this->_so_number )
			$this->_purchase->reference = $this->_so_number;

		$this->_purchase->save();

		$purchase_calibrate = new Beans_Vendor_Purchase_Calibrate($this->_beans_data_auth((object)array(
			'ids' => array($this->_purchase->id),
		)));
		$purchase_calibrate_result = $purchase_calibrate->execute();

		$this->_purchase = $this->_load_vendor_purchase($this->_purchase->id);

		if( ! $purchase_calibrate_result->success )
		{
			$this->_purchase->date_billed = NULL;
			$this->_purchase->date_due = NULL;
			$this->_purchase->aux_reference = NULL;
			$this->_purchase->reference = NULL;
			$this->_purchase->save();

			throw new Exception("Error trying to invoice purchase: ".$purchase_calibrate_result->error);
		}

		// Recalibrate Payments 
		$vendor_payment_calibrate = new Beans_Vendor_Payment_Calibrate($this->_beans_data_auth((object)array(
			'form_ids' => array($this->_purchase->id),
		)));
		$vendor_payment_calibrate_result = $vendor_payment_calibrate->execute();

		if( ! $vendor_payment_calibrate_result->success )
			throw new Exception("Error encountered when calibrating payments: ".$vendor_payment_calibrate_result->error);
		
		$this->_purchase = $this->_load_vendor_purchase($this->_purchase->id);
		
		return (object)array(
			"purchase" => $this->_return_vendor_purchase_element($this->_purchase),
		);
	}
}