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
$smarty->assign('ASSOCIATED_PRODUCTS', getDetailAssociatedProducts($currentModule, $focus));
$smarty->assign('ShowInventoryLines', strpos(GlobalVariable::getVariable('Inventory_DoNotUseLines', '', $currentModule, $current_user->id), $currentModule)===false);

$smarty->display('Inventory/InventoryDetailView.tpl');
?>
