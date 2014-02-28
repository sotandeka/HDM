<?php
/* $Id$*/

include('includes/session.inc');
$title=_('Debtors Control Integrity');
include('includes/header.inc');


//
//========[ SHOW OUR FORM ]===========
//
	echo '<a href="'. $rootpath . '/index.php?&Application=AR">' . _('Back to Customers') . '</a>';
	// Page Border
	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/customer.png" title="' . _('Purchase') . '" alt="" />' . ' ' . $title . '</p>';
	echo '<table class="selection">';
	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	// Context Navigation and Title


	$DefaultFromPeriod = ( !isset($_POST['FromPeriod']) OR $_POST['FromPeriod']=='' ) ? 1 : $_POST['FromPeriod'];

	if ( !isset($_POST['ToPeriod']) OR $_POST['ToPeriod']=='' )
	{
			$SQL = "SELECT MAX(periodno) AS maxperiodno FROM periods";
			$prdResult = DB_query($SQL,$db);
			$MaxPrdrow = DB_fetch_array($prdResult);
			DB_free_result($prdResult);
			$DefaultToPeriod = $MaxPrdrow['maxperiodno'];
	} else {
			$DefaultToPeriod = $_POST['ToPeriod'];
	}

	echo '<tr>
			<td>' . _('Start Period:') . '</td>
			<td><select name="FromPeriod">';
	$ToSelect = '<tr>
					<td>' . _('End Period:') .'</td>
					<td><select name="ToPeriod">';

	$SQL = "SELECT periodno, lastdate_in_period FROM periods ORDER BY periodno";
	$PerResult = DB_query($SQL,$db);

	while ( $PerRow=DB_fetch_array($PerResult) ) {
		$FromSelected = ( $PerRow['periodno'] == $DefaultFromPeriod ) ? 'selected' : '';
		echo '<option ' . $FromSelected . ' value="' . $PerRow['periodno'] . '">' .MonthAndYearFromSQLDate($PerRow['lastdate_in_period']) . '</option>';

		$ToSelected = ( $PerRow['periodno'] == $DefaultToPeriod ) ? 'selected' : '';
		$ToSelect .= '<option ' . $ToSelected . ' value="' . $PerRow['periodno'] . '">' . MonthAndYearFromSQLDate($PerRow['lastdate_in_period']) . '</option>';
	}
	DB_free_result($PerResult);
	echo '</select></td></tr>';


	echo $ToSelect . '</select></td></tr></table>';


	echo '<br /><div class="centre"><button type="submit" name="Show">'._('Accept'). '</button>';
	echo '<button type="submit" action="reset">' . _('Cancel') .'</button></div>';


	if ( isset($_POST['Show']) ) {
		//
		//========[ SHOW SYNOPSYS ]===========
		//
		echo '<br /><table class="selection">';
		echo '<tr>
				<th>' . _('Period') . '</th>
				<th>' . _('Bal B/F in GL') . '</th>
				<th>' . _('Invoices') . '</th>
				<th>' . _('Receipts') . '</th>
				<th>' . _('Bal C/F in GL') . '</th>
				<th>' . _('Calculated') . '</th>
				<th>' . _('Difference') . '</th>
			</tr>';

		$CurPeriod = $_POST['FromPeriod'];
		$GLOpening = $InvTotal = $RecTotal = $GLClosing = $CalcTotal = $DiffTotal = 0;
		$j=0;
		$Diff=0;
		while ( $CurPeriod <= $_POST['ToPeriod'] ) {
			$SQL = "SELECT bfwd,
							actual
						FROM chartdetails
						WHERE period = " . $CurPeriod . "
							AND accountcode=" . $_SESSION['CompanyRecord']['debtorsact'];
			$DTResult = DB_query($SQL,$db);
			$DTRow = DB_fetch_array($DTResult);
			DB_free_result($DTResult);

			$GLOpening += $DTRow['bfwd'];
			$GLMovement = $DTRow['bfwd'] + $DTRow['actual'];

			if ($j==1) {
				echo '<tr class="OddTableRows">';
				$j=0;
			} else {
				echo '<tr class="EvenTableRows">';
				$j++;
			}
			echo '<td>' . $CurPeriod . '</td>
					<td class="number">' . locale_money_format($DTRow['bfwd'],$_SESSION['CompanyRecord']['currencydefault']) . '</td>';

			$SQL = "SELECT SUM((ovamount+ovgst+ovdiscount)*rate) AS totinvnetcrds
					FROM debtortrans
					WHERE prd = " . $CurPeriod . "
					AND (type=10 OR type=11)";
			$InvResult = DB_query($SQL,$db);
			$InvRow = DB_fetch_array($InvResult);
			DB_free_result($InvResult);

			$InvTotal += $InvRow['totinvnetcrds'];

			echo '<td class="number">' . locale_money_format($InvRow['totinvnetcrds'],$_SESSION['CompanyRecord']['currencydefault']) . '</td>';

			$SQL = "SELECT SUM((ovamount+ovgst+ovdiscount)*rate) AS totreceipts
					FROM debtortrans
					WHERE prd = " . $CurPeriod . "
					AND type=12";
			$RecResult = DB_query($SQL,$db);
			$RecRow = DB_fetch_array($RecResult);
			DB_free_result($RecResult);

			$RecTotal += $RecRow['totreceipts'];
			$CalcMovement = $DTRow['bfwd'] + $InvRow['totinvnetcrds'] + $RecRow['totreceipts'];

			echo '<td class="number">' . locale_money_format($RecRow['totreceipts'],$_SESSION['CompanyRecord']['currencydefault']) . '</td>';

			$GLClosing += $GLMovement;
			$CalcTotal += $CalcMovement;
			$DiffTotal += $Diff;

			$Diff = ( $DTRow['bfwd'] == 0 ) ? 0 : round($GLMovement,2) - round($CalcMovement,2);
			$Color = ( $Diff == 0 OR $DTRow['bfwd'] == 0 ) ? 'green' : 'red';

			echo '<td class="number">' . locale_money_format($GLMovement,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
					<td class="number">' . locale_money_format(($CalcMovement),$_SESSION['CompanyRecord']['currencydefault']) . '</td>
					<td class="number" bgcolor=white><font color="' . $Color . '">' . locale_money_format($Diff,$_SESSION['CompanyRecord']['currencydefault']) . '</font></td>
			</tr>';
			$CurPeriod++;
		}

		$DiffColor = ( $DiffTotal == 0 ) ? 'green' : 'red';

		echo '<tr bgcolor=white>
				<td>' . _('Total') . '</td>
				<td class="number">' . locale_money_format($GLOpening,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
				<td class="number">' . locale_money_format($InvTotal,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
				<td class="number">' . locale_money_format($RecTotal,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
				<td class="number">' . locale_money_format($GLClosing,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
				<td class="number">' . locale_money_format($CalcTotal,$_SESSION['CompanyRecord']['currencydefault']) . '</td>
				<td class="number"><font color="' . $DiffColor . '">' . locale_money_format($DiffTotal,$_SESSION['CompanyRecord']['currencydefault']) . '</font></td>
			</tr>';
		echo '</table></form>';
	}

include('includes/footer.inc');

?>