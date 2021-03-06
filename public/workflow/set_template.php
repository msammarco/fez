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
include_once(APP_INC_PATH . "class.user.php");
include_once(APP_INC_PATH . "class.group.php");
include_once(APP_INC_PATH . "class.record.php");
include_once(APP_INC_PATH . "class.misc.php");
include_once(APP_INC_PATH . "class.status.php");
include_once(APP_INC_PATH . "class.db_api.php");
include_once(APP_INC_PATH . "class.controlled_vocab.php");
include_once(APP_INC_PATH . "class.collection.php");
include_once(APP_INC_PATH . "class.community.php");
include_once(APP_INC_PATH . "class.date.php");
include_once(APP_INC_PATH . "class.doc_type_xsd.php");
include_once(APP_INC_PATH . "class.xsd_html_match.php");
include_once(APP_INC_PATH . "class.workflow_status.php");
include_once(APP_INC_PATH . "najax/najax.php");
include_once(APP_INC_PATH . "najax_objects/class.select_org_structure.php");
include_once(APP_INC_PATH . "najax_objects/class.suggestor.php");

NAJAX_Server::allowClasses(array('SelectOrgStructure', 'Suggestor'));
if (NAJAX_Server::runServer()) {
	exit;
}

Auth::checkAuthentication(APP_SESSION);

$tpl = new Template_API();
$tpl->setTemplate("workflow/index.tpl.html");
$tpl->assign('type', 'set_template');

$isUser = Auth::getUsername();
$tpl->assign("isUser", $isUser);
$isAdministrator = User::isUserAdministrator($isUser);
$tpl->assign("isAdministrator", $isAdministrator);

$wfstatus = &WorkflowStatusStatic::getSession(); // restores WorkflowStatus object from the session
$pid = $wfstatus->pid;
$tpl->assign("pid", $pid);
$tpl->assign("sta_id", Status::getID("In Creation"));

$wfstatus->setTemplateVars($tpl);
// get the xdis_id of what we're creating
$xdis_id = $wfstatus->getXDIS_ID();

if ($pid == -1 || !$pid) {
    $access_ok = $isAdministrator;
} else {
    $community_pid = $pid;
    $collection_pid = $pid;
    $record = new RecordObject($pid);
    $access_ok = $record->canCreate();
}
if ($access_ok) {
    // check for post action
    if (@$_POST["cat"] == "report") {
        $res = Record::makeInsertTemplate();
        $wfstatus->assign('template', $res);
    }
    $wfstatus->checkStateChange();

    $tpl->assign("isCreator", 1);
    if (!is_numeric($xdis_id)) { // if still can't find the xdisplay id then ask for it
        print_r($wfstatus);
        exit;
        Auth::redirect(APP_RELATIVE_URL . "select_xdis.php?return=insert_form".$extra_redirect, false);
    }
    $tpl->assign("xdis_id", $xdis_id);
    $tpl->assign("xdis_title", $xdis_title);
    $xdis_collection_list = XSD_Display::getAssocListCollectionDocTypes(); // @@@ CK - 13/1/06 added for communities to be able to select their collection child document types/xdisplays
    $xdis_list = XSD_Display::getAssocListDocTypes();
    $community_list = Community::getAssocList();
    $collection_list = Collection::getEditListAssoc();
    $jtaskData = "";
    $maxG = 0;
    $xsd_display_fields = XSD_HTML_Match::getListByDisplay($xdis_id, array("FezACML"), array(""));  // XSD_DisplayObject

    if ($wfstatus->parent_pid != "-1") {
      $parent_record = new RecordObject($wfstatus->parent_pid);
      $parent_xdis_id = $parent_record->getXmlDisplayId();
      $parent_relationships = XSD_Relationship::getColListByXDIS($parent_xdis_id);
      array_push($parent_relationships, $parent_xdis_id);
    } else {
        $parent_relationships = array();
    }

    //@@@ CK - 26/4/2005 - fix the combo and multiple input box lookups
    // - should probably move this into a function somewhere later
    foreach ($xsd_display_fields as $dis_key => $dis_field) {
        if ($dis_field["xsdmf_enabled"] == 1) {
            if ($dis_field["xsdmf_html_input"] == 'org_selector') {
                if ($dis_field["xsdmf_org_level"] != "") {
                    $xsd_display_fields[$dis_key]['field_options'] = Org_Structure::getAssocListByLevel($dis_field["xsdmf_org_level"]);
                }
            }
            if ($dis_field["xsdmf_html_input"] == 'author_selector') {
                if ($dis_field["xsdmf_use_parent_option_list"] == 1) {
                    // Loop through the parents - there is only one parent for entering metadata
                    if (in_array($dis_field["xsdmf_parent_option_xdis_id"], $parent_relationships)) {
                        $parent_details = $parent_record->getDetails();
                        if (is_numeric($parent_details[$dis_field["xsdmf_parent_option_child_xsdmf_id"]])) {
                            $authors_sub_list = Org_Structure::getAuthorsByOrgID($parent_details[$dis_field["xsdmf_parent_option_child_xsdmf_id"]]);
                            $xsd_display_fields[$dis_key]['field_options'] = $authors_sub_list;
                        }
                    }
                }
            }
            if (($dis_field["xsdmf_html_input"] == 'author_suggestor') || ($dis_field["xsdmf_html_input"] == 'conference_suggestor') || ($dis_field["xsdmf_html_input"] == 'publisher_suggestor')) {

                foreach ($xsd_display_fields as $dis_key2 => $dis_field2) {
                    if ($dis_field2['xsdmf_id'] == $dis_field['xsdmf_asuggest_xsdmf_id']) {
                        $suggestor_count = $dis_field2['xsdmf_multiple_limit'];
                    }
                }

                if (!is_numeric($suggestor_count)) {
                    $suggestor_count = 1;
                }
                for ($x=1;$x<=$suggestor_count;$x++) {
                    if ($dis_field["xsdmf_html_input"] == 'author_suggestor') {
                     $tpl->headerscript .= "window.oTextbox_xsd_display_fields_{$dis_field['xsdmf_id']}_".$x."_lookup
                            = new AutoSuggestControl(document.wfl_form1, 'xsd_display_fields_{$dis_field['xsdmf_id']}_".$x."', document.getElementById('xsd_display_fields_{$dis_field['xsdmf_asuggest_xsdmf_id']}_".$x."'), document.getElementById('xsd_display_fields_{$dis_field['xsdmf_id']}_".$x."_lookup'),
                                    new StateSuggestions('Author','suggest',false,
                                        'class.author.php'), 'authorSuggestorCallback');
                            ";
                    } elseif ($dis_field["xsdmf_html_input"] == 'conference_suggestor') {
                        $tpl->headerscript .= "window.oTextbox_xsd_display_fields_{$dis_field['xsdmf_id']}_".$x."_lookup
                               = new AutoSuggestControl(document.wfl_form1, 'xsd_display_fields_{$dis_field['xsdmf_id']}_".$x."', document.getElementById('xsd_display_fields_{$dis_field['xsdmf_asuggest_xsdmf_id']}_".$x."'), document.getElementById('xsd_display_fields_{$dis_field['xsdmf_id']}_".$x."_lookup'),
                                       new StateSuggestions('Conference','suggest',false,
                                           'class.conference.php'), 'conferenceSuggestorCallback');
                               ";
                    } elseif ($dis_field["xsdmf_html_input"] == 'publisher_suggestor') {
                        $tpl->headerscript .= "window.oTextbox_xsd_display_fields_{$dis_field['xsdmf_id']}_".$x."_lookup
                               = new AutoSuggestControl(document.wfl_form1, 'xsd_display_fields_{$dis_field['xsdmf_id']}_".$x."', document.getElementById('xsd_display_fields_{$dis_field['xsdmf_asuggest_xsdmf_id']}_".$x."'), document.getElementById('xsd_display_fields_{$dis_field['xsdmf_id']}_".$x."_lookup'),
                                       new StateSuggestions('Publisher','suggest',false,
                                           'class.publisher.php'), 'publisherSuggestorCallback');
                               ";
                    }
                }
            }

            if ($dis_field["xsdmf_html_input"] == 'combo' || $dis_field["xsdmf_html_input"] == 'multiple' || $dis_field["xsdmf_html_input"] == 'dual_multiple') {
                if (!empty($dis_field["xsdmf_smarty_variable"]) && $dis_field["xsdmf_smarty_variable"] != "none") {
                    eval("\$xsd_display_fields[\$dis_key]['field_options'] = " . $dis_field["xsdmf_smarty_variable"] . ";");
                }
                if (!empty($dis_field["xsdmf_dynamic_selected_option"])
                        && $dis_field["xsdmf_dynamic_selected_option"] != "none") {
                    eval("\$xsd_display_fields[\$dis_key]['selected_option'] = "
                            . $dis_field["xsdmf_dynamic_selected_option"] . ";");
                }
                if ($dis_field["xsdmf_use_parent_option_list"] == 1) { // if the display field inherits this list from a parent then get those options
                    // Loop through the parents
                    if (in_array($dis_field["xsdmf_parent_option_xdis_id"], $parent_relationships)) {
                        $parent_details = $parent_record->getDetails();
                        if (is_array($parent_details[$dis_field["xsdmf_parent_option_child_xsdmf_id"]])) {
                            $xsdmf_details = XSD_HTML_Match::getDetailsByXSDMF_ID($dis_field["xsdmf_parent_option_child_xsdmf_id"]);
                            if ($xsdmf_details['xsdmf_smarty_variable'] != "" && ($xsdmf_details['xsdmf_html_input'] == "multiple" || $xsdmf_details['xsdmf_html_input'] == "dual_multiple")) {
                                $temp_parent_options = array();
                                $temp_parent_options_final = array();
                                eval("\$temp_parent_options = ". $xsdmf_details['xsdmf_smarty_variable'].";");
                                $xsd_display_fields[$dis_key]['field_options'] = array();
                                foreach ($parent_details[$dis_field["xsdmf_parent_option_child_xsdmf_id"]] as $parent_smarty_option) {
                                    if (array_key_exists($parent_smarty_option, $temp_parent_options)) {
                                        $xsd_display_fields[$dis_key]['field_options'][$parent_smarty_option] = $temp_parent_options[$parent_smarty_option];
                                    }
                                }
                            } else {
                                $xsd_display_fields[$dis_key]['field_options'] = array();
                                foreach ($parent_details[$dis_field["xsdmf_parent_option_child_xsdmf_id"]] as $parent_detail_text) {
                                    $xsd_display_fields[$dis_key]['field_options'][$parent_detail_text] = $parent_detail_text;
                                }
                            }
                        }
                    }
                }
            }
			if ($dis_field["xsdmf_html_input"] == 'dual_multiple') {
                $tempArray = $xsd_display_fields[$dis_key]["selected_option"];
                if (is_array($tempArray)) {
                    $xsd_display_fields[$dis_key]["selected_option"] = array();
                    foreach ($tempArray as $cv_key => $cv_value) {
                        $xsd_display_fields[$dis_key]["selected_option"][$cv_value] = Record::getTitleFromIndex($cv_value);
                    }
                } elseif  (trim($xsd_display_fields[$dis_key]["selected_option"]) != "") {
                    $tempValue = $xsd_display_fields[$dis_key]["selected_option"];
                    $xsd_display_fields[$dis_key]["selected_option"] = array();
                    $xsd_display_fields[$dis_key]["selected_option"][$tempValue] = Record::getTitleFromIndex($tempValue);
            	}
			}
            if (($dis_field["xsdmf_html_input"] == 'contvocab')
                    || ($dis_field["xsdmf_html_input"] == 'contvocab_selector')) {
                $xsd_display_fields[$dis_key]['field_options'] = @$cvo_list['data'][$dis_field['xsdmf_cvo_id']];
            }
        }
    }
    $tpl->assign("xsd_display_fields", $xsd_display_fields);
    $tpl->assign("xdis_id", $xdis_id);
    $tpl->assign("autosuggest", 1);
    $tpl->assign("form_title", "Set Template");
    $tpl->assign("form_description", "These values will be used as the defaults for the batch operation.");
	$tpl->assign('najax_header', NAJAX_Utilities::header(APP_RELATIVE_URL.'include/najax'));
	$tpl->registerNajax(NAJAX_Client::register('SelectOrgStructure', 'set_template.php')."\n"
                        .NAJAX_Client::register('Suggestor', 'set_template.php'));

}


$tpl->displayTemplate();
?>
