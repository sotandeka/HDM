<?php

include('includes/session.inc');
$title=_('Reprint a GRN');
include('includes/header.inc');

echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/supplier.png" title="' . $title . '" alt="" />' . ' ' . $title . '</p>';

if (!isset($_POST['PONumber'])) {
	$_POST['PONumber']='';
}

echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
echo '<table class="selection">';
echo '<tr><th colspan="2" class="header">' . _('Select a purchase order') . '</th></tr>';
echo '<tr><td>' . _('Enter a Purchase Order Number') . '</td>';
echo '<td>' . '<input type="text" name="PONumber" class="number" size="7" value="'.$_POST['PONumber'].'" /></td></tr>';
echo '<tr><td colspan="2" style="text-align: center">' . '<button type="submit" name="Show">' . _('Show GRNs') . '</button></td></tr>';

echo '</table>';
echo '</form>';

if (isset($_POST['Show'])) {
	if ($_POST['PONumber']=='') {
		echo '<br />';
		prnMsg( _('You must enter a purchase order number in the box above'), 'warn');
		include('includes/footer.inc');
		exit;
	}
	$sql="SELECT count(orderno)
				FROM purchorders
				WHERE orderno='" . $_POST['PONumber'] ."'";
	$result=DB_query($sql, $db);
	$myrow=DB_fetch_row($result);
	if ($myrow[0]==0) {
		echo '<br />';
		prnMsg( _('This purchase order does not exist on the system. Please try again.'), 'warn');
		include('includes/footer.inc');
		exit;
	}
	$sql="SELECT grnbatch,
					grnno,
					podetailitem,
					itemcode,
					itemdescription,
					deliverydate,
					qtyrecd,
					suppliers.suppname,
					stockmaster.decimalplaces
				FROM grns
				LEFT JOIN suppliers
				ON grns.supplierid=suppliers.supplierid
				LEFT JOIN stockmaster
				ON grns.itemcode=stockmaster.stockid
				WHERE orderno='" . $_POST['PONumber'] ."'";
	$result=DB_query($sql, $db);
	if (DB_num_rows($result)==0) {
		echo '<br />';
		prnMsg( _('There are no GRNs for this purchase order that can be reprinted.'), 'warn');
		include('includes/footer.inc');
		exit;
	}
	$k=0;
	echo '<br /><table class="selection">';
	echo '<tr><th colspan="8" class="header">' . _('GRNs for Purchase Order No') .' ' . $_POST['PONumber'] . '</th></tr>';
	echo '<tr><th>' . _('Supplier') . '</th>';
	echo '<th>' . _('PO Order line') . '</th>';
	echo '<th>' . _('GRN Number') . '</th>';
	echo '<th>' . _('Item Code') . '</th>';
	echo '<th>' . _('Item Description') . '</th>';
	echo '<th>' . _('Delivery Date') . '</th>';
	echo '<th>' . _('Quantity Received') . '</th></tr>';
	while ($myrow=DB_fetch_array($result)) {
		if ($k==1){
			echo '<tr class="EvenTableRows">';
			$k=0;
		} else {
			echo '<tr class="OddTableRows">';
			$k=1;
		}
		echo '<td>' . $myrow['suppname'] . '</td>';
		echo '<td class="number">' . $myrow['podetailitem'] . '</td>';
		echo '<td class="number">' . $myrow['grnbatch'] . '</td>';
		echo '<td>' . $myrow['itemcode'] . '</td>';
		echo '<td>' . $myrow['itemdescription'] . '</td>';
		echo '<td>' . $myrow['deliverydate'] . '</td>';
		echo '<td class="number">' . locale_number_format($myrow['qtyrecd'], $myrow['decimalplaces']) . '</td>';
		echo '<td><a href="PDFGrn.php?GRNNo=' . $myrow['grnbatch'] .'&PONo=' . $_POST['PONumber'] . '">' . _('Reprint') . '</a></td>';
		echo '</tr>';
	}
	echo '</table>';
}

include('includes/footer.inc');

?>