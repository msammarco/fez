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

//Create a site map to allow search engines find all of out pids

include_once dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'config.inc.php';
require_once ('sitemap.php');
define(BASE_URI, dirname(__FILE__).DIRECTORY_SEPARATOR);
define(BASE_URL, 'http://'.APP_HOSTNAME.'/');

if (php_sapi_name()==="cli" || 1) {
    echo "Script started: " . date('Y-m-d H:i:s') . "\n";
    flush();
    ob_flush();
    $approved_roles=array(9,10);
    // get listing of all published pids, which since it's run from CLI will be publicly viewable pids
    $stmt = "SELECT rek_pid, rek_updated_date FROM " . APP_TABLE_PREFIX . "record_search_key";
    $authArray = Collection::getAuthIndexStmt($approved_roles, "rek_pid");
    $stmt .= $authArray['authStmt'];
    $stmt .= " AND rek_status = '2' ORDER BY rek_updated_date DESC";
    $pidList = $db->fetchAll($stmt);

    echo "Adding " . count($pidList) . " pids to sitemap\n";
    flush();
    ob_flush();

    $sitemap = new Sitemap(false);
    $sitemap->page('records');

    foreach ($pidList as $pidDetails) {
        $pid = $pidDetails['rek_pid'];
        $updated = $pidDetails['rek_updated_date'];

        //We'll tell google to check on pids updated lately(Past 4 weeks) sooner rather than later
        $changeFrequency = ($updated > strftime("%Y-%m-%d", time() - 60*60*24*7*4)) ? 'daily' : 'monthly';
        $url = 'view/'.$pid;
        $sitemap->url($url, $updated, $changeFrequency);
    }

    $sitemap->close();
    unset ($sitemap);
    echo "Script finished: " . date('Y-m-d H:i:s') . "\n";
} else {
    echo "Run from command only";
}