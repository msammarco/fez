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

/**
 * Class to handle authentication and authorisation issues.
 *
 * @version 1.0
 * @author Christiaan Kortekaas <c.kortekaas@library.uq.edu.au>
 * @author Matthew Smith <m.smith@library.uq.edu.au>
 */

include_once(APP_INC_PATH . "class.error_handler.php");
include_once(APP_INC_PATH . "class.collection.php");
include_once(APP_INC_PATH . "class.user.php");
include_once(APP_INC_PATH . "class.record.php");
include_once(APP_INC_PATH . "class.misc.php");
include_once(APP_INC_PATH . "class.fedora_api.php");
include_once(APP_INC_PATH . "class.date.php");
//include_once(APP_INC_PATH . "private_key.php");

global $NonRestrictedRoles;
$NonRestrictedRoles = array("Viewer","Lister","Comment_Viewer");
global $NonRestrictedRoleIDs;
$NonRestrictedRoleIDs = array(10,9,5);
global $defaultRoles;
$defaultRoles = array("Editor", "Creator", "Lister", "Viewer", "Approver", "Community Administrator", "Annotator", "Comment_Viewer", "Commentor");
global $defaultRoleIDs;
$defaultRoleIDs = array(8, 7, 5, 9, 2, 6, 1, 5, 4);

global $auth_isBGP;
global $auth_bgp_session;

$auth_isBGP = false;
$auth_bgp_session = array();

class Auth
{
    /**
     * Method used to get the current listing related cookie information for the users shibboleth home idp
     *
     * @access  public
     * @return  array The Record listing information
     */
   	function getHomeIDPCookie()
    {
        return @unserialize(base64_decode($_COOKIE[APP_SHIB_HOME_IDP_COOKIE]));
    }


	function setHomeIDPCookie($home_idp) 
	{
//        $_COOKIE[APP_SHIB_HOME_IDP_COOKIE] = @serialize(base64_decode($home_idp));
		$encoded = base64_encode(serialize($home_idp));
        @setcookie(APP_SHIB_HOME_IDP_COOKIE, $encoded, APP_SHIB_HOME_IDP_COOKIE_EXPIRE);
	}

    /**
     * Method used to check for the appropriate authentication for a specific
     * page. It will check for the session name provided and redirect the user
     * to another page if needed.
     *
     * @access  public
     * @param   string $session_name The name of the session to check for
     * @param   string $failed_url The URL to redirect to if the user is not authenticated
     * @param   boolean $is_popup Flag to tell the function if the current page is a popup window or not
     * @return  void
     */
    function checkAuthentication($session_name, $failed_url = NULL, $is_popup = false)
    {
        global $auth_isBGP, $auth_bgp_session;

        if ($auth_isBGP) {
            $ses =& $auth_bgp_session;
        } else {
            session_name(APP_SESSION);
            @session_start();
            $ses =& $_SESSION;
            if (empty($failed_url)) {
                $failed_url = APP_RELATIVE_URL . "login.php?err=5";
            } else {
				$failed_url = base64_encode($failed_url);
                $failed_url = APP_RELATIVE_URL . "login.php?err=21&url=".$failed_url;
            }

//			echo $failed_url; exit;
            if (!Auth::isValidSession($_SESSION)) {
                Auth::removeSession($session_name);
                Auth::redirect($failed_url, $is_popup);
            }

        }
        Auth::checkRuleGroups();
        // if the current session is still valid, then renew the expiration
        Auth::createLoginSession($ses['username'], $ses['fullname'], $ses['email'], $ses['distinguishedname'], $ses['autologin']);
    }

    
	/**
     * Method used to get the list of FezACML roles using in any XSD Display.
     *
     * @access  public
     * @return  array The list of FezACML roles
     */
    function getAllRoleIDs()
    {
        $stmt = "SELECT aro_id, aro_role FROM ". APP_TABLE_PREFIX . "auth_roles ";
        $res = $GLOBALS["db_api"]->dbh->getAll($stmt, DB_FETCHMODE_ASSOC);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return array();
        } else {
        	$result = array();
        	foreach ($res as $key => $data) {
        		$result[$data['aro_role']] = $data['aro_id'];
       		}
			return $result;
		}
		
	}    

	/**
     * Method used to get the list of FezACML roles using in any XSD Display.
     *
     * @access  public
     * @return  array The list of FezACML roles
     */
    function getAssocRoleIDs()
    {
        $stmt = "SELECT aro_id, aro_role FROM ". APP_TABLE_PREFIX . "auth_roles where aro_id != 0";
        $res = $GLOBALS["db_api"]->dbh->getAssoc($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return array();
        } else {
			return $res;
		}
		
	}

    function convertTextRolesToIDS($aro_roles = array())
    {
		if (is_array($aro_roles)) {
			if (count($aro_roles) > 0) {
		        $stmt = "SELECT aro_id, aro_role
		                  FROM ". APP_TABLE_PREFIX . "auth_roles 
		                  WHERE aro_role in ('".implode("','", $aro_roles)."') 
		                        AND aro_id != 0";
		        $res = $GLOBALS["db_api"]->dbh->getAssoc($stmt);
		        if (PEAR::isError($res)) {
		            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
		            return array();
		        } else {
					return $res;
				}
			} else {
				return array();
			}				
		} else {
			return array();
		}
	}
	
	function getRoleIDByTitle($title) {
		$title = Misc::escapeString($title);
        $stmt = "SELECT aro_id FROM ". APP_TABLE_PREFIX . "auth_roles where aro_role = '".$title."'";
        $res = $GLOBALS["db_api"]->dbh->getOne($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return array();
        } else {
			return $res;
		}		
	}

	function getRoleTitleByID($aro_id) {
		if (!is_numeric($aro_id)) {
			return false;
		}
		$aro_id = Misc::escapeString($aro_id);
        $stmt = "SELECT aro_role FROM ". APP_TABLE_PREFIX . "auth_roles where aro_id = ".$aro_id;
        $res = $GLOBALS["db_api"]->dbh->getOne($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return array();
        } else {
			return $res;
		}		
	}
    
    /**
     * Method used to get the list of FezACML roles using in any XSD Display.
     *
     * @access  public
     * @return  array The list of FezACML roles
     */
    function getAllRoles()
    {
        $stmt = "SELECT distinct xsdsel_title 
			from " . APP_TABLE_PREFIX . "xsd_loop_subelement s1
			inner join " . APP_TABLE_PREFIX . "xsd_display_matchfields x1 on x1.xsdmf_id = xsdsel_xsdmf_id
			inner join " . APP_TABLE_PREFIX . "xsd_display d1 on d1.xdis_id = x1.xsdmf_xdis_id
			inner join " . APP_TABLE_PREFIX . "xsd x2 on x2.xsd_id = xdis_xsd_id and x2.xsd_title = 'FezACML'";
        $res = $GLOBALS["db_api"]->dbh->getCol($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return "";
        } else {
			return $res;
		}
		
	}
		

    /**
     * Method used to get the ACML details for a records parent objects, using the Fez index.
	 * This method is usually only triggered when an object does not have its own ACML set against it.
     *
     * NOTE: This is a RECURSIVE function, as it keeps going up the record hierarchy if it can't find an ACML at each level.
     *	 
     * @access  public
     * @param   array $array The array of ACMLs that will be built and passed back by reference to the calling function.
     * @param   string $pid The persistant identifier of the object
     * @return  void (returns array by reference).
     */	
	function getIndexParentACMLs(&$array, $pid) {
		$ACMLArray = &$array;
		static $returns;
        if (!empty($returns[$pid])) { // check if this has already been found and set to a static variable		
			foreach ( $returns[$pid] as $fezACML_row) {					
				array_push($ACMLArray, $fezACML_row); //add it to the acml array and dont go any further up the hierarchy
			}
        } else {
			$pre_stmt =  "SELECT r2.rek_ismemberof 
							FROM  " . APP_TABLE_PREFIX . "record_search_key_ismemberof r2
							WHERE r2.rek_ismemberof_pid = '".$pid."')";
//			debug_print_backtrace();
//			echo $pre_stmt;
			$res = $GLOBALS["db_api"]->dbh->getCol($pre_stmt);
            if (PEAR::isError($res)) {
                Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            }
			$parent_pid_string = implode("', '", $res);
			$stmt = "SELECT 
						* 
					 FROM
						" . APP_TABLE_PREFIX . "record_matching_field r1
						inner join " . APP_TABLE_PREFIX . "xsd_display_matchfields x1 on (r1.rek_xsdmf_id = x1.xsdmf_id)
					    left join " . APP_TABLE_PREFIX . "search_key k1 on (k1.sek_title = 'isMemberOf' AND k1.sek_id = x1.xsdmf_sek_id)
						left join " . APP_TABLE_PREFIX . "xsd_display d1 on (x1.xsdmf_xdis_id = d1.xdis_id)
						inner join " . APP_TABLE_PREFIX . "xsd as xsd1 on (xsd1.xsd_id = d1.xdis_xsd_id and xsd1.xsd_title = 'FezACML')
						left join " . APP_TABLE_PREFIX . "xsd_loop_subelement s1 on (x1.xsdmf_xsdsel_id = s1.xsdsel_id)
					 WHERE
						r1.rek_pid in ('".$parent_pid_string."') and (r1.rek_dsid IS NULL or r1.rek_dsid = '')
						"; 

			$securityfields = Auth::getAllRoles();
			$res = $GLOBALS["db_api"]->dbh->getAll($stmt, DB_FETCHMODE_ASSOC);
			if (PEAR::isError($res)) {
                Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            }
            $return = array();	
			foreach ($res as $result) {
				if (!is_array(@$return[$result['rek_pid']])) {
					$return[$result['rek_pid']]['exists'] = array();
				}
				if (in_array($result['xsdsel_title'], $securityfields) && ($result['xsdmf_element'] != '!rule!role!name') && is_numeric(strpos($result['xsdmf_element'], '!rule!role!')) ) {
					if (!is_array(@$return[$result['rek_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']])) {
						$return[$result['rek_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']] = array();
					}
					if (!in_array($result['rek_'.$result['xsdmf_data_type']], $return[$result['rek_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']])) {
						array_push($return[$result['rek_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']], $result['rek_'.$result['xsdmf_data_type']]); // need to array_push because there can be multiple groups/users for a role
					}
				}
			}
			foreach ($return as $key => $record) {
				if (is_array(@$record['FezACML'])) {
					if (!is_array(@$returns[$pid])) {
						$returns[$pid] = array();
					}
					foreach ($record['FezACML'] as $fezACML_row) {					
						array_push($ACMLArray, $fezACML_row); //add it to the acml array and dont go any further up the hierarchy
						array_push($returns[$pid], $fezACML_row);
					} 
				} else {
					Auth::getIndexParentACMLs($ACMLArray, $key); //otherwise go up the hierarchy recursively
				}
			}
		}
	}

    /**
     * Method used to loop over a set of known parents of an object to get the ACML details.
	 * This method is usually only triggered when an object does not have its own ACML set against it.
     *
     * @access  public
     * @param   array $array The array of ACMLs that will be built and passed back by reference to the calling function.
     * @param   string $pid The persistant identifier of the object
     * @param   array $parents The array of parent PIDS to loop over
     * @return  false if an array of parents is not set in the parameter, returns array by reference.
     */	
	function getIndexParentACMLMemberList(&$array, $pid, $parents) {
		if (!is_array($parents)) {
			return false;
		}

		foreach ($parents as $parent) {
			Auth::getIndexParentACMLMember(&$array, $parent);
		}
	}

    /**
     * Method used to get the ACML details for a records parent objects, using the Fez index.
	 * This method is usually only triggered when an object does not have its own ACML set against it.
	 * Differs from the "non-member" version as you already know the pids of the member parents when using this function.
     *
     * NOTE: This is a RECURSIVE function, as it keeps going up the record hierarchy if it can't find an ACML at each level.
     *	 
     * @access  public
     * @param   array $array The array of ACMLs that will be built and passed back by reference to the calling function.
     * @param   string $pid The persistant identifier of the object
     * @return  void (returns array by reference).
     */	
	function getIndexParentACMLMember(&$array, $pid) {
		$ACMLArray = &$array;
		static $returns;
		if (is_array($pid)) {
			$pid = $pid['pid'];			
		}

        if (is_array(@$returns[$pid])) {		
//			$ACMLArray = $returns[$pid]; //add it to the acml array and dont go any further up the hierarchy
//			array_push($ACMLArray, $returns[$pid]); //add it to the acml array and dont go any further up the hierarchy			
			foreach ($returns[$pid] as $fezACML_row) {					
				array_push($ACMLArray, $fezACML_row); //add it to the acml array and dont go any further up the hierarchy
			}


        } else {
			$stmt = "SELECT 
						* 
					 FROM
						" . APP_TABLE_PREFIX . "record_matching_field r1
						inner join " . APP_TABLE_PREFIX . "xsd_display_matchfields x1 on (r1.rek_xsdmf_id = x1.xsdmf_id)
						inner join " . APP_TABLE_PREFIX . "xsd_display d1 on (d1.xdis_id = x1.xsdmf_xdis_id and r1.rek_pid_num=".Misc::numPID($pid)." and r1.rek_pid ='".$pid."')
						left join " . APP_TABLE_PREFIX . "xsd x2 on (x2.xsd_id = d1.xdis_xsd_id and x2.xsd_title = 'FezACML')
						left join " . APP_TABLE_PREFIX . "xsd_loop_subelement s1 on (x1.xsdmf_xsdsel_id = s1.xsdsel_id)
						left join " . APP_TABLE_PREFIX . "search_key k1 on (k1.sek_title = 'isMemberOf' AND r1.rek_xsdmf_id = x1.xsdmf_id AND k1.sek_id = x1.xsdmf_sek_id)
						WHERE (r1.rek_dsid IS NULL or r1.rek_dsid = '')";
	
//          global $defaultRoles;
//			$returnfields = $defaultRoles;

			$securityfields = Auth::getAllRoles();
			$res = $GLOBALS["db_api"]->dbh->getAll($stmt, DB_FETCHMODE_ASSOC);
			$return = array();
			$returns = array();			

			foreach ($res as $result) {
				if (!is_array(@$return[$result['rek_pid']])) {
					$return[$result['rek_pid']]['exists'] = array();
				}
				if (in_array($result['xsdsel_title'], $securityfields)  && ($result['xsdmf_element'] != '!rule!role!name') && is_numeric(strpos($result['xsdmf_element'], '!rule!role!')) )  {
					if (!is_array(@$return[$result['rek_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']])) {
						$return[$result['rek_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']] = array();
					}
					if (!in_array($result['rek_'.$result['xsdmf_data_type']], $return[$result['rek_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']])) {
						array_push($return[$result['rek_pid']]['FezACML'][0][$result['xsdsel_title']][$result['xsdmf_element']], $result['rek_'.$result['xsdmf_data_type']]); // need to array_push because there can be multiple groups/users for a role
					}
				}
				if ($result['xsdmf_element'] == '!inherit_security') {
					if (!is_array(@$return[$result['rek_pid']]['FezACML'][0]['!inherit_security'])) {
						$return[$result['rek_pid']]['FezACML'][0]['!inherit_security'] = array();
					}
					if (!in_array($result['rek_'.$result['xsdmf_data_type']], $return[$result['rek_pid']]['FezACML'][0]['!inherit_security'])) {
						array_push($return[$result['rek_pid']]['FezACML'][0]['!inherit_security'], $result['rek_'.$result['xsdmf_data_type']]);
					}
				}								
			}
			foreach ($return as $key => $record) {	
				if (is_array(@$record['FezACML'])) {
					if (!is_array(@$returns[$pid]) || count(@$returns[$pid]) > 10) {
						$returns[$pid] = array();
					}
					foreach ($record['FezACML'] as $fezACML_row) {
						array_push($ACMLArray, $fezACML_row);
						array_push($returns[$pid], $fezACML_row);
					}
					//add it to the acml array and dont go further up the hierarchy only if inherity security is set
					$parentACMLs = array();
					foreach ($record['FezACML'] as $fezACML_row) {
						if (@$fezACML_row['!inherit_security'][0] == "on") {
							Auth::getIndexParentACMLs($parentACMLs, $key);
						}					
					}
					foreach ($parentACMLs as $pACML) {
						array_push($ACMLArray, $pACML);
						array_push($returns[$pid], $pACML);
					}
				} else {
					if (!is_array(@$returns[$pid]) || count(@$returns[$pid]) > 10) {
						$returns[$pid] = array();
					}
					$parentACMLs = array();
					Auth::getIndexParentACMLs($parentACMLs, $key);
					foreach ($parentACMLs as $pACML) {
						array_push($ACMLArray, $pACML);
						array_push($returns[$pid], $pACML);
					}
				}
			}
		
		}
	}

    /**
     * Method used to get the ACML details for a records parent objects, using the Fez Fedora connection.
	 * This method is usually only triggered when an object does not have its own ACML set against it.
	 * This way of getting the security directly from the Fedora connection is only called when a user
	 * directly accessed the object, eg with update and view, otherwise they will use the index.
     *
     * NOTE: This is a RECURSIVE function, as it keeps going up the record hierarchy if it can't find an ACML at each level.
     *	 
     * @access  public
     * @param   array $array The array of ACMLs that will be built and passed back by reference to the calling function.
     * @param   array $parents The array of parent PIDS to loop over
     * @return  void (returns array by reference).
     */	
	function getParentACMLs(&$array, $parents) {
		
		if (!is_array($parents)) {
			return false;
		}
		
		$ACMLArray = &$array;
        foreach ($parents as $parent) {
            $inherit = false;
            $parentACML = Record::getACML($parent);
            
            if ($parentACML != false) {
            	
                array_push($ACMLArray, $parentACML);
                
	            // Check if it inherits security                
	            $xpath = new DOMXPath($parentACML);
	            $anyRuleSearch = $xpath->query('/FezACML/rule/role/*[string-length(normalize-space()) > 0]');
	            if ($anyRuleSearch->length == 0) {
	                    
	                $inherit = true;
	                  
	            } else {            
	                $inheritSearch = $xpath->query('/FezACML[inherit_security="on" or inherit_security=""]');
	                  
	                if( $inheritSearch->length > 0 ) {
	                    $inherit = true;
	                }
	            }
                
                if ($inherit == true) { // if need to inherit
                    $superParents = Record::getParents($parent);
                    if ($superParents != false) {
                        Auth::getParentACMLs(&$ACMLArray, $superParents);
                    }
                }
            } else { // if no ACML found then assume inherit
                $superParents = Record::getParents($parent);
                if ($superParents != false) {
                    Auth::getParentACMLs(&$ACMLArray, $superParents);
                }
            }
        }
	}
	

   /**
     * isAdministrator
     * Checks if the current user is the administrator.
     * @returns boolean true if access is ok.
     */
    function isAdministrator()
    {
        global $auth_isBGP, $auth_bgp_session;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            session_name(APP_SESSION);
            @session_start();
            $session =& $_SESSION;
        }

        $answer = false;
        if (Auth::isValidSession($session)) {
            if (!isset($session['isAdministrator'])) {
                $session['isAdministrator'] = User::isUserAdministrator(Auth::getUsername());
            }
            $answer = $session['isAdministrator']?true:false;
        }
        return $answer;
    }
    
   /**
    * checkAuthorisation
	* Can the user access the object?
    *
    * @access  public
    * @param   string $pid The persistant identifier of the object
    * @param   array $acceptable_roles The array of roles that will be accepted to access the object.
    * @param   string $failed_url The URL to redirect back to once the user has logged in, if they are not logged in.
    * @param   array $userPIDAuthGroups The array of groups this user belongs to.
    * @param   boolean $userPIDAuthGroups OPTIONAL (default is true) whether to redirect to the login page or not.
    * @returns boolean true if access is ok.
    */
    function checkAuthorisation($pid, $dsID, $acceptable_roles, $failed_url, $userPIDAuthGroups=null, $redirect=true) {
        global $auth_isBGP, $auth_bgp_session;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            session_name(APP_SESSION);
            @session_start();
            $session =& $_SESSION;
        }
        
		$isAdministrator = Auth::isAdministrator();
		if ($isAdministrator) {
			return true;
		}
		if (!is_array($acceptable_roles) || empty($pid) ) {
			return false;
		}
        // find out which role groups this user belongs to
        if (is_null($userPIDAuthGroups)) {
            $userPIDAuthGroups = Auth::getAuthorisationGroups($pid, $dsID);
        }
		$auth_ok = false;
        if (is_array($userPIDAuthGroups)) {
            foreach ($acceptable_roles as $role) {
                if (in_array($role, $userPIDAuthGroups)) {
                    $auth_ok = true;
                }
            }
        }
		if ($auth_ok != true) {
            // Perhaps the user hasn't logged in
			if (!Auth::isValidSession($session)) {
				if ($redirect != false) {
					$failed_url = base64_encode($failed_url);
				    Auth::redirect(APP_RELATIVE_URL . "login.php?err=21&url=".$failed_url, $is_popup);
				}
			} else {
				return false;	
			}
		} else {
			return true;
		}
	}

    function getAuthorisation(&$indexArray) 
    {
        $userPIDAuthGroups = $indexArray['FezACML'];
        $editor_matches = array_intersect(explode(',',APP_EDITOR_ROLES), $userPIDAuthGroups); 
        $creator_matches = array_intersect(explode(',',APP_CREATOR_ROLES), $userPIDAuthGroups); 
        $approver_matches = array_intersect(explode(',',APP_APPROVER_ROLES), $userPIDAuthGroups); 
        
		$indexArray['isCommunityAdministrator'] = (in_array('Community Administrator', $userPIDAuthGroups) || Auth::isAdministrator()); //editor is only for the children. To edit the actual community record details you need to be a community admin
		$indexArray['isApprover'] = (!empty($approver_matches) || $indexArray['isCommunityAdministrator'] == true);
		$indexArray['isEditor'] = (!empty($editor_matches) || $indexArray['isCommunityAdministrator'] == true);
		$indexArray['isCreator'] = (!empty($creator_matches) || $indexArray['isCommunityAdministrator'] == true);
		$indexArray['isArchivalViewer'] = (in_array('Archival_Viewer', $userPIDAuthGroups) || ($indexArray['isEditor'] == true));
		$indexArray['isViewer'] = (in_array('Viewer', $userPIDAuthGroups) || ($indexArray['isEditor'] == true));
		$indexArray['isLister'] = (in_array('Lister', $userPIDAuthGroups) || ($indexArray['isViewer'] == true));
		
		return $indexArray;		
	}

    function getIndexAuthCascade($indexArray) 
    {    	    	
		$isAdministrator = Auth::isAdministrator();
		foreach ($indexArray as $indexKey => $indexRecord) {            
            
            if ($indexRecord["authi_role"]) {
            	$editor_matches = array_intersect(explode(',',APP_EDITOR_ROLE_IDS), $indexRecord["authi_role"]); 
            	$creator_matches = array_intersect(explode(',',APP_CREATOR_ROLE_IDS), $indexRecord["authi_role"]); 
            	$approver_matches = array_intersect(explode(',',APP_APPROVER_ROLE_IDS), $indexRecord["authi_role"]); 
            	$userPIDAuthGroups = $indexRecord["authi_role"];
            } else {
            	$editor_matches = array();
            	$userPIDAuthGroups = array();
            }

			$indexArray[$indexKey]['isCommunityAdministrator'] = (in_array(6, $userPIDAuthGroups) || $isAdministrator); //editor is only for the children. To edit the actual community record details you need to be a community admin
			$indexArray[$indexKey]['isEditor'] = (!empty($editor_matches) || $indexArray[$indexKey]['isCommunityAdministrator'] == true);
			$indexArray[$indexKey]['isCreator'] = (!empty($creator_matches) || $indexArray[$indexKey]['isCommunityAdministrator'] == true);
			$indexArray[$indexKey]['isApprover'] = (!empty($approver_matches) || $indexArray[$indexKey]['isCommunityAdministrator'] == true);
			$indexArray[$indexKey]['isArchivalViewer'] = (in_array(3, $userPIDAuthGroups) || ($indexArray[$indexKey]['isEditor'] == true));
			$indexArray[$indexKey]['isViewer'] = (in_array(10, $userPIDAuthGroups) || ($indexArray[$indexKey]['isEditor'] == true));
			$indexArray[$indexKey]['isLister'] = (in_array(9, $userPIDAuthGroups) || ($indexArray[$indexKey]['isViewer'] == true));
		}
        
		return $indexArray;
	}

    /**
     * getAuthorisationGroups
	 * This method gets the roles (or authorisation groups) the user has, based on the given ACMLs using the Fez Fedora connection.
	 * It performs some of the lookups using XPATH searches. This is called when the user is working directly with the object
	 * eg view, update, edit etc.
     *
     * @access  public
     * @param   string $pid The persistent identifier of the object
     * @param   string $dsID (optional) The datastream ID 
     * @returns array $userPIDAuthGroups The authorisation groups (roles) the user belongs to against this object.
    */
	function getAuthorisationGroups($pid, $dsID="") {
        global $auth_isBGP, $auth_bgp_session;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            session_name(APP_SESSION);
            @session_start();
            $session =& $_SESSION;
        }
        static $roles_cache;
		$inherit = false;
		if ($dsID != "") {
	        if (isset($roles_cache[$pid][$dsID])) {
				return $roles_cache[$pid][$dsID];
			}
		} else {
			if (isset($roles_cache[$pid])) {
				return $roles_cache[$pid];
			}
		}
        $userPIDAuthGroups = array();
        // Usually everyone can list, view and view comments
        global $NonRestrictedRoles;
        $userPIDAuthGroups = $NonRestrictedRoles;
		$usingDS = false;
        $acmlBase = false;
		
        if ($dsID != "") {
			$usingDS = true;
	        $acmlBase = Record::getACML($pid, $dsID);
		}
		
		// if no FezACML exists for a datastream then it must inherit from the pid object
        if ($acmlBase == false) {
			$usingDS = false;
	        $acmlBase = Record::getACML($pid);
		}
        $ACMLArray = array();
        
        // no FezACML was found for DS or PID object 
        // so go to parents straight away (inherit presumed)
        if ($acmlBase == false) {
            $parents = Record::getParents($pid);
            Auth::getParentACMLs(&$ACMLArray, $parents);
        } else { // otherwise found something so use that and check if need to inherit
            
            $ACMLArray[0] = $acmlBase;
            
            // Check if it inherits security                
            $xpath = new DOMXPath($acmlBase);
            $anyRuleSearch = $xpath->query('/FezACML/rule/role/*[string-length(normalize-space()) > 0]');
            if ($anyRuleSearch->length == 0) {
                    
                $inherit = true;
                  
            } else {            
                $inheritSearch = $xpath->query('/FezACML[inherit_security="on" or inherit_security=""]');
                  
                if( $inheritSearch->length > 0 ) {
                    $inherit = true;
                }
            }
            
			if ($inherit == true) { // if need to inherit, check if at dsID level or not first and then 
				$userPIDAuthGroups["security"] = "inherit";	
				
				// if already at PID level just get parent pids and add them
				if (($dsID == "") || ($usingDS == false)) {
					$parents = Record::getParents($pid);
					Auth::getParentACMLs(&$ACMLArray, $parents);				
				} else { // otherwise get the pid object first and check whether to inherit
					$acmlBase = Record::getACML($pid);
					if ($acmlBase == false) { // if pid level doesnt exist go higher
						$parents = Record::getParents($pid);
						Auth::getParentACMLs(&$ACMLArray, $parents);
					} else { // otherwise found pid level so add to ACMLArray and check whether to inherit or not
						$userPIDAuthGroups["security"] = "include";	
				    	array_push($ACMLArray, $acmlBase);
						// If found an ACML then check if it inherits security
						$inherit = false;
						$xpath = new DOMXPath($acmlBase);
						$inheritSearch = $xpath->query('/FezACML/inherit_security');
						foreach ($inheritSearch as $inheritRow) {
							if ($inheritRow->nodeValue == "on") {
								$inherit = true;
							}
						}
						if ($inherit == true) {
							$parents = Record::getParents($pid);
							Auth::getParentACMLs(&$ACMLArray, $parents);				
						}
					}
				}
			} else {
				$userPIDAuthGroups["security"] = "exclude";	
			}
        }
        
        // loop through the ACML docs found for the current pid or in the ancestry
		$cleanedArray = array();
		$overrideAuth = array();
        foreach ($ACMLArray as &$acml) {
	        // Usually everyone can list, view and view comments - these need to be reset for each ACML loop
			// because they are presumed ok first
		    //$userPIDAuthGroups = Misc::array_merge_values($userPIDAuthGroups, $NonRestrictedRoles);
            // Use XPath to find all the roles that have groups set and loop through them
            $xpath = new DOMXPath($acml);
            $roleNodes = $xpath->query('/FezACML/rule/role');
            $inheritSearch = $xpath->query('/FezACML[inherit_security="on"]');
            $inherit = false;
            if( $inheritSearch->length > 0 ) {
                $inherit = true;
            }
            
            foreach ($roleNodes as $roleNode) {
                $role = $roleNode->getAttribute('name');
                // Use XPath to get the sub groups that have values
                $groupNodes = $xpath->query('./*[string-length(normalize-space()) > 0]', $roleNode);
                
                /*
                 * Empty rules override non-empty rules. Example:
                 * If a pid belongs to 2 collections, 1 collection has lister restricted to fez users
                 * and 1 collection has no restriction for lister, we want no restrictions for lister
                 * for this pid.
                 */
                if($groupNodes->length == 0 && ($role == "Viewer" || $role == "Lister") && $inherit == false) {
                	$overridetmp[$role] = true;
                }
                
                foreach ($groupNodes as $groupNode) {
                    $group_type = $groupNode->nodeName;
                    $group_values = explode(',', $groupNode->nodeValue);
                    foreach ($group_values as $group_value) {
                        
                    	$group_value = trim($group_value, ' ');
	                    
                        // if the role is in the ACML with a non 'off' value 
	                    // and not empty value then it is restricted so remove it
	                    if ($group_value != "off" && $group_value != "" && in_array($role, $userPIDAuthGroups) && in_array($role, $NonRestrictedRoles) && (@$cleanedArray[$role] != true)) {
	                        $userPIDAuthGroups = Misc::array_clean($userPIDAuthGroups, $role, false, true);
							$cleanedArray[$role] = true;
							$overridetmp[$role] = false;
						
	                    } elseif(($group_value == "" || $group_value == "off") 
	                               && ($role == "Viewer" || $role == "Lister")) {
	                               	
	                    	if($overridetmp[$role] !== false) {
                                $overridetmp[$role] = true;
	                    	}
	                    	
	                    } elseif( $group_value != "off" && $group_value != "" ) {
	                    	$overridetmp[$role] = false;
	                    }
	                    
                        // @@@ CK - if the role has already been
                        // found then don't check for it again
                        if (!in_array($role, $userPIDAuthGroups)) {
                            switch ($group_type) {
                                case 'AD_Group':
                                    if (@in_array($group_value, $session[APP_LDAP_GROUPS_SESSION])) {
                                        array_push($userPIDAuthGroups, $role);
                                    }
                                    break;
                                case 'in_AD':
                                    if (($group_value == 'on') && Auth::isValidSession($session)
                                            && Auth::isInAD()) {
                                        array_push($userPIDAuthGroups, $role);
                                    }
                                    break;
                                case 'in_Fez':
                                    if (($group_value == 'on') && Auth::isValidSession($session)
                                            && Auth::isInDB()) {
                                        array_push($userPIDAuthGroups, $role);
                                    }    
                                    break;
                                case 'AD_User':
                                    if (Auth::isValidSession($session)
                                            && $group_value == Auth::getUsername()) {
                                        array_push($userPIDAuthGroups, $role);
                                    }
                                    break;
                                case 'AD_DistinguishedName':
                                    if (is_numeric(strpos(@$session['distinguishedname'], $group_value))) {
                                        array_push($userPIDAuthGroups, $role);
                                    }
                                    break;
								case 'eduPersonTargetedID':
									if (is_numeric(strpos(@$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID'], $group_value))) {
										array_push($userPIDAuthGroups, $role);
									}
									break;												
								case 'eduPersonAffiliation':
									if (is_numeric(strpos(@$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-UnscopedAffiliation'], $group_value))) {
										array_push($userPIDAuthGroups, $role);
									}
									break;												
								case 'eduPersonScopedAffiliation':
									if (is_numeric(strpos(@$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-ScopedAffiliation'], $group_value))) {
										array_push($userPIDAuthGroups, $role);
									}
									break;												
								case 'eduPersonPrimaryAffiliation':
									if (is_numeric(strpos(@$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrimaryAffiliation'], $group_value))) {
										array_push($userPIDAuthGroups, $role);
									}
									break;												
								case 'eduPersonPrincipalName':
									if (is_numeric(strpos(@$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'], $group_value))) {
										array_push($userPIDAuthGroups, $role);
									}
									break;		
								case 'eduPersonOrgUnitDN':
									if (is_numeric(strpos(@$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-OrgUnitDN'], $group_value))) {
										array_push($userPIDAuthGroups, $role);
									}
									break;		
								case 'eduPersonPrimaryOrgUnitDN':
									if (is_numeric(strpos(@$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrimaryOrgUnitDN'], $group_value))) {
										array_push($userPIDAuthGroups, $role);
									}
									break;		
									
                                case 'Fez_Group':
                                    if (@in_array($group_value, $session[APP_INTERNAL_GROUPS_SESSION])) {
                                    	array_push($userPIDAuthGroups, $role);
                                    }
                                    break;

                                case 'Fez_User':
                                    if (Auth::isValidSession($session) && $group_value == Auth::getUserID()) {
                                        array_push($userPIDAuthGroups, $role);
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
                
                // If all groups rules were empty $overridetmp for this role will be true
                // Therefore we want this rule to be enabled for this user
                if($overridetmp[$role] == true && $inherit == false) {
                	$overrideAuth[$role] = true;
                }
                
                $overridetmp = array();
            }
        }
        
        
		
		if (in_array('Community_Administrator', $userPIDAuthGroups) && !in_array('Editor', $userPIDAuthGroups)) {
			array_push($userPIDAuthGroups, "Editor");	
		}
		if (in_array('Community_Administrator', $userPIDAuthGroups) && !in_array('Creator', $userPIDAuthGroups)) {
			array_push($userPIDAuthGroups, "Creator");	
		}
		if (in_array('Community_Administrator', $userPIDAuthGroups) && !in_array('Approver', $userPIDAuthGroups)) {
			array_push($userPIDAuthGroups, "Approver");	
		}
		if (in_array('Editor', $userPIDAuthGroups) && !in_array('Archival_Viewer', $userPIDAuthGroups)) {
			array_push($userPIDAuthGroups, "Archival_Viewer");	
		}
		if ((in_array('Editor', $userPIDAuthGroups) && !in_array('Viewer', $userPIDAuthGroups)) || $overrideAuth['Viewer'] == true) {
			array_push($userPIDAuthGroups, "Viewer");	
		}
		if ((in_array('Viewer', $userPIDAuthGroups) && !in_array('Lister', $userPIDAuthGroups)) || $overrideAuth['Lister'] == true) {
			array_push($userPIDAuthGroups, "Lister");	
		}
                
		/*
		 * Special Auth Case (This isn't set via the interface)
		 * If a user has creator rights, the pid isn't 'submitted for approval'
		 * and the user is assigned to this pid, then they can edit it
		 */
		if(!in_array("Editor", $userPIDAuthGroups)) {
			if(in_array("Creator", $userPIDAuthGroups)) {
				$status = Record::getSearchKeyIndexValue($pid, "Status", false);
				$assigned_user_ids = Record::getSearchKeyIndexValue($pid, "Assigned User ID", false);
				
				if(in_array(Auth::getUserID(), $assigned_user_ids) && $status != Status::getID("Submitted for Approval")) {
					array_push($userPIDAuthGroups, "Editor");
				}
			}
		}
		
        if ($GLOBALS['app_cache']) {
			if (!is_array($roles_cache) || count($roles_cache) > 10) { //make sure the static memory var doesnt grow too large and cause a fatal out of memory error
				$roles_cache = array();
			}
			if ($dsID != "") {
				$roles_cache[$pid][$dsID] = $userPIDAuthGroups;
			} else {			
				$roles_cache[$pid] = $userPIDAuthGroups;
			}
		}
		return $userPIDAuthGroups;
	} 

	    /**
	     * getAuth
		 * This method gets the roles (or authorisation groups) the user has, based on the given ACMLs using the Fez Fedora connection.
		 * It performs some of the lookups using XPATH searches. This is called when the user is working directly with the object
		 * eg view, update, edit etc.
	     *
	     * @access  public
	     * @param   string $pid The persistent identifier of the object
	     * @param   string $dsID (optional) The datastream ID 
	     * @returns array $userPIDAuthGroups The authorisation groups (roles) the user belongs to against this object.
	    */
		function getAuth($pid, $dsID="") {
	        static $roles_cache;
			
			if ($dsID != "") {
		        if (isset($roles_cache[$pid][$dsID])) {
					return $roles_cache[$pid][$dsID];
				}
			} else {
				if (isset($roles_cache[$pid])) {
					return $roles_cache[$pid];
				}
			}
			
	        $auth_groups = array();
	        $ACMLArray = array();
	        
			$usingDS = false;
	        $acmlBase = false;
	        $inherit = false;
	        
			if ($dsID != "") {
				$usingDS = true;
		        $acmlBase = Record::getACML($pid, $dsID);
			}
			
			// if no FezACML exists for a datastream then it must inherit from the pid object
	        if ($acmlBase == false) {
				$usingDS = false;
		        $acmlBase = Record::getACML($pid);
			}
	        
	        /*
	         * No FezACML was found for DS or PID object 
	         * so go to parents straight away (inherit presumed)
	         */
	        if ($acmlBase == false) {
	            $parents = Record::getParents($pid);
	            Auth::getParentACMLs(&$ACMLArray, $parents);
	            
            /*
             * otherwise found something so use that and check if need to inherit
             */
	        } else {
	            
	        	$ACMLArray[0] = $acmlBase;
				
	            // Check if it inherits security	            
				$xpath = new DOMXPath($acmlBase);
	            $anyRuleSearch = $xpath->query('/FezACML/rule/role/*[string-length(normalize-space()) > 0]');
	            if ($anyRuleSearch->length == 0) {
	            	
            	   $inherit = true;
	              
	            } else {
	            	
                    $inheritSearch = $xpath->query('/FezACML[inherit_security="on" or inherit_security=""]');
                    if( $inheritSearch->length > 0 ) {
                        $inherit = true;
                    }
                    
	            }
	            
	            /*
	             * If need to inherit, check if at dsID level or not first and then
	             */
				if ($inherit == true) {
					
					/*
					 * If already at PID level just get parent pids and add them
					 */
					if (($dsID == "") || ($usingDS == false)) {
						$parents = Record::getParents($pid);
						Auth::getParentACMLs(&$ACMLArray, $parents);			

				    /*
				     * Otherwise get the pid object first and check whether to inherit
				     */
					} else { 
						
						$acmlBase = Record::getACML($pid);
						
						// if pid level doesnt exist go higher
						if ($acmlBase == false) { 
							$parents = Record::getParents($pid);
							Auth::getParentACMLs(&$ACMLArray, $parents);
                        
                        /*
                         * Otherwise found pid level so add to ACMLArray and 
                         * check whether to inherit or not
                         */
						} else {
					    	
							array_push($ACMLArray, $acmlBase);
							
							// If found an ACML then check if it inherits security
							$xpath = new DOMXPath($acmlBase);
			                $inheritSearch = $xpath->query('/FezACML[inherit_security="on" or inherit_security=""]');
			                
			                if( $inheritSearch->length > 0 ) {
			                    $parents = Record::getParents($pid);
                                Auth::getParentACMLs(&$ACMLArray, $parents);
			                }
							
						}
					}
					
				}
	        }
	        
	        
	        // loop through the ACML docs found for the current pid or in the ancestry
	        foreach ($ACMLArray as &$acml) {
	        	
	            // Use XPath to find all the roles that have groups set and loop through them
	            $xpath = new DOMXPath($acml);
	            $roleNodes = $xpath->query('/FezACML/rule/role');
	            
	            $inherit = false;
	            $inheritSearch = $xpath->query('/FezACML[inherit_security="on" or inherit_security=""]');
                if( $inheritSearch->length > 0 ) {
                	$inherit = true;
                }
	            
	            foreach ($roleNodes as $roleNode) {
	                $role = $roleNode->getAttribute('name');
	                
	                // Use XPath to get the sub groups that have values
	                // Note: off can be considered as empty
	                $groupNodes = $xpath->query('./*[string-length(normalize-space()) > 0 and text() != "off"]', $roleNode);
	                if ($groupNodes->length == 0) {
	                	
	                	/*
	                	 * If this is a top level rule (not inherited) and lister and 
	                	 * viewer is empty then we want public listing for this pid no 
	                	 * matter what other security this pid has for lister and viewer
	                	 */
	                	if(($role == 'Lister' || $role == 'Viewer') && $inherit == false) {
                            
                            $rule_array = array(
                                "rule"    => "override", 
                                "value"   => "true"
                            );
                            Auth::addRuleArray(&$auth_groups, $role, $rule_array);
                            
	                	}
	                	
	                    continue;
	                }
	                
	                foreach ($groupNodes as $groupNode) {
	                    $group_type = $groupNode->nodeName;
	                    $group_values = explode(',', $groupNode->nodeValue);
	                    foreach ($group_values as $group_value) {
	                    	
	                        $group_value = trim($group_value, ' ');
	                        $rule_array = array(
		                        "rule"    => "!rule!role!".$group_type, 
		                        "value"   => $group_value
	                        );
							Auth::addRuleArray(&$auth_groups, $role, $rule_array);
							
	                    }
	                }
	            }
	        }
	        
			if ($GLOBALS['app_cache']) {
				if (!is_array($roles_cache) || count($roles_cache) > 10) { //make sure the static memory var doesnt grow too large and cause a fatal out of memory error
					$roles_cache = array();
				}
				if ($dsID != "") {
				    $roles_cache[$pid][$dsID] = $auth_groups;
				} else {			
				    $roles_cache[$pid] = $auth_groups;
				}
			}

			return $auth_groups;
	    }

	function addRuleArray(&$auth_groups, $role, $ruleArray = array()) {
		if (!is_array($auth_groups[$role])) {
			$auth_groups[$role] = array();
		}
		array_push($auth_groups[$role], $ruleArray);
		
	}
    
    /**
     * Find all the possible rights that this user has to any records in the system.  For example
     * if they have admin rights on one record then this will return that role.  Use this function to check if the
     * user should be allowed to start a workflow which requires a particular role on the objects it selects.
     * NOTE: This assumes that the user is logged in as the auth_rule_group_users table is only updated when 
     * a user is logged in.
     * @return array of strings - each string is a role name that this user has on at least one pid int he system 
     */
    function getAllIndexAuthorisationGroups($user_id)
    {
    	$stmt = "SELECT distinct aro_role as authi_role 
    	         FROM " . APP_TABLE_PREFIX . "auth_rule_group_users " .
                "INNER JOIN " . APP_TABLE_PREFIX . "auth_rule_group_rules " .
                        "ON argu_usr_id=".$user_id." AND argr_arg_id=argu_arg_id " .
                "INNER JOIN " . APP_TABLE_PREFIX . "auth_index2 " .
                        "ON authi_arg_id=argr_arg_id " .
                "INNER JOIN " . APP_TABLE_PREFIX . "auth_roles " .
                        "ON authi_role=aro_id ";

        $res = $GLOBALS["db_api"]->dbh->getCol($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return array();
        } else {
            return $res;
        }                
    }
    
    function isUserApprover($user_id) {
    	
    	$stmt = "SELECT * " .
                "FROM " . APP_TABLE_PREFIX . "auth_rule_group_users " .
                "INNER JOIN " . APP_TABLE_PREFIX . "auth_rule_group_rules " .
                        "ON argu_usr_id = ".$user_id." AND argr_arg_id = argu_arg_id " .
                "INNER JOIN " . APP_TABLE_PREFIX . "auth_index2 " .
                        "ON authi_arg_id = argr_arg_id " .
                "INNER JOIN " . APP_TABLE_PREFIX . "auth_roles " .
                        "ON (authi_role = " . Auth::getRoleIDByTitle("Approver") .
    	                " OR authi_role = " . Auth::getRoleIDByTitle("Community_Administrator") . ") ".
    	        "LIMIT 1";
        
        $res = $GLOBALS["db_api"]->dbh->getCol($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return false;
        } else {
            if(count($res) > 0) {
            	return true;
            } else {
            	return false;
            }
        }                
    	
    }
    
    /**
     * Method to check if the user has session support enabled in his browser or
     * not.
     *
     * @access  public
     * @param   string $session_name The name of the session to check for
     * @return  boolean
     */
    function hasSessionSupport($session_name)
    {
        if (@!in_array($session_name, array_keys($_SESSION))) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * Method to check if the user has a valid session.
     *
     * @access  public
     * @param   string $session_name The name of the session to check for
     * @return  boolean
     */
    function hasValidSession($session_name)
    {
        global $auth_isBGP, $auth_bgp_session;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            session_name(APP_SESSION);
            @session_start();
            $session =& $_SESSION;
        }
 
        return Auth::isValidSession($session);
    }



    /**
     * Method used to check whether a session is valid or not.
     *
     * @access  public
     * @param   array $session The unserialized contents of the session
     * @return  boolean
     */
    function isValidSession(&$session)
    {
        if ((empty($session["username"])) || (empty($session["hash"])) 
                || ($session["hash"] != md5($GLOBALS["private_key"] . md5($session["login_time"]) 
                        . $session["username"]))
                || ($session['ipaddress'] != @$_SERVER['REMOTE_ADDR'])) {
            return false;
        } else {
			return true;
        }
    }


    /**
     * Method used to create the login session in the user's machine.
     *
     * @access  public
     * @param   string $username The username to be stored in the session
     * @param   string $fullname The user full name to be stored in the session
     * @param   string $email The email address to be stored in the session
     * @param   string $distinguishedname The user distinguishedname to be stored in the session
     * @param   integer $autologin Flag to indicate whether this user should be automatically logged in or not
     * @return  void
     */
    function createLoginSession($username, $fullname,  $email, $distinguishedname, $autologin = 0)
    {
		global $auth_bgp_session, $auth_isBGP;

        if ($auth_isBGP) {
            $ses =& $auth_bgp_session;
        } else {
            $ses =& $_SESSION;
        }
		$ipaddress = @$_SERVER['REMOTE_ADDR'];
        $time = time();
        $ses["username"] = $username;
        $ses["fullname"] = $fullname;
        $ses["distinguishedname"] = $distinguishedname;
        $ses["email"] = $email;
        $ses["ipaddress"] = $ipaddress;
        $ses["login_time"] = $time;
        $ses["hash"] = md5($GLOBALS["private_key"] . md5($time) . $username);
		$ses["autologin"] = $autologin;
    }



    /**
     * Method used to redirect people to another URL.
     *
     * @access  public
     * @param   string $new_url The URL the user should be redirected to
     * @param   boolean $is_popup Whether the current window is a popup or not
     * @return  void
     */
    function redirect($new_url, $is_popup = false)
    {
        if ($is_popup) {
            $html = '<script language="JavaScript">
                     <!--
                     window.opener.location.href = "' . $new_url . '";
                     window.close();
                     //-->
                     </script>';
            echo $html;
            exit;
        } else {
            header("Refresh: 0; URL=".$new_url);
            exit;
        }
    }


    /**
     * Method used to remove a session from the user's browser.
     *
     * @access  public
     * @param   string $session_name The name of the session that needs to be deleted
     * @return  void
     */
    function removeSession($session_name)
    {
		// Initialize the session.
		// If you are using session_name("something"), don't forget it now!
        session_name($session_name);
		@session_start();		
		// Unset all of the session variables.
		$_SESSION = array();
		// If it's desired to kill the session, also delete the session cookie.
		// Note: This will destroy the session, and not just the session data!
		if (isset($_COOKIE[session_name()])) {
		   setcookie(session_name(), '', time()-42000, '/');
		}
		// Finally, destroy the session.
		@session_destroy();
    }


    /**
     * Checks whether an user exists or not in the database.
     *
     * @access  public
     * @param   string $email The email address to check for
     * @return  boolean
     */
    function userExists($username)
    {
        if (empty($username)) {
            return false;
          } else {
            $stmt = "SELECT usr_administrator 
                    FROM " . APP_TABLE_PREFIX . "user 
                    WHERE usr_username='".Misc::escapeString($username)."'";
            $info = $GLOBALS["db_api"]->dbh->getOne($stmt);
            if (PEAR::isError($info)) {
                Error_Handler::logError(array($info->getMessage(), $info->getDebugInfo()), __FILE__, __LINE__);
                return false;
            } elseif (count($info) != 1) {
                return false;
            } else {
                return true;
            }
        }
    }


    /**
     * Checks whether the provided password match against the username.
     *
     * @access  public
     * @param   string $email The email address to check for
     * @param   string $password The password of the user to check for
     * @return  boolean
     */
    function isCorrectPassword($username, $password)
    {
        if (APP_DISABLE_PASSWORD_CHECKING == "true" && $_SERVER['REMOTE_ADDR'] == APP_DISABLE_PASSWORD_IP) {
            return true;
        } else {
            if (empty($username)) {
            	$username = $_POST["username"];
            }
            if (Auth::userExists($username)) {
                $userDetails = User::getDetails($username);
                if (($userDetails['usr_ldap_authentication'] == 1) && (LDAP_SWITCH == "ON")) {
                    return Auth::ldap_authenticate($username, $password);
                } else {
                    if ($userDetails['usr_password'] != md5($password) || (trim($password) == "")) {
                        return false;
                    } else {
                        return true;
                    }
                }
            } else {
                if (LDAP_SWITCH == "ON") { 
                    return Auth::ldap_authenticate($username, $password);
                } else {
                    return false;
                }
            }
        }
    }


    /**
     * Method to check whether an user is active or not.
     *
     * @access  public
     * @param   string $username The username to be checked
     * @return  boolean
     */
    function isActiveUser($username)
    {
        $status = User::getStatusByUsername($username);
        if ($status != 'active') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Gets the current user ID.
     *
     * @access  public
     * @return  integer The ID of the user
     */
    function getUserID()
    {
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            $session =& $_SESSION;
        }
	
        if (empty($session['username'])) {
            return '';
        } else {
            return @User::getUserIDByUsername($session["username"]);
        }
    }


    /**
     * Gets the current user ID.
     *
     * @access  public
     * @return  integer The ID of the user
     */
    function getUsername()
    {
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            session_name(APP_SESSION);
            @session_start();
            $session =& $_SESSION;
        }
        if (empty($session) || empty($session['username'])) {
            return '';
        } else {
            return $session['username'];
        }
    }
    /**
     * Gets the current user ID.
     *
     * @access  public
     * @return  integer The ID of the user
     */
    function getUserFullName()
    {
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            session_name(APP_SESSION);
			@session_start();			
            $session =& $_SESSION;
        }
        if (empty($session) || empty($session["fullname"])) {
            return '';
        } else {
            return $session["fullname"];
        }
    }
    /**
     * Gets the current user ID.
     *
     * @access  public
     * @return  integer The ID of the user
     */
    function getUserEmail()
    {
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            $session =& $_SESSION;
        }
        if (empty($session) || empty($session["email"])) {
            return '';
        } else {
            return $session["email"];
        }
    }

    /**
     * Gets the LDAP groups the user belongs to. 
     *
     * @access  public
     * @param   string $username The username of the user (in ldap)
     * @param   string $password The password of the user (in ldap)
     * @return  array $usersgroups, plus saves them to the LDAP groups session variable
     */
    function GetUsersLDAPGroups($username, $password)  {
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            $session =& $_SESSION;
        }
		$memberships = array();
		$success = null;
		$useringroupcount = null;
		$useringroupcount = 0;
		$ldap_conn = null;
		$ldap_result = null;
		$ldap_info = null;
		$ldap_infoadmin = null;
		$usersgroups = array();
		$success = 'true';
		$filter = "(samaccountname=".$username.")";
		$ldap_conn = ldap_connect(LDAP_SERVER, LDAP_PORT);
	    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    	ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
		$ldap_bind = @ldap_bind($ldap_conn, LDAP_PREFIX."\\".$username, $password);
		if ($ldap_bind) {
			$ldap_result = ldap_search($ldap_conn, LDAP_ROOT_DN, $filter);
			// retrieve all the entries from the search result
			$ii=0;
			if ($ldap_result) {
				$info = ldap_get_entries($ldap_conn, $ldap_result);
				for ($i=0; $ii<$info[$i]["count"]; $ii++) {
					$data = $info[$i][$ii];
					for($j=0; $j<$info[$i][$data]["count"]; $j++) {	
						if ($data == "memberof") {
							array_push($memberships, $info[$i][$data][$j] );
						}
					}
				}
				foreach($memberships as $item) {
					list($CNitem, $rest) = split(",", $item);
					list($tag, $group) = split("=", $CNitem);
//					echo $username." is a member of group: $group<br>\n";
					array_push($usersgroups, $group);
				}
			} else {
				echo ldap_error($ldap_conn);
				exit;
			}
		} 	
		// close connection to ldap server
		ldap_close($ldap_conn);
		$session[APP_LDAP_GROUPS_SESSION] = $usersgroups;
		return $usersgroups;
    } //end of GetUserGroups function.


    /**
     * Checks if the user can authentication off the LDAP server. 
     *
     * @access  public
     * @param   string $p_user_id The username of the user (in ldap)
     * @param   string $p_password The password of the user (in ldap)
     * @return  boolean true if the user successfully binds to the LDAP server
     */
	function ldap_authenticate($p_user_id, $p_password) {
        if ((APP_DISABLE_PASSWORD_CHECKING == "true") && ($_SERVER['REMOTE_ADDR'] == APP_DISABLE_PASSWORD_IP)) {
            return true; // switch this on and comment the rest out for debugging/development
        } else {
            $t_authenticated 		= false;
            $t_username             = $p_user_id;
            $t_ds                   = ldap_connect(LDAP_SERVER, LDAP_PORT);
# Attempt to bind with the DN and password
            $t_br = @ldap_bind( $t_ds, LDAP_PREFIX."\\".$t_username, $p_password );
            if ($t_br) {
                $t_authenticated = true;
            }
            @ldap_unbind( $t_ds );
            return $t_authenticated; 
        }
	}


    /**
     * Retrieves an array of Shibboleth Federation IDPs for display in the Fez WAYF 
     *
     * @access  public
     * @return  array 
     */
    function getIDPList() {	
		if (is_file(SHIB_WAYF_METADATA_LOCATION) == true) {		
			$sourceXML = fopen(SHIB_WAYF_METADATA_LOCATION, "r");
			$sourceXMLRead = '';
			while ($tmp = fread($sourceXML, 4096)) {
				$sourceXMLRead .= $tmp;
			}
			$xmlDoc= new DomDocument();
			$xmlDoc->preserveWhiteSpace = false;
			$xmlDoc->loadXML($sourceXMLRead);
			$xpath = new DOMXPath($xmlDoc);
			$xpath->registerNamespace("md", "urn:oasis:names:tc:SAML:2.0:metadata");
			$xpath->registerNamespace("shib","urn:mace:shibboleth:metadata:1.0");
			$recordNodes = $xpath->query("//md:EntitiesDescriptor/md:EntityDescriptor");
			$IDPArray = array();
			foreach ($recordNodes as $recordNode) {
				$type_fields = $xpath->query("./md:IDPSSODescriptor", $recordNode);
				$foundIDP = false;
				foreach ($type_fields as $type_field) {
					$foundIDP = true;
				}				
				if ($foundIDP == true) {
					$entityID = "";
					$type_fields = $xpath->query("./@entityID[string-length(.) > 0]", $recordNode);
					foreach ($type_fields as $type_field) {
						if  ($entityID == "") {
							$entityID = $type_field->nodeValue;
						}
					}
					$OrganisationDisplayName = "";
					$type_fields = $xpath->query("./md:Organization/md:OrganizationDisplayName", $recordNode);
					foreach ($type_fields as $type_field) {
						if  ($OrganisationDisplayName == "") {
							$OrganisationDisplayName = $type_field->nodeValue;
						}
					}
					$SSO = "";
					$type_fields = $xpath->query("./md:IDPSSODescriptor/md:SingleSignOnService/@Location", $recordNode);
					foreach ($type_fields as $type_field) {
						if  ($SSO == "") {
							$SSO = $type_field->nodeValue;
						}
					}
					if ($OrganisationDisplayName != "" && $entityID != "" && $SSO != "" && is_numeric(strpos($entityID,SHIB_FEDERATION))) {
						$IDPArray['List'][$entityID] = $OrganisationDisplayName;
						$IDPArray['SSO'][$entityID]['SSO'] = $SSO;
//						$IDPArray['SSO'][$entityID]['SSO'] = "https://$SSO/shibboleth-idp/SSO";
						$IDPArray['SSO'][$entityID]['Name'] = $OrganisationDisplayName;
					}
				}			
			}
//			print_r($IDPArray);
			return $IDPArray;
		} else {
			return array(); //if the file cannot be found return an empty array
		}
	}

    /**
     * Logs the user in with session variables for user groups etc. 
     *
     * @access  public
     * @param   string $username The username of the user (in ldap)
     * @param   string $password The password of the user (in ldap)
     * @return  boolean true if the user successfully binds to the LDAP server
     */
    function LoginAuthenticatedUser($username, $password, $shib_login=false) {	
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            $session =& $_SESSION;
        }
		$alreadyLoggedIn = false;
		if (!empty($session["login_time"])) {
			$alreadyLoggedIn = true;
		} else {
			$alreadyLoggedIn = false;
		}
		
		
		if ($shib_login == true && @$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-Attributes'] == "") {
		    return 24;
		}
		
		if ($shib_login == true) {
			// Get the username from eduPerson Targeted ID. If empty then they are (really) anonymous
			if ($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID'] != "") {
				$username = $session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID'];

				// if user has a principal name already in fez add their shibboleth username, 
				// but otherwise their username is their epTid
				if ($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'] != "") {
					$principal_prefix = substr($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'], 0, strpos($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'], "@"));
					
					if ($principal_prefix != '' ) {
					    if (Auth::userExists($username)) {
					       User::updateUsername($principal_prefix, $username);
					    }
					    
					    $username = $principal_prefix;
					    // this is mainly to cater for having login available for both shib and ldap/ad
                        if (Auth::userExists($principal_prefix)) {
                            User::updateShibUsername($principal_prefix, $session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID']);
                        }
					}
				}
			} elseif ($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'] != "") { // if no eptid then try using EP principalname - this should be rare
				$principal_prefix = substr($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'], 0, strpos($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'], "@"));
				if (Auth::userExists($principal_prefix)) {
					$username = $principal_prefix;
				} else {
					$username = $session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'];				
				}
			} else {
				// if trying to login via shib and can't find a username in the IDP 
				// attribs then return false to make redirect to login page with message
				return 23; 
			}
		}

		// If the user isn't a registered fez user, get their details elsewhere (The AD/LDAP) 
		// as they must have logged in with LDAP or Shibboleth
        if (!Auth::userExists($username)) {
			if ($shib_login == true) {
				$session['isInAD'] = false;
				$session['isInDB'] = false;
				$session['isInFederation'] = true;			

				if ($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-Person-commonName'] != "") {
					$fullname =	$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-Person-commonName'];
				} elseif ($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'] != "") {
					$fullname =	$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'];
				} elseif ($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID'] != "") {
					$fullname =	$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID'];
				} elseif ($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-Nickname'] != "") {
					$fullname =	$session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-Nickname'];
				} else {
					$fullname = "Anonymous User";
				}
				if ($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-Person-mail'] != "") {
					$email = $session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-Person-mail'];
				} else {				
					$email = "";
				}
				
				if ($session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID'] != "") {
					$shib_username = $session[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID'];
				} else {				
					$shib_username = $username;
				}
				
				$distinguishedname = "";
				// Create the user in Fez
				User::insertFromShibLogin($username, $fullname, $email, $shib_username);
			} else {
				$session['isInAD'] = true;
				$session['isInDB'] = false;
				$session['isInFederation'] = false;				
				$userDetails = User::GetUserLDAPDetails($username, $password);
				
				$fullname = $userDetails['displayname'];
				$email = $userDetails['email'];
				$distinguishedname = $userDetails['distinguishedname'];
				Auth::GetUsersLDAPGroups($username, $password);
				// Create the user in Fez				
				User::insertFromLDAPLogin();				
			}
            $usr_id = User::getUserIDByUsername($username);
        } else { // if it is a registered Fez user then get their details from the fez user table
            $session['isInDB'] = true;
            $userDetails = User::getDetails($username);
            if (!Auth::isActiveUser($username)) {
            	return 7;
            }		
			if ($shib_login == true) {
				$session['isInFederation'] = true;
			} else {
				$session['isInFederation'] = false;
				if ($userDetails['usr_ldap_authentication'] == 1) {
					if (!$auth_isBGP) {
						$distinguishedname = @$userDetails['distinguishedname'];
						Auth::GetUsersLDAPGroups($userDetails['usr_username'], $password);
					} else {
						$distinguishedname = '';
					}
					$session['isInAD'] = true;
				}  else {
                    $distinguishedname = '';
					$session['isInAD'] = false;
				}
			}
            $fullname = $userDetails['usr_full_name'];
            $email = $userDetails['usr_email'];
			$usr_id = User::getUserIDByUsername($username);
			if ($alreadyLoggedIn !== true) {
	            User::updateLoginDetails($usr_id); //incremement login count and last login date
				if ($shib_login == true) {
		            User::updateShibLoginDetails($usr_id); //incremement login count for shib logins for this user
		            
		            // Save attribs incase we need them when shib server goes down
		            User::updateShibAttribs($usr_id);
				}
				else {
				    User::loadShibAttribs($usr_id);
				}
			}

            // get internal fez groups
			Auth::GetUsersInternalGroups($usr_id);
        }
        
        Auth::createLoginSession($username, $fullname, $email, $distinguishedname, @$_POST["remember_login"]);
        // pre process authorisation rules matches for this user
        Auth::setAuthRulesUsers();
		return 0;
    }

    /**
     * Gets the internal Fez system groups the user belongs to. 
     *
     * @access  public
     * @param   string $usr_id The Fez internal user id of the user
     * @return  void Sets the internal groups session to the found internal groups
     */
	function GetUsersInternalGroups($usr_id) {
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            $session =& $_SESSION;
        }
		$internal_groups = Group::getGroupColList($usr_id);
		$session[APP_INTERNAL_GROUPS_SESSION] = $internal_groups;
	}

    /**
     * Gets the Shibboleth attributes. 
     *
     * @access  public
     * @return  void Sets the internal shib attributes session to the found shib attributes
     */
	function GetShibAttributes() {
        session_name(APP_SESSION);
        @session_start();
	    $headers = apache_request_headers();
		//$shib_attributes = $headers['Shib-Attributes'];

		$_SESSION[APP_SHIB_ATTRIBUTES_SESSION] = $headers;
	}

    /**
     * Is the user in the institutions AD/LDAP system?
     *
     * @access  public
     * @return  boolean true if in the AD/LDAP, false otherwise.
     */
    function isInAD()
    {
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            $session =& $_SESSION;
        }
        return @$session['isInAD'];
    }

    /**
     * Is the user in the internal Fez system?
     *
     * @access  public
     * @return  boolean true if in the internal Fez system, false otherwise.
     */
    function isInDB()
    {
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            $session =& $_SESSION;
        }
        return @$session['isInDB'];
    }

    /**
     * Is the user in the Shibboleth system?
     *
     * @access  public
     * @return  boolean true if in the Shibboleth system, false otherwise.
     */
    function isInFederation()
    {
        global $auth_bgp_session, $auth_isBGP;
        if ($auth_isBGP) {
            $session =& $auth_bgp_session;
        } else {
            $session =& $_SESSION;
        }
        return @$session['isInFederation'];
    }

    /**
     * Return the global default security roles
     *
     * @access  public
     * @return  array $defaultRoles
     */
    function getDefaultRoles() {
        global $defaultRoles;
        return $defaultRoles;
    }

    /**
     * Return the global default security role name of the given role id
     *
     * @access  public
     * @param integer $role_id
     * @return array $defaultRoles
     */
    function getDefaultRoleName($role_id) {
        global $defaultRoles;
        return $defaultRoles[$role_id];
    }

	function getUserAuthRuleGroups($usr_id) {
		$dbtp = APP_TABLE_PREFIX;		
		$stmt = "SELECT argu_arg_id 
		         FROM ".$dbtp."auth_rule_group_users 
		         WHERE argu_usr_id = ".$usr_id;
        $res = $GLOBALS["db_api"]->dbh->getCol($stmt);
        if (PEAR::isError($res)) {
            Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            return -1;
        } else {
			return $res;
		}
	}

    function setAuthRulesUsers()
    {
        global $auth_isBGP;

        if (!$auth_isBGP) {
            $ses = &Auth::getSession();
            $fez_groups_sql = Misc::arrayToSQL(@$ses[APP_INTERNAL_GROUPS_SESSION]);
            $ldap_groups_sql = Misc::arrayToSQL(@$ses[APP_LDAP_GROUPS_SESSION]);
            $dbtp =  APP_TABLE_PREFIX;
            $usr_id = Auth::getUserID();
            
            // clear the rule matches for this user
            $stmt = "DELETE FROM ".$dbtp."auth_rule_group_users WHERE argu_usr_id=".$usr_id;
    		$res = $GLOBALS["db_api"]->dbh->query($stmt);
            if (PEAR::isError($res)) {
                Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
            }
            
            // test and insert matching rules for this user
            $authStmt = "
                INSERT INTO ".$dbtp."auth_rule_group_users (argu_arg_id, argu_usr_id)
                SELECT distinct argr_arg_id, ".$usr_id." 
                FROM ".$dbtp."auth_rule_group_rules
                INNER JOIN ".$dbtp."auth_rules ON argr_ar_id=ar_id
                AND 
                (
                    (ar_rule='public_list' AND ar_value='1') 
                OR  (ar_rule = '!rule!role!Fez_User' AND ar_value='".$usr_id."') 
                OR (ar_rule = '!rule!role!AD_User' AND ar_value='".Auth::getUsername()."') ";
            if (!empty($fez_groups_sql)) {
                $authStmt .="
                    OR (ar_rule = '!rule!role!Fez_Group' AND ar_value IN (".$fez_groups_sql.") ) ";
            }
            if (!empty($ldap_groups_sql)) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!AD_Group' AND ar_value IN (".$ldap_groups_sql.") ) ";
            }
            if (!empty($ses['distinguishedname'])) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!AD_DistinguishedName' 
                            AND INSTR('".$ses['distinguishedname']."', ar_value)
                       ) ";
            } 
    
            if (!empty($ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID'])) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!eduPersonTargetedID' 
                            AND INSTR('".$ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-TargetedID']."', ar_value)
                       ) ";
            }
            if (!empty($ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-UnscopedAffiliation'])) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!eduPersonAffiliation' 
                            AND INSTR('".$ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-UnscopedAffiliation']."', 
                                ar_value)
                       ) ";
            }
            if (!empty($ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-ScopedAffiliation'])) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!eduPersonScopedAffiliation' 
                            AND INSTR('".$ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-ScopedAffiliation']."', 
                                ar_value)
                       ) ";
            }
            if (!empty($ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrimaryAffiliation'])) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!eduPersonPrimaryAffiliation' 
                            AND INSTR('".$ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrimaryAffiliation']."', ar_value)
                       ) ";
            }
            if (!empty($ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName'])) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!eduPersonPrincipalName' 
                            AND INSTR('".$ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrincipalName']."', ar_value)
                       ) ";
            }
            if (!empty($ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-OrgDN'])) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!eduPersonOrgUnitDN' 
                            AND INSTR('".$ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-OrgDN']."', ar_value)
                       ) ";
            }
            if (!empty($ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrimaryOrgUnitDN'])) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!eduPersonPrimaryOrgUnitDN' 
                            AND INSTR('".$ses[APP_SHIB_ATTRIBUTES_SESSION]['Shib-EP-PrimaryOrgUnitDN']."', ar_value)
                       ) ";
            }
    
            if (Auth::isInAD())  {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!in_AD' AND ar_value = 'on')";
            }
            if (Auth::isInDB()) {
                $authStmt .= "
                    OR (ar_rule = '!rule!role!in_Fez' AND ar_value = 'on')";
            }
            
            $authStmt .= ")";
    		
            $res = $GLOBALS["db_api"]->dbh->query($authStmt);
            if (PEAR::isError($res)) {
                Error_Handler::logError(array($res->getMessage(), $res->getDebugInfo()), __FILE__, __LINE__);
                return -1;
            }
            
			Auth::setSession('auth_index_user_rule_groups', Auth::getUserAuthRuleGroups($usr_id));
            Auth::setSession('auth_index_highest_rule_group', AuthIndex::highestRuleGroup());
            Auth::setSession('auth_is_approver', Auth::isUserApprover($usr_id));
        }
        Auth::setSession('can_edit', null);
        Auth::setSession('can_create', null);
        return 1;
    }

    /**
     * Check caching of auth stuff to see if it needs to be invalidated.  If a new rule group has been set then 
     * it probably needs to be invalidated.
     */
    function checkRuleGroups()
    {
        global $auth_isBGP, $auth_bgp_session;

        if (!$auth_isBGP) {
            $ses = &Auth::getSession();
            if (AuthIndex::highestRuleGroup() > $ses['auth_index_highest_rule_group']) {
                //Error_Handler::logError(AuthIndex::highestRuleGroup()." > ".$ses['auth_index_highest_rule_group'],__FILE__,__LINE__);;
                Auth::setAuthRulesUsers();
            }
        }
    }

    /**
     * Get a reference to the session - not sure if you are running as background process or in apache so
     * it grabs a global var and treats it as a session otherwise.
     * NOTE:  There seems to be a bug that means that the session is not updated if you just set a key in the
     * reference to the $_SESSION returned from the function.  So use Auth::setSession to make it do the right thing. 
     */
    function getSession()
    {
        global $auth_isBGP, $auth_bgp_session;

        if ($auth_isBGP) {
            $ses =& $auth_bgp_session;
        } else {
            session_name(APP_SESSION);
            @session_start();
            $ses =& $_SESSION;
        }
        return $ses;
    }
    
    /**
     * Determines if we are background process and sets the right SESSION global for the occasion.
     */
    function setSession($key, $value)
    {
        global $auth_isBGP, $auth_bgp_session;

        if ($auth_isBGP) {
            $auth_bgp_session[$key] = $value;
        } else {
            session_name(APP_SESSION);
            @session_start();
            $_SESSION[$key] = $value;
        }
    }
    
}

// benchmarking the included file (aka setup time)
if (defined('APP_BENCHMARK') && APP_BENCHMARK) {
    $GLOBALS['bench']->setMarker('Included Auth Class');
}
?>
