<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'Smarty_setup.php';

global $mod_strings, $app_strings, $currentModule, $current_user, $theme, $log;

$smarty = new vtigerCRM_Smarty();

require_once 'modules/Vtiger/DetailView.php';

//Get the associated Products and then display above Terms and Conditions
$smarty->assign("ASSOCIATED_PRODUCTS", getReceiptcardsDetailAssociatedProducts($focus));

$smarty->display('Inventory/InventoryDetailView.tpl');

function getReceiptcardsDetailAssociatedProducts($focus) {
	global $log;
	$log->debug("Entering getReceiptcardsDetailAssociatedProducts('Receiptcards',".get_class($focus).") method ...");
	global $adb;
	global $mod_strings;
	global $theme;
	global $log;
	global $app_strings,$current_user;
	$theme_path="themes/".$theme."/";
	$image_path=$theme_path."images/";

	//Get the taxtype of this entity
	$taxtype = getReceiptcardsInventoryTaxType($focus->id);

	$currencytype = getReceiptcardsInventoryCurrencyInfo($focus->id);

	$output = '';
	//Header Rows
	$output .= '

	<table width="100%"  border="0" align="center" cellpadding="5" cellspacing="0" class="crmTable" id="proTab">
	   <tr valign="top">
	   	<td class="dvInnerHeader"><b>'.$app_strings['LBL_ITEM_DETAILS'].'</b></td>
		<td class="dvInnerHeader" align="center" colspan="2"><b>'.
			$app_strings['LBL_CURRENCY'].' : </b>'. $currencytype['currency_name']. '('. $currencytype['currency_symbol'] .')
		</td>
		<td class="dvInnerHeader" align="center" colspan="2"><b>'.
			$app_strings['LBL_TAX_MODE'].' : </b>'.$app_strings[$taxtype].'
		</td>
	   </tr>
	   <tr valign="top">
		<td width=40% class="lvtCol"><font color="red">*</font>
			<b>'.$app_strings['LBL_ITEM_NAME'].'</b>
		</td>';

	$output .= '	
		<td width=10% class="lvtCol"><b>'.$app_strings['LBL_QTY'].'</b></td>
		<td width=10% class="lvtCol" align="right"><b>'.$app_strings['LBL_LIST_PRICE'].'</b></td>
		<td width=12% nowrap class="lvtCol" align="right"><b>'.$app_strings['LBL_TOTAL'].'</b></td>
		<td width=13% valign="top" class="lvtCol" align="right"><b>'.$app_strings['LBL_NET_PRICE'].'</b></td>
	   </tr>
	   	';

	// DG 15 Aug 2006
	// Add "ORDER BY sequence_no" to retain add order on all inventoryproductrel items

		$query="select case when vtiger_products.productid != '' then vtiger_products.productname else vtiger_service.servicename end as productname," .
				" case when vtiger_products.productid != '' then 'Products' else 'Services' end as entitytype," .
				" case when vtiger_products.productid != '' then vtiger_products.unit_price else vtiger_service.unit_price end as unit_price," .
				" case when vtiger_products.productid != '' then vtiger_products.qtyinstock else 'NA' end as qtyinstock, vtiger_inventoryproductrel.* " .
				" from vtiger_inventoryproductrel" .
				" left join vtiger_products on vtiger_products.productid=vtiger_inventoryproductrel.productid " .
				" left join vtiger_service on vtiger_service.serviceid=vtiger_inventoryproductrel.productid " .
				" where id=? ORDER BY sequence_no";

	$result = $adb->pquery($query, array($focus->id));
	$num_rows=$adb->num_rows($result);
	$netTotal = '0.00';
	for ($i=1; $i<=$num_rows; $i++) {
		$sub_prod_query = $adb->pquery("SELECT productid from vtiger_inventorysubproductrel WHERE id=? AND sequence_no=?", array($focus->id,$i));
		$subprodname_str='';
		if ($adb->num_rows($sub_prod_query)>0) {
			for ($j=0; $j<$adb->num_rows($sub_prod_query); $j++) {
				$sprod_id = $adb->query_result($sub_prod_query, $j, 'productid');
				$sprod_name = getProductName($sprod_id);
				$str_sep = "";
				if ($j>0) {
					$str_sep = ":";
				}
				$subprodname_str .= $str_sep." - ".$sprod_name;
			}
		}
		$subprodname_str = str_replace(":", "<br>", $subprodname_str);

		$productid=$adb->query_result($result, $i-1, 'productid');
		$entitytype=$adb->query_result($result, $i-1, 'entitytype');
		$productname=$adb->query_result($result, $i-1, 'productname');
		if ($subprodname_str!='') {
			$productname .= "<br/><span style='color:#C0C0C0;font-style:italic;'>".$subprodname_str."</span>";
		}
		$comment=$adb->query_result($result, $i-1, 'comment');
		$qtyinstock=$adb->query_result($result, $i-1, 'qtyinstock');
		$qty=$adb->query_result($result, $i-1, 'quantity');
		$unitprice=$adb->query_result($result, $i-1, 'unit_price');
		$listprice=$adb->query_result($result, $i-1, 'listprice');
		$total = $qty*$listprice;

		//Product wise Discount calculation - starts
		$discount_percent=$adb->query_result($result, $i-1, 'discount_percent');
		$discount_amount=$adb->query_result($result, $i-1, 'discount_amount');
		$totalAfterDiscount = $total;

		$productDiscount = '0.00';
		if ($discount_percent != 'NULL' && $discount_percent != '') {
			$productDiscount = $total*$discount_percent/100;
			$totalAfterDiscount = $total-$productDiscount;
			//if discount is percent then show the percentage
			$discount_info_message = "$discount_percent % of $total = $productDiscount";
		} elseif ($discount_amount != 'NULL' && $discount_amount != '') {
			$productDiscount = $discount_amount;
			$totalAfterDiscount = $total-$productDiscount;
			$discount_info_message = $app_strings['LBL_DIRECT_AMOUNT_DISCOUNT']." = $productDiscount";
		} else {
			$discount_info_message = $app_strings['LBL_NO_DISCOUNT_FOR_THIS_PRODUCT'];
		}
		//Product wise Discount calculation - ends

		$netprice = $totalAfterDiscount;
		//Calculate the individual tax if taxtype is individual
		if ($taxtype == 'individual') {
			$taxtotal = '0.00';
			$tax_info_message = $app_strings['LBL_TOTAL_AFTER_DISCOUNT']." = $totalAfterDiscount \\n";
			$tax_details = getTaxDetailsForProduct($productid, 'all');
			for ($tax_count=0; $tax_count<count($tax_details); $tax_count++) {
				$tax_name = $tax_details[$tax_count]['taxname'];
				$tax_label = $tax_details[$tax_count]['taxlabel'];
				$tax_value = getInventoryProductTaxValue($focus->id, $productid, $tax_name);

				$individual_taxamount = $totalAfterDiscount*$tax_value/100;
				$taxtotal = $taxtotal + $individual_taxamount;
				$tax_info_message .= "$tax_label : $tax_value % = $individual_taxamount \\n";
			}
			$tax_info_message .= "\\n ".$app_strings['LBL_TOTAL_TAX_AMOUNT']." = $taxtotal";
			$netprice = $netprice + $taxtotal;
		}

		$sc_image_tag = '';
		if ($entitytype == 'Services') {
			$sc_image_tag = '<a href="index.php?module=ServiceContracts&action=EditView&service_id='.$productid.'&return_module=Receiptcards&return_id='.$focus->id.'">'
				.'<img border="0" src="'.vtiger_imageurl('handshake.gif', $theme).'" title="'. getTranslatedString('Add Service Contract')
				.'" style="cursor: pointer;" align="absmiddle" /></a>';
		}

		//For Product Name
		$output .= '
			   <tr valign="top">
				<td class="crmTableRow small lineOnTop">
					'.$productname.'&nbsp;'.$sc_image_tag.'
					<br>'.$comment.'
				</td>';
		//Upto this added to display the Product name and comment

		$output .= '<td class="crmTableRow small lineOnTop">'.$qty.'</td>';
		$output .= '
			<td class="crmTableRow small lineOnTop" align="right">
				<table width="100%" border="0" cellpadding="5" cellspacing="0">
				   <tr>
				   	<td align="right">'.$listprice.'</td>
				   </tr>
				   <tr>
					<td align="right">(-)&nbsp;<b><a href="javascript:;" onclick="alert(\''.$discount_info_message.'\');">'.$app_strings['LBL_DISCOUNT'].' : </a></b></td>
				   </tr>
				   <tr>
				   	<td align="right" nowrap>'.$app_strings['LBL_TOTAL_AFTER_DISCOUNT'].' : </td>
				   </tr>';
		if ($taxtype == 'individual') {
			$output .= '
				   <tr>
					   <td align="right" nowrap>(+)&nbsp;<b><a href="javascript:;" onclick="alert(\''.$tax_info_message.'\');">'.$app_strings['LBL_TAX'].' : </a></b></td>
				   </tr>';
		}
		$output .= '
				</table>
			</td>';

		$output .= '
			<td class="crmTableRow small lineOnTop" align="right">
				<table width="100%" border="0" cellpadding="5" cellspacing="0">
				   <tr><td align="right">'.$total.'</td></tr>
				   <tr><td align="right">'.$productDiscount.'</td></tr>
				   <tr><td align="right" nowrap>'.$totalAfterDiscount.'</td></tr>';

		if ($taxtype == 'individual') {
			$output .= '<tr><td align="right" nowrap>'.$taxtotal.'</td></tr>';
		}

		$output .= '		   
				</table>
			</td>';
		$output .= '<td class="crmTableRow small lineOnTop" valign="bottom" align="right">'.$netprice.'</td>';
		$output .= '</tr>';

		$netTotal = $netTotal + $netprice;
	}

	$output .= '</table>';

	//$netTotal should be equal to $focus->column_fields['hdnSubTotal']
	$netTotal = $focus->column_fields['hdnSubTotal'];

	//Display the total, adjustment, S&H details
	$output .= '<table width="100%" border="0" cellspacing="0" cellpadding="5" class="crmTable">';
	$output .= '<tr>';
	$output .= '<td width="88%" class="crmTableRow small" align="right"><b>'.$app_strings['LBL_NET_TOTAL'].'</td>';
	$output .= '<td width="12%" class="crmTableRow small" align="right"><b>'.$netTotal.'</b></td>';
	$output .= '</tr>';

	//Decide discount
	$finalDiscount = '0.00';
	$final_discount_info = '0';
	if ($focus->column_fields['hdnDiscountPercent'] != '0') {
		$finalDiscount = ($netTotal*$focus->column_fields['hdnDiscountPercent']/100);
		$final_discount_info = $focus->column_fields['hdnDiscountPercent']." % of $netTotal = $finalDiscount";
	} elseif ($focus->column_fields['hdnDiscountAmount'] != '0') {
		$finalDiscount = $focus->column_fields['hdnDiscountAmount'];
		$final_discount_info = $finalDiscount;
	}

	//Alert the Final Discount amount even it is zero
	$final_discount_info = $app_strings['LBL_FINAL_DISCOUNT_AMOUNT']." = $final_discount_info";
	$final_discount_info = 'onclick="alert(\''.$final_discount_info.'\');"';

	$output .= '<tr>';
	$output .= '<td align="right" class="crmTableRow small lineOnTop">(-)&nbsp;<b><a href="javascript:;" '.$final_discount_info.'>';
	$output .= $app_strings['LBL_DISCOUNT'].'</a></b></td>';
	$output .= '<td align="right" class="crmTableRow small lineOnTop">'.$finalDiscount.'</td>';
	$output .= '</tr>';

	if ($taxtype == 'group') {
		$taxtotal = '0.00';
		$final_totalAfterDiscount = $netTotal - $finalDiscount;
		$tax_info_message = $app_strings['LBL_TOTAL_AFTER_DISCOUNT']." = $final_totalAfterDiscount \\n";
		//First we should get all available taxes and then retrieve the corresponding tax values
		$tax_details = getAllTaxes('available', '', 'edit', $focus->id);
		//if taxtype is group then the tax should be same for all products in vtiger_inventoryproductrel table
		for ($tax_count=0; $tax_count<count($tax_details); $tax_count++) {
			$tax_name = $tax_details[$tax_count]['taxname'];
			$tax_label = $tax_details[$tax_count]['taxlabel'];
			$tax_value = $adb->query_result($result, 0, $tax_name);
			if ($tax_value == '' || $tax_value == 'NULL') {
				$tax_value = '0.00';
			}

			$taxamount = ($netTotal-$finalDiscount)*$tax_value/100;
			$taxtotal = $taxtotal + $taxamount;
			$tax_info_message .= "$tax_label : $tax_value % = $taxamount \\n";
		}
		$tax_info_message .= "\\n ".$app_strings['LBL_TOTAL_TAX_AMOUNT']." = $taxtotal";

		$output .= '<tr>';
		$output .= '<td align="right" class="crmTableRow small">(+)&nbsp;<b><a href="javascript:;" onclick="alert(\''.$tax_info_message.'\');">';
		$output .= $app_strings['LBL_TAX'].'</a></b></td>';
		$output .= '<td align="right" class="crmTableRow small">'.$taxtotal.'</td>';
		$output .= '</tr>';
	}

	$shAmount = ($focus->column_fields['hdnS_H_Amount'] != '')?$focus->column_fields['hdnS_H_Amount']:'0.00';
	$output .= '<tr>';
	$output .= '<td align="right" class="crmTableRow small">(+)&nbsp;<b>'.$app_strings['LBL_SHIPPING_AND_HANDLING_CHARGES'].'</b></td>';
	$output .= '<td align="right" class="crmTableRow small">'.$shAmount.'</td>';
	$output .= '</tr>';

	//calculate S&H tax
	$shtaxtotal = '0.00';
	//First we should get all available taxes and then retrieve the corresponding tax values
	$shtax_details = getAllTaxes('available', 'sh', 'edit', $focus->id);
	//if taxtype is group then the tax should be same for all products in vtiger_inventoryproductrel table
	$shtax_info_message = $app_strings['LBL_SHIPPING_AND_HANDLING_CHARGE']." = $shAmount \\n";
	for ($shtax_count=0; $shtax_count<count($shtax_details); $shtax_count++) {
		$shtax_name = $shtax_details[$shtax_count]['taxname'];
		$shtax_label = $shtax_details[$shtax_count]['taxlabel'];
		$shtax_percent = getInventorySHTaxPercent($focus->id, $shtax_name);
		$shtaxamount = $shAmount*$shtax_percent/100;
		$shtaxtotal = $shtaxtotal + $shtaxamount;
		$shtax_info_message .= "$shtax_label : $shtax_percent % = $shtaxamount \\n";
	}
	$shtax_info_message .= "\\n ".$app_strings['LBL_TOTAL_TAX_AMOUNT']." = $shtaxtotal";

	$output .= '<tr>';
	$output .= '<td align="right" class="crmTableRow small">(+)&nbsp;<b><a href="javascript:;" onclick="alert(\''.$shtax_info_message.'\')">';
	$output .= $app_strings['LBL_TAX_FOR_SHIPPING_AND_HANDLING'].'</a></b></td>';
	$output .= '<td align="right" class="crmTableRow small">'.$shtaxtotal.'</td>';
	$output .= '</tr>';

	$adjustment = ($focus->column_fields['txtAdjustment'] != '')?$focus->column_fields['txtAdjustment']:'0.00';
	$output .= '<tr>';
	$output .= '<td align="right" class="crmTableRow small">&nbsp;<b>'.$app_strings['LBL_ADJUSTMENT'].'</b></td>';
	$output .= '<td align="right" class="crmTableRow small">'.$adjustment.'</td>';
	$output .= '</tr>';

	$grandTotal = ($focus->column_fields['hdnGrandTotal'] != '')?$focus->column_fields['hdnGrandTotal']:'0.00';
	$output .= '<tr>';
	$output .= '<td align="right" class="crmTableRow small lineOnTop"><b>'.$app_strings['LBL_GRAND_TOTAL'].'</b></td>';
	$output .= '<td align="right" class="crmTableRow small lineOnTop">'.$grandTotal.'</td>';
	$output .= '</tr>';
	$output .= '</table>';

	$log->debug('Exiting getDetailAssociatedProducts');
	return $output;
}
?>
