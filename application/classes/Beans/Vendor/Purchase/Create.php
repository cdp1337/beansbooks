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
@action Beans_Vendor_Purchase_Create
@description Create a new vendor purchase.
@required auth_uid
@required auth_key
@required auth_expiration
@required vendor_id INTEGER The ID for the #Beans_Vendor# this will belong to.
@required account_id INTEGER The ID for the AP #Beans_Account# this purchase is being added to.
@required date_created STRING The date of the purchase in YYYY-MM-DD format.
@optional date_billed STRING The bill date in YYYY-MM-DD for the sale; adding this will automatically convert it to an invoice.
@optional invoice_number STRING An invoice number to be tied to the purchase.
@optional purchase_number STRING An purchase number to reference this purchase.  If none is created, it will auto-generate.
@optional so_number STRING An SO number to reference this purchase.
@optional quote_number STRING A quote number to reference this purchase.
@optional remit_address_id INTEGER The ID of the #Beans_Vendor_Address# to remit payment to.
@optional shipping_address_id INTEGER The ID of the #Beans_Vendor_Address_Shipping# to ship to.
@required lines ARRAY An array of objects representing line items for the purchase.
@required @attribute lines description STRING The text for the line item.
@required @attribute lines amount DECIMAL The amount per unit.
@required @attribute lines quantity DECIMAL The number of units (up to three decimal places).
@optional @attribute lines account_id INTEGER The ID of the #Beans_Account# to count the cost of the purchase towards.
@returns purchase OBJECT The resulting #Beans_Vendor_Purchase#.
---BEANSENDSPEC---
*/
class Beans_Vendor_Purchase_Create extends Beans_Vendor_Purchase {

	protected $_auth_role_perm = "vendor_purchase_write";

	protected $_data;
	protected $_purchase;
	protected $_purchase_lines;
	
	protected $_transaction_purchase_account_id;
	protected $_transaction_purchase_line_account_id;
	protected $_transaction_purchase_prepaid_purchase_account_id;

	protected $_date_billed;
	protected $_invoice_number;

	public function __construct($data = NULL)
	{
		parent::__construct($data);
		
		$this->_data = $data;
		$this->_purchase = $this->_default_vendor_purchase();
		$this->_purchase_lines = array();
		
		$this->_transaction_purchase_account_id = $this->_beans_setting_get('purchase_default_account_id');
		$this->_transaction_purchase_line_account_id = $this->_beans_setting_get('purchase_default_line_account_id');
		$this->_transaction_purchase_prepaid_purchase_account_id = $this->_beans_setting_get('purchase_prepaid_purchase_account_id');

		$this->_date_billed = ( isset($this->_data->date_billed) )
							? $this->_data->date_billed
							: FALSE;

		$this->_invoice_number = ( isset($this->_data->invoice_number) )
							   ? $this->_data->invoice_number
							   : FALSE;
	}

	protected function _execute()
	{
		if( ! $this->_transaction_purchase_account_id )
			throw new Exception("INTERNAL ERROR: Could not find default PO account.");

		if( ! $this->_transaction_purchase_line_account_id )
			throw new Exception("INTERNAL ERROR: Could not find default PO Line account.");

		if( ! $this->_transaction_purchase_prepaid_purchase_account_id )
			throw new Exception("INTERNAL ERROR: Could not find default deferred asset account.");

		// Independently validate $this->_date_billed and $this->_invoice_number
		if( $this->_date_billed AND 
			$this->_date_billed != date("Y-m-d",strtotime($this->_date_billed)) )
			throw new Exception("Invalid invoice date: must be in YYYY-MM-DD format.");

		if( $this->_invoice_number AND 
			strlen($this->_invoice_number) > 16 )
			throw new Exception("Invalid invoice number: maxmimum length of 16 characters.");

		if( $this->_invoice_number AND 
			! $this->_date_billed )
			throw new Exception("An invoice date is required if an invoice number is provided.");

		$this->_purchase->entity_id = ( isset($this->_data->vendor_id) )
								   ? $this->_data->vendor_id
								   : NULL;

		$this->_purchase->account_id = ( isset($this->_data->account_id) )
									? $this->_data->account_id
									: NULL;

		$this->_purchase->refund_form_id = ( isset($this->_data->refund_purchase_id) )
										? $this->_data->refund_purchase_id
										: NULL;
		
		$this->_purchase->sent = ( isset($this->_data->sent) )
							? $this->_data->sent
							: NULL;

		$this->_purchase->date_created = ( isset($this->_data->date_created) )
									? $this->_data->date_created
									: NULL;

		$this->_purchase->code = ( isset($this->_data->purchase_number) AND 
								  $this->_data->purchase_number )
							? $this->_data->purchase_number
							: "AUTOGENERATE";

		$this->_purchase->reference = ( isset($this->_data->so_number) )
								   ? $this->_data->so_number
								   : NULL;

		$this->_purchase->alt_reference = ( isset($this->_data->quote_number) )
									 ? $this->_data->quote_number
									 : NULL;

		$this->_purchase->remit_address_id = ( isset($this->_data->remit_address_id) )
										   ? (int)$this->_data->remit_address_id
										   : NULL;

		$this->_purchase->shipping_address_id = ( isset($this->_data->shipping_address_id) )
											  ? (int)$this->_data->shipping_address_id
											  : NULL;

		if( $this->_date_billed AND 
			strtotime($this->_date_billed) < strtotime($this->_purchase->date_created) )
			throw new Exception("Invalid invoice date: must be on or after the creation date of ".$this->_purchase->date_created.".");


		// Handle Default Account Payable
		
		// Vendor Default Account Payable
		if( $this->_purchase->account_id === NULL )
		{
			$vendor = $this->_load_vendor($this->_purchase->entity_id);
			if( $vendor->loaded() AND 
				$vendor->default_account_id )
				$this->_purchase->account_id = $vendor->default_account_id;
		}

		// Default Account Payable
		if( $this->_purchase->account_id === NULL ) {
			$this->_purchase->account_id = $this->_beans_setting_get('account_default_payable');
		}

		// Make sure we have good purchase information before moving on.
		$this->_validate_vendor_purchase($this->_purchase);
		
		$this->_purchase->total = 0.00;
		$this->_purchase->amount = 0.00;
		
		if( ! isset($this->_data->lines) OR 
			! is_array($this->_data->lines) OR
			! count($this->_data->lines) )
			throw new Exception("Invalid purchase lines: none provided.");

		foreach( $this->_data->lines as $purchase_line )
		{
			$new_purchase_line = $this->_default_form_line();

			$new_purchase_line->account_id = ( isset($purchase_line->account_id) )
										  ? (int)$purchase_line->account_id
										  : NULL;

			$new_purchase_line->description = ( isset($purchase_line->description) )
										   ? $purchase_line->description
										   : NULL;

			$new_purchase_line->amount = ( isset($purchase_line->amount) )
									  ? $this->_beans_round($purchase_line->amount)
									  : NULL;

			$new_purchase_line->quantity = ( isset($purchase_line->quantity) )
										? (float)$purchase_line->quantity
										: NULL;

			// Handle Default Cost of Goods
			if( $new_purchase_line->account_id === NULL ) {
				$new_purchase_line->account_id = $this->_beans_setting_get('account_default_costofgoods');
			}

			$this->_validate_form_line($new_purchase_line);

			$new_purchase_line->total = $this->_beans_round( $new_purchase_line->amount * $new_purchase_line->quantity );

			$new_purchase_line_total = $new_purchase_line->total;

			$this->_purchase->amount = $this->_beans_round( $this->_purchase->amount + $new_purchase_line->total );
			
			$this->_purchase_lines[] = $new_purchase_line;
		}

		$this->_purchase->total = $this->_beans_round( $this->_purchase->total + $this->_purchase->amount );
		
		// Validate Totals
		if( $this->_purchase->refund_form_id )
		{
			$refund_form = $this->_load_vendor_purchase($this->_purchase->refund_form_id);

			if( ! $refund_form->loaded() )
				throw new Exception("That refund_purchase_id was not found.");

			$original_purchase = $refund_form;
			$refund_purchase = $this->_purchase;

			if( ( $original_purchase->total > 0.00 AND $refund_purchase->total > 0.00 ) OR 
				( $original_purchase->total < 0.00 AND $refund_purchase->total < 0.00 ) )
				throw new Exception("Refund and original purchase totals must offset each other ( they cannot both be positive or negative ).");

			if( abs($refund_purchase->total) > abs($original_purchase->total) )
				throw new Exception("The refund total cannot be greater than the original purchase total.");
		}
		
		// Save Purchase + Children
		$this->_purchase->save();

		if( $this->_purchase->code == "AUTOGENERATE" )
			$this->_purchase->code = $this->_purchase->id;

		foreach( $this->_purchase_lines as $j => $purchase_line )
		{
			$purchase_line->form_id = $this->_purchase->id;
			$purchase_line->save();
		}
		
		$this->_purchase->save();

		$purchase_calibrate = new Beans_Vendor_Purchase_Calibrate($this->_beans_data_auth((object)array(
			'ids' => array($this->_purchase->id),
		)));
		$purchase_calibrate_result = $purchase_calibrate->execute();

		if( ! $purchase_calibrate_result->success )
		{
			// We've had an account transaction failure and need to delete the purchase we just created.
			$delete_purchase = new Beans_Vendor_Purchase_Delete($this->_beans_data_auth((object)array(
				'id' => $this->_purchase->id,
			)));
			$delete_purchase_result = $delete_purchase->execute();

			// V2Item
			// Fatal error!  Ensure coverage or ascertain 100% success.
			if( ! $delete_purchase_result->success )
				throw new Exception("Error creating account transaction for purchase purchase. ".
									"COULD NOT DELETE PURCHASE ORDER! ".
									$delete_purchase_result->error);
			
			throw new Exception("Error trying to create purchase: ".$purchase_calibrate_result->error);
		}

		// Reload the sale.
		$this->_purchase = $this->_load_vendor_purchase($this->_purchase->id);
		
		if( $this->_date_billed )
		{
			$vendor_purchase_invoice = new Beans_Vendor_Purchase_Invoice($this->_beans_data_auth((object)array(
				'id' => $this->_purchase->id,
				'date_billed' => $this->_date_billed,
				'invoice_number' => $this->_invoice_number,
			)));
			$vendor_purchase_invoice_result = $vendor_purchase_invoice->execute();

			// If it fails - we undo everything.
			if( ! $vendor_purchase_invoice_result->success )
			{
				$vendor_purchase_delete = new Beans_Vendor_Purchase_Delete($this->_beans_data_auth((object)array(
					'id' => $this->_purchase->id,
				)));
				$vendor_purchase_delete_result = $vendor_purchase_delete->execute();

				if( ! $vendor_purchase_delete_result->success )
					throw new Exception("Error creating account transaction for purchase invoice.".
										"COULD NOT DELETE PURCHASE! ".
										$vendor_purchase_delete_result->error.' '.
										$vendor_purchase_invoice_result->error);

				throw new Exception("Error creating purchase invoice transaction: ".
									$vendor_purchase_invoice_result->error);
			}

			return $this->_beans_return_internal_result($vendor_purchase_invoice_result);
		}

		// We need to reload the purchase so that we can get the correct balance, etc.
		$this->_purchase = $this->_load_vendor_purchase($this->_purchase->id);
		
		return (object)array(
			"purchase" => $this->_return_vendor_purchase_element($this->_purchase),
		);
	}
}