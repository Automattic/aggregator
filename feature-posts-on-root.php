<?php

/*
Plugin Name: Feature Posts on Root Blog
Plugin URI: http://codeforthepeople.com/plugins/
Description: Allows subsites to push posts from their blog to the root blog.
Network: true
Version: 1.0
Author: Simon Wheatley
Author URI: http://codeforthepeople.com/
*/

require_once( 'class-plugin.php' );
require_once( 'class-feature-posts-on-root.php' );

function feature_posts_on_root_blog_file() {
	return __FILE__;
}
