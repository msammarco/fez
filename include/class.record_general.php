<?php

/**
 * class RecordGeneral
 * For general record stuff - shared by collections and communities as well as records.
 */
class RecordGeneral
{
	var $pid;
	var $xdis_id;
	var $no_xdis_id = false;  // true if we couldn't find the xdis_id
	var $viewer_roles;
	var $lister_roles;
	var $editor_roles;
	var $creator_roles;
	var $deleter_roles;
	var $approver_roles;
	var $checked_auth = false;
	var $auth_groups;
	var $display;
	var $details;
	var $record_parents;
	var $status_array = array(
	Record::status_undefined => 'Undefined',
	Record::status_unpublished => 'Unpublished',
	Record::status_published => 'Published'
	);
	var $title;

	/**
	 * RecordGeneral
	 * If instantiated with a pid, then this object is linked with the record with the pid, otherwise we are inserting
	 * a record.
	 *
	 * @access  public
	 * @param   string $pid The persistant identifier of the object
	 * @param   string $createdDT (optional) Fedora timestamp of version to retrieve
	 * @return  void
	 */
	function RecordGeneral($pid=null, $createdDT=null)
	{
		$this->pid = $pid;
		$this->createdDT = $createdDT;
		$this->lister_roles = explode(',',APP_LISTER_ROLES);
		$this->viewer_roles = explode(',',APP_VIEWER_ROLES);
		$this->editor_roles = explode(',',APP_EDITOR_ROLES);
		$this->creator_roles = explode(',',APP_CREATOR_ROLES);
		$this->deleter_roles = explode(',',APP_DELETER_ROLES);
		$this->approver_roles = explode(',',APP_APPROVER_ROLES);
		$this->versionsViewer_roles = explode(',',APP_VIEW_VERSIONS_ROLES);
		//        $this->versionsReverter_roles = explode(',',APP_REVERT_VERSIONS_ROLES);
	}

	function getPid()
	{
		return $this->pid;
	}

	/**
	 * refresh
	 * Reset the status of the record object so that all values will be re-queried from the database.
	 * Call this function if the database is expected to have changed in relation to this record.
	 *
	 * @access  public
	 * @return  void
	 */
	function refresh()
	{
		$this->checked_auth = false;
	}

	/**
	 * getXmlDisplayId
	 * Retrieve the display id for this record
	 *
	 * @access  public
	 * @return  void
	 */
	function getXmlDisplayId($getFromXML = false) {
		if (!$this->no_xdis_id) {
			if (empty($this->xdis_id) || ($getFromXML === true)) {
				if (!$this->checkExists()) {
					Error_Handler::logError("Record ".$this->pid." doesn't exist",__FILE__,__LINE__);
					return null;
				}
				if ($getFromXML === true) {
					$xdis_array = Fedora_API::callGetDatastreamContentsField($this->pid, 'FezMD', array('xdis_id'), $this->createdDT);
					if (isset($xdis_array['xdis_id'][0])) {
						$xdis_id = $xdis_array['xdis_id'][0];
					} else {
						$this->no_xdis_id = true;
						return null;
					}
				} else {
					$xdis_id = XSD_HTML_Match::getDisplayType($this->pid);
				}
				if (isset($xdis_id)) {
					$this->xdis_id = $xdis_id;
				} else {
					$this->no_xdis_id = true;
					return null;
				}


			}
			return $this->xdis_id;
		}
		return null;
	}

	function getXmlDisplayIdUseIndex()
	{
		$log = FezLog::get();
		$db = DB_API::get();

		$dbtp = APP_TABLE_PREFIX;
		if (!$this->no_xdis_id) {
			if (empty($this->xdis_id)) {
				$stmt = "SELECT rek_display_type FROM ".$dbtp."record_search_key
						WHERE rek_pid = ".$db->quote($this->pid);
				try {
					$res = $db->fetchOne($stmt);
					$this->xdis_id = $res;
				}
				catch(Exception $ex) {
					$log->err(array('Message' => $ex->getMessage(), 'File' => __FILE__, 'Line' => __LINE__));
					$this->xdis_id = null;
					$this->no_xdis_id = true;
				}
			}
			return $this->xdis_id;
		}
		return null;
	}

	/**
	 * getImageFezACML
	 * Retrieve the FezACML image details eg copyright message and watermark boolean settings
	 *
	 * @access  public
	 * @return  void
	 */
	function getImageFezACML($dsID)
	{
		if (!empty($dsID)) {
			$xdis_array = Fedora_API::callGetDatastreamContentsField($this->pid, 'FezACML'.$dsID.'.xml', array('image_copyright', 'image_watermark'), $this->createdDT);
			if (isset($xdis_array['image_copyright'][0])) {
				$this->image_copyright[$dsID] = $xdis_array['image_copyright'][0];
			}
			if (isset($xdis_array['image_watermark'][0])) {
				$this->image_watermark[$dsID] = $xdis_array['image_watermark'][0];
			}
		}
	}

	/**
	 * getAuth
	 * Retrieve the authroisation groups allowed for this record with the current user.
	 *
	 * @access  public
	 * @return  void
	 */
	function getAuth()
	{
		if (!$this->checked_auth) {
			$this->getXmlDisplayId();
			$this->auth_groups = Auth::getAuthorisationGroups($this->pid);
			$this->checked_auth = true;
		}

		return $this->auth_groups;
	}

	/**
	 * checkAuth
	 * Find out if the current user can perform the given roles for this record
	 *
	 * @param  array $roles The allowed roles to access the object
	 * @param  $redirect
	 * @access  public
	 * @return  void
	 */
	function checkAuth($roles, $redirect=true)
	{
		$this->getAuth();
		$ret_url = $_SERVER['REQUEST_URI'];
		/*	        $ret_url = $_SERVER['PHP_SELF'];
		 if (!empty($_SERVER['QUERY_STRING'])) {
		 $ret_url .= "?".$_SERVER['QUERY_STRING'];
		 } */
		return Auth::checkAuthorisation($this->pid, "", $roles, $ret_url, $this->auth_groups, $redirect);
	}

	/**
	 * canView
	 * Find out if the current user can view this record
	 *
	 * @access  public
	 * @param  $redirect
	 * @return  void
	 */
	function canView($redirect=true)
	{
		if (Auth::isAdministrator()) { return true; }
		if ($this->getPublishedStatus() == 2) {
			return $this->checkAuth($this->viewer_roles, $redirect);
		} else {
			return $this->canCreate($redirect); //changed this so that creators can view the objects even when they are not published
			//            return $this->canEdit($redirect);
		}
	}

	/**
	 * canList
	 * Find out if the current user can list this record
	 *
	 * @access  public
	 * @param  $redirect
	 * @return  void
	 */
	function canList($redirect=true)
	{
		if (Auth::isAdministrator()) { return true; }
		if ($this->getPublishedStatus() == 2) {
			return $this->checkAuth($this->lister_roles, $redirect);
		} else {
			return $this->canCreate($redirect); //changed this so that creators can view the objects even when they are not published
			//            return $this->canEdit($redirect);
		}
	}

	/**
	 * canEdit
	 * Find out if the current user can edit this record
	 *
	 * @access  public
	 * @param  $redirect
	 * @return  void
	 */
	function canEdit($redirect=false)
	{
		if (Auth::isAdministrator()) { return true; }
		return $this->checkAuth($this->editor_roles, $redirect);
	}


	/**
	 * canDelete
	 * Find out if the current user can edit this record
	 *
	 * @access  public
	 * @param  $redirect
	 * @return  void
	 */
	function canDelete($redirect=false)
	{
		if (Auth::isAdministrator()) { return true; }
		return $this->checkAuth($this->deleter_roles, $redirect);
	}

	/**
	 * canApprove
	 * Find out if the current user can publish this record
	 *
	 * @access  public
	 * @param  $redirect
	 * @return  void
	 */
	function canApprove($redirect=false)
	{
		if (Auth::isAdministrator()) { return true; }
		return $this->checkAuth($this->approver_roles, $redirect);
	}

	/**
	 * canCreate
	 * Find out if the current user can create this record
	 *
	 * @access  public
	 * @param  $redirect
	 * @return  void
	 */
	function canCreate($redirect=false)
	{
		return $this->checkAuth($this->creator_roles, $redirect);
	}

	/**
	 * canViewVersions
	 * Find out if the current user can view versions of this record
	 *
	 * @access  public
	 * @param  $redirect
	 * @return  void
	 */
	function canViewVersions($redirect=false)
	{
		if(APP_VERSION_UPLOADS_AND_LINKS != "ON") return false;
		return $this->checkAuth($this->versionsViewer_roles, $redirect);
	}

	/**
	 * canRevertVersions
	 * Find out if the current user can revert this record to an earlier version
	 *
	 * @access  public
	 * @param  $redirect
	 * @return  void
	 */
	//    function canRevertVersions($redirect=false) {
	//		  if(APP_VERSION_UPLOADS_AND_LINKS != "ON") return false;
	//        return $this->checkAuth($this->versionsReverter_roles, $redirect);
	//    }

	function getPublishedStatus($astext = false)
	{

		$this->getDisplay();
		$this->display->getXSD_HTML_Match();
		$this->getDetails();
		//$xsdmf_id = XSD_HTML_Match::getXSDMF_IDByElement("!sta_id", $this->xdis_id);
		$xsdmf_id = $this->display->xsd_html_match->getXSDMF_IDByXDIS_ID('!sta_id');
		$status = $this->details[$xsdmf_id];

		if (!$astext) {
			return $status;
		} else {
			return $this->status_array[$status];
		}
	}

	function getRecordType()
	{
		$this->getDisplay();
		$this->getDetails();
		$this->display->getXSD_HTML_Match();

		//$this->getXmlDisplayId();
		if (!empty($this->xdis_id)) {
			//$xsdmf_id = XSD_HTML_Match::getXSDMF_IDByElement("!ret_id", $this->xdis_id);
			//echo $xsdmf_id;
			$xsdmf_id = $this->display->xsd_html_match->getXSDMF_IDByXDIS_ID('!ret_id');
			$ret_id = $this->details[$xsdmf_id];
			return $ret_id;
		} else {
			return null;
		}
	}


	/**
	 * setStatusID
	 * Used to assocaiate a display for this record
	 *
	 * @access  public
	 * @param  integer $sta_id The new Status ID of the object
	 * @return  void
	 */
	function setStatusId($sta_id)
	{
		$this->setFezMD_Datastream('sta_id', $sta_id);
		$this->getDisplay();
		//        $this->display->getXSD_HTML_Match();
		/*        $xsdmf_id = $this->display->xsd_html_match->getXSDMF_IDByXDIS_ID('!sta_id');
		Record::removeIndexRecordByXSDMF_ID($this->pid, $xsdmf_id);
		Record::insertIndexMatchingField($this->pid, '', $xsdmf_id, $sta_id); */
		$this->setIndexMatchingFields();
		return 1;
	}

	/**
	 * setFezMD_Datastream
	 * Used to associate a display for this record
	 *
	 * @access  public
	 * @param  $key
	 * @param  $value
	 * @return  void
	 */
	function setFezMD_Datastream($key, $value)
	{
		$items = Fedora_API::callGetDatastreamContents($this->pid, 'FezMD');
		$newXML = '<FezMD xmlns:xsi="http://www.w3.org/2001/XMLSchema">';
		$foundElement = false;
		foreach ($items as $xkey => $xdata) {
			foreach ($xdata as $xinstance) {
				if ($xkey == $key) {
					$foundElement = true;
					$newXML .= "<".$xkey.">".$value."</".$xkey.">";
				} elseif ($xinstance != "") {
					$newXML .= "<".$xkey.">".$xinstance."</".$xkey.">";
				}
			}
		}
		if ($foundElement != true) {
			$newXML .= "<".$key.">".$value."</".$key.">";
		}
		$newXML .= "</FezMD>";
		//Error_handler::logError($newXML,__FILE__,__LINE__);
		if ($newXML != "") {
			Fedora_API::callModifyDatastreamByValue($this->pid, "FezMD", "A", "Fez extension metadata", $newXML, "text/xml", "inherit");
		}
	}

	/**
	 * _Datastream
	 * Used to associate a display for this record
	 *
	 * @access  public
	 * @param  $key
	 * @param  $value
	 * @return  void
	 */
	function updateRELSEXT($key, $value, $removeCurrent = true)
	{
		$newXML = "";
		$xmlString = Fedora_API::callGetDatastreamContents($this->pid, 'RELS-EXT', true);

		if(empty($xmlString) || !is_string($xmlString)) {
			return -3;
		}

		$doc = DOMDocument::loadXML($xmlString);
		$xpath = new DOMXPath($doc);
		$fieldNodeList = $xpath->query("/rdf:RDF//rel:isMemberOf");

		if($fieldNodeList->length == 0) {

			/*
			 * There was a point in time when incorrect RELS-EXT xml
			 * was created, with an incorrect namespace 'rdf:isMemberOf'
			 * instead of 'rel:isMemberOf'.
			 */
			$fieldNodeList = $xpath->query("/rdf:RDF//rdf:isMemberOf");
			if($fieldNodeList->length == 0) {
				return -2;
			}
		}


		foreach ($fieldNodeList as $fieldNode) { // first delete all the isMemberOfs
			$parentNode = $fieldNode->parentNode;
			if ( $removeCurrent ) {
				$parentNode->removeChild($fieldNode);
			}
		}
		$newNode = $doc->createElementNS('info:fedora/fedora-system:def/relations-external#', 'rel:isMemberOf');
		$newNode->setAttribute('rdf:resource', 'info:fedora/'.$value);
		$parentNode->appendChild($newNode);
		$newXML = $doc->SaveXML();
		if ($newXML != "") {
			Fedora_API::callModifyDatastreamByValue($this->pid, "RELS-EXT", "A", "Relationships to other objects", $newXML, "text/xml", "inherit");
			Record::setIndexMatchingFields($this->pid);
			return 1;
		}

		return -1;
	}

	function addSearchKeyValueList($datastreamName, $datastreamDesc, $search_keys=array(), $values=array(), $removeCurrent=true) {

		$xmlString = Fedora_API::callGetDatastreamContents($this->pid, $datastreamName, true);
		if (is_array($xmlString) || $xmlString == "") {
			echo "\n**** PID ".$this->pid." without a ".$datastreamName." datastream was found, this will need content model changing first **** \n";
			return -1;
		}
		$doc = DOMDocument::loadXML($xmlString);

		$search_keys_added = array();
		foreach($search_keys as $s => $sk) {
			$tempdoc = $this->addSearchKeyValue($doc, $sk, $values[$s], $removeCurrent);
			if ($tempdoc !== false) {
				if (!empty($values[$s])) {
					$search_keys_added[$sk] = $values[$s];
				}
				$doc = $tempdoc;
			}
		}
		echo "\nnewXML = \n";


		$newXML = $doc->SaveXML();
		echo $newXML;
		if ((count($search_keys_added) > 0) && ($newXML != "")) {
			/*	        $this->getDisplay();
	 		$display->getXSD_HTML_Match();
	 		$datastreamTitles = $display->getDatastreamTitles(); */
			//Need to make this not just for MODS at some stage
			Fedora_API::callModifyDatastreamByValue($this->pid, $datastreamName, "A", $datastreamDesc, $newXML, "text/xml", "inherit");
			$historyDetail = "";
			foreach ($search_keys_added as $hkey => $hval) {
				if ($historyDetail != "") {
					$historyDetail .= ", ";
				}
				$historyDetail .= $hkey.": ".$hval;
			}
			$historyDetail .= " was added based on Links AMR Service data";
			echo 'PID: ' . $this->pid . ' - ' . $historyDetail."\n";
			History::addHistory($this->pid, null, "", "", true, $historyDetail);
			$this->setIndexMatchingFields();
			return 1;
		}
		return -1;

	}


// Experimental function - like a swiss army knife for adding abitrary values to datastreams
    function addSearchKeyValue($doc, $sek_title, $value, $removeCurrent = true) {
		$newXML = "";
		$xdis_id = $this->getXmlDisplayId();
		$xpath_query = XSD_HTML_Match::getXPATHBySearchKeyTitleXDIS_ID($sek_title, $xdis_id);

                if (empty($value)) {
                  return false;
                } 

		if (!$xpath_query) {
			echo "\n**** PID ".$this->pid." has no search key ".$sek_title." so it will need content model changing first **** \n";
			return false;
		}
		
		$xpath = new DOMXPath($doc);
		$fieldNodeList = $xpath->query($xpath_query);
        $element = substr($xpath_query, (strrpos($xpath_query, "/") + 1));
		$pre_element = substr($xpath_query, 0, (strrpos($xpath_query, "/")));
		$attributeStartPos = strpos($element, "[");
		$attributeEndPos = strpos($element, "]") + 1;
		$attribute = "";
		if (is_numeric($attributeStartPos) && is_numeric($attributeEndPos)) {
			$attribute = substr($element, $attributeStartPos, ($attributeEndPos - $attributeStartPos));
			$element = substr($element, 0, $attributeStartPos);
		}
		$attributeNameStartPos = strpos($attribute, "[@") + 2;
		$attributeNameEndPos = strpos($attribute, " =");
		$attributeValueStartPos = strpos($attribute, "= ") + 2;
		$attributeValueEndPos = strpos($attribute, "]");
		$attributeName = substr($attribute, $attributeNameStartPos, ($attributeNameEndPos - $attributeNameStartPos));
		$attributeValue = substr($attribute, $attributeValueStartPos, ($attributeValueEndPos - $attributeValueStartPos));
		$attributeValue = str_replace("'", "", $attributeValue);

		if ( $removeCurrent ) {		
			foreach ($fieldNodeList as $fieldNode) { // first delete all the isMemberOfs
				$parentNode = $fieldNode->parentNode;
                $parentNode->removeChild($fieldNode);
			}
		}
		// If no existing one is found, add to the parent (will error currently if the xpath parent doesn't exist either, but thats unusual)
		if (is_null($parentNode)) {
			$parentNodeList = $xpath->query($pre_element);
			foreach ($parentNodeList as $fieldNode) {
				$parentNode = $fieldNode;
			}
		}

		$newNode = $doc->createElement($element, $value);
		$newNode->setAttribute($attributeName, $attributeValue);
	
		$parentNode->appendChild($newNode); 
		return $doc;
    }




	/**
	 * Remove record from collection
	 *
	 * @param string $collection  the pid of the collection
	 *
	 * @return bool  TRUE if removed OK. FALSE if not removed.
	 *
	 * @access public
	 * @since Method available since RC1
	 */
	function removeFromCollection($collection)
	{
		if( $collection == "" ) {
			return false;
		}

		$newXML = "";
		$xmlString = Fedora_API::callGetDatastreamContents($this->pid, 'RELS-EXT', true);

		$doc = DOMDocument::loadXML($xmlString);
		$xpath = new DOMXPath($doc);

		$fieldNodeList = $xpath->query("//rel:isMemberOf[@rdf:resource='info:fedora/$collection']");

		if( $fieldNodeList->length == 0 ) {
			return false;
		}

		$collectionNode   = $fieldNodeList->item(0);
		$parentNode       = $collectionNode->parentNode;
		$parentNode->removeChild($collectionNode);

		$newXML = $doc->SaveXML();
		if ($newXML != "") {
			Fedora_API::callModifyDatastreamByValue($this->pid, "RELS-EXT", "A", "Relationships to other objects", $newXML, "text/xml", "inherit");
			Record::setIndexMatchingFields($this->pid);
			if( APP_SOLR_INDEXER == "ON" ) {
				FulltextQueue::singleton()->add($this->pid);
			}
			return true;
		}

		return false;
	}


	/**
	 * updateFezMD_User
	 * Used to assign this record to a user
	 *
	 * @access  public
	 * @param  $key
	 * @param  $value
	 * @return  void
	 */
	function updateFezMD_User($key, $value)
	{
		$newXML = "";
		$xmlString = Fedora_API::callGetDatastreamContents($this->pid, 'FezMD', true);
		$doc = DOMDocument::loadXML($xmlString);
		$xpath = new DOMXPath($doc);
		$fieldNodeList = $xpath->query("//usr_id");
		if ($fieldNodeList->length > 0) {
			foreach ($fieldNodeList as $fieldNode) { // first delete all the existing user associations
				$parentNode = $fieldNode->parentNode;
				$parentNode->removeChild($fieldNode);
			}
		} else {
			$parentNode = $doc->lastChild;
		}
		$newNode = $doc->createElement('usr_id');
		$newNode->nodeValue = $value;
		$parentNode->insertBefore($newNode);
		$newXML = $doc->SaveXML();
		if ($newXML != "") {
			Fedora_API::callModifyDatastreamByValue($this->pid, "FezMD", "A", "Fez Admin Metadata", $newXML, "text/xml", "inherit");
			Record::setIndexMatchingFields($this->pid);
		}
	}

	/**
	 * assignGroupFezMD
	 * Used to assign this record to a group
	 *
	 * @access  public
	 * @param  $key
	 * @param  $value
	 * @return  void
	 */
	function updateFezMD_Group($key, $value)
	{

		$newXML = "";
		$xmlString = Fedora_API::callGetDatastreamContents($this->pid, 'FezMD', true);
		$doc = DOMDocument::loadXML($xmlString);
		$xpath = new DOMXPath($doc);
		$fieldNodeList = $xpath->query("//grp_id");
		if ($fieldNodeList->length > 0) {
			foreach ($fieldNodeList as $fieldNode) { // first delete all the existing group associations
				$parentNode = $fieldNode->parentNode;
				Error_Handler::logError($fieldNode->nodeName.$fieldNode->nodeValue,__FILE__,__LINE__);
				$parentNode->removeChild($fieldNode);
			}
		} else {
			$parentNode = $doc->lastChild;
		}
		$newNode = $doc->createElement('grp_id');
		$newNode->nodeValue = $value;
		$parentNode->insertBefore($newNode);
		//		Error_Handler::logError($doc->SaveXML(),__FILE__,__LINE__);
		$newXML = $doc->SaveXML();
		if ($newXML != "") {
			Fedora_API::callModifyDatastreamByValue($this->pid, "FezMD", "A", "Fez Admin Metadata", $newXML, "text/xml", "inherit");
			Record::setIndexMatchingFields($this->pid);
		}
	}

	/**
	 * Function can update a single xsdmf in the XML but doesn't work for sublooping elements.
	 * @param integer $xsdmf_id the mapping to update
	 * @param string $value what to set the element to
	 * @param integer $idx the index of the item if this is a multiple item
	 * @return boolean true on success, false on failure.
	 */
	function setValue($xsdmf_id, $value, $idx)
	{
		$this->getDisplay();
		$this->display->getXSD_HTML_Match();
		$cols = $this->display->xsd_html_match->getDetailsByXSDMF_ID($xsdmf_id);
		// which datastream to get XML for?
		// first find the xdis id that the xsdmf_id matches in (not the base xdis_id since this will be in a
		// refered display)
		$xdis_id = $cols['xsdmf_xdis_id'];
		$xsd_id = XSD_Display::getParentXSDID($xdis_id);
		$xsd_details = Doc_Type_XSD::getDetails($xsd_id);
		$dsID = $xsd_details['xsd_title'];
		if ($dsID == 'OAI DC') {
			$dsID = 'DC';
		}
		//Error_Handler::logError($dsID,__FILE__,__LINE__);
		$xsdmf_element = $cols['xsdmf_element'];
		$steps = explode('!',$xsdmf_element);
		// get rid of blank on the front
		array_shift($steps);
		$doc = DOMDocument::loadXML($xsd_details['xsd_file']);
		$xsd_array = array();
		Misc::dom_xsd_to_referenced_array($doc, $xsd_details['xsd_top_element_name'], $xsd_array,"","",$doc);
		$sXml = Fedora_API::callGetDatastreamContents($this->pid, $dsID, true);
		if (!empty($sXml) && $sXml != false) {
			$doc = DOMDocument::loadXML($sXml);
			// it would be good if we could just do a xpath query here but unfortunately, the xsdmf_element
			// is missing information like namespaces and attribute '@' thing.
			if ($this->setValueRecurse($value, $doc->documentElement, $steps,
			$xsd_array[$xsd_details['xsd_top_element_name']], $idx)) {
				Fedora_API::callModifyDatastreamByValue($this->pid, $dsID, "A", "setValue", $doc->saveXML(), "text/xml", "inherit");
				Record::setIndexMatchingFields($this->pid);
				return true;
			}
		} else {
			return false;
		}
	}

	function setValueRecurse($value, $node, $remaining_steps, $xsd_array, $vidx, $current_idx=0)
	{
		$next_step = array_shift($remaining_steps);
		$next_xsd_array = $xsd_array[$next_step];
		$theNode = null;
		if (isset($next_xsd_array['fez_nodetype']) && $next_xsd_array['fez_nodetype'] == 'attribute') {
			$node->setAttribute($next_step, $value);
			return true;
		} else {
			$use_idx = false;  // should we look the element that matches vidx?  Only if this is the end of the path
			$att_step = $remaining_steps[0];
			$att_xsd = $next_xsd_array[$att_step];
			if (isset($att_xsd['fez_nodetype']) && $att_xsd['fez_nodetype'] == 'attribute') {
				$use_idx = true;
			}
			if (count($remaining_steps) == 0) {
				$use_idx = true;
			}
			$idx = 0;
			foreach ($node->childNodes as $childNode) {
				// remove namespace
				$next_step_name = $next_step;
				if (!strstr($next_step_name, '!dc:')) {
					$next_step_name = preg_replace('/![^:]+:/', '!', $next_step_name);
				}
				if ($childNode->nodeName == $next_step_name) {
					if ($use_idx) {
						if ($idx == $vidx) {
							$theNode = $childNode;
							break;
						}
						$idx++;
					} else {
						$theNode = $childNode;
						break;
					}
				}
			}
		}
		if (is_null($theNode)) {
			$theNode = $node->ownerDocument->createElement($next_step);
			$node->appendChild($theNode);
		}
		if (count($remaining_steps)) {
			if ($this->setValueRecurse($value, $theNode, $remaining_steps, $next_xsd_array, $vidx, $idx)) {
				return true;
			}
		} else {
			if (!empty($value)) {
				$theNode->nodeValue = $value;
			} else {
				$theNode->parentNode->removeChild($theNode);
			}
			return true;
		}
		return false;
	}

	/**
	 * getDisplay
	 * Get a display object for this record
	 *
	 * @access  public
	 * @return  array $this->details The display of the object, or null
	 */
	function getDisplay()
	{
		$log = FezLog::get();
		$db = DB_API::get();

		$this->getXmlDisplayId();
		if (!empty($this->xdis_id)) {
			if (is_null($this->display)) {
				$this->display = new XSD_DisplayObject($this->xdis_id);
				$this->display->getXSD_HTML_Match();
			}
			return $this->display;
		} else {
			// if it has no xdis id (display id) log an error and return a null
			$log->err(array("The PID ".$this->pid." does not have an display id (FezMD->xdis_id). This object is currently in an erroneous state.",__FILE__,__LINE__));
			return null;
		}
	}

	function getDocumentType()
	{
		$this->getDisplay();
		return $this->display->getTitle();
	}

	/**
	 * getDetails
	 * Users a more object oriented approach with the goal of storing query results so that we don't need to make
	 * so many queries to view a record.
	 *
	 * @access  public
	 * @return  array $this->details The details of the object
	 */
	function getDetails($dsID = "", $xdis_id = "")
	{
		$log = FezLog::get();
		$db = DB_API::get();

		if (is_null($this->details) || $dsID != "") {
			// Get the Datastreams.
			if ($xdis_id == "") {
				$this->getDisplay();
			} else {
				$this->display = new XSD_DisplayObject($xdis_id);
				$this->display->getXSD_HTML_Match();
			}
			if ($this->display) {
				if ($dsID != "") {
					$this->details = $this->display->getXSDMF_Values_Datastream($this->pid, $dsID, $this->createdDT);
				} else {
					$this->details = $this->display->getXSDMF_Values($this->pid, $this->createdDT);
				}
			} else {
				$log->err(array("The PID ".$this->pid." has an error getting it's display details. This object is currently in an erroneous state.",__FILE__,__LINE__));
			}
		}

		return $this->details;
	}


	/**
	 * Clear the cached details in this record.  Used when the record has been altered to force
	 * details to be reparsed from the fedora object.
	 */
	function clearDetails()
	{
		$this->details = null;
	}

	/**
	 * getFieldValueBySearchKey
	 * Get the value or values of a metadata field that matches a given search key
	 *
	 * @access  public
	 * @param $sek_title string - The name of the search key to get the field value for, e.g. 'Title'
	 * @return  array $this->details[$xsdmf_id] The Dublin Core title of the object
	 */
	function getFieldValueBySearchKey($sek_title)
	{
		$log = FezLog::get();
		$db = DB_API::get();

		$this->getDetails();

		if (!empty($this->xdis_id)) {
			$sek_id = Search_Key::getID($sek_title);
			if (!$sek_id) {
				return null;
			}
			$res = array();

			foreach ($this->display->xsd_html_match->getMatchCols() as $xsdmf ) {
				if ($xsdmf['xsdmf_sek_id'] == $sek_id) {
					$res[] = $this->details[$xsdmf['xsdmf_id']];
				}
			}
			return $res;
		} else {
			// if it has no xdis id (display id) log an error and return a null
			$log->err(array("The PID ".$this->pid." does not have an display id (FezMD->xdis_id). This object is currently in an erroneous state.",__FILE__,__LINE__));
			return null;
		}
	}

	/**
	 * getTitle
	 * Get the dc:title for the record
	 *
	 * @access  public
	 * @return  array $this->details[$xsdmf_id] The Dublin Core title of the object
	 */
	function getTitle()
	{
		$log = FezLog::get();
		$db = DB_API::get();

		$this->title = Record::getTitleFromIndex($this->pid);
		if (empty($this->title)) {
			$log->debug('Title is empty');
			$this->getDetails();
			$this->getXmlDisplayId();
			if (!empty($this->xdis_id)) {
				$xsdmf_id = $this->display->xsd_html_match->getXSDMF_IDByXDIS_ID("!dc:title");
				//$xsdmf_id = $this->display->xsd_html_match->getXSDMF_IDByXDIS_ID('!dc:title');
				$this->title = $this->details[$xsdmf_id];
			} else {
				// if it has no xdis id (display id) log an error and return a null
				$log->err(array("Fez cannot display PID " . $this->pid .
        	    	" because it does not have a display id (FezMD/xdis_id). ",__FILE__,__LINE__));
				return null;
			}
		}		
		return $this->title;
	}

	/**
	 * getDCType
	 * Get the dc:type for the record
	 *
	 * @access  public
	 * @return  array $this->details[$xsdmf_id] The Dublin Core type of the object
	 */
	function getDCType()
	{
		$log = FezLog::get();
		$db = DB_API::get();

		$this->getDetails();
		$this->getXmlDisplayId();
		if (!empty($this->xdis_id)) {
			$xsdmf_id = $this->display->xsd_html_match->getXSDMF_IDByXDIS_ID('!dc:type');
		} else {
			// if it has no xdis id (display id) log an error and return a null
			$log->err(array("The PID ".$this->pid." does not have an display id (FezMD->xdis_id). This object is currently in an erroneous state.",__FILE__,__LINE__));
			return null;
		}
		return $this->details[$xsdmf_id];
	}

	function getXSDMF_ID_ByElement($xsdmf_element)
	{
		$this->getDisplay();
		$this->display->getXSD_HTML_Match();
		return $this->display->xsd_html_match->getXSDMF_IDByXDIS_ID($xsdmf_element);
	}

	/**
	 * getDetailsByXSDMF_element
	 *
	 * Returns the value of an element in a datastream addressed by element
	 *
	 * @param string $xsdmf_element - The path to the XML element in a datastream.
	 *      Use XSD_HTML_Match::escapeXPath to convert an xpath - /oai_dc:dc/dc:title to an xsdmf_element string !dc:title
	 * @param string $xsdmf_title - option field to use when xsdmf_element is ambiguous
	 * @returns mixed - Array of values or single value for each element match in XML tree
	 */
	function getDetailsByXSDMF_element($xsdmf_element, $xsdmf_title="")
	{
		$log = FezLog::get();
		$db = DB_API::get();

		$this->getDetails();

		$this->getXmlDisplayId();
		if (!empty($this->xdis_id)) {
			$xsdmf_id = $this->display->xsd_html_match->getXSDMF_IDByXDIS_ID($xsdmf_element, $xsdmf_title);
			return @$this->details[$xsdmf_id];
		} else {
			// if it has no xdis id (display id) log an error and return a null
			$log->err(array("The PID ".$this->pid." does not have an display id (FezMD->xdis_id). This object is currently in an erroneous state.",__FILE__,__LINE__));
			return null;
		}
	}

	function getDetailsByXSDMF_ID($xsdmf_id)
	{
		$this->getDetails();
		return @$this->details[$xsdmf_id];
	}

	/**
	 * getXSDMFDetailsByElement
	 *
	 * Returns XSDMF values to describe how the element should be treated in a HTML form or display
	 *
	 * @param string $xsdmf_element - The path to the XML element in a datastream.
	 *      Use XSD_HTML_Match::escapeXPath to convert an xpath - /oai_dc:dc/dc:title to an xsdmf_element string !dc:title
	 * @returns array - Keypairs from the XSDMF table for the element on this record and record type to
	 *      describe how the element should be treated in a HTML form or display.
	 */
	function getXSDMFDetailsByElement($xsdmf_element)
	{
		$this->getDisplay();
		$this->display->getXSD_HTML_Match();
		return $this->display->xsd_html_match->getDetailsByElement($xsdmf_element);
	}

	/**
	 * isCollection
	 * Is the record a Collection
	 *
	 * @access  public
	 * @return  boolean
	 */
	function isCollection()
	{
		return ($this->getRecordType() == 2) ? true : false;
	}

	/**
	 * isCommunity
	 * Is the record a Community
	 *
	 * @access  public
	 * @return  boolean
	 */
	function isCommunity()
	{
		return ($this->getRecordType() == 1) ? true : false;
	}


	/**
	 * function getParents()
	 * getParents
	 * Get the parent pids of an object
	 *
	 * @access  public
	 * @return  array list of parents
	 */
	function getParents()
	{
		if (!$this->record_parents) {
			$this->record_parents = Record::getParents($this->pid);
		}
		return $this->record_parents;
	}

	function getWorkflowsByTrigger($trigger)
	{
		$this->getParents();
		$triggers = WorkflowTrigger::getListByTrigger($this->pid, $trigger);
		foreach ($this->record_parents as $ppid) {
			$triggers = array_merge($triggers, WorkflowTrigger::getListByTrigger($ppid, $trigger));
		}
		// get defaults
		$triggers = array_merge($triggers, WorkflowTrigger::getListByTrigger(-1, $trigger));
		return $triggers;
	}

	function getWorkflowsByTriggerAndRET_IDAndXDIS_ID($trigger, $ret_id, $xdis_id, $strict=false)
	{
		$this->getParents();
		$triggers = WorkflowTrigger::getListByTriggerAndRET_IDAndXDIS_ID($this->pid, $trigger, $ret_id, $xdis_id, $strict);
		foreach ($this->record_parents as $ppid) {
			$triggers = array_merge($triggers,
			WorkflowTrigger::getListByTriggerAndRET_IDAndXDIS_ID($ppid, $trigger, $ret_id, $xdis_id, $strict));
		}
		// get defaults
		$triggers = array_merge($triggers,
		WorkflowTrigger::getListByTriggerAndRET_IDAndXDIS_ID(-1, $trigger, $ret_id, $xdis_id, $strict));
		return $triggers;
	}


	function getWorkflowsByTriggerAndXDIS_ID($trigger, $xdis_id, $strict=false)
	{
		$this->getParents();
		$triggers = WorkflowTrigger::getListByTriggerAndXDIS_ID($this->pid, $trigger, $xdis_id, $strict);
		foreach ($this->record_parents as $ppid) {
			$triggers = array_merge($triggers,
			WorkflowTrigger::getListByTriggerAndXDIS_ID($ppid, $trigger, $xdis_id, $strict));
		}
		// get defaults
		$triggers = array_merge($triggers,
		WorkflowTrigger::getListByTriggerAndXDIS_ID(-1, $trigger, $xdis_id, $strict));
		return $triggers;
	}

	function getWorkflowsByTriggerAndRET_ID($trigger, $ret_id, $strict=false)
	{
		$this->getParents();
		$triggers = WorkflowTrigger::getListByTriggerAndRET_ID($this->pid, $trigger, $ret_id, $strict);
		foreach ($this->record_parents as $ppid) {
			$triggers = array_merge($triggers,
			WorkflowTrigger::getListByTriggerAndRET_ID($ppid, $trigger, $ret_id, $strict));
		}
		// get defaults
		$triggers = array_merge($triggers,
		WorkflowTrigger::getListByTriggerAndRET_ID(-1, $trigger, $ret_id, $strict));
		return $triggers;
	}

	function getFilteredWorkflows($options)
	{
		$this->getParents();
		$triggers = WorkflowTrigger::getFilteredList($this->pid, $options);
		foreach ($this->record_parents as $ppid) {
			$triggers = array_merge($triggers,
			WorkflowTrigger::getFilteredList($ppid, $options));
		}
		// get defaults
		$triggers = array_merge($triggers,
		WorkflowTrigger::getFilteredList(-1, $options));
		return $triggers;
	}


	function getChildrenPids($clearcache=false, $searchKey='isMemberOf')
	{
		$log = FezLog::get();
		$db = DB_API::get();

		$pid = $this->pid;
		$sek_title = Search_Key::makeSQLTableName($searchKey);
		$stmt = "SELECT ".APP_SQL_CACHE."
					m1.rek_".$sek_title."_pid
				 FROM
					" . APP_TABLE_PREFIX . "record_search_key_".$sek_title." m1
				 WHERE m1.rek_".$sek_title." = ".$db->quote($pid);
		try {
			$res = $db->fetchCol($stmt);
		}
		catch(Exception $ex) {
			$log->err(array('Message' => $ex->getMessage(), 'File' => __FILE__, 'Line' => __LINE__));
			return false;
		}

		return $res;
	}

	function export()
	{
		return Fedora_API::export($this->pid);
	}

	function getObjectXML()
	{
		return Fedora_API::getObjectXMLByPID($this->pid);
	}

	function getDatastreams($dsState='A')
	{
		return Fedora_API::callGetDatastreams($this->pid,null,$dsState);
	}
	function checkExists()
	{
		return Fedora_API::objectExists($this->pid);
	}
	function getDatastreamContents($dsID, $filehandle=null) {
		return Fedora_API::callGetDatastreamContents($this->pid, $dsID, false, $filehandle);
	}

	function setIndexMatchingFields()
	{
		// careful what you do with the record object - don't want to use the index while reindexing
		$pid = $this->pid;
		$xdis_id = $this->getXmlDisplayId();
		if (!is_numeric($xdis_id)) {
			$xdis_id = XSD_Display::getXDIS_IDByTitle('Generic Document');
		}
		$display = new XSD_DisplayObject($xdis_id);
		$xsdmf_array = $display->getXSDMF_Values($pid, null, true);

		$searchKeyData = array();

		foreach ($xsdmf_array as $xsdmf_id => $xsdmf_value) {
			$xsdmf_details = XSD_HTML_Match::getDetailsByXSDMF_ID($xsdmf_id);
			if ($xsdmf_details['xsdmf_sek_id'] != "") {
				//CK 2008/12/19 - commed this out and just ran removeIndexRecord($pid) below, just before we call updateSearchKeys as this was missing index rows where the xsdmf id had changed
				//        		Record::removeIndexRecordByXSDMF_ID($pid,$xsdmf_id);
				$sekDetails = Search_Key::getBasicDetails($xsdmf_details['xsdmf_sek_id']);

				if ($sekDetails['sek_data_type'] == 'int' && $sekDetails['sek_html_input'] == 'checkbox') {
					if ($xsdmf_value == 'on') {
						$xsdmf_value = 1;
					} else {
						$xsdmf_value = 0;
					}
				}

				if ($sekDetails['sek_data_type'] == 'date') {
					if(!empty($xsdmf_value)) {
						if (is_numeric($xsdmf_value) && strlen($xsdmf_value) == 4) {
							// It appears we've just been fed a year. We'll pad this,
							// so it can be added to the index.
							$xsdmf_value = $xsdmf_value . "-01-01 00:00:00";
						} elseif (strlen($xsdmf_value) == 7) {
							// YYYY-MM. We could arguably write some better string inspection stuff here,
							// but this will do for now.
							$xsdmf_value = $xsdmf_value . "-01 00:00:00";
						}
						// Looks like a regular fully-formed date.
						$xsdmf_value = strtotime($xsdmf_value);
						$date = new Date($xsdmf_value);
						$xsdmf_value = $date->format('%Y-%m-%d %T');
						if ($xsdmf_value == "0000-01-01 00:00:00" || $xsdmf_value == "0000-00-00 00:00:00" || $xsdmf_value == "0-01-01 00:00:00") {
							$xsdmf_value = "NULL";
						}
					} else {
						$xsdmf_value = "NULL";
					}
				}

				if(@empty($searchKeyData[$sekDetails['sek_relationship']][$sekDetails['sek_title_db']]['xsdmf_value'])) {

					$searchKeyData[$sekDetails['sek_relationship']][$sekDetails['sek_title_db']] = array(
			        		  "xsdmf_id"        => $xsdmf_id,
			        		  "xsdmf_value"     => $xsdmf_value,
					);

				}
			}
		}
		Record::removeIndexRecord($pid, false); //clean out the SQL index, but do not remove from Solr, the solr entry will get updated in updateSearchKeys
		Record::updateSearchKeys($pid, $searchKeyData);
	}

	/**
	 * copyToNewPID
	 * This makes a copy of the fedora object with the current PID to a new PID.  The getNextPID call on fedora is
	 * used to get the new PID. All datastreams are extracted from the original object and reingested to the new object.
	 * Premis history is not brought across, the first entry in the new premis history identifies the PID of the
	 * source object.   The $new_xdis_id specifies a change of content model.  If $new_xdis_id is null, then the
	 * xdis_id of the source object is used.  If $is_succession is true, the RELS-EXT will have a isSuccessor element
	 * pointing back to the sourec object.
	 * @param integer $new_xdis_id - optional new content model
	 * @param boolean $is_succession - optional link back to original
	 * @return string - the new PID for success, false for failure.  Calls Error_Handler::logError if there is a problem.
	 */
	function copyToNewPID($new_xdis_id = null, $is_succession = false, $clone_attached_datastreams=false, $collection_pid=null)
	{
		$log = FezLog::get();
		$db = DB_API::get();

		if (empty($this->pid)) {
			return false;
		}
		if (empty($new_xdis_id)) {
			$new_xdis_id = $this->getXmlDisplayIdUseIndex();
		}
		$pid = $this->pid;
		$new_pid = Fedora_API::getNextPID();
		// need to get hold of a copy of the fedora XML, and substitute the PIDs in it then ingest it.
		$xml_str = Fedora_API::getObjectXMLByPID($pid);
		$xml_str = str_replace($pid, $new_pid, $xml_str);  // change to new pid
		// strip off datastreams - we'll add them later.  This gets rid of the internal fedora audit datastream
		$doc = DOMDocument::loadXML($xml_str);
		$xpath = new DOMXPath($doc);
		$xpath->registerNamespace('foxml','info:fedora/fedora-system:def/foxml#');
		$xpath->registerNamespace('fedoraxsi','http://www.w3.org/2001/XMLSchema-instance');
		$xpath->registerNamespace('audit','info:fedora/fedora-system:def/audit#');
		$nodes = $xpath->query('/foxml:digitalObject/foxml:datastream');
		foreach ($nodes as $node) {
			$node->parentNode->removeChild($node);
		}
		$new_xml = $doc->saveXML();
		Fedora_API::callIngestObject($new_xml);

		$datastreams = Fedora_API::callGetDatastreams($pid); // need the full get datastreams to get the controlGroup etc
		if (empty($datastreams)) {
			$log->err(array("The PID ".$pid." doesn't appear to be in the fedora repository - perhaps it was not ingested correctly.  " .
                    "Please let the Fez admin know so that the Fez index can be repaired.",__FILE__,__LINE__));
			return false;
		}

		// exclude these prefixes if we're not cloning the binaries
		$exclude_prefix = array('presmd','thumbnail','web','preview', 'stream');

		foreach ($datastreams as $ds_key => $ds_value) {
			if (!$clone_attached_datastreams) {
				// don't process derived datastreams if we're not copying the binaries
				if (in_array(substr($ds_value['ID'],0,strpos($ds_value['ID'],'_')), $exclude_prefix)) {
					continue;
				}
			}
			switch ($ds_value['ID']) {
				case 'DC':
					$value = Fedora_API::callGetDatastreamContents($pid, $ds_value['ID'], true);
					Fedora_API::callModifyDatastreamByValue($new_pid, $ds_value['ID'], $ds_value['state'],
					$ds_value['label'], $value, $ds_value['MIMEType'], $ds_value['versionable']);
					//					if (!array_key_exists("MODS", $datastreams)) {
					if (!Misc::in_multi_array("MODS", $datastreams)) {
						// transform the DC into a MODS datastream and attach it
						$dc_to_mods_xsl = APP_INC_PATH . "xslt/dc_to_mods.xsl";
						$xsl_dom = DOMDocument::load($dc_to_mods_xsl);
						$dc_dom = DOMDocument::loadXML($value);
						// transform the DC to MODS with the XSLT
						$proc = new XSLTProcessor();
						$proc->importStyleSheet($xsl_dom);
						$transformResult = $proc->transformToXML($dc_dom);
						Fedora_API::getUploadLocation($new_pid, "MODS", $transformResult, "Metadata Object Description Schema", "text/xml", "X", "MODS", 'true');
					}
					break;
				case 'BookMD':
					break;

				case 'FezMD':
					// let's fix up a few things in FezMD
					$value = Fedora_API::callGetDatastreamContents($pid, $ds_value['ID'], true);
					$doc = DOMDocument::loadXML($value);
					XML_Helper::setElementNodeValue($doc, '/FezMD', 'created_date',
					Date_API::getFedoraFormattedDateUTC());
					XML_Helper::setElementNodeValue($doc, '/FezMD', 'updated_date',
					Date_API::getFedoraFormattedDateUTC());
					XML_Helper::setElementNodeValue($doc, '/FezMD', 'depositor', Auth::getUserID());
					XML_Helper::setElementNodeValue($doc, '/FezMD', 'xdis_id', $new_xdis_id);
					$value = $doc->saveXML();
					Fedora_API::getUploadLocation($new_pid, $ds_value['ID'], $value, $ds_value['label'],
					$ds_value['MIMEType'], $ds_value['controlGroup'],null,$ds_value['versionable']);
					break;
				case 'RELS-EXT':
					// set the successor thing in RELS-EXT
					$value = Fedora_API::callGetDatastreamContents($pid, $ds_value['ID'], true);
					$value = str_replace($pid, $new_pid, $value);
					if ($is_succession || !empty($collection_pid)) {
						$doc = DOMDocument::loadXML($value);
						//    <rel:isDerivationOf rdf:resource="info:fedora/MSS:379"/>
						if ($is_succession) {
							$node = XML_Helper::getOrCreateElement($doc, '/rdf:RDF/rdf:description', 'rel:isDerivationOf',
							array('rdf'=>"http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                                    'rel'=>"info:fedora/fedora-system:def/relations-external#"));
							$node->setAttributeNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", 'resource', $pid);
						}
						if (!empty($collection_pid)) {
							$node = XML_Helper::getOrCreateElement($doc, '/rdf:RDF/rdf:description', 'rel:isMemberOf',
							array('rdf'=>"http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                                    'rel'=>"info:fedora/fedora-system:def/relations-external#"));
							$node->setAttributeNS("http://www.w3.org/1999/02/22-rdf-syntax-ns#", 'resource', $collection_pid);
						}
						$value = $doc->saveXML();
					}
					Fedora_API::getUploadLocation($new_pid, $ds_value['ID'], $value, $ds_value['label'],
					$ds_value['MIMEType'], $ds_value['controlGroup'],null,$ds_value['versionable']);
					break;
				default:
					if (isset($ds_value['controlGroup']) && $ds_value['controlGroup'] == 'X') {
						$value = Fedora_API::callGetDatastreamContents($pid, $ds_value['ID'], true);
						$value = str_replace($pid, $new_pid, $value);
						Fedora_API::getUploadLocation($new_pid, $ds_value['ID'], $value, $ds_value['label'],
						$ds_value['MIMEType'], $ds_value['controlGroup'], null, $ds_value['versionable']);
					} elseif (isset($ds_value['controlGroup']) && $ds_value['controlGroup'] == 'M'
					&& $clone_attached_datastreams) {
						$value = Fedora_API::callGetDatastreamContents($pid, $ds_value['ID'], true);
						Fedora_API::getUploadLocation($new_pid, $ds_value['ID'], $value, $ds_value['label'],
						$ds_value['MIMEType'], $ds_value['controlGroup'], null, $ds_value['versionable']);
					} elseif (isset($ds_value['controlGroup']) && $ds_value['controlGroup'] == 'R'
					&& $clone_attached_datastreams) {
						$value = Fedora_API::callGetDatastreamContents($pid, $ds_value['ID'], true);
						Fedora_API::callAddDatastream($new_pid, $ds_value['ID'], $value, $ds_value['label'],
						$ds_value['state'],$ds_value['MIMEType'], $ds_value['controlGroup'], $ds_value['versionable']);
					}
					break;
			}
		}
		Record::setIndexMatchingFields($new_pid);

		return $new_pid;
	}

	/**
	 * Generate a string which is a citation for this record.  Uses a citation template.
	 */
	function getCitation()
	{
		$details = $this->getDetails();
		$xsdmfs = $this->display->xsdmf_array;

		return Citation::renderCitation($this->xdis_id, $details, $xsdmfs);
	}
	/**
	 * Mark the fedora state of the record as deleted.  This keeps the record around in case we want to undelete it
	 * later. We tell the Fez indexer not to index Fedora Deleted objects.
	 */
	function markAsDeleted()
	{
		return Record::markAsDeleted($this->pid);
	}

	/**
	 * Mark the fedora state of the record as active.  Also restores the fez index of the object.
	 */
	function markAsActive($do_index = true)
	{
		return Record::markAsActive($this->pid, $do_index);
	}

	function isDeleted()
	{
		return Record::isDeleted($this->pid);
	}



	function getLock($context=self::CONTEXT_NONE, $extra_context=null)
	{
		return RecordLock::getLock($this->pid, Auth::getUserID(),$context,$extra_context);
	}

	function releaseLock()
	{
		return RecordLock::releaseLock($this->pid);
	}

	function getLockOwner()
	{
		return RecordLock::getOwner($this->pid);
	}

	function isLocked()
	{
		return RecordLock::getOwner($this->pid) > 0 ? true : false;
	}
}