<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Fez - Digital Repository System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2005, 2006 The University of Queensland,               |
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

include_once(APP_INC_PATH . "najax/najax.php");
include_once(APP_INC_PATH . "najax_objects/class.image_preview.php");
include_once(APP_INC_PATH . "class.author.php");
include_once(APP_INC_PATH . "class.lister.php");
include_once(APP_PEAR_PATH . "Date.php");

$username = Auth::getUsername();
$tpl->assign("isUser", $username);
$isAdministrator = Auth::isAdministrator(); 
$tpl->assign("isAdministrator", $isAdministrator);

$tpl->assign("fez_root_dir", APP_PATH);
$tpl->assign("eserv_url", APP_BASE_URL."eserv.php?pid=".$pid."&dsID=");
$tpl->assign("local_eserv_url", APP_BASE_URL."eserv.php?pid=".$pid."&dsID=");
$tpl->assign("extra_title", "Record #$pid Details");
if (!empty($pid)) {
	$tpl->assign("pid", $pid);
	$record = new RecordObject($pid);
	$xdis_id = $record->getXmlDisplayId();
	$xdis_title = XSD_Display::getTitle($xdis_id);	
    $tpl->assign("xdis_title", $xdis_title);
	if (!is_numeric($xdis_id)) {
		$xdis_id = @$HTTP_POST_VARS["xdis_id"] ? $HTTP_POST_VARS["xdis_id"] : @$HTTP_GET_VARS["xdis_id"];	
		if (is_numeric($xdis_id)) { // must have come from select xdis so save xdis in the Fez MD
			$record->updateAdminDatastream($xdis_id);
		}
	}
	if (!is_numeric($xdis_id)) { // if still can't find the xdisplay id then ask for it
		Auth::redirect(APP_RELATIVE_URL . "select_xdis.php?return=view_form&pid=".$pid.$extra_redirect, false);
	}
	
	$canEdit = false;
	$canView = true;
	$canEdit = $record->canEdit(false);
	if ($canEdit == true) {
		$canView = true;
	} else {
		$canView = $record->canView();
	}
	$tpl->assign("isViewer", $canView);
	if ($canView) {
		$tpl->assign("isEditor", $canEdit);
		$tpl->assign("sta_id", $record->getPublishedStatus()); 
		$display = new XSD_DisplayObject($xdis_id);
		//$xsd_display_fields = $display->getMatchFieldsList();
		$xsd_display_fields = $record->display->getMatchFieldsList(array("FezACML"), array(""));  // XSD_DisplayObject
		$tpl->assign("xsd_display_fields", $xsd_display_fields);
		$details = $record->getDetails();
		$controlled_vocabs = Controlled_Vocab::getAssocListAll();
		$tpl->assign("details_array", $details);
		$parents = $record->getParents();
		$author_list = Author::getAssocListAll();
		$parent_relationships = array(); 
		foreach ($parents as $parent) {
			$parent_rel = XSD_Relationship::getColListByXDIS($parent['display_type'][0]);
			$parent_relationships[$parent['pid']] = array();
			foreach ($parent_rel as $prel) {
				array_push($parent_relationships[$parent['pid']], $prel);
			}
			array_push($parent_relationships[$parent['pid']], $parent['display_type'][0]);
		} 
		
		foreach ($xsd_display_fields as $dis_key => $dis_field) {
			if (($dis_field['xsdmf_enabled'] == 1) && ($dis_field['xsdmf_show_in_view'] == 1)) {
				if (($dis_field['xsdmf_html_input'] == "contvocab") || ($dis_field['xsdmf_html_input'] == "contvocab_selector")) {
					if (!empty($details[$dis_field['xsdmf_id']])) {
						if (is_array($details[$dis_field['xsdmf_id']])) {
							foreach ($details[$dis_field['xsdmf_id']] as $ckey => $cdata) {
								$details[$dis_field['xsdmf_id']][$ckey] = $controlled_vocabs[$cdata];
							}
						} else {
							$details[$dis_field['xsdmf_id']] = $controlled_vocabs[$details[$dis_field['xsdmf_id']]];
						}
					}
				}
				if ($dis_field['xsdmf_data_type'] == "date") {
					if (!empty($details[$dis_field['xsdmf_id']])) {
						if (is_array($details[$dis_field['xsdmf_id']])) {
							foreach ($details[$dis_field['xsdmf_id']] as $ckey => $cdata) {
								$details[$dis_field['xsdmf_id']][$ckey] = $details[$cdata];
							}
						} else {
							$tempDate = new Date($details[$dis_field['xsdmf_id']]);
	//						$tempDate->format
							if ($details[$dis_field['xsdmf_attached_xsdmf_id']] == 1) {
								$details[$dis_field['xsdmf_id']] = substr($details[$dis_field['xsdmf_id']], 0, 4);
							} elseif ($details[$dis_field['xsdmf_attached_xsdmf_id']] == 2) {
								$details[$dis_field['xsdmf_id']] = substr($details[$dis_field['xsdmf_id']], 0, 7);
							}
						}
					}
				} 
				if ($dis_field['xsdmf_html_input'] == "author_selector") {
					if (!empty($details[$dis_field['xsdmf_id']])) {
						if (is_array($details[$dis_field['xsdmf_id']])) {
							foreach ($details[$dis_field['xsdmf_id']] as $ckey => $cdata) {
								$details[$dis_field['xsdmf_id']][$ckey] = $author_list[$cdata];
							}
						} else {
							$details[$dis_field['xsdmf_id']] = $author_list[$details[$dis_field['xsdmf_id']]];
						}
					}
				} 
				if ($dis_field["xsdmf_use_parent_option_list"] == 1) { 
					// if the display field inherits this list from a parent then get those options
					// Loop through the parents
					foreach ($parent_relationships as $pkey => $prel) {
						if (in_array($dis_field["xsdmf_parent_option_xdis_id"], $prel)) {
							$parent_record = new RecordObject($pkey);
							$parent_details = $parent_record->getDetails();
							if (is_array($parent_details[$dis_field["xsdmf_parent_option_child_xsdmf_id"]])) {
								$xsdmf_details = XSD_HTML_Match::getDetailsByXSDMF_ID($dis_field["xsdmf_parent_option_child_xsdmf_id"]);
								if ($xsdmf_details['xsdmf_smarty_variable'] != "" && $xsdmf_details['xsdmf_html_input'] == "multiple") {
									$temp_parent_options = array();
									$temp_parent_options_final = array();
									eval("\$temp_parent_options = ". $xsdmf_details['xsdmf_smarty_variable'].";");
									$xsd_display_fields[$dis_key]['field_options'] = array();
									foreach ($parent_details[$dis_field["xsdmf_parent_option_child_xsdmf_id"]] as $parent_smarty_option) {
										if (array_key_exists($details[$dis_field['xsdmf_id']], $temp_parent_options)) {
											$details[$dis_field['xsdmf_id']] = $temp_parent_options[$details[$dis_field['xsdmf_id']]];
										}
									}
								}
							}
						}
					}
				}	
			}
		}
		// Now generate the META Tag headers
		$meta_head = '<META NAME="DC.Identifier" SCHEMA="URI" CONTENT="'.substr(APP_BASE_URL,0,-1).$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'">';
		foreach ($xsd_display_fields as $dis_key => $dis_field) {
			if (($dis_field['xsdmf_enabled'] == 1) && ($dis_field['xsdmf_meta_header'] == 1) && (trim($dis_field['xsdmf_meta_header_name']) != "")) {
				if (is_array($details[$dis_field['xsdmf_id']])) {
					foreach ($details[$dis_field['xsdmf_id']] as $ckey => $cdata) {
						if ($cdata != "") {
							$meta_head .= '<META NAME="'.$dis_field['xsdmf_meta_header_name'].'" CONTENT="'.trim($cdata).'">';
						}
					}
				} else {
					if ($details[$dis_field['xsdmf_id']] != "") {
						$meta_head .= '<META NAME="'.$dis_field['xsdmf_meta_header_name'].'" CONTENT="'.trim($details[$dis_field['xsdmf_id']]).'">';
						if ($dis_field['xsdmf_meta_header_name'] == "DC.Title") {
							$tpl->assign("extra_title", trim($details[$dis_field['xsdmf_id']]));
						}
					}
				}
			}
		}
        $tpl->assign('meta_head', $meta_head);

		foreach ($details as $dkey => $dvalue) { // turn any array values into a comma seperated string value
			if (is_array($dvalue)) {
				$details[$dkey] = implode("<br /> ", $dvalue);
			}
		}

        // Setup the Najax Image Preview object.
        $tpl->assign('najax_header', NAJAX_Utilities::header(APP_BASE_URL.'include/najax'));
        $tpl->assign('najax_register', NAJAX_Client::register('NajaxImagePreview', APP_BASE_URL.'najax_services/image_preview.php'));
	} else {
		$tpl->assign("show_not_allowed_msg", true);
	} 

	if (empty($details)) {
		$tpl->assign('details', '');
	} else {
		$datastreams = Fedora_API::callGetDatastreams($pid);
		$datastreams = Misc::cleanDatastreamList($datastreams);
		$securityfields = Auth::getAllRoles();
		$datastream_workflows = WorkflowTrigger::getListByTrigger('-1', 5);
//		print_r($datastreams);
		foreach ($datastreams as $ds_key => $ds) {
			if ($datastreams[$ds_key]['controlGroup'] == 'R' && $datastreams[$ds_key]['ID'] != 'DOI') {
				$datastreams[$ds_key]['location'] = trim($datastreams[$ds_key]['location']);
				// Check for APP_LINK_PREFIX and add if not already there
				if (APP_LINK_PREFIX != "") {
					if (!is_numeric(strpos($datastreams[$ds_key]['location'], APP_LINK_PREFIX))) {
						$datastreams[$ds_key]['location'] = APP_LINK_PREFIX.$datastreams[$ds_key]['location'];
					}
				}
			} elseif ($datastreams[$ds_key]['controlGroup'] == 'M') {
				$FezACML_DS = array();
				$FezACML_DS = Record::getIndexDatastream($pid, $ds['ID'], 'FezACML');
			
				$return = array();
				foreach ($FezACML_DS as $result) {
					if (in_array($result['xsdsel_title'], $securityfields)  && ($result['xsdmf_element'] != '!rule!role!name') && is_numeric(strpos($result['xsdmf_element'], '!rule!role!')) )  {
						if (!is_array($return[$result['rmf_rec_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']])) {
							$return[$result['rmf_rec_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']] = array();
						}
						if (!in_array($result['rmf_'.$result['xsdmf_data_type']], $return[$result['rmf_rec_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']])) {
							array_push($return[$result['rmf_rec_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']], $result['rmf_'.$result['xsdmf_data_type']]); // need to array_push because there can be multiple groups/users for a role
						}
					}
					if ($result['xsdmf_element'] == '!inherit_security') {
						if (!is_array($return[$result['rmf_rec_pid']]['FezACML'][0]['!inherit_security'])) {
							$return[$result['rmf_rec_pid']]['FezACML'][0]['!inherit_security'] = array();
						}
						if (!in_array($result['rmf_'.$result['xsdmf_data_type']], $return[$result['rmf_rec_pid']]['FezACML'][0]['!inherit_security'])) {
							array_push($return[$result['rmf_rec_pid']]['FezACML'][0]['!inherit_security'], $result['rmf_'.$result['xsdmf_data_type']]);
						}
					}
				}
			
				$datastreams[$ds_key]['FezACML'] = @$return[$pid]['FezACML'];
				$datastreams[$ds_key]['workflows'] = $datastream_workflows;
				$parentsACMLs = array();
				if (count($FezACML_DS) == 0 || $datastreams[$ds_key]['FezACML'][0]['!inherit_security'][0] == "on") {
					// if there is no FezACML set for this row yet, then is it will inherit from above, so show this for the form
					if ($datastreams[$ds_key]['FezACML'][0]['!inherit_security'][0] == "on") {
						$datastreams[$ds_key]['security'] = "include";
						$parentsACMLs = $datastreams[$ds_key]['FezACML'];
					} else {
						$datastreams[$ds_key]['security'] = "inherit";
						$parentsACMLs = array();
					} 
					$parents = Record::getParents($pid);
					Auth::getIndexParentACMLMemberList(&$parentsACMLs, $pid, $parents);
					$datastreams[$ds_key]['FezACML'] = $parentsACMLs;			
				} else {
					$datastreams[$ds_key]['security'] = "exclude";			
				}
			}
            if ($datastreams[$ds_key]['controlGroup'] == 'R' && $datastreams[$ds_key]['ID'] == 'DOI') {
                $datastreams[$ds_key]['location'] = trim($datastreams[$ds_key]['location']);
                $tpl->assign('doi', $datastreams[$ds_key]);
            }
		} 
		$datastreams = Auth::getIndexAuthorisationGroups($datastreams);
		$tpl->assign("datastreams", $datastreams);	
		$tpl->assign("ds_get_path", APP_FEDORA_GET_URL."/".$pid."/");		
		$parents = Record::getParents($pid);
		$tpl->assign("parents", $parents);		
		$tpl->assign("details", $details);
        $tpl->assign('title', $record->getTitle());
		$tpl->assign("controlled_vocabs", $controlled_vocabs);				

        // get prev / next info
        
        // Check if we have moved onto the next listing page
        if (@$_GET['go_next']) {
            $res = getNextPage();
        }
        if (@$_GET['go_prev']) {
            $res = getPrevPage();
        }
        if (@$_GET['go_next'] || @$_GET['go_prev']) {
            $_SESSION['list'] = $res['list'];
            $_SESSION['list_params'] = $res['list_params'];
            $_SESSION['list_info'] = $res['list_info'];
            $_SESSION['view_page'] = $res['list_info']['current_page'];
        }

        // Get the current listing 
        $list = $_SESSION['list'];
        $list_info = $_SESSION['list_info'];
        $view_page = $_SESSION['view_page'];

        // find current position in list
        foreach ($list as $key => $item) {
            if ($item['pid'] == $pid) {
                $list_idx = $key;
                break;
            }
        }
        $prev = null;  // the next item in the list
        $next = null;  // the previous item in the list
        $go_next = null;  // whether we need to page down
        $go_prev = null;  // whether we need to page up
        if (!is_null($list_idx)) {
            if ($list_idx > 0) {
                $prev = $list[$list_idx-1];
            } else {
                $res = getPrevPage();
                if (!empty($res)) {
                    $prev = $res['list'][count($res['list'])-1];
                    $go_prev = true;
                }
            }
            if ($list_idx < count($list)-1) {
                $next = $list[$list_idx+1];
            } else {
                $res = getNextPage();
                if (!empty($res)) {
                    $next = $res['list'][0];
                    $go_next = true;
                }
            }
        }
        $tpl->assign(compact('prev','next','go_next','go_prev'));
	}
} else {
	$tpl->assign("show_not_allowed_msg", true);
}


function getNextPage()
{
    $params = $_SESSION['list_params'];
    $info = $_SESSION['list_info'];
    $view_page = $_SESSION['view_page'];
    if ($view_page < $info['last_page']) {
        $params['pagerRow'] = $view_page + 1;
        $res = Lister::getList($params, false);
        $res['list_params'] = $params;
        return $res;
    }
    return array();
}
function getPrevPage()
{
    $params = $_SESSION['list_params'];
    $info = $_SESSION['list_info'];
    $view_page = $_SESSION['view_page'];
    if ($view_page > 0) {
        $params['pagerRow'] = $view_page - 1;
        $res = Lister::getList($params, false);
        $res['list_params'] = $params;
        return $res;
    }
    return array();
}
//print_r($GLOBALS['bench']->getProfiling());
?>
