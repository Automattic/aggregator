<?php

/*
Plugin Name: Aggregator
Plugin URI: https://bitbucket.org/cftp/aggregator/
Description: Fork of Simon Wheatley's Feature Posts on Root Blog plugin
Network: true
Version: 1.0
Author: Simon Wheatley, Philip John
Author URI: http://codeforthepeople.com/
*/

require_once( 'class-plugin.php' );
require_once( 'class-aggregator.php' );

function aggregator_file() {
	return __FILE__;
}
