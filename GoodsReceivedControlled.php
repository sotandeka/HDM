<?php
/* $Revision: 1.15 $ */
/* $Id$*/
include('includes/DefinePOClass.php');
include('includes/DefineSerialItems.php');

include('includes/session.inc');

$title = _('Receive Controlled Items');
/* Session started in header.inc for password checking and authorisation level check */
include('includes/header.inc');

if (isset($_POST['InvalidImports'])) {
	$invalid_imports=$_POST['InvalidImports'];
}

if (!isset($_SESSION['PO'])) {
	/* This page can only be called with a purchase order number for receiving*/
	echo '<div class="centre"><a href="' . $rootpath . '/PO_SelectOSPurchOrder.php">'.
		_('Select a purchase order to receive'). '</a></div><br />';
	prnMsg( _('This page can only be opened if a purchase order and line item has been selected') . '. ' . _('Please do that first'),'error');
	include('includes/footer.inc');
	exit;
}

if (isset($_GET['LineNo']) and $_GET['LineNo']>0){
	$LineNo = $_GET['LineNo'];
} else if ($_POST['LineNo']>0){
	$LineNo = $_POST['LineNo'];
} else {
	echo '<div class="centre"><a href="' . $rootpath . '/GoodsReceived.php">'.
		_('Select a line Item to Receive').'</a></div>';
	prnMsg( _('This page can only be opened if a Line Item on a PO has been selected') . '. ' . _('Please do that first'), 'error');
	include( 'includes/footer.inc');
	exit;
}

global $LineItem;
$LineItem = &$_SESSION['PO']->LineItems[$LineNo];

if ($LineItem->Controlled !=1 ){ /*This page only relavent for controlled items */

	echo '<div class="centre"><a href="' . $rootpath . '/GoodsReceived.php">'.
		_('Back to the Purchase Order'). '</a></div>';
	prnMsg( _('The line being received must be controlled as defined in the item definition'), 'error');
	include('includes/footer.inc');
	exit;
}

/********************************************
  Get the page going....
********************************************/

echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/magnifier.png" title="" alt="" />'. _('Receive controlled item'). ' '. $LineItem->StockID  . ' - ' . $LineItem->ItemDescription .
	' ' . _('on order') . ' ' . $_SESSION['PO']->OrderNo . ' ' . _('from') . ' ' . $_SESSION['PO']->SupplierName . '</p>';

/** vars needed by InputSerialItem : **/
$LocationOut = $_SESSION['PO']->Location;
$ItemMustExist = false;
$StockID = $LineItem->StockID;
$InOutModifier=1;
$ShowExisting = false;
include ('includes/InputSerialItems.php');
echo '<div style="text-align: right">';

echo '<br /><a href="'.$rootpath.'/GoodsReceived.php">'. _('Back To Purchase Order'). ' # '. $_SESSION['PO']->OrderNo . '</a>';

echo '</div>';

/*TotalQuantity set inside this include file from the sum of the bundles
of the item selected for dispatch */
$_SESSION['PO']->LineItems[$LineItem->LineNo]->ReceiveQty = $TotalQuantity;

include( 'includes/footer.inc');
?>