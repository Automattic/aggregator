<?php

/*
Plugin Name: Aggregator
Plugin URI: https://bitbucket.org/cftp/aggregator/
Description: Synchronise posts between blogs in a multisite network
Network: true
Version: 1.0
Author: Simon Wheatley, Philip John
Author URI: http://codeforthepeople.com/
*/

require_once( 'class-plugin.php' );
require_once( 'class-aggregator.php' );
require_once( 'class-aggregator_job.php' );
require_once( 'class-aggregate.php' );

function aggregator_file() {
	return __FILE__;
}
