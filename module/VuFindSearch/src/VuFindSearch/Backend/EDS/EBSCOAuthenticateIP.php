<?php

/**
 * EDS Authenticate local users via IP address
 *
 * PHP version 5
 *
 * Copyright (C) EBSCO 2015.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the Apache license (http://www.apache.org/licenses/LICENSE-2.0) ,
 * as published by the Apache Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the Apache License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   RuiFrancisco <rfrancisco@ebsco.com>
 * @license  http://www.apache.org/licenses/LICENSE-2.0.txt Apache license
 * @link     http://vufind.org   Main Site
 */
	function validAuthIP($listIPs) {

		$m = explode(",",$listIPs);
		// get the ip address of the request
		$ip_address = trim($_SERVER['REMOTE_ADDR']);
		foreach($m as $ip) {
		  $v=trim($ip);
		  if ( strcmp(substr($ip_address,0,strlen($v)),$v)==0)   {
			// inside of ip address range of customer
			return true;
		  }
		}
		// if not found, return false, not authenticated by IP address
		return false;
	  
	}


?>