<?php

/* $Id$*/
/* $Revision: 1.16 $ */

include ('includes/session.inc');
$title = _('General Ledger Transaction Inquiry');
include('includes/header.inc');

// Page Border
$menuUrl = '<a href="'. $rootpath . '/index.php?Application=GL">' . _('General Ledger Menu') . '</a></div>';

if ( !isset($_GET['TypeID']) OR !isset($_GET['TransNo']) ) {
	prnMsg(_('This page requires a valid transaction type and number'),'warn');
	echo $menuUrl;
} else {
	$typeSQL = "SELECT typename,
						typeno
				FROM systypes
				WHERE typeid = '" . $_GET['TypeID'] . "'";

	$TypeResult = DB_query($typeSQL,$db);

	if ( DB_num_rows($TypeResult) == 0 ){
		prnMsg(_('No transaction of this type with id') . ' ' . $_GET['TypeID'],'error');
		echo $menuUrl;
	} else {
		$myrow = DB_fetch_row($TypeResult);
		DB_free_result($TypeResult);
		$TransName = $myrow[0];

		// Context Navigation and Title
		echo $menuUrl;
		//
		//========[ SHOW SYNOPSYS ]===========
		//
		echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/magnifier.png" title="' . _('Print') . '" alt="" />' . ' ' . $title . '</p>';
		echo '<table class="selection">'; //Main table
		echo '<tr><th colspan="7"><font size="3" color="blue"><b>' . $TransName . ' ' . $_GET['TransNo'] . '</b></font></th></tr>';
		echo '<tr>
				<th>' . _('Date') . '</th>
				<th>' . _('Period') .'</th>
				<th>'. _('GL Account') .'</th>
				<th>'. _('Debits') .'</th>
				<th>'. _('Credits') .'</th>
				<th>' . _('Description') .'</th>
				<th>'. _('Posted') . '</th>
			</tr>';

		$SQL = "SELECT gltrans.type,
						gltrans.trandate,
						gltrans.periodno,
						gltrans.account,
						gltrans.narrative,
						gltrans.amount,
						gltrans.posted,
						chartmaster.accountname,
						periods.lastdate_in_period
					FROM gltrans
					INNER JOIN chartmaster
						ON gltrans.account = chartmaster.accountcode
					INNER JOIN periods
						ON periods.periodno=gltrans.periodno
					WHERE gltrans.type= '" . $_GET['TypeID'] . "'
						AND gltrans.typeno = '" . $_GET['TransNo'] . "'
					ORDER BY gltrans.counterindex";
		$TransResult = DB_query($SQL,$db);

		$Posted = _('Yes');
		$CreditTotal = $DebitTotal = 0;

		$j = 1;
		while ( $TransRow = DB_fetch_array($TransResult) ) {
			$TranDate = ConvertSQLDate($TransRow['trandate']);
			$DetailResult = false;

			if ( $TransRow['amount'] > 0) {
				$DebitAmount = locale_money_format($TransRow['amount'],$_SESSION['CompanyRecord']['currencydefault']);
				$DebitTotal += $TransRow['amount'];
				$CreditAmount = '&nbsp';
			} else {
				$CreditAmount = locale_money_format(-$TransRow['amount'],$_SESSION['CompanyRecord']['currencydefault']);
				$CreditTotal += $TransRow['amount'];
				$DebitAmount = '&nbsp';
			}

			if ( $TransRow['posted']==0 ){
				$Posted = _('No');
			}

			if ( $TransRow['account'] == $_SESSION['CompanyRecord']['debtorsact'] )	{
				$URL = $rootpath . '/CustomerInquiry.php?CustomerID=';
				$date = '&TransAfterDate=' . $TranDate;

				$DetailSQL = "SELECT debtortrans.debtorno,
									debtortrans.ovamount,
									debtortrans.ovgst,
									debtortrans.ovfreight,
									debtortrans.rate,
									debtorsmaster.name
								FROM debtortrans,
									debtorsmaster
								WHERE debtortrans.debtorno = debtorsmaster.debtorno
									AND debtortrans.type = '" . $TransRow['type'] . "'
									AND debtortrans.transno = '" . $_GET['TransNo']. "'";
				$DetailResult = DB_query($DetailSQL,$db);
			} elseif ( $TransRow['account'] == $_SESSION['CompanyRecord']['creditorsact'] )	{
				$URL = $rootpath . '/SupplierInquiry.php?SupplierID=';
				$date = '&FromDate=' . $TranDate;

				$DetailSQL = "SELECT supptrans.supplierno,
									supptrans.ovamount,
									supptrans.ovgst,
									supptrans.rate,
									suppliers.suppname
								FROM supptrans,
									suppliers
								WHERE supptrans.supplierno = suppliers.supplierid
									AND supptrans.type = '" . $TransRow['type'] . "'
									AND supptrans.transno = '" . $_GET['TransNo'] . "'";
				$DetailResult = DB_query($DetailSQL,$db);
			} else {
				$URL = $rootpath . '/GLAccountInquiry.php?Account=' . $TransRow['account'];

				if( mb_strlen($TransRow['narrative'])==0 ) {
					$TransRow['narrative'] = '&nbsp';
				}

				if ($j==1) {
					echo '<tr class="OddTableRows">';
					$j=0;
				} else {
					echo '<tr class="EvenTableRows">';
					$j++;
				}
				echo '<td>' . $TranDate . '</td>
						<td>' . MonthAndYearFromSQLDate($TransRow['lastdate_in_period']) . '</td>
						<td><a href="' . $URL . '">' . $TransRow['accountname'] . '</a></td>
						<td class="number">' . $DebitAmount . '</td>
						<td class="number">' . $CreditAmount . '</td>
						<td>' . $TransRow['narrative'] . '</td>
						<td>' . $Posted . '</td>
					</tr>';
			}

			if ($DetailResult) {
				while ( $DetailRow = DB_fetch_row($DetailResult) ) {
					if ( $TransRow['amount'] > 0){
						if ($TransRow['account'] == $_SESSION['CompanyRecord']['debtorsact']) {
							$Debit = locale_money_format(($DetailRow[1] + $DetailRow[2]+ $DetailRow[3]) / $DetailRow[4],$_SESSION['CompanyRecord']['currencydefault']);
							$Credit = '&nbsp';
						} else {
							$Debit = locale_money_format((-$DetailRow[1] - $DetailRow[2] ) / $DetailRow[3],$_SESSION['CompanyRecord']['currencydefault']);
							$Credit = '&nbsp';
						}
					} else {
						if ($TransRow['account'] == $_SESSION['CompanyRecord']['debtorsact']) {
							$Credit = locale_money_format(-($DetailRow[1] + $DetailRow[2] + $DetailRow[3]) / $DetailRow[4],$_SESSION['CompanyRecord']['currencydefault']);
							$Debit = '&nbsp';
						} else {
							$Credit = locale_money_format(($DetailRow[1] + $DetailRow[2]) / $DetailRow[3],$_SESSION['CompanyRecord']['currencydefault']);
							$Debit = '&nbsp';
						}
					}

					if ($j==1) {
						echo '<tr class="OddTableRows">';
						$j=0;
					} else {
						echo '<tr class="EvenTableRows">';
						$j++;
					}
					echo '<td>' . $TranDate . '</td>
							<td>' . MonthAndYearFromSQLDate($TransRow['lastdate_in_period']) . '</td>
							<td><a href="' . $URL . $DetailRow[0] . $date . '">' . $TransRow['accountname']  . ' - ' . $DetailRow[5] . '</a></td>
							<td class="number">' . $Debit . '</td>
							<td class="number">' . $Credit . '</td>
							<td>' . $TransRow['narrative'] . '</td>
							<td>' . $Posted . '</td>
						</tr>';
				}
				DB_free_result($DetailResult);
			}
		}
		DB_free_result($TransResult);

		echo '<tr bgcolor="#FFFFFF">
					<td class="number" colspan="3"><b>' . _('Total') . '</b></td>
					<td class="number">' . locale_money_format(($DebitTotal),$_SESSION['CompanyRecord']['currencydefault']) . '</td>
					<td class="number">' . locale_money_format((-$CreditTotal),$_SESSION['CompanyRecord']['currencydefault']) . '</td>
					<td colspan="2">&nbsp</td>
				</tr>';
		echo '</table><br />';
	}

}

echo '</td></tr></table>'; // end Page Border
include('includes/footer.inc');

?>