<?php
/* $Id$*/

include('includes/session.inc');
include('includes/SQL_CommonFunctions.inc');
include('includes/DefinePOClass.php');
if(!isset($_GET['OrderNo']) and !isset($_POST['OrderNo'])){
	$title = _('Select a Purchase Order');
	include('includes/header.inc');
	echo '<div class="centre"><br /><br /><br />';
	prnMsg( _('Select a Purchase Order Number to Print before calling this page') , 'error');
	echo '<br /><br /><br /><table class="table_index">
		<tr><td class="menu_group_item">
		<li><a href="'. $rootpath . '/PO_SelectOSPurchOrder.php">' . _('Outstanding Purchase Orders') . '</a></li>
		<li><a href="'. $rootpath . '/PO_SelectPurchOrder.php">' . _('Purchase Order Inquiry') . '</a></li>
		</td></tr></table></div><br /><br /><br />';
	include('includes/footer.inc');
	exit();

	echo '<div class="centre"><br /><br /><br />' . _('This page must be called with a purchase order number to print');
	echo '<br /><a href="'. $rootpath . '/index.php">' . _('Back to the menu') . '</a></div>';
	exit;
}
if (isset($_GET['OrderNo'])){
	$OrderNo = $_GET['OrderNo'];
} elseif (isset($_POST['OrderNo'])){
	$OrderNo = $_POST['OrderNo'];
}
$title = _('Print Purchase Order Number').' '. $OrderNo;
/* If we are not previewing the order then find
 * the order status */
if ($OrderNo != 'Preview') {
	$sql="SELECT status
		FROM purchorders
		WHERE orderno='".$OrderNo."'";
	$result=DB_query($sql, $db);
	$myrow=DB_fetch_array($result);
	$OrderStatus=$myrow['status'];
} else {
	/* otherwise set it to Printed */
	$_POST['ShowAmounts']='Yes';
	$OrderStatus = _('Printed');
	$MakePDFThenDisplayIt = True;
}
if ($OrderStatus != PurchOrder::STATUS_AUTHORISED and $OrderStatus != PurchOrder::STATUS_PRINTED) {
	include('includes/header.inc');
	prnMsg( _('Purchase orders can only be printed once they have been authorised') . '. ' .
		_('This order is currently at a status of') . ' ' . _($OrderStatus),'warn');
	include('includes/footer.inc');
	exit;
}
$ViewingOnly = 0;
if (isset($_GET['ViewingOnly']) and $_GET['ViewingOnly']!='') {
	$ViewingOnly = $_GET['ViewingOnly'];
} elseif (isset($_POST['ViewingOnly']) and $_POST['ViewingOnly']!='') {
	$ViewingOnly = $_POST['ViewingOnly'];
}
/* If we are previewing the order then we dont
 * want to email it */
if ($OrderNo != 'Preview') {
	$_POST['PrintOrEmail']='Print';
}
if (isset($_POST['DoIt'])  and ($_POST['PrintOrEmail']=='Print' or $ViewingOnly==1) ){
	$MakePDFThenDisplayIt = True;
} elseif (isset($_POST['DoIt']) AND $_POST['PrintOrEmail']=='Email' AND mb_strlen($_POST['EmailTo'])>6){
	$MakePDFThenEmailIt = True;
}
if (isset($OrderNo) and $OrderNo != "" and $OrderNo > 0 and $OrderNo != 'Preview'){
	//Check this up front. Note that the myrow recordset is carried into the actual make pdf section
	/*retrieve the order details from the database to print */
	$ErrMsg = _('There was a problem retrieving the purchase order header details for Order Number'). ' ' . $OrderNo .
			' ' . _('from the database');
	$sql = "SELECT
			purchorders.supplierno,
			suppliers.suppname,
			suppliers.address1,
			suppliers.address2,
			suppliers.address3,
			suppliers.address4,
			purchorders.comments,
			purchorders.orddate,
			purchorders.rate,
			purchorders.dateprinted,
			purchorders.deladd1,
			purchorders.deladd2,
			purchorders.deladd3,
			purchorders.deladd4,
			purchorders.deladd5,
			purchorders.deladd6,
			purchorders.allowprint,
			purchorders.requisitionno,
			purchorders.initiator,
			purchorders.paymentterms,
			suppliers.currcode
		FROM purchorders INNER JOIN suppliers
			ON purchorders.supplierno = suppliers.supplierid
		WHERE purchorders.orderno='" . $OrderNo ."'";
	$result=DB_query($sql,$db, $ErrMsg);
	if (DB_num_rows($result)==0){ /*There is no order header returned */
		$title = _('Print Purchase Order Error');
		include('includes/header.inc');
		echo '<div class="centre"><br /><br /><br />';
		prnMsg( _('Unable to Locate Purchase Order Number') . ' : ' . $OrderNo . ' ', 'error');
		echo '<br /><br /><br /><table class="table_index">
			<tr><td class="menu_group_item">
					<li><a href="'. $rootpath . '/PO_SelectOSPurchOrder.php">' . _('Outstanding Purchase Orders') . '</a></li>
					<li><a href="'. $rootpath . '/PO_SelectPurchOrder.php">' . _('Purchase Order Inquiry') . '</a></li>
					</td></tr></table></div><br /><br /><br />';
		include('includes/footer.inc');
		exit();
	} elseif (DB_num_rows($result)==1){ /*There is only one order header returned */
		$POHeader = DB_fetch_array($result);
		if ($ViewingOnly==0) {
			if ($POHeader['allowprint']==0){
				$title = _('Purchase Order Already Printed');
				include('includes/header.inc');
				echo '<br />';
				prnMsg( _('Purchase Order Number').' ' . $OrderNo . ' '.
					_('has previously been printed') . '. ' . _('It was printed on'). ' ' .
				ConvertSQLDate($POHeader['dateprinted']) . '<br />'.
					_('To re-print the order it must be modified to allow a reprint'). '<br />'.
					_('This check is there to ensure that duplicate purchase orders are not sent to the supplier resulting in several deliveries of the same supplies'), 'warn');
				echo '<br /><table class="table_index">
					<tr><td class="menu_group_item">
 					<li><a href="' . $rootpath . '/PO_PDFPurchOrder.php?OrderNo=' . $OrderNo . '&ViewingOnly=1">'.
				_('Print This Order as a Copy'). '</a>
 				<li><a href="' . $rootpath . '/PO_Header.php?ModifyOrderNumber=' . $OrderNo . '">'.
				_('Modify the order to allow a real reprint'). '</a>' .
				'<li><a href="'. $rootpath .'/PO_SelectPurchOrder.php">'.
				_('Select another order'). '</a>'.
				'<li><a href="' . $rootpath . '/index.php">'. _('Back to the menu').'</a>';
				include('includes/footer.inc');
				exit;
			}//AllowedToPrint
		}//not ViewingOnly
	}// 1 valid record
}//if there is a valid order number
else if ($OrderNo=='Preview') {// We are previewing the order
/* Fill the order header details with dummy data */
	$POHeader['supplierno']=str_pad('',10,'x');
	$POHeader['suppname']=str_pad('',40,'x');
	$POHeader['address1']=str_pad('',40,'x');
	$POHeader['address2']=str_pad('',40,'x');
	$POHeader['address3']=str_pad('',40,'x');
	$POHeader['address4']=str_pad('',30,'x');
	$POHeader['comments']=str_pad('',50,'x');
	$POHeader['orddate']='1900-01-01';
	$POHeader['rate']='0.0000';
	$POHeader['dateprinted']='1900-01-01';
	$POHeader['deladd1']=str_pad('',40,'x');
	$POHeader['deladd2']=str_pad('',40,'x');
	$POHeader['deladd3']=str_pad('',40,'x');
	$POHeader['deladd4']=str_pad('',40,'x');
	$POHeader['deladd5']=str_pad('',20,'x');
	$POHeader['deladd6']=str_pad('',15,'x');
	$POHeader['allowprint']=1;
	$POHeader['requisitionno']=str_pad('',15,'x');
	$POHeader['initiator']=str_pad('',50,'x');
	$POHeader['paymentterms']=str_pad('',15,'x');
	$POHeader['currcode']='XXX';
} // end of If we are previewing the order
/* Load the relevant xml file */
if (isset($MakePDFThenDisplayIt) or isset($MakePDFThenEmailIt)) {
	if ($OrderNo=='Preview') {
		$FormDesign = simplexml_load_file(sys_get_temp_dir().'/PurchaseOrder.xml');
	} else {
		$FormDesign = simplexml_load_file($PathPrefix.'companies/'.$_SESSION['DatabaseName'].'/FormDesigns/PurchaseOrder.xml');
	}
// Set the paper size/orintation
	$PaperSize = $FormDesign->PaperSize;
	include('includes/PDFStarter.php');
	$pdf->addInfo('Title', _('Purchase Order') );
	$pdf->addInfo('Subject', _('Purchase Order Number' ) . ' ' . $OrderNo);
	$line_height = $FormDesign->LineHeight;
	$PageNumber = 1;
	/* Then there's an order to print and its not been printed already (or its been flagged for reprinting)
	Now ... Has it got any line items */
	if ($OrderNo !='Preview') { // It is a real order
		$ErrMsg = _('There was a problem retrieving the line details for order number') . ' ' . $OrderNo . ' ' .
			_('from the database');
		$sql = "SELECT itemcode,
					deliverydate,
				itemdescription,
				unitprice,
				uom as units,
				quantityord,
				decimalplaces
			FROM purchorderdetails LEFT JOIN stockmaster
				ON purchorderdetails.itemcode=stockmaster.stockid
			LEFT JOIN unitsofmeasure
				ON purchorderdetails.uom=unitsofmeasure.unitid
			WHERE orderno ='" . $OrderNo ."'";
		$result=DB_query($sql,$db);
	}
	if ($OrderNo=='Preview' or DB_num_rows($result)>0){
		/*Yes there are line items to start the ball rolling with a page header */
		include('includes/PO_PDFOrderPageHeader.inc');
		$YPos=$Page_Height - $FormDesign->Data->y;
		$OrderTotal = 0;
		while ((isset($OrderNo) and $OrderNo=='Preview') or (isset($result) and $POLine=DB_fetch_array($result))) {
			/* If we are previewing the order then fill the
			 * order line with dummy data */
			if ($OrderNo=='Preview') {
				$POLine['itemcode']=str_pad('',10,'x');
				$POLine['deliverydate']='1900-01-01';
				$POLine['itemdescription']=str_pad('',50,'x');
				$POLine['unitprice']=9999.99;
				$POLine['units']=str_pad('',4,'x');
				$POLine['quantityord']=999.99;
				$POLine['decimalplaces']=2;
			}
			$DisplayQty = locale_number_format($POLine['quantityord'],$POLine['decimalplaces']);
			if ($_POST['ShowAmounts']=='Yes'){
				$DisplayPrice = locale_money_format($POLine['unitprice'],$POHeader['currcode']);
			} else {
				$DisplayPrice = "----";
			}
			$DisplayDelDate = ConvertSQLDate($POLine['deliverydate'],2);
			if ($_POST['ShowAmounts']=='Yes'){
				$DisplayLineTotal = locale_money_format($POLine['unitprice']*$POLine['quantityord'],$POHeader['currcode']);
			} else {
				$DisplayLineTotal = "----";
			}
			/* Dont search for supplier data if it is a preview */
			if ($OrderNo !='Preview') {
				//check the supplier code from code item
				$sqlsupp = "SELECT suppliers_partno, supplierdescription
				FROM purchdata
					WHERE stockid='" . $POLine['itemcode'] . "'
					AND supplierno ='" . $POHeader['supplierno'] . "'";

				$SuppResult = DB_query($sqlsupp,$db);

				if ( DB_num_rows($SuppResult) > 0 ) {
					$SuppDescRow = DB_fetch_row($SuppResult);

					$Desc = $SuppDescRow[0] . " - ";

					// If the supplier's desc. is provided, use it;
					// otherwise, use the stock's desc.
					if ( mb_strlen($SuppDescRow[1]) > 2 ) {
						$Desc .= $SuppDescRow[1];
					}
					else {
						$Desc .= $POLine['itemdescription'];
					}
				}
				else {
					// No purchdata found, so use the stock's desc.
					$Desc = $POLine['itemdescription'];
				}
			} else {
				// We are previewing; use the preview's desc.
				$Desc = $POLine['itemdescription'];
			}
			$OrderTotal += ($POLine['unitprice']*$POLine['quantityord']);

			//use suppliers itemcode if available i.e. stringlength >0
			if (strlen($POLine['suppliers_partno']>0)) {
				$Itemcode=$POLine['suppliers_partno'];
			} else {
				$Itemcode=$POLine['itemcode'];
			}
			$LeftOvers = $pdf->addTextWrap($FormDesign->Data->Column1->x,$YPos,$FormDesign->Data->Column1->Length,$FormDesign->Data->Column1->FontSize,$Itemcode, 'left');
			$LeftOvers = $pdf->addTextWrap($FormDesign->Data->Column2->x,$YPos,$FormDesign->Data->Column2->Length,$FormDesign->Data->Column2->FontSize,$Desc, 'left');
			$LeftOvers = $pdf->addTextWrap($FormDesign->Data->Column3->x,$YPos,$FormDesign->Data->Column3->Length,$FormDesign->Data->Column3->FontSize,$DisplayQty, 'left');
			$LeftOvers = $pdf->addTextWrap($FormDesign->Data->Column4->x,$YPos,$FormDesign->Data->Column4->Length,$FormDesign->Data->Column4->FontSize,$POLine['units'], 'left');
			$LeftOvers = $pdf->addTextWrap($FormDesign->Data->Column5->x,$YPos,$FormDesign->Data->Column5->Length,$FormDesign->Data->Column5->FontSize,$DisplayDelDate, 'left');
			$LeftOvers = $pdf->addTextWrap($FormDesign->Data->Column6->x,$YPos,$FormDesign->Data->Column6->Length,$FormDesign->Data->Column6->FontSize,$DisplayPrice, 'right');
			$LeftOvers = $pdf->addTextWrap($FormDesign->Data->Column7->x,$YPos,$FormDesign->Data->Column7->Length,$FormDesign->Data->Column7->FontSize,$DisplayLineTotal, 'right');
			if (mb_strlen($LeftOvers)>1){
				$LeftOvers = $pdf->addTextWrap($Left_Margin+1+94,$YPos-$line_height,270,$FontSize,$LeftOvers, 'left');
				$YPos-=$line_height;
			}
			if ($YPos-$line_height <= $Bottom_Margin){
				/* We reached the end of the page so finsih off the page and start a newy */
				$PageNumber++;
				$YPos=$Page_Height - $FormDesign->Data->y;
				include ('includes/PO_PDFOrderPageHeader.inc');
			} //end if need a new page headed up
			/*increment a line down for the next line item */
			$YPos -= $line_height;
			/* If we are previewing we want to stop showing order
			 * lines after the first one */
			if ($OrderNo=='Preview') {
//				unlink(sys_get_temp_dir().'/PurchaseOrder.xml');
				unset($OrderNo);
			}
		} //end while there are line items to print out
		if ($YPos-$line_height <= $Bottom_Margin){ // need to ensure space for totals
				$PageNumber++;
				include ('includes/PO_PDFOrderPageHeader.inc');
		} //end if need a new page headed up
		if ($_POST['ShowAmounts']=='Yes'){
			$DisplayOrderTotal = locale_money_format($OrderTotal,$POHeader['currcode']);
		} else {
			$DisplayOrderTotal = "----";
		}
		$pdf->addText($FormDesign->OrderTotalCaption->x,$Page_Height - $FormDesign->OrderTotalCaption->y, $FormDesign->OrderTotalCaption->FontSize, _('Order Total - excl tax'). ' ' . $POHeader['currcode']);
		$LeftOvers = $pdf->addTextWrap($FormDesign->OrderTotal->x,$Page_Height - $FormDesign->OrderTotal->y,$FormDesign->OrderTotal->Length,$FormDesign->OrderTotal->FontSize,$DisplayOrderTotal, 'right');
	} /*end if there are order details to show on the order*/
	//} /* end of check to see that there was an order selected to print */
	//failed var to allow us to print if the email fails.
	$failed = false;
	if ($MakePDFThenDisplayIt){
		$pdf->OutputD($_SESSION['DatabaseName'] . '_PurchaseOrder_' . date('Y-m-d') . '.pdf');//UldisN
		$pdf->__destruct(); //UldisN
	} else { /* must be MakingPDF to email it */
		/* UldisN
	  	$pdfcode = $pdf->output();
		$fp = fopen( $_SESSION['reports_dir'] . '/PurchOrder.pdf','wb');
		fwrite ($fp, $pdfcode);
		fclose ($fp);
		*/
		$PdfFileName = $_SESSION['DatabaseName'] . '_PurchaseOrder_' . date('Y-m-d') . '.pdf';
		$ReportsDirName = $_SESSION['reports_dir'];
		$pdf->Output($ReportsDirName . '/' . $PdfFileName,'F');//UldisN
		$pdf->__destruct(); //UldisN
		include('includes/htmlMimeMail.php');
		$mail = new htmlMimeMail();
		$attachment = $mail->getFile($ReportsDirName . '/' . $PdfFileName);
		$mail->setText( _('Please find herewith our purchase order number').' ' . $OrderNo);
		$mail->setSubject( _('Purchase Order Number').' ' . $OrderNo);
		$mail->addAttachment($attachment, $PdfFileName, 'application/pdf');
		$mail->setFrom($_SESSION['CompanyRecord']['coyname'] . "<" . $_SESSION['CompanyRecord']['email'] .">");
		$result = $mail->send(array($_POST['EmailTo']));
		if ($result==1){
			$failed = false;
			echo '<br />';
			prnMsg( _('Purchase Order'). ' ' . $OrderNo.' ' . _('has been emailed to') .' ' . $_POST['EmailTo'] . ' ' . _('as directed'), 'success');
		} else {
			$failed = true;
			echo '<br />';
			prnMsg( _('Emailing Purchase order'). ' ' . $OrderNo.' ' . _('to') .' ' . $_POST['EmailTo'] . ' ' . _('failed'), 'error');
		}
	}
	if ($ViewingOnly==0 and !$failed) {
		$commentsql="SELECT initiator,stat_comment FROM purchorders WHERE orderno='".$OrderNo."'";
		$commentresult=DB_query($commentsql,$db);
		$commentrow=DB_fetch_array($commentresult);
		$comment=$commentrow['stat_comment'];
		$emailsql="SELECT email FROM www_users WHERE userid='".$commentrow['initiator']."'";
		$emailresult=DB_query($emailsql, $db);
		$emailrow=DB_fetch_array($emailresult);
		$date = date($_SESSION['DefaultDateFormat']);
		$StatusComment=$date.' - Printed by <a href="mailto:'.$emailrow['email'].'">'.$_SESSION['UserID'].
			'</a><br />'.$comment;
		$sql = "
			UPDATE purchorders
			SET
				allowprint	=  0,
				dateprinted  = '" . Date('Y-m-d') . "',
				status		= '" . PurchOrder::STATUS_PRINTED . "',
				stat_comment = '" . $StatusComment . "'
			WHERE
				purchorders.orderno = '" .  $OrderNo."'";
		$result = DB_query($sql,$db);
	}
} /* There was enough info to either print or email the purchase order */
 else { /*the user has just gone into the page need to ask the question whether to print the order or email it to the supplier */
	include ('includes/header.inc');

	echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/printer.png" title="' . _('Search') . '" alt="" />' . ' ' .
		$title . '</p><br />';

	echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	if ($ViewingOnly==1){
		echo '<input type="hidden" name="ViewingOnly" value="1" />';
	}
	echo '<input type="hidden" name="OrderNo" value="'. $OrderNo. '" />';
	echo '<table class="selection"><tr><td>'. _('Print or Email the Order'). '</td><td>
		<select name="PrintOrEmail">';
	if (!isset($_POST['PrintOrEmail'])){
		$_POST['PrintOrEmail'] = 'Print';
	}
	if ($_POST['PrintOrEmail']=='Print'){
		echo '<option selected="True" value="Print">'. _('Print') . '</option>';
		echo '<option value="Email">' . _('Email') . '</option>';
	} else {
		echo '<option value="Print">'. _('Print') . '</option>';
		echo '<option selected="True" value="Email">'. _('Email') . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><td>'. _('Show Amounts on the Order'). '</td><td>
		<select name="ShowAmounts">';
	if (!isset($_POST['ShowAmounts'])){
		$_POST['ShowAmounts'] = 'Yes';
	}
	if ($_POST['ShowAmounts']=='Yes'){
		echo '<option selected="True" value="Yes">'. _('Yes') . '</option>';
		echo '<option value="No">' . _('No') . '</option>';
	} else {
		echo '<option value="Yes">'. _('Yes') . '</option>';
		echo '<option selected="True" value="No">'. _('No') . '</option>';
	}
	echo '</select></td></tr>';
	if ($_POST['PrintOrEmail']=='Email'){
		$ErrMsg = _('There was a problem retrieving the contact details for the supplier');
		$SQL = "SELECT suppliercontacts.contact,
				suppliercontacts.email
			FROM suppliercontacts INNER JOIN purchorders
			ON suppliercontacts.supplierid=purchorders.supplierno
			WHERE purchorders.orderno='".$OrderNo."'";
		$ContactsResult=DB_query($SQL,$db, $ErrMsg);
		if (DB_num_rows($ContactsResult)>0){
			echo '<tr><td>'. _('Email to') .':</td><td><select name="EmailTo">';
			while ($ContactDetails = DB_fetch_array($ContactsResult)){
				if (mb_strlen($ContactDetails['email'])>2 AND mb_strpos($ContactDetails['email'],'@')>0){
					if ($_POST['EmailTo']==$ContactDetails['email']){
						echo '<option selected="True" value="' . $ContactDetails['email'] . '">' . $ContactDetails['Contact'] . ' - ' . $ContactDetails['email'] . '</option>';
					} else {
						echo '<option value="' . $ContactDetails['email'] . '">' . $ContactDetails['contact'] . ' - ' . $ContactDetails['email'] . '</option>';
					}
				}
			}
			echo '</select></td></tr></table>';
		} else {
			echo '</table><br />';
			prnMsg ( _('There are no contacts defined for the supplier of this order') . '. ' .
				_('You must first set up supplier contacts before emailing an order'), 'error');
			echo '<br />';
		}
	} else {
		echo '</table>';
	}
	echo '<br /><div class="centre"><button type="submit" name="DoIt">' . _('OK') . '</button></div>';
	echo '</form>';
	include('includes/footer.inc');
}
?>