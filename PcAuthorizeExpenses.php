<?php
/* $Revision: 1.0 $ */

include('includes/session.inc');
$title = _('Authorisation of Petty Cash Expenses');
include('includes/header.inc');
include('includes/SQL_CommonFunctions.inc');

if (isset($_POST['SelectedTabs'])){
	$SelectedTabs = mb_strtoupper($_POST['SelectedTabs']);
} elseif (isset($_GET['SelectedTabs'])){
	$SelectedTabs = mb_strtoupper($_GET['SelectedTabs']);
}

if (isset($_POST['SelectedIndex'])){
	$SelectedIndex = $_POST['SelectedIndex'];
} elseif (isset($_GET['SelectedIndex'])){
	$SelectedIndex = $_GET['SelectedIndex'];
}

if (isset($_POST['Days'])){
	$Days = $_POST['Days'];
} elseif (isset($_GET['Days'])){
	$Days = $_GET['Days'];
}

if (isset($Errors)) {
	unset($Errors);
}

$Errors = array();

if (isset($_POST['process'])) {
	//initialise no input errors assumed initially before we test
	$InputError = 0;
	$i=1;

	if ($SelectedTabs=='') {
		$InputError = 1;
		prnMsg( _('You Must First Select a Petty Cash Tab To Authorise'),'error');
		$Errors[$i] = 'TabSelect';
		$i++;
		unset($SelectedTabs);
	}
}

if (isset($_POST['Go'])) {
	$InputError = 0;
	$i=1;
	if ($Days<=0) {
		$InputError = 1;
		prnMsg('<br />' . _('The number of days must be a positive number'),'error');
		$Errors[$i] = 'Days';
		$i++;
		$Days=30;
	}
}

if (isset($SelectedTabs)) {
	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/magnifier.png" title="' . _('Petty Cash') . '" alt="" />' .
			_('Authorisation Of Petty Cash Expenses ') . ''.$SelectedTabs.'</p>';
} else {
	echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/magnifier.png" title="' . _('Petty Cash') . '" alt="" />' .
			_('Authorisation Of Petty Cash Expenses ') . '</p>';
}
if (isset($_POST['submit']) or isset($_POST['update']) OR isset($SelectedTabs) OR isset ($_POST['GO'])) {

	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	if(!isset ($Days)){
		$Days=30;
	}
	echo '<input type="hidden" name="SelectedTabs" value="' . $SelectedTabs . '" />';
	echo '<p><div class="centre"><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">' . _('Select another tab') . '</a></div></p>';
	echo '<br /><table class="selection">';
	echo '<tr><th colspan="7">' . _('Detail Of Movement For Last ') .': ';
	echo '<input type="text" class="number" name="Days" value="' . $Days . '" maxlength="3" size="4" /> Days ';
	echo '<button type="submit" name="Go">' . _('Go') . '</button></tr></th>';
	echo '</form>';

	$sql = "SELECT pcashdetails.counterindex,
				pcashdetails.tabcode,
				pcashdetails.date,
				pcashdetails.codeexpense,
				pcashdetails.amount,
				pcashdetails.authorized,
				pcashdetails.posted,
				pcashdetails.notes,
				pcashdetails.receipt,
				pctabs.glaccountassignment,
				pctabs.glaccountpcash,
				pctabs.usercode,
				pctabs.currency,
				currencies.rate
			FROM pcashdetails, pctabs, currencies
			WHERE pcashdetails.tabcode = pctabs.tabcode
				AND pctabs.currency = currencies.currabrev
				AND pcashdetails.tabcode = '" . $SelectedTabs . "'
				AND pcashdetails.date >= DATE_SUB(CURDATE(), INTERVAL '".$Days."' DAY)
			ORDER BY pcashdetails.date, pcashdetails.counterindex ASC";

	$result = DB_query($sql,$db);

	echo '<tr>
			<th>' . _('Date') . '</th>
			<th>' . _('Expense Code') . '</th>
			<th>' . _('Amount') . '</th>
			<th>' . _('Posted') . '</th>
			<th>' . _('Notes') . '</th>
			<th>' . _('Receipt') . '</th>
			<th>' . _('Authorized') . '</th>
		</tr>';

	$k=0; //row colour counter
	echo '<form action="PcAuthorizeExpenses.php" method="post" name="'._('update').'">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	while ($myrow=DB_fetch_array($result))	{

		$Currency = $myrow['currency'];
		//update database if update pressed
		if ((isset($_POST['submit']) and $_POST['submit']=='Update') AND isset($_POST[$myrow['counterindex']])){

			$PeriodNo = GetPeriod(ConvertSQLDate($myrow['date']), $db);

			if ($myrow['rate'] == 1){ // functional currency
				$Amount = $myrow['amount'];
			}else{ // other currencies
				$Amount = $myrow['amount']/$myrow['rate'];
			}

			if ($myrow['codeexpense'] == 'ASSIGNCASH'){
				$type = 2;
				$AccountFrom = $myrow['glaccountassignment'];
				$AccountTo = $myrow['glaccountpcash'];
			}else{
				$type = 1;
				$Amount = -$Amount;
				$AccountFrom = $myrow['glaccountpcash'];
				$SQLAccExp = "SELECT glaccount,
									tag
								FROM pcexpenses
								WHERE codeexpense = '".$myrow['codeexpense']."'";
				$ResultAccExp = DB_query($SQLAccExp,$db);
				$myrowAccExp = DB_fetch_array($ResultAccExp);
				$AccountTo = $myrowAccExp['glaccount'];
				$TagTo = $myrowAccExp['tag'];
			}

			//get typeno
			$typeno = GetNextTransNo($type,$db);

			//build narrative
			$narrative= _('PettyCash') . ' - '.$myrow['tabcode'].' - '.$myrow['codeexpense'].' - '.$myrow['notes'].' - '.$myrow['receipt'];
			//insert to gltrans
			DB_Txn_Begin($db);

			$sqlFrom="INSERT INTO `gltrans`
					(`counterindex`,
					`type`,
					`typeno`,
					`chequeno`,
					`trandate`,
					`periodno`,
					`account`,
					`narrative`,
					`amount`,
					`posted`,
					`jobref`,
					`tag`)
			VALUES (NULL,
					'".$type."',
					'".$typeno."',
					0,
					'".$myrow['date']."',
					'".$PeriodNo."',
					'".$AccountFrom."',
					'".$narrative."',
					'".-$Amount."',
					0,
					'',
					'".$TagTo."')";

			$ResultFrom = DB_Query($sqlFrom, $db, '', '', true);

			$sqlTo="INSERT INTO `gltrans`
					(`counterindex`,
					`type`,
					`typeno`,
					`chequeno`,
					`trandate`,
					`periodno`,
					`account`,
					`narrative`,
					`amount`,
					`posted`,
					`jobref`,
					`tag`)
			VALUES (NULL,
					'".$type."',
					'".$typeno."',
					0,
					'".$myrow['date']."',
					'".$PeriodNo."',
					'".$AccountTo."',
					'".$narrative."',
					'".$Amount."',
					0,
					'',
					'".$TagTo."')";

			$ResultTo = DB_Query($sqlTo, $db, '', '', true);

			if ($myrow['codeexpense'] == 'ASSIGNCASH'){
			// if it's a cash assignation we need to updated banktrans table as well.
				$ReceiptTransNo = GetNextTransNo( 2, $db);
				$SQLBank= "INSERT INTO banktrans (transno,
							type,
							bankact,
							ref,
							exrate,
							functionalexrate,
							transdate,
							banktranstype,
							amount,
							currcode)
					VALUES ('". $ReceiptTransNo . "',
						1,
						'" . $AccountFrom . "',
						'" . $narrative . "',
						1,
						'" . $myrow['rate'] . "',
						'" . $myrow['date'] . "',
						'Cash',
						'" . -$myrow['amount'] . "',
						'" . $myrow['currency'] . "'
					)";
				$ErrMsg = _('Cannot insert a bank transaction because');
				$DbgMsg =  _('Cannot insert a bank transaction with the SQL');
				$resultBank = DB_query($SQLBank,$db,$ErrMsg,$DbgMsg,true);

			}

			$sql = "UPDATE pcashdetails
					SET authorized = '".Date('Y-m-d')."',
					posted = 1
					WHERE counterindex = '".$myrow['counterindex']."'";
			$resultupdate = DB_query($sql,$db, '', '', true);
			DB_Txn_Commit($db);
		}

		if ($k==1){
			echo '<tr class="EvenTableRows">';
			$k=0;
		} else {
			echo '<tr class="OddTableRows">';
			$k=1;
		}

		if ($myrow['posted']==0) {
			$Posted=_('No');
		} else {
			$Posted=_('Yes');
		}

		echo '<td>'.ConvertSQLDate($myrow['date']).'</td>
			<td>'.$myrow['codeexpense'].'</td>
			<td class="number">'.locale_money_format($myrow['amount'],$myrow['currency']).'</td>
			<td>'.$Posted.'</td>
			<td>'.$myrow['notes'].'</td>
			<td>'.$myrow['receipt'].'</td>';

		if (isset($_POST[$myrow['counterindex']])){
			echo'<td>'.ConvertSQLDate(Date('Y-m-d')).'</td>';
		}else{
			$Authorizer=ConvertSQLDate($myrow['authorized']);
			if(($Authorizer!='00/00/0000')){
				echo'<td>'.ConvertSQLDate($myrow['authorized']).'</td>';
			}else{
				echo '<td align="right"><input type="checkbox" name="'.$myrow['counterindex'].'" />';
			}
		}

		echo '<input type="hidden" name="SelectedIndex" value="' . $myrow['counterindex']. '" />';
		echo '<input type="hidden" name="SelectedTabs" value="' . $SelectedTabs . '" />';
		echo '<input type="hidden" name="Days" value="' .$Days. '" />';
		echo '</tr>';


	} //end of looping

	$sqlamount="SELECT sum(amount)
			FROM pcashdetails
			WHERE tabcode='".$SelectedTabs."'";

	$ResultAmount = DB_query($sqlamount,$db);
	$Amount=DB_fetch_array($ResultAmount);

	if (!isset($Amount['0'])) {
		$Amount['0']=0;
	}

	echo '<tr><td colspan="2" class="number">' . _('Current balance') . ':</td>
				<td class="number">'.locale_money_format($Amount['0'],$Currency).'</td></tr>';



	// Do the postings
	include ('includes/GLPostings.inc');

	echo'</table><br /><div class="centre"><button type="submit" name="submit">' . _('Update') . '</button></div></form><br />';

} else { /*The option to submit was not hit so display form */


	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<br /><table class="selection">'; //Main table

	echo '<tr><td>' . _('Authorize expenses to Petty Cash Tab') . ':</td><td><select name="SelectedTabs">';

	DB_free_result($result);
	$SQL = "SELECT tabcode
		FROM pctabs
		WHERE authorizer='" . $_SESSION['UserID'] . "'";

	$result = DB_query($SQL,$db);

	echo '<option value=""></option>';
	while ($myrow = DB_fetch_array($result)) {
		if (isset($_POST['SelectTabs']) and $myrow['tabcode']==$_POST['SelectTabs']) {
			echo '<option selected="True" value="' . $myrow['tabcode'] . '">' . $myrow['tabcode'] . '</option>';
		} else {
			echo '<option value="' . $myrow['tabcode'] . '">' . $myrow['tabcode'] . '</option>';
		}
	} //end while loop get type of tab

	echo '</select></td></tr>';

	echo '</td></tr></table>'; // close main table

	echo '<br /><div class="centre"><button type="submit" name="process">' . _('Accept') . '</button>
			<button type="submit" name="Cancel">' . _('Cancel') . '</button></div><br />';

	echo '</form>';
} /*end of else not submit */
include('includes/footer.inc');
?>