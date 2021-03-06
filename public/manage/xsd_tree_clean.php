<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Fez - Digital Repository System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2005, 2006, 2007 The University of Queensland,         |
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
include_once(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."config.inc.php");
include_once(APP_INC_PATH . "class.template.php");
include_once(APP_INC_PATH . "class.auth.php");
include_once(APP_INC_PATH . "class.misc.php");
include_once(APP_INC_PATH . "class.doc_type_xsd.php");
include_once(APP_INC_PATH . "class.xsd_html_match.php");
include_once(APP_INC_PATH . "class.xsd_display.php");
include_once(APP_INC_PATH . "class.collection.php");
include_once(APP_INC_PATH . "class.db_api.php");

$tpl = new Template_API();

$tpl->setTemplate("manage/xsd_tree_clean.tpl.html");

Auth::checkAuthentication(APP_SESSION);

$isUser = Auth::getUsername();
$isAdministrator = User::isUserAdministrator($isUser);
$isSuperAdministrator = User::isUserSuperAdministrator($isUser);
$tpl->assign("isUser", $isUser);
$tpl->assign("isAdministrator", $isAdministrator);
$tpl->assign("isSuperAdministrator", $isSuperAdministrator);
$tpl->assign("active_nav", "admin");

if ($isSuperAdministrator) {
	$xdis_id = @$_POST["xdis_id"] ? $_POST["xdis_id"] : @$_GET["xdis_id"];
	$xsd_id = XSD_HTML_Match::getXSD_ID($xdis_id);
	$form_cat = @$_POST["form_cat"];

	if ($form_cat == "delete") { 
		$tpl->assign("result", XSD_HTML_Match::removeByXSDMF_IDs());
	}

	$xdis_title = XSD_Display::getTitle($xdis_id);
	$tpl->assign("xdis_title", $xdis_title);   
	$top_element_name = Doc_Type_XSD::getDetails($xsd_id);
	$top_element_name = $top_element_name['xsd_top_element_name'];
	$xsd_str = array();
	$xsd_str = Doc_Type_XSD::getXSDSource($xsd_id);
	$xsd_str = $xsd_str[0]['xsd_file'];
	$xsd = new DomDocument();
    $xsd->loadXML($xsd_str);
	$array_ptr = array();
	Misc::dom_xsd_to_referenced_array($xsd, $top_element_name, $array_ptr, "", "", $xsd);
	$element_match_list = XSD_HTML_Match::getElementMatchList($xdis_id);
	$orphan_list = XSD_HTML_Match::getElementOrphanList($xdis_id, $array_ptr);
	$orphan_count = count($orphan_list);
	$tpl->assign("xdis_id", $xdis_id);   
	$tpl->assign("orphan_count", $orphan_count);   
	$tpl->assign("orphan_list", $orphan_list);   			
} else {
    $tpl->assign("show_not_allowed_msg", true);
}

$tpl->displayTemplate();
