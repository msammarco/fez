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
// |          Lachlan Kuhn <l.kuhn@library.uq.edu.au>,                    |
// |          Rhys Palmer <r.rpalmer@library.uq.edu.au>                   |
// +----------------------------------------------------------------------+

include_once(APP_INC_PATH.'class.background_process.php');
include_once(APP_INC_PATH.'class.bulk_assign_record_user.php');

class BackgroundProcess_Bulk_Assign_Record_User extends BackgroundProcess
{
	function __construct()
	{
		parent::__construct();
		$this->include = 'class.bgp_bulk_assign_record_user.php';
		$this->name = 'Bulk Assign Records to User';
	}

	function run()
	{
		$this->setState(1);
		extract(unserialize($this->inputs));

		$barg = new Bulk_Assign_Record_User;
		$barg->setBGP($this);
		if (!empty($pids) && is_array($pids)) {

            $this->setStatus("Assigning ". count($pids) ." Records to User '". $assign_usr_ids[0] ."'");

            $record_counter = 0;
            $record_count = count($pids);

            // Get the configurations for ETA calculation
            $eta_cfg = $this->getETAConfig();

			foreach ($pids as $pid) {
                $record_counter++;
				$barg->assignUserBGP($pid, $assign_usr_ids[0], $regen, true, $eta_cfg, $record_counter, $record_count);
				$this->markPidAsFinished($pid);
			}
		}
		$this->setState(2);
	}
}
