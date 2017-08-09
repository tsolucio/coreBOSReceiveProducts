<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once('data/CRMEntity.php');
require_once('data/Tracker.php');

class Receiptcards extends CRMEntity {
	var $db, $log; // Used in class functions of CRMEntity

	var $table_name = 'vtiger_receiptcards';
	var $table_index= 'receiptcardid';
	var $column_fields = Array();

	/** Indicator if this is a custom module or standard module */
	var $IsCustomModule = true;
	var $HasDirectImageField = false;
	/**
	 * Mandatory table for supporting custom fields.
	 */
	var $customFieldTable = Array('vtiger_receiptcardscf', 'receiptcardid');

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	var $tab_name = Array('vtiger_crmentity', 'vtiger_receiptcards', 'vtiger_receiptcardscf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	var $tab_name_index = Array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_receiptcards'   => 'receiptcardid',
		'vtiger_receiptcardscf' => 'receiptcardid');

	/**
	 * Mandatory for Listing (Related listview)
	 */
	var $list_fields = Array (
		/* Format: Field Label => Array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Receiptcards No'=> Array('project' => 'receiptcards_no'),
		'Assigned To' => Array('crmentity' => 'smownerid')
	);
	var $list_fields_name = Array(
		/* Format: Field Label => fieldname */
		'Receiptcards No'=> 'receiptcards_no',
		'Assigned To' => 'assigned_user_id'
	);

	// Make the field link to detail view from list view (Fieldname)
	var $list_link_field = 'receiptcards_no';

	// For Popup listview and UI type support
	var $search_fields = Array(
		/* Format: Field Label => Array(tablename => columnname) */
		// tablename should not have prefix 'vtiger_'
		'Receiptcards No'=> Array('receiptcards' => 'receiptcards_no')
	);
	var $search_fields_name = Array(
		/* Format: Field Label => fieldname */
		'Receiptcards No'=> 'receiptcards_no'
	);

	// For Popup window record selection
	var $popup_fields = Array('receiptcards_no', 'startdate');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	var $sortby_fields = Array();

	// For Alphabetical search
	var $def_basicsearch_col = 'receiptcards_no';

	// Column value to use on detail view record text display
	var $def_detailview_recname = 'receiptcards_no';

	// Required Information for enabling Import feature
	var $required_fields = Array('receiptcards_no'=>1);

	// Callback function list during Importing
	var $special_functions = Array('set_import_assigned_user');

	var $default_order_by = 'receiptcards_no';
	var $default_sort_order='ASC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	var $mandatory_fields = Array('createdtime', 'modifiedtime', 'receiptcards_no');

	function save_module($module) {
		//in ajax save we should not call this function, because this will delete all the existing product values
		if(isset($_REQUEST)) {
			if($_REQUEST['action'] != 'ReceiptcardsAjax' && $_REQUEST['ajxaction'] != 'DETAILVIEW' && $_REQUEST['action'] != 'MassEditSave')
			{
				//Based on the total Number of rows we will save the product relationship with this entity
				saveReceiptcardsInventoryProductDetails($this, 'Receiptcards');
			}
		}
		// Update the currency id and the conversion rate for the invoice
		$update_query = "update vtiger_receiptcards set currency_id=?, conversion_rate=? where receiptcardid=?";
		$update_params = array($this->column_fields['currency_id'], $this->column_fields['conversion_rate'], $this->id);
		$this->db->pquery($update_query, $update_params);
	}

	function restore($module, $id) {
		global $current_user;

		$this->db->println("TRANS restore starts $module");
		$this->db->startTransaction();

		$this->db->pquery('UPDATE vtiger_crmentity SET deleted=0 WHERE crmid = ?', array($id));
		//Restore related entities/records
		$this->restoreRelatedRecords($module,$id);

		$product_info = $this->db->pquery("SELECT productid, quantity, sequence_no, incrementondel from vtiger_inventoryproductrel WHERE id=?",array($id));
		$numrows = $this->db->num_rows($product_info);
		for($index = 0;$index <$numrows;$index++){
			$productid = $this->db->query_result($product_info,$index,'productid');
			$qty = $this->db->query_result($product_info,$index,'quantity');
			addToProductStock($productid,$qty);
		}
		$this->db->completeTransaction();
		$this->db->println("TRANS restore ends");
	}

	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	function vtlib_handler($modulename, $event_type) {
		if($event_type == 'module.postinstall') {
			// TODO Handle post installation actions
			$modAccounts=Vtiger_Module::getInstance('Accounts');
			$modContacts=Vtiger_Module::getInstance('Contacts');
			$modRC=Vtiger_Module::getInstance('Receiptcards');
			if ($modAccounts) $modAccounts->setRelatedList($modRC, 'Receiptcards', Array('ADD'),'get_dependents_list');
			if ($modContacts) $modContacts->setRelatedList($modRC, 'Receiptcards', Array('ADD'),'get_dependents_list');
			$this->setModuleSeqNumber('configure', $modulename, 'RECM-', '0000001');
		} else if($event_type == 'module.disabled') {
			// TODO Handle actions when this module is disabled.
		} else if($event_type == 'module.enabled') {
			// TODO Handle actions when this module is enabled.
		} else if($event_type == 'module.preuninstall') {
			// TODO Handle actions when this module is about to be deleted.
		} else if($event_type == 'module.preupdate') {
			// TODO Handle actions before this module is updated.
		} else if($event_type == 'module.postupdate') {
			// TODO Handle actions after this module is updated.
		}
	}

	/**
	 * Handle getting related list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//function get_related_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

	/**
	 * Handle getting dependents list information.
	 * NOTE: This function has been added to CRMEntity (base class).
	 * You can override the behavior by re-defining it here.
	 */
	//function get_dependents_list($id, $cur_tab_id, $rel_tab_id, $actions=false) { }

 /**
	 * Here we override the parent's method,
	 * This is done because the related lists for this module use a custom query
	 * that queries the child module's table (column of the uitype10 field)
	 *
	 * @see data/CRMEntity#save_related_module($module, $crmid, $with_module, $with_crmid)
	 */
	function save_related_module($module, $crmid, $with_module, $with_crmid) {
		if (!in_array($with_module, array('Deadline', 'Operation'))) {
			parent::save_related_module($module, $crmid, $with_module, $with_crmid);
			return;
		}
		/**
		 * $_REQUEST['action']=='Save' when choosing ADD from Related list.
		 * Do nothing on the project's entity when creating a related new child using ADD in relatedlist
		 * by doing nothing we do not insert any line in the crmentity's table when
		 * we are relating a module to this module
		 */
		if ($_REQUEST['action'] != 'updateRelations') {
			return;
		}
		//update the child elements' column value for uitype10
		$destinationModule = vtlib_purify($_REQUEST['destination_module']);
		if (!is_array($with_crmid))
			$with_crmid = Array($with_crmid);
		foreach ($with_crmid as $relcrmid) {
			$child = CRMEntity::getInstance($destinationModule);
			$child->retrieve_entity_info($relcrmid, $destinationModule);
			$child->mode = 'edit';
			$child->column_fields['projectid'] = $crmid;
			$child->save($destinationModule, $relcrmid);
		}
	}

	/**
	 * Here we override the parent's method
	 * This is done because the related lists for this module use a custom query
	 * that queries the child module's table (column of the uitype10 field)
	 *
	 * @see data/CRMEntity#delete_related_module($module, $crmid, $with_module, $with_crmid)
	 */
	function delete_related_module($module, $crmid, $with_module, $with_crmid) {
		if (!in_array($with_module, array('Deadline', 'Operation'))) {
			parent::delete_related_module($module, $crmid, $with_module, $with_crmid);
			return;
		}
		$destinationModule = vtlib_purify($_REQUEST['destination_module']);
		if (!is_array($with_crmid))
			$with_crmid = Array($with_crmid);
		foreach ($with_crmid as $relcrmid) {
			$child = CRMEntity::getInstance($destinationModule);
			$child->retrieve_entity_info($relcrmid, $destinationModule);
			$child->mode = 'edit';
			$child->column_fields['projectid'] = '';
			$child->save($destinationModule, $relcrmid);
		}
	}

	/* Generic function to get attachments in the related list of a given module */
	function get_attachments($id, $cur_tab_id, $rel_tab_id, $actions = false) {
		return parent::get_attachments($id, $cur_tab_id, $rel_tab_id, $actions);
	}

} // END OF CLASS

function getReceiptcardsInventoryTaxType($id) {
	global $log, $adb;
	$log->debug("Entering into function getReceiptcardsInventoryTaxType($id).");
	$res = $adb->pquery("select taxtype from vtiger_receiptcards where receiptcardid=?", array($id));
	$taxtype = $adb->query_result($res,0,'taxtype');
	$log->debug("Exit from function getReceiptcardsInventoryTaxType($id).");
	return $taxtype;
}

function getReceiptcardsInventoryCurrencyInfo($id)
{
	global $log, $adb;

	$log->debug("Entering into function getReceiptcardsInventoryCurrencyInfo($id).");

	$res = $adb->pquery("select currency_id, vtiger_receiptcards.conversion_rate as conv_rate, vtiger_currency_info.* from vtiger_receiptcards
						inner join vtiger_currency_info on vtiger_receiptcards.currency_id = vtiger_currency_info.id
						where receiptcardid=?", array($focus->id));

	$currency_info = array();
	$currency_info['currency_id'] = $adb->query_result($res,0,'currency_id');
	$currency_info['conversion_rate'] = $adb->query_result($res,0,'conv_rate');
	$currency_info['currency_name'] = $adb->query_result($res,0,'currency_name');
	$currency_info['currency_code'] = $adb->query_result($res,0,'currency_code');
	$currency_info['currency_symbol'] = $adb->query_result($res,0,'currency_symbol');

	$log->debug("Exit from function getReceiptcardsInventoryCurrencyInfo($id).");

	return $currency_info;
}

function saveReceiptcardsInventoryProductDetails($focus, $module, $update_prod_stock='false', $updateDemand='')
{
	global $log, $adb;
	$id=$focus->id;
	$log->debug("Entering into function saveInventoryProductDetails($module).");
	//Added to get the convertid
	if(isset($_REQUEST['convert_from']) && $_REQUEST['convert_from'] !='')
	{
		$id=$_REQUEST['return_id'];
	}
	else if(isset($_REQUEST['duplicate_from']) && $_REQUEST['duplicate_from'] !='')
	{
		$id=$_REQUEST['duplicate_from'];
	}

	$ext_prod_arr = Array();
	if($focus->mode == 'edit')
	{
		if($_REQUEST['taxtype'] == 'group')
			$all_available_taxes = getAllTaxes('available','','edit',$id);
		$return_old_values = 'return_old_values';
		deleteReceiptcardsInventoryProductDetails($focus);
	}
	else
	{
	if($_REQUEST['taxtype'] == 'group')
		$all_available_taxes = getAllTaxes('available','','edit',$id);
	}
	$tot_no_prod = $_REQUEST['totalProductCount'];
	//If the taxtype is group then retrieve all available taxes, else retrive associated taxes for each product inside loop
	$prod_seq=1;
	for($i=1; $i<=$tot_no_prod; $i++)
	{
		//if the product is deleted then we should avoid saving the deleted products
		if($_REQUEST["deleted".$i] == 1)
			continue;

		$prod_id = $_REQUEST['hdnProductId'.$i];
		if(isset($_REQUEST['productDescription'.$i]))
			$description = $_REQUEST['productDescription'.$i];

		$qty = $_REQUEST['qty'.$i];
		$listprice = $_REQUEST['listPrice'.$i];
		$comment = $_REQUEST['comment'.$i];

		addToProductStock($prod_id,$qty);

		$query ="insert into vtiger_inventoryproductrel(id, productid, sequence_no, quantity, listprice, comment, description) values(?,?,?,?,?,?,?)";
		$qparams = array($focus->id,$prod_id,$prod_seq,$qty,$listprice,$comment,$description);
		$adb->pquery($query,$qparams);

		$sub_prod_str = $_REQUEST['subproduct_ids'.$i];
		if (!empty($sub_prod_str)) {
			$sub_prod = split(":",$sub_prod_str);
			for($j=0;$j<count($sub_prod);$j++){
				$query ="insert into vtiger_inventorysubproductrel(id, sequence_no, productid) values(?,?,?)";
				$qparams = array($focus->id,$prod_seq,$sub_prod[$j]);
				$adb->pquery($query,$qparams);
			}
		}
		$prod_seq++;

		//we should update discount and tax details
		$updatequery = "update vtiger_inventoryproductrel set ";
		$updateparams = array();

		//set the discount percentage or discount amount in update query, then set the tax values
		if($_REQUEST['discount_type'.$i] == 'percentage')
		{
			$updatequery .= " discount_percent=?,";
			array_push($updateparams, $_REQUEST['discount_percentage'.$i]);
		}
		elseif($_REQUEST['discount_type'.$i] == 'amount')
		{
			$updatequery .= " discount_amount=?,";
			$discount_amount = $_REQUEST['discount_amount'.$i];
			array_push($updateparams, $discount_amount);
		}
		if($_REQUEST['taxtype'] == 'group')
		{
			for($tax_count=0;$tax_count<count($all_available_taxes);$tax_count++)
			{
				$tax_name = $all_available_taxes[$tax_count]['taxname'];
				$tax_val = $all_available_taxes[$tax_count]['percentage'];
				$request_tax_name = $tax_name."_group_percentage";
				if(isset($_REQUEST[$request_tax_name]))
					$tax_val =$_REQUEST[$request_tax_name];
				$updatequery .= " $tax_name = ?,";
				array_push($updateparams,$tax_val);
			}
				$updatequery = trim($updatequery,',')." where id=? and productid=?";
				array_push($updateparams,$focus->id,$prod_id);
		}
		else
		{
			$taxes_for_product = getTaxDetailsForProduct($prod_id,'all');
			for($tax_count=0;$tax_count<count($taxes_for_product);$tax_count++)
			{
				$tax_name = $taxes_for_product[$tax_count]['taxname'];
				$request_tax_name = $tax_name."_percentage".$i;
				$updatequery .= " $tax_name = ?,";
				array_push($updateparams, $_REQUEST[$request_tax_name]);
			}
			$updatequery = trim($updatequery,',')." where id=? and productid=?";
			array_push($updateparams, $focus->id,$prod_id);
		}
		// jens 2006/08/19 - protect against empy update queries
		if( !preg_match( '/set\s+where/i', $updatequery)) {
			$adb->pquery($updatequery,$updateparams);
		}
	}

	//we should update the netprice (subtotal), taxtype, group discount, S&H charge, S&H taxes, adjustment and total
	//netprice, group discount, taxtype, S&H amount, adjustment and total to entity table

	$updatequery  = " update $focus->table_name set ";
	$updateparams = array();
	$subtotal = $_REQUEST['subtotal'];
	$updatequery .= " subtotal=?,";
	array_push($updateparams, $subtotal);

	$updatequery .= " taxtype=?,";
	array_push($updateparams, $_REQUEST['taxtype']);

	//for discount percentage or discount amount
	if($_REQUEST['discount_type_final'] == 'percentage')
	{
		$updatequery .= " discount_percent=?,";
		array_push($updateparams, $_REQUEST['discount_percentage_final']);
	}
	elseif($_REQUEST['discount_type_final'] == 'amount')
	{
		$discount_amount_final = $_REQUEST['discount_amount_final'];
		$updatequery .= " discount_amount=?,";
		array_push($updateparams, $discount_amount_final);
	}

	$shipping_handling_charge = $_REQUEST['shipping_handling_charge'];
	$updatequery .= " s_h_amount=?,";
	array_push($updateparams, $shipping_handling_charge);

	//if the user gave - sign in adjustment then add with the value
	$adjustmentType = '';
	if($_REQUEST['adjustmentType'] == '-')
		$adjustmentType = $_REQUEST['adjustmentType'];

	$adjustment = $_REQUEST['adjustment'];
	$updatequery .= " adjustment=?,";
	array_push($updateparams, $adjustmentType.$adjustment);

	$total = $_REQUEST['total'];
	$updatequery .= " total=?";
	array_push($updateparams, $total);

	//$id_array = Array('PurchaseOrder'=>'purchaseorderid','SalesOrder'=>'salesorderid','Quotes'=>'quoteid','Invoice'=>'invoiceid');
	//Added where condition to which entity we want to update these values
	$updatequery .= " where ".$focus->table_index."=?";
	array_push($updateparams, $focus->id);

	$adb->pquery($updatequery,$updateparams);

	//to save the S&H tax details in vtiger_inventoryshippingrel table
	$sh_tax_details = getAllTaxes('all','sh');
	$sh_query_fields = "id,";
	$sh_query_values = "?,";
	$sh_query_params = array($focus->id);
	for($i=0;$i<count($sh_tax_details);$i++)
	{
		$tax_name = $sh_tax_details[$i]['taxname']."_sh_percent";
		if($_REQUEST[$tax_name] != '')
		{
			$sh_query_fields .= $sh_tax_details[$i]['taxname'].",";
			$sh_query_values .= "?,";
			array_push($sh_query_params, $_REQUEST[$tax_name]);
		}
	}
	$sh_query_fields = trim($sh_query_fields,',');
	$sh_query_values = trim($sh_query_values,',');

	$sh_query = "insert into vtiger_inventoryshippingrel($sh_query_fields) values($sh_query_values)";
	$adb->pquery($sh_query,$sh_query_params);

	$log->debug("Exit from function saveInventoryProductDetails($module).");
}

function deleteReceiptcardsInventoryProductDetails($focus, $sql_del = true)
{
	global $log, $adb,$updateInventoryProductRel_update_product_array;
	$log->debug("Entering into function deleteInventoryProductDetails(".$focus->id.").");
	$product_info = $adb->pquery("SELECT productid, quantity, sequence_no, incrementondel from vtiger_inventoryproductrel WHERE id=?",array($focus->id));
	$numrows = $adb->num_rows($product_info);
	for($index = 0;$index <$numrows;$index++){
		$productid = $adb->query_result($product_info,$index,'productid');
		$sequence_no = $adb->query_result($product_info,$index,'sequence_no');
		$qty = $adb->query_result($product_info,$index,'quantity');
		deductFromProductStock($productid,$qty);
	}
	if ($sql_del) {
		$adb->pquery("delete from vtiger_inventoryproductrel where id=?", array($focus->id));
		$adb->pquery("delete from vtiger_inventorysubproductrel where id=?", array($focus->id));
		$adb->pquery("delete from vtiger_inventoryshippingrel where id=?", array($focus->id));
	}
	$log->debug("Exit from function deleteInventoryProductDetails(".$focus->id.")");
}
?>
