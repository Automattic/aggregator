<?php

/*
Plugin Name: Aggregator
Plugin URI: https://github.com/automattic/aggregator/
Description: Synchronise posts between blogs in a multisite network
Network: true
Version: 1.1.1
Author: WordPress.com VIP / Philip John
Author URI: http://vip.wordpress.com
License: GPLv2

Copyright 2016 Automattic, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

require_once( 'class-plugin.php' );
require_once( 'class-aggregator.php' );
require_once( 'class-aggregator_job.php' );
require_once( 'class-aggregate.php' );

function aggregator_file() {
	return __FILE__;
}
