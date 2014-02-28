<?php

/* $Id$*/

include('includes/session.inc');

$title = _('Purchase Order Authorisation Maintenance');
include('includes/header.inc');

echo '<p class="page_title_text"><img src="'.$rootpath.'/css/'.$theme.'/images/group_add.png" title="' . _('Search') . '" alt="" />' . ' ' . $title.'</p><br />';
$User='';
$Currency='';
$CanCreate=0;
$OffHold=0;
$AuthLevel=0;
$InputError=0;
if (isset($_POST['Submit'])) {
	if (isset($_POST['CanCreate']) and $_POST['CanCreate']=='on') {
		$CanCreate=0;
	} else {
		$CanCreate=1;
	}
	if (isset($_POST['OffHold']) and $_POST['OffHold']=='on') {
		$OffHold=0;
	} else {
		$OffHold=1;
	}
	if ($_POST['AuthLevel']=='') {
		$_POST['AuthLevel']=0;
	}
	if ($_POST['UserID']=='') {
		prnMsg(_('You must select a user from the list'), 'error');
		echo '<br />';
		$InputError=1;
	}
	if ($_POST['CurrCode']=='') {
		prnMsg(_('You must select a currency from the list'), 'error');
		echo '<br />';
		$InputError=1;
	}

	$sql="SELECT COUNT(*)
			FROM purchorderauth
			WHERE userid='".$_POST['UserID']."'
		AND currabrev='".$_POST['CurrCode']."'";
	$result=DB_query($sql, $db);
	$myrow=DB_fetch_array($result);
	if ($myrow[0]==0 and $InputError==0) {
		$sql="INSERT INTO purchorderauth (
			userid,
			currabrev,
			cancreate,
			offhold,
			authlevel)
			VALUES(
			'".$_POST['UserID']."',
			'".$_POST['CurrCode']."',
			'".$CanCreate."',
			'".$OffHold."',
			'".filter_currency_input($_POST['AuthLevel'])."')";
		$ErrMsg = _('The authentication details cannot be inserted because');
		$Result=DB_query($sql,$db,$ErrMsg);
	} else {
		prnMsg(_('There already exists an entry for this user/currency combination'), 'error');
		echo '<br />';
	}
}

if (isset($_POST['Update'])) {
	if (isset($_POST['CanCreate']) and $_POST['CanCreate']=='on') {
		$CanCreate=0;
	} else {
		$CanCreate=1;
	}
	if (isset($_POST['OffHold']) and $_POST['OffHold']=='on') {
		$OffHold=0;
	} else {
		$OffHold=1;
	}
	$sql="UPDATE purchorderauth SET
			cancreate='".$CanCreate."',
			offhold='".$OffHold."',
			authlevel='".filter_currency_input($_POST['AuthLevel'])."'
		WHERE userid='".$_POST['UserID']."'
		AND currabrev='".$_POST['CurrCode']."'";

	$ErrMsg = _('The authentication details cannot be updated because');
	$Result=DB_query($sql,$db,$ErrMsg);
}

if (isset($_GET['Delete'])) {
	$sql="DELETE FROM purchorderauth
		WHERE userid='".$_GET['UserID']."'
		AND currabrev='".$_GET['Currency']."'";

	$ErrMsg = _('The authentication details cannot be deleted because');
	$Result=DB_query($sql,$db,$ErrMsg);
}

$sql="SELECT
	purchorderauth.userid,
	www_users.realname,
	currencies.currabrev,
	currencies.currency,
	purchorderauth.cancreate,
	purchorderauth.offhold,
	purchorderauth.authlevel
	FROM (purchorderauth
	LEFT JOIN www_users ON purchorderauth.userid=www_users.userid)
	LEFT JOIN currencies ON purchorderauth.currabrev=currencies.currabrev";

$ErrMsg = _('The authentication details cannot be retrieved because');
$Result=DB_query($sql,$db,$ErrMsg);

echo '<table class="selection"><tr>';
echo '<th>'._('User ID').'</th>';
echo '<th>'._('User Name').'</th>';
echo '<th>'._('Currency').'</th>';
echo '<th>'._('Create Order').'</th>';
echo '<th>'._('Can Release').'<br />'. _('Invoices').'</th>';
echo '<th>'._('Authority Level').'</th></tr>';

while ($myrow=DB_fetch_array($Result)) {
	if ($myrow['cancreate']==0) {
		$CanCreate='Yes';
	} else {
		$CanCreate='No';
	}
	if ($myrow['offhold']==0) {
		$OffHold='Yes';
	} else {
		$OffHold='No';
	}
	echo '<tr><td>'.$myrow['userid'].'</td>';
	echo '<td>'.$myrow['realname'].'</td>';
	echo '<td>'.$myrow['currency'].'</td>';
	echo '<td>'.$CanCreate.'</td>';
	echo '<td>'.$OffHold.'</td>';
	echo '<td class="number">'.locale_money_format($myrow['authlevel'],$myrow['currabrev']).'</td>';
	echo '<td><a href="'.$rootpath.'/PO_AuthorisationLevels.php?Edit=Yes&UserID=' . $myrow['userid'] . '&Currency='.$myrow['currabrev'].'">'._('Edit').'</td>';
	echo '<td><a href="'.$rootpath.'/PO_AuthorisationLevels.php?Delete=Yes&UserID=' . $myrow['userid'] . '&Currency='.$myrow['currabrev'].'">'._('Delete').'</td></tr>';
}

echo '</table><br />';

echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '" method="post" name="form1">';
echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
echo '<table class="selection">';

if (isset($_GET['Edit'])) {
	echo '<tr><td>'._('User ID').'</td><td>'.$_GET['UserID'].'</td></tr>';
	echo '<input type="hidden" name="UserID" value="'.$_GET['UserID'].'" />';
} else {
	echo '<tr><td>'._('User ID').'</td><td><select name="UserID">';
	$usersql="SELECT userid FROM www_users";
	$userresult=DB_query($usersql,$db);
	echo '<option value=""></option>';
	while ($myrow=DB_fetch_array($userresult)) {
		if ($myrow['userid']==$UserID) {
			echo '<option selected="True" value="'.$myrow['userid'].'">'.$myrow['userid'].'</option>';
		} else {
			echo '<option value="'.$myrow['userid'].'">'.$myrow['userid'].'</option>';
		}
	}
	echo '</select></td></tr>';
}

if (isset($_GET['Edit'])) {
	$sql="SELECT cancreate,
				offhold,
				authlevel
			FROM purchorderauth
		WHERE userid='".$_GET['UserID']."'
		AND currabrev='".$_GET['Currency']."'";
	$ErrMsg = _('The authentication details cannot be retrieved because');
	$result=DB_query($sql,$db,$ErrMsg);
	$myrow=DB_fetch_array($result);
	$UserID=$_GET['UserID'];
	$Currency=$_GET['Currency'];
	$CanCreate=$myrow['cancreate'];
	$OffHold=$myrow['offhold'];
	$AuthLevel=$myrow['authlevel'];
	if ($myrow['cancreate']==0) {
		$CanCreate='Yes';
	} else {
		$CanCreate='No';
	}
	if ($myrow['offhold']==0) {
		$OffHold='Yes';
	} else {
		$OffHold='No';
	}
	$currencysql="SELECT currency FROM currencies WHERE currabrev='".$Currency."'";
	$currencyresult=DB_query($currencysql,$db);
	$myrow=DB_fetch_array($currencyresult);
	echo '<tr><td>'._('Currency').'</td><td>'.$myrow['currency'].'</td></tr>';
	echo '<input type="hidden" name="CurrCode" value="'.$Currency.'" />';
} else {
	echo '<tr><td>'._('Currency').'</td><td><select name="CurrCode">';
	echo '<option value=""></option>';
	$currencysql="SELECT currabrev,currency FROM currencies";
	$currencyresult=DB_query($currencysql,$db);
	while ($myrow=DB_fetch_array($currencyresult)) {
		if ($myrow['currabrev']==$Currency) {
			echo '<option selected="True" value="'.$myrow['currabrev'].'">'.$myrow['currency'].'</option>';
		} else {
			echo '<option value="'.$myrow['currabrev'].'">'.$myrow['currency'].'</option>';
		}
	}
	echo '</select></td></tr>';
}

echo '<tr><td>'._('User can create orders').'</td>';
if (isset($CanCreate) and $CanCreate=='No') {
	echo '<td><input type="checkbox" name="CanCreate" /></td></tr>';
} else {
	echo '<td><input type="checkbox" checked="True" name="CanCreate" /></td></tr>';
}

echo '<tr><td>'._('User can release invoices').'</td>';
if (isset($OffHold) and $OffHold=='No') {
	echo '<td><input type="checkbox" name="OffHold" /></td></tr>';
} else {
	echo '<td><input type="checkbox" checked="True" name="OffHold" /></td></tr>';
}

echo '<tr><td>'._('User can authorise orders up to :').'</td>';
if ($Currency!='') {
	echo '<td><input type="input" name="AuthLevel" size="11" class="number" value="'.locale_money_format($AuthLevel, $Currency).'" /></td></tr>';
} else {
	echo '<td><input type="input" name="AuthLevel" size="11" class="number" value="0" /></td></tr>';
}
echo '</table>';

if (isset($_GET['Edit'])) {
	echo '<br /><div class="centre"><button type="submit" name="Update">'._('Update Information').'</button></div><br /></form>';
} else {
	echo '<br /><div class="centre"><button type="submit" name="Submit">'._('Enter Information').'</button></div><br /></form>';
}
include('includes/footer.inc');
?>