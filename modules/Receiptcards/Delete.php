<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'modules/Receiptcards/Receiptcards.php';
global $mod_strings;

if (!isset($_REQUEST['record'])) {
	die($mod_strings['ERR_DELETE_RECORD']);
}

$record = vtlib_purify($_REQUEST['record']);
$module = urlencode(vtlib_purify($_REQUEST['module']));
$return_module = vtlib_purify($_REQUEST['return_module']);
$return_action = urlencode(vtlib_purify($_REQUEST['return_action']));
$return_id = isset($_REQUEST['return_id']) ? vtlib_purify($_REQUEST['return_id']) : '';
$url = getBasic_Advance_SearchURL();
if (!empty($_REQUEST['start']) && !empty($_REQUEST['return_viewname'])) {
	$start = vtlib_purify($_REQUEST['start']);
	$relationId = vtlib_purify($_REQUEST['return_viewname']);
	coreBOS_Session::set('rlvs^'.$return_module.'^'.$relationId.'^start', $start);
}
if (isset($_REQUEST['activity_mode'])) {
	$url .= '&activity_mode='.urlencode(vtlib_purify($_REQUEST['activity_mode']));
}
if ($_REQUEST['module'] == $_REQUEST['return_module']) {
	$focus = new Receiptcards();
	$focus->id = $record;
	deleteReceiptcardsInventoryProductDetails($focus, false);
	$focus->mark_deleted($record);
}
header('Location: index.php?module=' . urlencode($return_module) . "&action=$return_action&record=" . urlencode($return_id) . "&relmodule=$module" . $url);
?>
