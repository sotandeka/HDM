<?php
/* $Revision: 1.14 $ */
/* $Id$*/

include('includes/session.inc');

$title = _('Item Prices');

include('includes/header.inc');
include('includes/SQL_CommonFunctions.inc');

//initialise no input errors assumed initially before we test
$InputError = 0;


if (isset($_GET['Item'])){
	$Item = trim(mb_strtoupper($_GET['Item']));
}elseif (isset($_POST['Item'])){
	$Item = trim(mb_strtoupper($_POST['Item']));
}

if (!isset($_POST['TypeAbbrev']) OR $_POST['TypeAbbrev']==""){
	$_POST['TypeAbbrev'] = $_SESSION['DefaultPriceList'];
}

if (!isset($_POST['CurrAbrev'])){
	$_POST['CurrAbrev'] = $_SESSION['CompanyRecord']['currencydefault'];
}

echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/money_add.png" title="' . _('Search') .  '" alt="" />' . '</img>' . $title.'</p>';

echo '<div class="centre"><a href="' . $rootpath . '/SelectProduct.php">' . _('Back to Items') . '</a></div>';


$result = DB_query("SELECT stockmaster.description,
							stockmaster.mbflag,
							stockmaster.units
					FROM stockmaster
					WHERE stockmaster.stockid='".$Item."'",$db);
$myrow = DB_fetch_array($result);
$DefaultUOM=$myrow['units'];

if (DB_num_rows($result)==0){
	prnMsg( _('The part code entered does not exist in the database') . '. ' . _('Only valid parts can have prices entered against them'),'error');
	$InputError=1;
}


if (!isset($Item)){
	echo '<br />';
	prnMsg (_('An item must first be selected before this page is called') . '. ' . _('The product selection page should call this page with a valid product code'),'error');
	include('includes/footer.inc');
	exit;
}

$PartDescription = $myrow[0];

if ($myrow[1]=='K'){
	prnMsg(_('The part selected is a kit set item') .', ' . _('these items explode into their components when selected on an order') . ', ' . _('prices must be set up for the components and no price can be set for the whole kit'),'error');
	exit;
}

if (isset($_POST['submit'])) {

	/* actions to take once the user has clicked the submit button
	ie the page has called itself with some user input */

	//first off validate inputs sensible
	// This gives some date in 1999?? $ZeroDate = Date($_SESSION['DefaultDateFormat'],Mktime(0,0,0,0,0,0));
	$_POST['Price'] = filter_currency_input($_POST['Price']);
	if (!is_double((double) trim($_POST['Price'])) OR $_POST['Price']=='') {
		$InputError = 1;
		prnMsg( _('The price entered must be numeric'),'error');
	}
	if (! Is_Date($_POST['StartDate'])){
		$InputError =1;
		prnMsg (_('The date this price is to take effect from must be entered in the format') . ' ' . $_SESSION['DefaultDateFormat'],'error');
	}

	if (FormatDateForSQL($_POST['EndDate'])!='0000-00-00'){
		if (! Is_Date($_POST['EndDate']) AND $_POST['EndDate']!=''){
			$InputError =1;
			prnMsg (_('The date this price is be in effect to must be entered in the format') . ' ' . $_SESSION['DefaultDateFormat'],'error');
		}
		if (Date1GreaterThanDate2($_POST['StartDate'],$_POST['EndDate']) AND $_POST['EndDate']!='' AND FormatDateForSQL($_POST['EndDate'])!='0000-00-00'){
			$InputError =1;
			prnMsg (_('The end date is expected to be after the start date, enter an end date after the start date for this price'),'error');
		}
		if (Date1GreaterThanDate2(Date($_SESSION['DefaultDateFormat']),$_POST['EndDate']) AND $_POST['EndDate']!='' AND FormatDateForSQL($_POST['EndDate'])!='0000-00-00'){
			$InputError =1;
			prnMsg(_('The end date is expected to be after today. There is no point entering a new price where the effective date is before today!'),'error');
		}
	}
	if (Is_Date($_POST['EndDate'])){
		$SQLEndDate = FormatDateForSQL($_POST['EndDate']);
	} else {
		$SQLEndDate = '2030-01-01';
	}

	if (isset($_POST['OldTypeAbbrev']) AND isset($_POST['OldCurrAbrev']) AND mb_strlen($Item)>1 AND $InputError !=1) {

		/* Need to see if there is also a price entered that has an end date after the start date of this price and if
		so we will need to update it so there is no ambiguity as to which price will be used*/


		//editing an existing price
		$sql = "UPDATE prices SET
					typeabbrev='" . $_POST['TypeAbbrev'] . "',
					currabrev='" . $_POST['CurrAbrev'] . "',
					price='" . filter_currency_input($_POST['Price']) . "',
					units='" . $_POST['Units'] . "',
					conversionfactor='" . $_POST['ConversionFactor'] . "',
					decimalplaces='" . $_POST['DecimalPlaces'] . "',
					startdate='" . FormatDateForSQL($_POST['StartDate']) . "',
					enddate='" . $SQLEndDate . "'
				WHERE prices.stockid='".$Item."'
				AND startdate='" .$_POST['OldStartDate'] . "'
				AND enddate ='" . $_POST['OldEndDate'] . "'
				AND prices.typeabbrev='" . $_POST['OldTypeAbbrev'] . "'
				AND prices.currabrev='" . $_POST['OldCurrAbrev'] . "'
				AND prices.debtorno=''";

		$ErrMsg = _('Could not be update the existing prices');
		$result = DB_query($sql,$db,$ErrMsg);

		ReSequenceEffectiveDates ($Item, $_POST['TypeAbbrev'], $_POST['CurrAbrev'], $db) ;

		prnMsg(_('The price has been updated'),'success');
		echo '<br />';

	} elseif ($InputError !=1) {

	/*Selected price is null cos no item selected on first time round so must be adding a	record must be submitting new entries in the new price form */
		$sql = "UPDATE prices SET
					enddate='" . date('Y-m-d') . "'
				WHERE prices.stockid='".$Item."'
				AND startdate='" .FormatDateForSQL($_POST['StartDate']) . "'
				AND enddate ='" . $SQLEndDate . "'
				AND prices.typeabbrev='" . $_POST['TypeAbbrev'] . "'
				AND prices.currabrev='" . $_POST['CurrAbrev'] . "'
				AND prices.debtorno=''";

		$ErrMsg = _('Could not be update the existing prices');
		$result = DB_query($sql,$db,$ErrMsg);

		ReSequenceEffectiveDates ($Item, $_POST['TypeAbbrev'], $_POST['CurrAbrev'], $db) ;

		$sql = "INSERT INTO prices (stockid,
									typeabbrev,
									currabrev,
									units,
									conversionfactor,
									decimalplaces,
									startdate,
									enddate,
									price)
							VALUES ('".$Item."',
								'" . $_POST['TypeAbbrev'] . "',
								'" . $_POST['CurrAbrev'] . "',
								'" . $_POST['Units'] . "',
								'" . filter_number_input($_POST['ConversionFactor']) . "',
								'" . $_POST['DecimalPlaces'] . "',
								'" . FormatDateForSQL($_POST['StartDate']) . "',
								'" . $SQLEndDate. "',
								'" . filter_currency_input($_POST['Price']) . "')";
		$ErrMsg = _('The new price could not be added');
		$result = DB_query($sql,$db,$ErrMsg);

		ReSequenceEffectiveDates ($Item, $_POST['TypeAbbrev'], $_POST['CurrAbrev'], $db) ;
		prnMsg(_('The new price has been inserted'),'success');
		echo '<br />';
	}
	unset($_POST['Price']);
	unset($_POST['StartDate']);
	unset($_POST['EndDate']);
	unset($_POST['Units']);
	unset($_POST['ConversionFactor']);
	unset($_POST['DecimalPlaces']);

} elseif (isset($_GET['delete'])) {
//the link to delete a selected record was clicked instead of the submit button

	$sql="DELETE FROM prices
				WHERE prices.stockid = '". $Item ."'
				AND prices.typeabbrev='". $_GET['TypeAbbrev'] ."'
				AND prices.currabrev ='". $_GET['CurrAbrev'] ."'
				AND  prices.startdate = '" .$_GET['StartDate'] . "'
				AND  prices.enddate = '" . $_GET['EndDate'] . "'
				AND prices.debtorno=''";
	$ErrMsg = _('Could not delete this price');
	$result = DB_query($sql,$db,$ErrMsg);
	prnMsg( _('The selected price has been deleted'),'success');
	echo '<br />';
}

//Always do this stuff
if ($InputError ==0){
	$sql = "SELECT currencies.currency,
				salestypes.sales_type,
			prices.units,
			prices.price,
			prices.conversionfactor,
			prices.decimalplaces,
			prices.stockid,
			prices.typeabbrev,
			prices.currabrev,
			prices.startdate,
			prices.enddate
		FROM prices,
			salestypes,
			currencies
		WHERE prices.currabrev=currencies.currabrev
		AND prices.typeabbrev = salestypes.typeabbrev
		AND prices.stockid='".$Item."'
		AND prices.debtorno=''
		ORDER BY prices.typeabbrev ASC,
			prices.currabrev,
			prices.startdate";

	$result = DB_query($sql,$db);

	if (DB_num_rows($result) > 0) {
		echo '<table class="selection">';
		echo '<tr><th colspan="8"><form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">';
		echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
		echo _('Pricing for part') . ':<input type="text" name="Item" maxsize="22" value="' . $Item . '" maxlength="20" />
				<button type="submit" name="NewPart">' . _('Review Prices') . '</button>';
		echo '</th></tr></form>';

		echo '<tr>
				<th>' . _('Sales Type') . '</th>
				<th>' . _('Currency') . '</th>
				<th>' . _('UOM') . '</th>
				<th>' . _('Conversion') . '<br />' . _('Factor') . '</th>
				<th>' . _('Decimal') . '<br />' . _('Places') . '</th>
				<th>' . _('Price') . '</th>
				<th>' . _('Start Date') . ' </th>
				<th>' . _('End Date') . '</th></tr>';

		$k=0; //row colour counter

		while ($myrow = DB_fetch_array($result)) {
			if ($k==1){
				echo '<tr class="EvenTableRows">';
				$k=0;
			} else {
				echo '<tr class="OddTableRows">';
				$k=1;
			}
			if ($myrow['enddate']=='0000-00-00'){
				$EndDateDisplay = _('No End Date');
			} else {
				$EndDateDisplay = ConvertSQLDate($myrow['enddate']);
			}
			/*Only allow access to modify prices if securiy token 5 is allowed */
			if (in_array(12,$_SESSION['AllowedPageSecurityTokens'])) {

				echo '<input type="hidden" name="Units" value="' . $myrow['units'] . '" />';
				echo '<td>'.$myrow['sales_type'].'</td>
						<td>'.$myrow['currency'].'</td>
						<td>'.$myrow['units'].'</td>
						<td class="number">'.locale_number_format($myrow['conversionfactor'],4).'</td>
						<td class="number">'.$myrow['decimalplaces'].'</td>
						<td class="number">'.locale_money_format($myrow['price'],$myrow['currabrev']).'</td>
						<td>'.ConvertSQLDate($myrow['startdate']).'</td>
						<td>'.$EndDateDisplay.'</td>
						</tr>';
			} else {
				echo '<td>'.$myrow['sales_type'].'</td>
						<td>'.$myrow['currency'].'</td>
						<td>'.$myrow['units'].'</td>
						<td class="number">'.locale_number_format($myrow['conversionfactor']).'</td>
						<td class="number">'.$myrow['decimalplaces'].'</td>
						<td class="number">'.locale_money_format($myrow['price'],$myrow['currabrev']).'</td>
						<td>'.ConvertSQLDate($myrow['startdate']).'</td>
						<td>'.$EndDateDisplay.'</td>
						</tr>';
			}

		}
		//END WHILE LIST LOOP
		echo '</table><br />';
	} else {
		prnMsg(_('There are no prices set up for this part'),'warn');
	}

	if (in_array(12,$_SESSION['AllowedPageSecurityTokens'])) {
		echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">';
		echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
		if (isset($_GET['Edit'])){
			$sql = "SELECT currencies.currency,
				salestypes.sales_type,
				prices.units,
				prices.price,
				prices.conversionfactor,
				prices.decimalplaces,
				prices.stockid,
				prices.typeabbrev,
				prices.currabrev,
				prices.startdate,
				prices.enddate
			FROM prices,
				salestypes,
				currencies
			WHERE prices.currabrev=currencies.currabrev
				AND prices.typeabbrev = salestypes.typeabbrev
				AND prices.stockid='".$Item."'
				AND prices.debtorno=''
				AND prices.typeabbrev='". $_GET['TypeAbbrev'] . "'
				AND prices.currabrev='". $_GET['CurrAbrev'] . "'
				AND prices.startdate='". $_GET['StartDate'] . "'
				AND prices.enddate='". $_GET['EndDate'] . "'
			ORDER BY prices.currabrev,
				prices.typeabbrev,
				prices.startdate";

			$result = DB_query($sql,$db);
			$myrow = DB_fetch_array($result);
			echo '<input type="hidden" name="OldTypeAbbrev" value="' . $myrow['typeabbrev'] .'" />';
			echo '<input type="hidden" name="OldCurrAbrev" value="' . $myrow['currabrev'] . '" />';
			echo '<input type="hidden" name="OldStartDate" value="' . $myrow['startdate'] . '" />';
			echo '<input type="hidden" name="OldEndDate" value="' . $myrow['enddate'] . '" />';
			$_POST['CurrAbrev'] = $myrow['currabrev'];
			$_POST['TypeAbbrev'] = $myrow['typeabbrev'];
			$_POST['Price'] = $myrow['price'];
			$_POST['Units'] = $myrow['units'];
			$_POST['ConversionFactor'] = $myrow['conversionfactor'];
			$_POST['DecimalPlaces'] = $myrow['decimalplaces'];
			$_POST['StartDate'] = ConvertSQLDate($myrow['startdate']);
			if ($_GET['EndDate']=='' OR $_GET['EndDate']=='0000-00-00'){
				$_POST['EndDate'] = '';
			} else {
				$_POST['EndDate'] = ConvertSQLDate($myrow['enddate']);
			}
		}

		echo '<br /><table class="selection">';
		echo '<tr><th colspan="5" class="header"><b>' . $Item . ' - ' . $PartDescription . '</b></th></tr>';

		echo '<tr><td>' . _('Sales Type Price List') . ':</td><td><select name="TypeAbbrev">';

		$SQL = "SELECT typeabbrev, sales_type FROM salestypes";
		$result = DB_query($SQL,$db);

		while ($myrow = DB_fetch_array($result)) {
			if ($myrow['typeabbrev']==$_POST['TypeAbbrev']) {
				echo '<option selected="True" value="' . $myrow['typeabbrev'] . '">' . $myrow['sales_type'] . '</option>';
			} else {
				echo '<option value="' . $myrow['typeabbrev'] . '">' . $myrow['sales_type'] . '</option>';
			}
		} //end while loop
		echo '</select></td></tr>';

		DB_free_result($result);

		$SQL = "SELECT currabrev, currency FROM currencies";
		$result = DB_query($SQL,$db);
		echo '<tr><td>' . _('Currency') . ':</td><td><select name="CurrAbrev">';
		while ($myrow = DB_fetch_array($result)) {
			if ($myrow['currabrev']==$_POST['CurrAbrev']) {
				echo '<option selected="True" value="' . $myrow['currabrev'] . '">' . $myrow['currency'] . '</option>';
			} else {
				echo '<option value="' . $myrow['currabrev'] . '">' . $myrow['currency'] . '</option>';
			}
		} //end while loop
		echo '</select></td></tr>';

		DB_free_result($result);

		if (!isset($_POST['StartDate'])){
			$_POST['StartDate'] = Date($_SESSION['DefaultDateFormat']);
		}

		if (!isset($_POST['EndDate'])){
			$_POST['EndDate'] = DateAdd(date($_SESSION['DefaultDateFormat']),'y',3);
		}
		echo '<tr><td>' . _('Price Effective From Date')  . ':</td>
				<td><input type="text" class="date" alt="'.$_SESSION['DefaultDateFormat'].'" name="StartDate" size="10" maxlength="10" value="' . $_POST['StartDate'] . '" /></td></tr>';
		echo '<tr><td>' . _('Price Effective To Date')  . ':</td>
				<td><input type="text" class="date" alt="'.$_SESSION['DefaultDateFormat'].'" name="EndDate" size="10" maxlength="10" value="' . $_POST['EndDate'] . '" /></td></tr>';
		echo '<tr><td>' . _('Unit of Measure') . ':</td>';
		echo '<td><select name="Units">';
		$sql = "SELECT unitname FROM unitsofmeasure order by unitname asc";
		$result = DB_query($sql, $db);
		while ($myrow = DB_fetch_array($result)) {
			if ($_POST['Units'] == $myrow['unitname']) {
				echo '<option selected="True" value="' . $myrow['unitname'] . '">' . $myrow['unitname'] . '</option>';
			} else {
				echo '<option value="' . $myrow['unitname'] . '">' . $myrow['unitname'] . '</option>';
			}
		}
		echo '</td></tr>';
		echo '<input type="hidden" name="Item" value="'.$Item.'" />';

		echo '</select></td></tr>';

		echo '<tr><td>'. _('Decimal Places') . '<br />'._('to display').'</td>';

		if(isset($_POST['DecimalPlaces'])) {
			echo '<td><input type="text" class="number" name="DecimalPlaces" size="8" maxlength="8" value="' . locale_number_format($_POST['DecimalPlaces'],0) . '" />';
		} else {
			echo '<td><input type="text" class="number" name="DecimalPlaces" size="8" maxlength="8" value="0" />';
		}

		echo '</td></tr>';
		echo '<tr><td>'. _('Conversion Factor') . '<br />'._('to stock units').'</td>';

		if(isset($_POST['ConversionFactor'])) {
			echo '<td><input type="text" class="number" name="ConversionFactor" size="8" maxlength="8" value="' . locale_number_format($_POST['ConversionFactor'],4) . '" />';
		} else {
			echo '<td><input type="text" class="number" name="ConversionFactor" size="8" maxlength="8" value="' . locale_number_format(1.0000,4) . '" />';
		}

		echo '</td></tr>';

		echo '<tr><td>'. _('Price') . ':</td>';

		if(isset($_POST['Price'])) {
			echo '<td><input type="text" class="number" name="Price" size="12" maxlength="11" value="' . locale_money_format($_POST['Price'],$_POST['CurrAbrev']) . '" />';
		} else {
			echo '<td><input type="text" class="number" name="Price" size="12" maxlength="11" value="' . locale_money_format(0,$_POST['CurrAbrev']) . '" />';
		}

		echo '</td></tr>';

		echo '</table>';
		echo '<br /><div class="centre">';
		echo '<button type="submit" name="submit">'. _('Enter') . '/' . _('Amend Price') . '</button>';
		echo '</div><br />';
	}
}

echo '</form>';
include('includes/footer.inc');


function ReSequenceEffectiveDates ($Item, $PriceList, $CurrAbbrev, $db) {

	/*This is quite complicated - the idea is that prices set up should be unique and there is no way two prices could be returned as valid - when getting a price in includes/GetPrice.inc the logic is to first look for a price of the salestype/currency within the effective start and end dates - then if not get the price with a start date prior but a blank end date (the default price). We would not want two prices where one price falls inside another effective date range except in the case of a blank end date - ie no end date - the default price for the currency/salestype.
	I first thought that we would need to update the previous default price (blank end date), when a new default price is entered, to have an end date of the startdate of this new default price less 1 day - but this is  converting a default price into a special price which could result in having two special prices over the same date range - best to leave it unchanged and use logic in the GetPrice.inc to ensure the correct default price is returned
	*/
	//this is just the case where debtorno='' - see the Prices_Customer.php script for customer special prices
		$SQL = "SELECT price,
						startdate,
						enddate
						FROM prices
						WHERE debtorno=''
						AND stockid='" . $Item . "'
						AND currabrev='" . $CurrAbbrev . "'
						AND typeabbrev='" . $PriceList . "'
						AND enddate <>'0000-00-00'
						ORDER BY startdate, enddate";
		$result = DB_query($SQL,$db);
		$NextStartDate = Date($_SESSION['DefaultDateFormat']);
		unset($EndDate);
		unset($NextStartDate);
		while ($myrow = DB_fetch_array($result)){
			if (isset($NextStartDate)){
				if (Date1GreaterThanDate2(ConvertSQLDate($myrow['startdate']),$NextStartDate)){
					$NextStartDate = ConvertSQLDate($myrow['startdate']);
					//Only if the previous enddate is after the new start date do we need to look at updates
					if (Date1GreaterThanDate2(ConvertSQLDate($EndDate),ConvertSQLDate($myrow['startdate']))) {
						/*Need to make the end date the new start date less 1 day */
						$SQL = "UPDATE prices SET enddate = '" . FormatDateForSQL(DateAdd($NextStartDate,'d',-1))  . "'
										WHERE stockid ='" .$Item . "'
										AND currabrev='" . $CurrAbbrev . "'
										AND typeabbrev='" . $PriceList . "'
										AND startdate ='" . $StartDate . "'
										AND enddate = '" . $EndDate . "'
										AND debtorno =''";
						$UpdateResult = DB_query($SQL,$db);
					}
				} //end of if startdate  after NextStartDate - we have a new NextStartDate
			} //end of if set NextStartDate
				else {
					$NextStartDate = ConvertSQLDate($myrow['startdate']);
			}
			$StartDate = $myrow['startdate'];
			$EndDate = $myrow['enddate'];
			$Price = $myrow['price'];
		} // end of loop around all prices
} // end function ReSequenceEffectiveDates

?>