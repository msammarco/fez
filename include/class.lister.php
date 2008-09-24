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
// |          Matthew Smith <m.smith@library.uq.edu.au>,                  |
// |          Lachlan Kuhn <l.kuhn@library.uq.edu.au>                     |
// +----------------------------------------------------------------------+
//
//


/**
 * Class to handle the business logic related to the administration
 * of collections in the system.
 *
 * @version 1.0
 * @author Christiaan Kortekaas <c.kortekaas@library.uq.edu.au>
 * @author Matthew Smith <m.smith@library.uq.edu.au>
 * @author Lachlan Kuhn <l.kuhn@library.uq.edu.au>
 */
include_once(APP_INC_PATH . "db_access.php");
include_once(APP_INC_PATH . "class.template.php");
include_once(APP_INC_PATH . "class.auth.php");
include_once(APP_INC_PATH . "class.misc.php");
include_once(APP_INC_PATH . "class.record.php");
include_once(APP_INC_PATH . "class.pager.php");
include_once(APP_INC_PATH . "class.search_key.php");
include_once(APP_INC_PATH . "class.collection.php");
include_once(APP_INC_PATH . "class.community.php");
include_once(APP_INC_PATH . "class.controlled_vocab.php");
include_once(APP_INC_PATH . "class.fedora_api.php");
include_once(APP_INC_PATH . "class.status.php");
include_once(APP_INC_PATH . "class.citation.php");
include_once(APP_INC_PATH . "class.user.php");
include_once(APP_INC_PATH . "class.workflow_trigger.php");
include_once(APP_INC_PATH . "class.custom_view.php");
include_once(APP_INC_PATH . "najax_classes.php");

class Lister
{
    function getList($params, $display=true) {
   
        /*
         * These are the only $params(ie. $_GET) vars that can be passed to this page.
         * Strip out any that aren't in this list
         */
        $args = array(
            'browse'        =>  'string',
            'author_id'     =>  'numeric',
            'collection_pid'=>  'string',
            'community_pid' =>  'string',
            'cat'           =>  'string',
            'author'        =>  'string',
            'tpl'           =>  'numeric',
            'year'          =>  'numeric',
            'rows'          =>  'numeric',
            'pager_row'     =>  'numeric',
            'sort'          =>  'string',
            'sort_by'       =>  'string',
            'search_keys'   =>  'array',
            'order_by'      =>  'string',
            'value'         =>  'string',
            'operator'      =>  'string',
            'custom_view_pid' =>  'string',
            'form_name'     =>  'string',
        );

        foreach ($args as $getName => $getType) {            
            if( Misc::sanity_check($params[$getName], $getType) !== false ) {
                $allowed[$getName] = $params[$getName];
            }
        }
        $params = $allowed;

		$custom_view_pid = $params['custom_view_pid'];

//		$filter["searchKey".Search_Key::getID("isMemberOf")];


        $tpl = new Template_API();
		if (is_numeric($params['tpl'])) {
        	$tpl_idx = intval($params['tpl']);
		} else {
			$tpl_idx = 0;
		}
        $tpls = array(
            0 => array('file' => 'list.tpl.html', 'title' => 'Default'),
            2 => array('file' => 'rss.tpl.html', 'title' => 'RSS Feed'),
            3 => array('file' => 'xml_feed.tpl.html', 'title' => 'XML Feed'),
			1 => array('file' => 'excel.tpl.html', 'title' => 'Excel File'),
            4 => array('file' => 'citation_only_list.tpl.html', 'title' => 'Citations Only'),
            5 => array('file' => 'simple_list.tpl.html', 'title' => 'Classic Simple View'),
            6 => array('file' => 'gallery_list.tpl.html', 'title' => 'Image Gallery View')
        );
		// disabled edit authors view until it can be looked again in the future
        // 1 => array('file' => 'views/list/author_bulk_edit.tpl.html', 'title' => 'Edit Authors'),

		if (!empty($custom_view_pid)) {			
			if (!is_numeric($params['tpl'])) {
				$cvcom_details = Custom_View::getCommCview($custom_view_pid);
				foreach ($tpls as $tplkey => $tplval) {
					if ($cvcom_details['cvcom_default_template'] == $tplval['file']) {
						$tpl_idx = $tplkey;
					}
				}
			}
			$child_collections = Record::getCollectionChildrenAll($custom_view_pid);
			$child_pids = array();
			$filter["searchKey".Search_Key::getID("isMemberOf")]['override_op'] = 'OR';
			$filter["searchKey".Search_Key::getID("isMemberOf")][] = $custom_view_pid;
			foreach ($child_collections as $rek_row) {
				$filter["searchKey".Search_Key::getID("isMemberOf")][] = $rek_row['rek_pid'];
			}
		} 


    
		if ($tpl_idx != 0 && $tpl_index != 4) {
			$citationCache = false;
		} else {
			$citationCache = true;
		}
		$getSimple = false;
		if ($tpl_idx == 2 || $tpl_idx == 3) {
			$header = "Content-type: application/xml\n";
			header($header);
		} elseif ($tpl_idx == 1) {
			header("Content-type: application/vnd.ms-excel");
			header("Content-Disposition: attachment; filename=export.xls");
			header("Content-Description: PHP Generated XLS Data");
		}
		
        
        if (Auth::userExists($username)) { // if the user is registered as a Fez user
            $tpl->assign("isFezUser", $username);
        }
        
        $pager_row = $params['pager_row'];
        if (empty($pager_row)) {
            $pager_row = 0;
        }
        
        
        $rows = $params['rows'];
        if (empty($rows)) {
            
            if(!empty($_SESSION['rows'])) {
                $rows = $_SESSION['rows'];
            } else {
                $rows = APP_DEFAULT_PAGER_SIZE;
            }
            
        } else {
            $_SESSION['rows'] = $rows;
        }
        
        switch ($tpl_idx) {
        	case 2:
//        		$rows = "ALL"; //If an RSS feed show all the rows
//				$pager_row = 0;
        		break;
        	default:
        		break;
        }
        
        $cookie_key = Pager::getParam('form_name',$params);
        $options = Pager::saveSearchParams($params, $cookie_key);
	    
	    $getFunction = 'getListing';
        if( APP_SOLR_SWITCH == "ON" )
        {
            $getFunction = 'getSearchListing';
        }

        $options['tpl_idx'] = $tpl_idx;     
        $tpl->assign("options", $options);
        
        $terms          = $params['terms'];
        $cat            = $params['cat'];
        $browse         = $params['browse'];
        $letter         = $params['letter'];
        $collection_pid = $params['collection_pid'];
        $community_pid  = $params['community_pid'];
        
		if (!empty($collection_pid)) {
			$pid = $collection_pid;
			$browse_mode = "collection";
		} elseif (!empty($community_pid)) {
			$pid = $community_pid;
			$browse_mode = "community";
		} else {
			$browse_mode = "list";
		}
		$tpl->assign("pid", $pid);
		$tpl->assign("browse_mode", $browse_mode);
		$sort_by = $options["sort_by"];
		$operator = $options["operator"];
		
		if(empty($operator)) {
		    $operator = "AND";
		}

		/*
		 * These options are used in a dropdown box to allow the 
		 * user to sort a list
		 */
        $sort_by_list = array(
            "searchKey".Search_Key::getID("Title") => 'Title',
            "searchKey".Search_Key::getID("Description") => 'Description',
            "searchKey".Search_Key::getID("File Downloads") => 'File Downloads',
            "searchKey".Search_Key::getID("Date") => 'Date',
            "searchKey".Search_Key::getID("Created Date") => 'Created Date',
            "searchKey".Search_Key::getID("Updated Date") => 'Updated Date',
            "searchKey".Search_Key::getID("Sequence") => 'Sequence'
        );
        
        
        if (($cat == 'search' || $cat == 'all_fields' || $cat == 'quick_filter') ) {        	
            $sort_by_list['searchKey0'] = "Search Relevance";           
            if (($params["sort_by"]) == "") {
            	$sort_by = "searchKey0";
            }
            
            // if searching by Title, Abstract, Keywords and sort order not specifically set in the querystring 
            // (from a manual sort order change) than make search revelance sort descending
            if (!is_numeric($params["sort_order"]) && ($sort_by == "searchKey0")) {
            	$options["sort_order"] = 1; // DESC relevance
        	}
        }
        
		// Default Sort
        if (!array_key_exists($sort_by, $sort_by_list)) {
        	$sort_by = "searchKey".Search_Key::getID("Title");
        }
        
        $list_info = array();
        
        //get the bulk change workflows
		$bulk_workflows = WorkflowTrigger::getAssocListByTrigger("-1", WorkflowTrigger::getTriggerId('Bulk Change'));
		$bulk_search_workflows = WorkflowTrigger::getAssocListByTrigger("-1", WorkflowTrigger::getTriggerId('Bulk Change Search')); 
		
        $tpl->assign("bulk_workflows",          $bulk_workflows);
        $tpl->assign("bulk_search_workflows",   $bulk_search_workflows);
		// if it is 
        if (!empty($custom_view_pid) &&  empty($collection_pid) && empty($community_pid) && ($browse != "latest") && ($browse != "year") && (($browse != "author") && ($browse != "author_id")) &&  ($browse != "depositor") &&  ($browse != "subject") && ($cat != "quick_filter")) {
			$community_pid = $custom_view_pid;
		}
        
        if (!empty($collection_pid)) {
            
            // list a collection
            // first check the user has view rights over the collection object
            $record = new RecordObject($collection_pid);
            $canList = $record->canList(true);

            $tpl->assign("isLister", $canList);

            if ($canList) {
                
                $tpl->assign("xdis_id", Record::getSearchKeyIndexValue($collection_pid, "Display Type"));
                $parents = Record::getParentsDetails($collection_pid);

                $tpl->assign("parents", $parents);
                $collection_xdis_id = Collection::getCollectionXDIS_ID();
                $userPIDAuthGroups = AuthIndex::getIndexAuthRoles($collection_pid);
                $isCreator = @$userPIDAuthGroups['isCreator'] == 1;
                $tpl->assign("isCreator", $isCreator);
                $isEditor = @$userPIDAuthGroups['isEditor'] == 1;
                $tpl->assign("isEditor", $isEditor);
                $options = Search_Key::stripSearchKeys($options); 

                $filter["searchKey".Search_Key::getID("Status")] = 2; // enforce published records only
			    $filter["searchKey".Search_Key::getID("isMemberOf")] = $collection_pid;
			    
                $list = Record::getListing($options, array("Lister", "Viewer"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter);
                                
                //$list = Collection::getListing($collection_pid, $pager_row, $rows, $sort_by);
                $list_info = $list["info"];
                $facets = @$list['facets'];
                $snips = @$list['snips'];
                $list = $list["list"];
                
                $title = Record::getSearchKeyIndexValue($collection_pid, "Title");
                $display_type = Record::getSearchKeyIndexValue($collection_pid, "Display Type");
				$display_type = array_values($display_type);
				$citation = Record::getCitationIndex($collection_pid);
                $tpl->assign("list_heading", "List of Records in ".$title." ".$display_type[0]);
                $tpl->assign("list_heading_citation", "List of Records in ".$citation." ".$display_type[0]);
                $tpl->assign("list_type", "collection_records_list");

                $tpl->assign("collection_pid", $collection_pid);
                $childXDisplayOptions = Record::getSearchKeyIndexValue($collection_pid, "XSD Display Option");
                
                if (count($childXDisplayOptions) > 0) {
                    $tpl->assign("childXDisplayOptions", $childXDisplayOptions);
                } else {
                    $tpl->assign("childXDisplayOptions", 0);
                } 
                
                unset($params['collection_pid']);
                
                $tpl->assign('url', Misc::query_string_encode($params));
                
            } else {
                $tpl->assign("show_not_allowed_msg", true);
            }
            
        } elseif (!empty($community_pid)) {
            
			$sort_by = "searchKey".Search_Key::getID("Title");
			
            // list collections in a community
            // first check the user has view rights over the collection object
            $record = new RecordObject($community_pid);
            $canView = $record->canView(true);
            $tpl->assign("isViewer", $canView);
            if ($canView) {	
                
                $tpl->assign("community_pid", $community_pid);
                $userPIDAuthGroups = AuthIndex::getIndexAuthRoles($community_pid);
                $isCreator = @$userPIDAuthGroups['isCreator'] == 1;
                $tpl->assign("isCreator", $isCreator);
                $isEditor = @$userPIDAuthGroups['isEditor'] == 1;
                $tpl->assign("isEditor", $isEditor);
                $tpl->assign("xdis_id", $xdis_id);	
                //$community_details = Community::getDetails($community_pid);
                $community_title = Record::getSearchKeyIndexValue($community_pid, "Title");
                
                $options = Search_Key::stripSearchKeys($options);

            	$filter["searchKey".Search_Key::getID("Status")] = 2; // enforce published records only
				$filter["searchKey".Search_Key::getID("isMemberOf")] = $community_pid; // 
            	$list = Record::getListing($options, array("Lister", "Viewer"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter);
                
                $list_info = $list["info"];
                $facets = @$list['facets'];
                $snips = @$list['snips'];
                $list = $list["list"];
                
                $title = Record::getSearchKeyIndexValue($community_pid, "Title");
                $display_type = Record::getSearchKeyIndexValue($community_pid, "Display Type");
				$display_type = array_values($display_type);
				$citation = Record::getCitationIndex($community_pid);
				
                $tpl->assign("list_heading", "List of Collections in ".$title." ".$display_type[0]);		
                $tpl->assign("list_heading_citation", "List of Collections in ".$citation." ".$display_type[0]);
                $tpl->assign("list_type", "collection_list");
                
                $childXDisplayOptions = Record::getSearchKeyIndexValue($community_pid, "XSD Display Option");
                if (count($childXDisplayOptions) > 0) {
                    $tpl->assign("childXDisplayOptions", $childXDisplayOptions);
                } else {
                    $tpl->assign("childXDisplayOptions", 0);
                }
            } else {
                $tpl->assign("show_not_allowed_msg", true);
            }
            
            /*
             * Remove these sort options when viewing a list of community
             */
        	unset($sort_by_list["searchKey".Search_Key::getID("File Downloads")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("Sequence")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("Description")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("Date")]);
        	
        	/*
        	 * Remove 'citation' and 'classic' display option when viewing a list of subjects
        	 */
        	unset($tpls[4]);
        	unset($tpls[5]);
            
        } elseif ($browse == "latest") {
            
            $sort_by_list["searchKey".Search_Key::getID("Created Date")] = 'Created Date';
            
            /*
             * Remove these sort options when viewing the latest records
             */
            unset($sort_by_list["searchKey".Search_Key::getID("Title")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("File Downloads")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("Sequence")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("Description")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("Date")]);
            
			$options = array();

			$options["sort_order"] = 1;
			$filter["searchKey".Search_Key::getID("Status")] = 2; // enforce published records only
			
			$sort_by = "searchKey".Search_Key::getID("Created Date");
			
			$list = Record::$getFunction($options, $approved_roles=array("Lister"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter);
            $list_info = $list["info"];
            $list = $list["list"];
			
            $tpl->assign("browse_type", "browse_latest");
            $tpl->assign("list_heading", "Browse By Latest Additions");
            $tpl->assign("today", date("Y-m-d"));
            $tpl->assign("today_day_name", date("l"));
            $tpl->assign("yesterday", date("Y-m-d", time()-86400));
            $tpl->assign("last", "Last ");
            $tpl->assign("list_type", "all_records_list");
            
        } elseif ($browse == "year") {
            
            // browse by year
            $year = Lister::getValue($params,'year');
            if (is_numeric($year)) {
                
				$options = Search_Key::stripSearchKeys($options);

				$filter["searchKey".Search_Key::getID("Status")] = 2; // enforce published records only
				$filter["searchKey".Search_Key::getID("Date")] = array();
				$filter["searchKey".Search_Key::getID("Date")]["filter_type"] = "between";
				$filter["searchKey".Search_Key::getID("Date")]["filter_enabled"] = 1;
				$filter["searchKey".Search_Key::getID("Date")]["start_date"] = $year."-01-01";
				$filter["searchKey".Search_Key::getID("Date")]["end_date"] = $year."-12-31";			
				$list = Record::getListing($options, $approved_roles=array("Lister"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter);
                $list_info = $list["info"];
                $list = $list["list"];
				
                $tpl->assign("year", $year);
                $tpl->assign("browse_heading", "Browse By Year ".$year);
                $tpl->assign("list_heading", "List of Records");
            } else {
                $list = Collection::listByAttribute($pager_row, $rows, "Date", $sort_by);
                $list_info = $list["info"];
                $list = $list["list"];
                $tpl->assign("browse_heading", "Browse By Year");
                
                /*
                 * Remove these sort options when viewing a list of subjects
                 */
            	unset($sort_by_list["searchKey".Search_Key::getID("File Downloads")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Sequence")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Description")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Date")]);
            	
            	/*
            	 * Remove 'citation' and 'classic' display option when viewing a list of subjects
            	 */
            	unset($tpls[4]);
            	unset($tpls[5]);
            }
            $tpl->assign("browse_type", "browse_year");
            
        } elseif (($browse == "author") || ($browse == "author_id")) {
            
            // browse by author
            if( $browse == "author") {
                
                if( strlen(Lister::getValue($params,'author')) == 1 ) {
                    $letter = Lister::getValue($params,'author');
                } else {
                    $author = Lister::getValue($params,'author');
                }
            }
                
            if( $browse == "author_id" )
                $author_id = Lister::getValue($params,'author_id');
            
            if (!empty($author_id)) {            	
            	$options = Search_Key::stripSearchKeys($options);

            	$filter["searchKey".Search_Key::getID("Status")] = 2; // enforce published records only
				$filter["searchKey".Search_Key::getID("Author ID")] = $author_id; //
				$author = Author::getFullname($author_id); 
            	$list = Record::getListing($options, array("Lister", "Viewer"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter);
            	
                $list_info = $list["info"];
                $list = $list["list"];
				
                $tpl->assign("author_id", $author_id);
                $tpl->assign("browse_heading", "Browse By Author ID - ".$author);
                $tpl->assign("list_heading", "Browse By Author ID - ".$author);
            } elseif (!empty($author)) {	
	        	$options = Search_Key::stripSearchKeys($options);
            	$filter["searchKey".Search_Key::getID("Status")] = 2; // enforce published records only
				$filter["searchKey".Search_Key::getID("Author")] = $author; //
            	$list = Record::getListing($options, array("Lister", "Viewer"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter);
                $list_info = $list["info"];
                $list = $list["list"];
				
                $tpl->assign("author", $author);
                $tpl->assign("browse_heading", "Browse By Author Name - ".$author);
			    $tpl->assign("list_heading", "Browse By Author Name - ".$author);
            } else {
                
            	if ($browse == "author_id") {
	                $list = Collection::listByAuthor($pager_row, $rows, $sort_by, $letter);
	                $list_info = $list["info"];
	                $list = $list["list"];
					
	                $tpl->assign("browse_heading", "Browse By ".APP_NAME." Author ID");
				    $tpl->assign("list_heading", "Browse By ".APP_NAME." Author ID");
            	} else {
	                $list = Collection::listByAttribute($pager_row, $rows, "Author", $sort_by, $letter);
	                $list_info = $list["info"];
	                $list = $list["list"];
					
	                $tpl->assign("browse_heading", "Browse By Author Name");
				    $tpl->assign("list_heading", "Browse By Author Name");
            	}
            	
            	/*
                 * Remove these sort options when viewing a list of authors
                 */
            	unset($sort_by_list["searchKey".Search_Key::getID("File Downloads")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Sequence")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Description")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Date")]);
            	
            	/*
            	 * Remove 'citation' display option when viewing a list of authors
            	 */
            	unset($tpls[4]);
            }
            $tpl->assign("browse_type", "browse_author");
            $tpl->assign("alphabet_list", Misc::generateAlphabetArray());
        
        } elseif ($browse == "depositor") {
            
            // browse by depositor
            $depositor = Lister::getValue($params,'depositor');
			$depositor_fullname = User::getFullName($depositor);
			
			if (!empty($depositor)) {				
				$options = Search_Key::stripSearchKeys($options);
            	$filter["searchKey".Search_Key::getID("Status")] = 2; // enforce published records only
				$filter["searchKey".Search_Key::getID("Depositor")] = $depositor; // 
            	$list = Record::getListing($options, array("Lister", "Viewer"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter);
                $list_info = $list["info"];
                $list = $list["list"];
				
                $tpl->assign("depositor", $depositor);
                $tpl->assign("browse_heading", "Browse By Depositor - ".$depositor_fullname);
			    $tpl->assign("list_heading", "Browse By Depositor - ".$depositor_fullname);	
            } else {
                $list = Collection::listByAttribute($pager_row, $rows, "Depositor",$sort_by);
                $list_info = $list["info"];
                $list = $list["list"];
				
                $tpl->assign("browse_heading", "Browse By Depositor");
			    $tpl->assign("list_heading", "Browse By Depositor");
			    
			    /*
                 * Remove these sort options when viewing a list of Depositors
                 */
            	unset($sort_by_list["searchKey".Search_Key::getID("File Downloads")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Sequence")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Description")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Date")]);
            	
            	/*
            	 * Remove 'citation' display option when viewing a list of depositors
            	 */
            	unset($tpls[4]);
            }
            $tpl->assign("browse_type", "browse_depositor");
            
        } elseif ($browse == "subject") {
        	
            // browse by subject
            $parent_id = Lister::getValue($params,'parent_id');
            
            if (is_numeric($parent_id)) {	
                $subject_list = Controlled_Vocab::getList($parent_id);
                $treeIDs = Controlled_Vocab::getAllTreeIDs($parent_id);
                $subject_count = Collection::getCVCountSearch($treeIDs, $parent_id);
                
				$options = Search_Key::stripSearchKeys($options);
            	$filter["searchKey".Search_Key::getID("Status")] = 2; // enforce published records only
				$filter["searchKey".Search_Key::getID("Subject")] = $parent_id; // 
            	$list = Record::getListing($options, array("Lister", "Viewer"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter);	
                
                $list_info = $list["info"];
                $list = $list["list"];
                
                $tpl->assign('parent_id', $parent_id);
				
            } else {
                $subject_list = Controlled_Vocab::getList();
                
                /*
                 * Remove these sort options when viewing a list of subjects
                 */
            	unset($sort_by_list["searchKey".Search_Key::getID("File Downloads")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Sequence")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Description")]);
            	unset($sort_by_list["searchKey".Search_Key::getID("Date")]);
            	
            	/*
            	 * Remove 'citation' display option when viewing a list of subjects
            	 */
            	unset($tpls[4]);
            }
            
            $breadcrumb = Controlled_Vocab::getParentAssocListFullDisplay($parent_id);
            $breadcrumb = Misc::array_merge_preserve($breadcrumb, Controlled_Vocab::getAssocListByID($parent_id));
            $newcrumb = array();
            foreach ($breadcrumb as $key => $data) {
                array_push($newcrumb, array("cvo_id" => $key, "cvo_title" => $data));
            }
            $max_breadcrumb = (count($newcrumb) -1);
            
            $tpl->assign("max_subject_breadcrumb", $max_breadcrumb);
            $tpl->assign("subject_breadcrumb", $newcrumb);
            $tpl->assign("list_type", "all_records_list");
            $tpl->assign("parent_id", $parent_id);
            $tpl->assign("subject_list", $subject_list);
            $tpl->assign("subject_count", $subject_count);
            $tpl->assign("browse_heading", "Browse By Subject Classifications Records");
            $tpl->assign("list_heading", "List of Subject Classifications Records");
            $tpl->assign("browse_type", "browse_subject");
            
        } elseif ($cat == "quick_filter") { // Advanced Search

        	include_once(APP_INC_PATH . "class.spell.php");
        	include_once(APP_INC_PATH . "class.language.php");
        	
        	if (empty($sort_by)) {      
        		if ($options["searchKey0"] == "") {
        			$sort_by = "searchKey".Search_Key::getID("Title");	
        		} else {        			
        			$sort_by = "searchKey0"; // Search Relevance
					$options["sort_dir"] = 1;
        		}
        	}
        	
        	// search Fez
        	
			// enforce certain search parameters			
			// enforce published records only
			$options["searchKey".Search_Key::getID("Status")] = array(
			     'override_op'   =>  'AND',
			     'value'         =>  2,
			);
			$filter["searchKey".Search_Key::getID("Status")] = array(
			     'override_op'   =>  'AND',
			     'value'         =>  2,
			);
            
			$list = Record::$getFunction($options, array("Lister", "Viewer"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter, $operator);
			
			$spell = new spellcheck(APP_DEFAULT_LANG);
			
			if( $spell )
			{
			    $spell_suggest = $spell->query_suggest($params['search_keys'][0]);
			    
			    // Did pspell return any suggestions?
			    if( $spell_suggest )
			    {
			        // Replace search_key[0]={search} with search_key[0]={suggestion}
			        // search key 0 will be 'Title, Abstract, Keywords'
                    $tpl->assign("spell_suggest", $spell_suggest);
                    
                    $exclude[] = 'search_keys';
                    $tpl->assign('spell_suggest_url', Misc::query_string_encode($params,$exclude) . '&search_keys[0]='.urlencode($spell_suggest));
                    array_pop($exclude);
			    } 
			}
			
        	$list_info = @$list["info"];
        	$terms = @$list_info['search_info'];
        	$facets = @$list['facets'];
        	$snips = @$list['snips'];
        	$list = @$list["list"];
			
        	// KJ@ETH
        	$tpl->assign("major_function", "search");
			$tpl->assign("q", htmlspecialchars($params['search_keys'][0]));
        	
        	$tpl->assign("list_heading", "Search Results ($terms)");        	 
        	$tpl->assign("list_type", "all_records_list");
        } else {
                       
            $xdis_id = Community::getCommunityXDIS_ID();
            $tpl->assign("xdis_id", $xdis_id);
            $options = Search_Key::stripSearchKeys($options);
            $filter["searchKey".Search_Key::getID("Status")] = 2; // enforce published records only
            $filter["searchKey".Search_Key::getID("Object Type")] = 1; // enforce communities only
            $list = Record::getListing($options, array("Lister", "Viewer"), $pager_row, $rows, $sort_by, $getSimple, $citationCache, $filter);
            $list_info = $list["info"];
            $list = $list["list"];
            
            $tpl->assign("list_type", "community_list");
            $tpl->assign("list_heading", "List of Communities");
            
            /*
             * Remove these sort options when viewing a list of communities
             */
        	unset($sort_by_list["searchKey".Search_Key::getID("File Downloads")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("Sequence")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("Description")]);
        	unset($sort_by_list["searchKey".Search_Key::getID("Date")]);
        	
        	/*
        	 * Remove 'citation' and 'classic' display option when viewing a list of communities
        	 */
        	unset($tpls[4]);
        	unset($tpls[5]);
        }
        
        /*
         * We dont want to display facets that a user
         * has already searched by
         */
        if(isset($facets)) {
                
            foreach ($facets as $sek_id => $facetData) {
                if(!empty($options['searchKey'.$sek_id])) {
                    unset($facets[$sek_id]);
                }
            }
                
        }
        
        $tpl->assign('facets', $facets);
        $tpl->assign('snips', $snips);
        $tpl->assign('rows', $rows);
        $tpl->assign('tpl_list', array_map(create_function('$a','return $a[\'title\'];'), $tpls));
        $tpl->assign('browse', $browse);
        $tpl->assign('sort_by_list', $sort_by_list);
		$tpl->assign("cycle_colours", "#FFFFFF," . APP_CYCLE_COLOR_TWO);
        $tpl->assign('sort_by_default', $sort_by);
        $tpl->assign("eserv_url", APP_BASE_URL."eserv/");
        $tpl->assign('sort_order', $options["sort_order"]);
        $tpl->assign("list", $list);
        $tpl->assign("list_info", $list_info);
        
        // Hack to get SCRIPT_URL without querystrings.
        // Usually we could get this info from $_SERVER['SCRIPT_URL'], but can't since 
        // we are doing rewrite rules on a per-directory basis via .htaccess file
        $PAGE_URL = preg_replace('/(\?.*)/','',$_SERVER['REQUEST_URI']);
        
        // When generating the URL's for sorting etc do not include 
        // these variables as they will already be in the PAGE_URL
        $exclude = array(
            'pager_row',
            'browse',
            'value',
            'community_pid',
        );
        
        if(count($params) > 0) {
            
            $exclude[] = 'rows';
            $tpl->assign('url_wo_rows', Misc::query_string_encode($params,$exclude));
            array_pop($exclude);
            
            $exclude[] = 'tpl';
            $tpl->assign('url_wo_tpl',  Misc::query_string_encode($params,$exclude));
            array_pop($exclude);
            
            $exclude[] = 'sort';
            $exclude[] = 'sort_by';
            $tpl->assign('url_wo_sort', Misc::query_string_encode($params,$exclude));
        }

        $tpl->assign('PAGE_URL', $PAGE_URL);

		$tpl->assign("active_nav", "list");
        
        $tpl->registerNajax(NAJAX_Client::register('NajaxRecord', APP_RELATIVE_URL.'ajax.php')."\n"
            .NAJAX_Client::register('Suggestor', APP_RELATIVE_URL.'ajax.php')."\n");

		// If most results have thumbnails and there is no template set in the querystring than force the image gallery template
		if (!is_numeric($params['tpl'])) {
			if (is_numeric($list_info['thumb_ratio'])) {
				if ($list_info['thumb_ratio'] > 0.5) {
					$tpl_idx = 6;
				}
			}
		}
        $tpl_file = $tpls[$tpl_idx]['file'];
        $tpl->setTemplate($tpl_file);
		$tpl->assign("template_mode", $tpl_idx);

        if ($display) {
            $tpl->displayTemplate();
        } 
        return compact('list','list_info');
    }
    
    
    function getValue($params, $varName) {
        
        if(isset($params[$varName])) {
            
            return $params[$varName];
            
        } elseif(isset($params['value'])) {
            
            return $params['value'];
            
        }
        
        return false;
    }
}




?>
