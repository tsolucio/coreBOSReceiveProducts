<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
global $app_strings, $mod_strings, $current_language, $currentModule, $theme;
$list_max_entries_per_page = GlobalVariable::getVariable('Application_ListView_PageSize', 20, $currentModule);
require_once 'Smarty_setup.php';
require_once 'include/ListView/ListView.php';
require_once 'modules/CustomView/CustomView.php';
require_once 'include/DatabaseUtil.php';
require_once("data/Tracker.php");
require_once('modules/Receiptcards/Receiptcards.php');
require_once('include/logging.php');
require_once('include/utils/utils.php');

checkFileAccessForInclusion("modules/$currentModule/$currentModule.php");
require_once "modules/$currentModule/$currentModule.php";

$log = LoggerManager::getLogger('order_list');

$focus = new Receiptcards();
// Initialize sort by fields
$focus->initSortbyField('Receiptcards');
// END
$smarty = new vtigerCRM_Smarty;
$other_text = array();

if (!$_SESSION['lvs'][$currentModule]) {
	unset($_SESSION['lvs']);
	$modObj = new ListViewSession();
	$modObj->sorder = $sorder;
	$modObj->sortby = $order_by;
	$_SESSION['lvs'][$currentModule] = get_object_vars($modObj);
}

if (!empty($_REQUEST['errormsg'])) {
	$errormsg = vtlib_purify($_REQUEST['errormsg']);
	$smarty->assign("ERROR", "The User does not have permission to Change/Delete ".$errormsg." ".$currentModule);
} else {
	$smarty->assign("ERROR", "");
}
$sorder = $focus->getSortOrder();
$order_by = $focus->getOrderBy();

$_SESSION[$currentModule."_Order_By"] = $order_by;
$_SESSION[$currentModule."_Sort_Order"]=$sorder;
$url_string = '';
$smarty->assign('SEARCH_URL', $url_string);
if (isset($_REQUEST['query']) && $_REQUEST['query'] == 'true') {
	list($where, $ustring) = split("#@@#",getWhereCondition($currentModule));
	// we have a query
	$url_string .="&query=true".$ustring;
	$log->info("Here is the where clause for the list view: $where");
	$smarty->assign("SEARCH_URL",$url_string);
}
if (isset($where) && $where != '') {
	coreBOS_Session::set('export_where', $where);
} else {
	$where = '';
	coreBOS_Session::delete('export_where');
}
$smarty->assign('export_where', to_html($where));

//<<<<cutomview>>>>>>>
$oCustomView = new CustomView("Receiptcards");
$viewid = $oCustomView->getViewId($currentModule);
$customviewcombo_html = $oCustomView->getCustomViewCombo($viewid);
$viewnamedesc = $oCustomView->getCustomViewByCvid($viewid);

//Added to handle approving or denying status-public by the admin in CustomView
$statusdetails = $oCustomView->isPermittedChangeStatus($viewnamedesc['status']);
$smarty->assign("CUSTOMVIEW_PERMISSION",$statusdetails);

//To check if a user is able to edit/delete a customview
$edit_permit = $oCustomView->isPermittedCustomView($viewid,'EditView',$currentModule);
$delete_permit = $oCustomView->isPermittedCustomView($viewid,'Delete',$currentModule);
$smarty->assign("CV_EDIT_PERMIT",$edit_permit);
$smarty->assign("CV_DELETE_PERMIT",$delete_permit);

//<<<<<customview>>>>>
$smarty->assign("CHANGE_OWNER",getUserslist());
$smarty->assign("CHANGE_GROUP_OWNER",getGroupslist());
if(isPermitted('Receiptcards','Delete','') == 'yes') {
	$other_text['del'] = $app_strings['LBL_MASS_DELETE'];
}
if(isPermitted('Receiptcards','EditView','') == 'yes') {
	$other_text['mass_edit'] = $app_strings['LBL_MASS_EDIT'];
	$other_text['c_owner'] = $app_strings['LBL_CHANGE_OWNER'];
	$other_text['recalculate_stock'] = $mod_strings['LBL_RECALCULATE_STOCK'];
}

if($viewnamedesc['viewname'] == 'All')
{
	$smarty->assign("ALL", 'All');
}

$theme_path="themes/".$theme."/";
$image_path=$theme_path."images/";
$smarty->assign("MOD", $mod_strings);
$smarty->assign("APP", $app_strings);
$smarty->assign("THEME", $theme);
$smarty->assign("IMAGE_PATH",$image_path);
$smarty->assign("MODULE",$currentModule);
$smarty->assign("SINGLE_MOD",'Receiptcards');
$smarty->assign("CUSTOMVIEW_OPTION",$customviewcombo_html);
$smarty->assign("VIEWID", $viewid);
$category = getParentTab();
$smarty->assign("CATEGORY",$category);

//Retreive the list from Database
//<<<<<<<<<customview>>>>>>>>>
if($viewid != "0")
{
	$listquery = getListQuery("Receiptcards");
	$query = $oCustomView->getModifiedCvListQuery($viewid,$listquery,"Receiptcards");
}else
{
	$query = getListQuery("Receiptcards");
}

if(isset($where) && $where != '')
{
        $query .= ' and '.$where;
}

//$url_qry = getURLstring($focus);

if (isset($order_by) && $order_by != '') {
	if ($order_by == 'smownerid') {
		$query .= ' ORDER BY user_name '.$sorder;
	} else {
		$tablename = getTableNameForField('Receiptcards',$order_by);
		$tablename = (($tablename != '')?($tablename."."):'');
		$query .= ' ORDER BY '.$tablename.$order_by.' '.$sorder;
	}
}

if (GlobalVariable::getVariable('Application_ListView_Compute_Page_Count', 0)!=0) {
	$count_result = $adb->query(mkCountQuery($query));
	$noofrows = $adb->query_result($count_result, 0, 'count');
} else {
	$noofrows = null;
}

$queryMode = (isset($_REQUEST['query']) && $_REQUEST['query'] == 'true');
$start = ListViewSession::getRequestCurrentPage($currentModule, $query, $viewid, $queryMode);

$navigation_array = VT_getSimpleNavigationValues($start,$list_max_entries_per_page,$noofrows);

$limit_start_rec = ($start-1) * $list_max_entries_per_page;

if( $adb->dbType == "pgsql")
	$list_result = $adb->pquery($query. " OFFSET $limit_start_rec LIMIT $list_max_entries_per_page", array());
else
	$list_result = $adb->pquery($query. " LIMIT $limit_start_rec, $list_max_entries_per_page", array());

$recordListRangeMsg = getRecordRangeMessage($list_result, $limit_start_rec);
$smarty->assign('recordListRange',$recordListRangeMsg);

//Retreive the List View Table Header
if($viewid !='')
$url_string .="&viewname=".$viewid;

$listview_header = getListViewHeader($focus,"Receiptcards",$url_string,$sorder,$order_by,"",$oCustomView);
$smarty->assign("LISTHEADER", $listview_header);

$listview_header_search = getSearchListHeaderValues($focus,"Receiptcards",$url_string,$sorder,$order_by,"",$oCustomView);
$smarty->assign("SEARCHLISTHEADER",$listview_header_search);

$listview_entries = getListViewEntries($focus,"Receiptcards",$list_result,$navigation_array,"","","EditView","Delete",$oCustomView);
$smarty->assign("LISTENTITY", $listview_entries);

//Added to select Multiple records in multiple pages
$smarty->assign('SELECTEDIDS', isset($_REQUEST['selobjs']) ? vtlib_purify($_REQUEST['selobjs']) : '');
$smarty->assign('ALLSELECTEDIDS', isset($_REQUEST['allselobjs']) ? vtlib_purify($_REQUEST['allselobjs']) : '');
$smarty->assign('CURRENT_PAGE_BOXES', implode(array_keys($listview_entries), ';'));

$navigationOutput = getTableHeaderSimpleNavigation($navigation_array, $url_string,"Receiptcards","index",$viewid);
$alphabetical = AlphabeticalSearch($currentModule,'index','subject','true','basic',"","","","",$viewid);
$fieldnames = getAdvSearchfields($module);
$smarty->assign("FIELDNAMES", $fieldnames);
$smarty->assign("CUSTOMVIEW_OPTION",$customviewcombo_html);
$smarty->assign("VIEWID", $viewid);
$smarty->assign("ALPHABETICAL", $alphabetical);
$smarty->assign("NAVIGATION", $navigationOutput);
$smarty->assign("CUSTOMVIEW_OPTION",$customviewcombo_html);
$smarty->assign("VIEWID", $viewid);
$smarty->assign("BUTTONS", $other_text);

$check_button = Button_Check($module);
$smarty->assign("CHECK", $check_button);

$_SESSION[$currentModule.'_listquery'] = $query;

if (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] != '') {
	$smarty->display("modules/Receiptcards/ListViewEntries.tpl");
} else {
	$smarty->display("modules/Receiptcards/ListView.tpl");
}
?>