<?php
////////////////////////////////////////
// Salt & Pepper Encrypter v1.0
// (C) 2005 Nathan Bolender
// www.nathanbolender.com
////////////////////////////////////////
////////////////////////////////////////
// Feel free to use as you wish, but do
// not remove this copyright notice.
////////////////////////////////////////
// Redistribution prohibited! May only
// be distributed through
// www.nathanbolender.com
// Full license at:
// http://creativecommons.org/licenses/by-nc-nd/2.0/
////////////////////////////////////////
////////////////////////////
// Configuration
////////////////////////////
// Salt Key
//    Set this to anything you wish
//    but it must be specific to your
//    website and should never be
//    revealed to the public
$saltkey = '0x00x412ABfgh';
//    Note that if you change this key all of your stored passwords
//    will STOP WORKING! This value must be set correctly for pepper() to function correctly
//    If you have some experience you can set a different key for each password
//    But you must be able to retrieve that key to check the password !




////////////////////////////
// That's all!
// Now here is some usage instructions:
//
//  To get a hash to put into your database (encrypted password)
//  include this file and use this function:
//  salt('mypassword')
//  You can also set a static position and key hash like this:
//  salt('mypassword', 15, 'n')
//  Options for this is:
//   Position must be between 10 and 38
//   hash types are 'n' or 'b' where n is sha1 and b is md5
//
//  To check a string against a hash from the database:
//  pepper('mypass', '8fe5ccb19ba61c4c0873ddc')
//  This will return TRUE or FALSE, letting you do the action you
//  wish depending on the result.
//
//  Both of these functions also have a debug function which works like this:
//  salt('mypass', 'a', 'a', 1)  (note that a value of 'a' is the same as no value at all in this case
//  pepper('mypass', '8fe5ccb19ba61c4c0873ddc', 1)
//
//  This will echo the value of all of the variables set.
/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
///////////////////////// DO NOT EDIT BELOW THIS BLOCK! /////////////////////////
/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////
// You should not be down here!
//////////////////////////////////

function salt($string, $pos = 'a', $stype = 'a', $debug = 0) {
	global $saltkey;
	$stringA = sha1($string);
	if ($pos == 'a'): $pos = rand(10, 38);
	endif;
	if ((rand(1, 3) == 1) || ($stype == 'b')) {
		$salt = md5($saltkey);
		$stype = 'b';
		$slen = 32;
	} else {
		$salt = sha1($saltkey);
		$stype = 'n';
		$slen = 40;
	}
	$afterstr = substr($stringA, $pos);
	$startbeginning = -(strlen($afterstr));
	$beforestr = substr($stringA, 0, $startbeginning);
	$salted = $beforestr . $salt . $afterstr . $stype . $pos;

	if ($debug == 1) {
	echo '<br>$saltkey = '.$saltkey;
	echo '<br>$stringA = '.$stringA;
	echo '<br>$pos = '.$pos;
	echo '<br>$salt = '.$salt.'<br>$stype = '.$stype.'<br>$slen = '.$slen;
	echo '<br>$afterstr = '.$afterstr;
	echo '<br>$startbeginning = '.$startbeginning;
	echo '<br>$beforestr = '.$beforestr;
	echo '<br><br>$salted = '.$salted;
	}

	return $salted;
}

function pepper($str, $dbhash, $debug = 0) { // str = string to be checked against DBHASH
	global $saltkey;

	// Find the original sha1 hash  and check it with the new one
	$hashA = sha1($str); // new hash to be checked

	$pos = substr($dbhash, -2);

	$stype = substr($dbhash, -3, 1); // n or b

	if ($stype == 'n') {
		$slen = 40;
	} else {
		$slen = 32;
	}

	$beforesalt = substr($dbhash, 0, $pos);

	$aftersaltA = substr($dbhash, ($pos + $slen));

	$aftersalt = substr($aftersaltA, 0, -3);

	$saltA = substr($dbhash, $pos, ((-strlen($aftersalt)) - 3));

	if ($stype == 'n') {
		$salt = sha1($saltkey);
	} else {
		$salt = md5($saltkey);
	}

	$unsalted = $beforesalt . $aftersalt;

	if ($debug == 1) {
	echo '<br><br>$saltkey = '.$saltkey;
	echo '<br>$str = '.$str;
	echo '<br>$dbhash = '.$dbhash;
	echo '<br>$hashA = '.$hashA;
	echo '<br>$pos = '.$pos;
	echo '<br>$stype = '.$stype;
	echo '<br>$slen = '.$slen;
	echo '<br>$beforesalt = '.$beforesalt;
	echo '<br>$aftersaltA = '.$aftersaltA;
	echo '<br>$aftersalt = '.$aftersalt;
	echo '<br>$saltA = '.$saltA;
	echo '<br>$salt = '.$salt;
	echo '<br>$unsalted = '.$unsalted.'<br>if = ';
	}

	if (($hashA == $unsalted) && ($salt == $saltA)) {
		if ($debug == 1): echo 'true'; endif;
		return true;
	} else {
		if ($debug == 1): echo 'false'; endif;
		return false;
	}
}
?>