<?php
/* $Id$ */

include ('includes/session.inc');
$title = _('Update of Prices By A Multiple Of Cost');
include ('includes/header.inc');

echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/inventory.png" title="' . _('Inventory') . '" alt="" />' . ' ' . _('Update Price By Cost') . '</p>';

if (isset($_POST['submit']) or isset($_POST['update'])) {
	if ($_POST['Margin'] == '') {
		header('Location: PricesByCost.php');
	}
	if ($_POST['Comparator'] == 1) {
		$Comparator = '<=';
	} else {
		$Comparator = '>=';
	} /*end of else Comparator */
	if ($_POST['StockCat'] != 'all') {
		$Category = " AND stockmaster.categoryid = '" . $_POST['StockCat'] . "'";
	} else {
		$Category ='';
	}/*end of else StockCat */

	$sql = "SELECT 	stockmaster.stockid,
				stockmaster.description,
				prices.debtorno,
				prices.branchcode,
				(stockmaster.materialcost + stockmaster.labourcost + stockmaster.overheadcost) as cost,
				prices.price as price, prices.debtorno as customer, prices.branchcode as branch,
				prices.startdate,
				prices.enddate,
				prices.units,
				prices.conversionfactor
			FROM stockmaster
			LEFT JOIN prices
			ON stockmaster.stockid=prices.stockid
			WHERE stockmaster.discontinued = 0" . $Category . "
			AND   prices.price" . $Comparator . "(stockmaster.materialcost + stockmaster.labourcost + stockmaster.overheadcost)*conversionfactor * '" . filter_number_input($_POST['Margin']) . "'
			AND prices.typeabbrev ='" . $_POST['SalesType'] . "'
			AND prices.currabrev ='" . $_POST['CurrCode'] . "'
			AND (prices.enddate>='" . Date('Y-m-d') . "' OR prices.enddate='2030-01-01')";
	$result = DB_query($sql, $db);
	$numrow = DB_num_rows($result);

	if ($_POST['submit'] == 'Update') {
			//Update Prices
		$PriceCounter =0;
		while ($PriceCounter < $_POST['Counter']) {
			if (!isset($_POST['DebtorNo_' . $PriceCounter])) {
				$_POST['DebtorNo_' . $PriceCounter]='';
				$_POST['BranchCode_' . $PriceCounter]='';
			}
			$SQLTestExists = "SELECT price FROM prices
										WHERE stockid = '" . $_POST['StockID_' . $PriceCounter] . "'
									AND prices.typeabbrev ='" . $_POST['SalesType'] . "'
									AND prices.currabrev ='" . $_POST['CurrCode'] . "'
									AND prices.debtorno ='" . $_POST['DebtorNo_' . $PriceCounter] . "'
									AND prices.branchcode ='" . $_POST['BranchCode_' . $PriceCounter] . "'
									AND prices.startdate<='" . date('Y-m-d') . "'
									AND prices.enddate>'" . date('Y-m-d') . "'";
			$TestExistsResult = DB_query($SQLTestExists,$db);
			if (DB_num_rows($TestExistsResult)==1){
			//then we are updating
				$SQLUpdate = "UPDATE prices
									SET price = '" . filter_currency_input($_POST['Price_' . $PriceCounter]) . "'
								WHERE stockid = '" . $_POST['StockID_' . $PriceCounter] . "'
									AND prices.typeabbrev ='" . $_POST['SalesType'] . "'
									AND prices.currabrev ='" . $_POST['CurrCode'] . "'
									AND prices.debtorno ='" . $_POST['DebtorNo_' . $PriceCounter] . "'
									AND prices.branchcode ='" . $_POST['BranchCode_' . $PriceCounter] . "'
									AND prices.units ='" . $_POST['Units_' . $PriceCounter] . "'
									AND prices.conversionfactor ='" . filter_number_input($_POST['ConversionFactor_' . $PriceCounter]) . "'
									AND prices.startdate<='" . date('Y-m-d') . "'";
				$ResultUpdate = DB_query($SQLUpdate, $db);
				if (DB_error_no($db)==0) {
					prnMsg( _('The price for') . ' ' . $_POST['StockID_' . $PriceCounter] . ' ' . _('has been updated in the database'), 'success');
				} else {
					prnMsg( _('The price for') . ' ' . $_POST['StockID_' . $PriceCounter] . ' ' . _('could not be updated'), 'error');
				}
				echo '<br />';
			} else {
				//we need to add a new price from today
				$SQLInsert = "INSERT INTO prices (
							stockid,
							price,
							typeabbrev,
							currabrev,
							debtorno,
							branchcode,
							startdate,
							enddate,
							units,
							conversionfactor
						) VALUES (
							'" . $_POST['StockID_' . $PriceCounter] . "',
							'" . filter_currency_input($_POST['Price_' . $PriceCounter]) . "',
							'" . $_POST['SalesType'] . "',
							'" . $_POST['CurrCode'] . "',
							'" . $_POST['DebtorNo_' . $PriceCounter] . "',
							'" . $_POST['BranchCode_' . $PriceCounter] . "',
							'" . date('Y-m-d') . "',
							'2030-01-01',
							'" . $_POST['Units_' . $PriceCounter] . "',
							'" . filter_number_input($_POST['ConversionFactor_' . $PriceCounter]) . "'
						)";
				$ResultInsert = DB_query($SQLInsert, $db);
				if (DB_error_no($db)==0) {
					prnMsg( _('The price for') . ' ' . $_POST['StockID_' . $PriceCounter] . ' ' . _('has been inserted in the database'), 'success');
				} else {
					prnMsg( _('The price for') . ' ' . $_POST['StockID_' . $PriceCounter] . ' ' . _('could not be inserted'), 'error');
				}
				echo '<br />';
			}
			$PriceCounter++;
		}
		DB_free_result($result); //clear the old result
		$result = DB_query($sql, $db); //re-run the query with the updated prices
		$numrow = DB_num_rows($result); // get the new number - should be the same!!
		echo '<p><div class="centre"><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">' . _('Back') . '<a/></div></p>';
		include('includes/footer.inc');
		exit;
	}

	$sqlcat = "SELECT categorydescription
				FROM stockcategory
				WHERE categoryid='" . $_POST['StockCat'] . "'";
	$ResultCat = DB_query($sqlcat, $db);
	$CategoryRow = DB_fetch_array($ResultCat);

	$sqltype = "SELECT sales_type
				FROM salestypes
				WHERE typeabbrev='" . $_POST['SalesType'] . "'";
	$ResultType = DB_query($sqltype, $db);
	$SalesTypeRow = DB_fetch_array($ResultType);

	if (isset($CategoryRow['categorgdescription'])) {
		$CategoryText = $CategoryRow['categorgdescription'] . ' ' . _('category');
	} else {
		$CategoryText = _('all Categories');
	} /*end of else Category */

	echo '<div class="page_help_text">' . _('Items in') . ' ' . $CategoryText . ' ' . _('With Prices') . ' ' . $Comparator . '' . $_POST['Margin'] . ' ' . _('times') . ' ' . _('Cost in Price List') . ' ' . $SalesTypeRow['sales_type'] . '</div><br /><br />';

	if ($numrow > 0) { //the number of prices returned from the main prices query is
		echo '<table class="selection">';
		echo '<tr><th>' . _('Code') . '</th>
						<th>' . _('Description') . '</th>
						<th>' . _('Customer') . '</th>
						<th>' . _('Branch') . '</th>
						<th>' . _('Start Date') . '</th>
						<th>' . _('End Date') . '</th>
						<th>' . _('Units') . '</th>
						<th>' . _('Conversion') .'<br />' . _('Factor') . '</th>
						<th>' . _('Cost') . '</th>
						<th>' . _('GP %') . '</th>
						<th>' . _('Price Proposed') . '</th>
						<th>' . _('List Price') . '</th>
					<tr>';
		$k = 0; //row colour counter
		echo '<form action="' .htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') .'" method="post" name="update">';
		echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
		echo'<input type="hidden" value="' . $_POST['StockCat'] . '" name="StockCat" />
			<input type="hidden" value="' . $_POST['Margin'] . '" name="Margin" />
			<input type="hidden" value="' . $_POST['CurrCode'] . '" name="CurrCode" />
			<input type="hidden" value="' . $_POST['Comparator'] . '" name="Comparator" />
			<input type="hidden" value="' . $_POST['SalesType'] . '" name="SalesType" />';

		$PriceCounter =0;
		while ($myrow = DB_fetch_array($result)) {

			if ($k == 1) {
				echo '<tr class="EvenTableRows">';
				$k = 0;
			} else {
				echo '<tr class="OddTableRows">';
				$k = 1;
			}
			//get cost
			if ($myrow['cost'] == '') {
				$Cost = 0;
			} else {
				$Cost = $myrow['cost']*$myrow['conversionfactor'];
			} /*end of else Cost */

			//variables for update
			echo '<input type="hidden" value="' . $myrow['stockid'] . '" name="StockID_' . $PriceCounter .'" />
					<input type="hidden" value="' . $myrow['debtorno'] . '" name="DebtorNo_' . $PriceCounter .'" />
					<input type="hidden" value="' . $myrow['branchcode'] . '" name="BranchCode_' . $PriceCounter .'" />
					<input type="hidden" value="' . $myrow['conversionfactor'] . '" name="ConversionFactor_' . $PriceCounter .'" />
					<input type="hidden" value="' . $myrow['units'] . '" name="Units_' . $PriceCounter .'" />
					<input type="hidden" value="' . $myrow['startdate'] . '" name="StartDate_' . $PriceCounter .'" />
					<input type="hidden" value="' . $myrow['enddate'] . '" name="EndDate_' . $PriceCounter .'" />';
			//variable for current margin
			if ($myrow['price'] != 0){
				$CurrentGP = ($myrow['price']-$Cost)*100 / $myrow['price'];
			} else {
				$CurrentGP = 0;
			}
			//variable for proposed
			$Proposed = $Cost * $_POST['Margin'];
			if ($myrow['enddate']=='0000-00-00'){
				$EndDateDisplay = _('No End Date');
			} else {
				$EndDateDisplay = ConvertSQLDate($myrow['enddate']);
			}
			echo '   <td>' . $myrow['stockid'] . '</td>
						<td>' . $myrow['description'] . '</td>
						<td>' . $myrow['customer'] . '</td>
						<td>' . $myrow['branch'] . '</td>
						<td>' . ConvertSQLDate($myrow['startdate']) . '</td>
						<td>' . $EndDateDisplay . '</td>
						<td>' . $myrow['units'] . '</td>
						<td class="number">' . locale_number_format($myrow['conversionfactor'],4) . '</td>
						<td class="number">' . locale_money_format($Cost, $_POST['CurrCode']) . '</td>
						<td class="number">' . locale_number_format($CurrentGP, 1) . '%</td>
						<td class="number">' . locale_money_format($Proposed, $_POST['CurrCode']) . '</td>
						<td><input type="text" class="number" name="Price_' . $PriceCounter . '" maxlength="14" size="10" value="' . locale_money_format($myrow['price'], $_POST['CurrCode']) . '" /></td>
					</tr> ';
			$PriceCounter++;
		} //end of looping
		echo '<input type="hidden" name="Counter" value="' . $PriceCounter . '" />';
		echo '<tr>
			<td colspan="12" style="text-align:center"><button type="submit" name="submit">' . _('Update') . '</button>
			<a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '"><button type="submit">' . _('Back') . '</button><a/></td>
			 </tr></form>';
	} else {
		prnMsg(_('There were no prices meeting the criteria specified to review'),'info');
		echo '<p><div class="centre"><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">' . _('Back') . '<a/></div></p>';
	}
} else { /*The option to submit was not hit so display form */
	echo '<div class="page_help_text">' . _('Prices can be displayed based on their relation to cost') . '</div><br />';
	echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post"><table class="selection">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	$SQL = "SELECT categoryid, categorydescription
		      FROM stockcategory
			  ORDER BY categorydescription";
	$result1 = DB_query($SQL, $db);
	echo '<tr>
			<td>' . _('Category') . ':</td>
			<td><select name="StockCat">';
	echo '<option value="all">' . _('All Categories') . '' . '</option>';
	while ($myrow1 = DB_fetch_array($result1)) {
		echo '<option value="' . $myrow1['categoryid'] . '">' . $myrow1['categorydescription'] . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><td>' . _('Price') . '
				<select name="Comparator">';
	echo '<option value="1">' . _('Less than or equal to') . '' . '</option>';
	echo '<option value="2">' . _('Greater than or equal to') . '' . '</option>';
	if ($_SESSION['WeightedAverageCosting']==1) {
		echo '</select>'.' '. _('Average Cost') . ' x </td>';
	} else {
		echo '</select>'.' '. _('Standard Cost') . ' x </td>';
	}
	if (!isset($_POST['Margin'])){
		$_POST['Margin']=1;
	}
	echo '<td>
				<input type="text" class="number" name="Margin" maxlength="8" size="8" value="' . locale_number_format($_POST['Margin'],2) . '" /></td></tr>';
	$result = DB_query("SELECT typeabbrev, sales_type FROM salestypes ", $db);
	echo '<tr><td>' . _('Sales Type') . '/' . _('Price List') . ':</td>
		<td><select name="SalesType">';
	while ($myrow = DB_fetch_array($result)) {
		if ($_POST['SalesType'] == $myrow['typeabbrev']) {
			echo '<option selected="True" value="' . $myrow['typeabbrev'] . '">' . $myrow['sales_type'] . '</option>';
		} else {
			echo '<option value="' . $myrow['typeabbrev'] . '">' . $myrow['sales_type'] . '</option>';
		}
	} //end while loop
	DB_data_seek($result, 0);
	$result = DB_query("SELECT currency, currabrev FROM currencies", $db);
	echo '</select></td></tr>
		<tr><td>' . _('Currency') . ':</td>
		<td><select name="CurrCode">';
	while ($myrow = DB_fetch_array($result)) {
		if (isset($_POST['CurrCode']) and $_POST['CurrCode'] == $myrow['currabrev']) {
			echo '<option selected="True" value="' . $myrow['currabrev'] . '">' . $myrow['currency'] . '</option>';
		} else {
			echo '<option value="' . $myrow['currabrev'] . '">' . $myrow['currency'] . '</option>';
		}
	} //end while loop
	DB_data_seek($result, 0);
	echo '</select></td></tr>';
	echo '</table><br /><div class="centre"><button type="submit" name="submit">' . _('Submit') . '</button></div>';
} /*end of else not submit */
include ('includes/footer.inc');
?>