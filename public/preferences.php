<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Fez - Digital Repository System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2005-2009, The University of Queensland,               |
// | Australian Partnership for Sustainable Repositories,                 |
// | eScholarship Project                                                 |
// |                                                                      |
// | Some of the Fez code was derived from Eventum (Copyright 2003, 2004  |
// | MySQL AB - http://dev.mysql.com/downloads/other/eventum/ - GPL)      |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 2 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 59 Temple Place - Suite 330                                          |
// | Boston, MA 02111-1307, USA.                                          |
// +----------------------------------------------------------------------+
// | Authors: Christiaan Kortekaas <c.kortekaas@library.uq.edu.au>,       |
// |          Matthew Smith <m.smith@library.uq.edu.au>                   |
// +----------------------------------------------------------------------+
//
//
include_once("config.inc.php");
include_once(APP_INC_PATH . "class.template.php");
include_once(APP_INC_PATH . "class.auth.php");
include_once(APP_INC_PATH . "class.prefs.php");
include_once(APP_INC_PATH . "class.setup.php");
include_once(APP_INC_PATH . "class.date.php");
include_once(APP_INC_PATH . "class.author.php");
include_once(APP_INC_PATH . "class.db_api.php");

$tpl = new Template_API();
$tpl->setTemplate("preferences.tpl.html");

Auth::checkAuthentication(APP_COOKIE);

$usr_id = Auth::getUserID();

$username = Auth::getUsername();
$tpl->assign("isUser", $username);
$isAdministrator = User::isUserAdministrator($username);
if (Auth::userExists($username)) { // if the user is registered as a Fez user
	$tpl->assign("isFezUser", $username);
} else {
	// Not really possible to get here
	Auth::redirect(APP_RELATIVE_URL);
}
$tpl->assign("isAdministrator", $isAdministrator);
$isUPO = User::isUserUPO($isUser);
$tpl->assign("isUPO", $isUPO);

$mypub_url = @$_POST["mypub_url"];

if (@$_POST["cat"] == "update_account") {
    $res = Prefs::set($usr_id);
    $tpl->assign('update_account_result', $res);
} elseif (@$_POST["cat"] == "update_mypub_url") {
	$res = Author::updateMyPubURL($username, $mypub_url);
	$tpl->assign('update_mypub_url_result', $res);
} elseif (@$_POST["cat"] == "update_name") {
    $res = User::updateFullName($usr_id);
    $tpl->assign('update_name_result', $res);
} elseif (@$_POST["cat"] == "update_email") {
    $res = User::updateEmail($usr_id);
    $tpl->assign('update_email_result', $res);
} elseif (@$_POST["cat"] == "update_password") {
    $res = User::updatePassword($usr_id);
    $tpl->assign('update_password_result', $res);
} elseif (@$_POST["cat"] == "aut_update_identifiers") {
    $ids = array(
        'aut_people_australia_id' => @$_POST['aut_people_australia_id'],
        'aut_scopus_id' => @$_POST['aut_scopus_id'],
        'aut_orcid_id' => @$_POST['aut_orcid_id'],
        'aut_google_scholar_id' => @$_POST['aut_google_scholar_id'],
    );
    $res = Author::updateAuthorIdentifiers($username, $ids);
    $tpl->assign('aut_update_identifiers_result', $res);
}

$prefs = Prefs::get($usr_id);

$authorDetails = Author::getDetailsByUsername($username);
if (is_numeric($authorDetails['aut_id'])) {
	$isAuthor = 1;
	$myPubURLPref = $authorDetails['aut_mypub_url'];
    $aut_people_australia_id = $authorDetails['aut_people_australia_id'];
    $aut_scopus_id = $authorDetails['aut_scopus_id'];
    $aut_orcid_id = $authorDetails['aut_orcid_id'];
    $aut_google_scholar_id = $authorDetails['aut_google_scholar_id'];
} else {
	$isAuthor = 0;
	$myPubURLPref = "";
}
//$myPubURL = Author::getMyPubURL($username);
// if the user has no preferences set yet, get it from the system-wide options
if (empty($prefs)) {
    $prefs = Setup::load();
}
$shibAttribs = array();

if (isset($_SESSION[APP_SHIB_ATTRIBUTES_SESSION]) && is_array($_SESSION[APP_SHIB_ATTRIBUTES_SESSION])) {
	$counter = 0;
	foreach ($_SESSION[APP_SHIB_ATTRIBUTES_SESSION] as $name => $value) {
		if (is_numeric(strpos($name, "Shib-EP")) || is_numeric(strpos($name, "Shib-Person"))) {
			$shibAttribs[$counter]['name'] = $name;
			$shibAttribs[$counter]['value'] = $value;
			$counter++;
		}
	}
}

$front_pages = array("front_page" => "Standard Full Front Page",
                      "simple_front_page" => "Simple Front Page",
                      "very_simple_front_page" => "Very Simple Front Page");


$tpl->assign("SHIB_SWITCH", SHIB_SWITCH);
$tpl->assign("shibAttribs", $shibAttribs);
$tpl->assign("user_prefs", $prefs);
$tpl->assign("myPubURLPref", $myPubURLPref);
$tpl->assign("aut_people_australia_id", $aut_people_australia_id);
$tpl->assign("aut_scopus_id", $aut_scopus_id);
$tpl->assign("aut_orcid_id", $aut_orcid_id);
$tpl->assign("aut_google_scholar_id", $aut_google_scholar_id);
$tpl->assign("isAuthor", $isAuthor);
$tpl->assign("front_pages", $front_pages);
$tpl->assign("zones", Date_API::getTimezoneList());

if (Auth::isInDB() && !Auth::isInAD() && !Auth::isInFederation()) {
    $tpl->assign("local_db_user", "1");
} else {
    $tpl->assign("local_db_user", "0");
}

$tpl->assign("active_nav", "preferences");
$tpl->displayTemplate();
