<?php
 
/*  Copyright 2012 Code for the People Ltd

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

// Load our List Table Class for use in our settings page
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
require_once( 'class-aggregator_list_table.php' );

/**
 * 
 * 
 * @package Feature Posts on Root Blog
 * @author Code for the People Ltd
 **/
class Aggregator extends Aggregator_Plugin {

	/**
	 * A version number for cache busting, etc.
	 *
	 * @var boolean A version tag for cache-busting
	 */
	public $version;

	/**
	 * @todo document
	 */
	protected $list_table;

	/**
	 * Initiate!
	 */
	public function __construct() {
		$this->setup( 'aggregator' );

		$this->add_action( 'network_admin_menu' );
		$this->add_action( 'admin_init' );

		if ( is_admin() ) {
			$this->add_action( 'load-post.php', 'load_post_edit' );
			$this->add_action( 'load-post-new.php', 'load_post_edit' );
		}

		$this->add_action( 'template_redirect' );
		$this->add_filter( 'post_link', null, null, 2 );
		$this->add_filter( 'post_row_actions', null, 9999, 2 );
		$this->add_filter( 'page_row_actions', 'post_row_actions', 9999, 2 );

		$this->version = 1;

	}

	function admin_init() {

		$this->list_table = new Aggregator_List_Table();

		$this->set_cpt_cache();

	}

	/**
	 * Stop pushed posts from being edited on a portal site.
	 *
	 * When an attempt is made to edit a post on a portal site that has been pushed from elsewhere,
	 * the user is told to take a running jump and given a link to go do so.
	 *
	 * @return void
	 */
	public function load_post_edit() {

		$post_id = isset( $_GET[ 'post' ] ) ? absint( $_GET[ 'post' ] ) : false;
		
		if ( $orig_blog_id = get_post_meta( $post_id, '_aggregator_orig_blog_id', true ) ) {

			$orig_post_id = get_post_meta( $post_id, '_aggregator_orig_post_id', true );
			$blog_details = get_blog_details( array( 'blog_id' => $orig_blog_id ) );
			$edit_url = get_home_url( $orig_blog_id ) . '/wp-admin/post.php?action=edit&post=' . absint( $orig_post_id );
			$edit_link = '<a href="' . esc_url( $edit_url ) . '">' . __( 'edit post', 'aggregator' ) . '</a>';
			$message = sprintf( __( 'Sorry, you must edit this post from the %1$s site: %2$s', 'aggregator' ), $blog_details->blogname, $edit_link );
			wp_die( $message );

		}

	}

	/**
	 * Remove all but the "view" action link on synced posts.
	 *
	 * We don't want folks to edit synced posts on a portal site, so we want to remove the
	 * relevant action links from the posts table.
	 *
	 * @param array $actions Action links array for filtering
	 * @param object $post WP_Post object representing the post being displayed
	 *
	 * @return array Filtered array of actions
	 */
	public function post_row_actions( $actions, $post ) {

		if ( $orig_blog_id = get_post_meta( $post->ID, '_aggregator_orig_blog_id', true ) ) {
			foreach ( $actions as $action_name => & $action ) {
				if ( 'view' != $action_name )
					unset( $actions[ $action_name ] );
			}
		}

		return $actions;

	}

	/**
	 * Hooks the WP post_link filter to provide the original
	 * permalink (stored in post meta) when a permalink
	 * is requested from the index blog.
	 *
	 * @param string $permalink The permalink
	 * @param object $post A WP Post object
	 *
	 * @return string A permalink
	 **/
	public function post_link( $permalink, $post ) {

		if ( $original_permalink = get_post_meta( $post->ID, '_aggregator_permalink', true ) )
			return $original_permalink;
		
		return $permalink;
	}
	
	// UTILITIES
	// =========

	/**
	 * Sets up our network admin settings page
	 *
	 * @return void
	 */
	public function network_admin_menu() {
		add_submenu_page(
			'settings.php',
			__('Aggregator Setup'),
			__('Aggregator'),
			'manage_sites',
			'aggregator',
			array( $this, 'network_admin_menu_callback' )
		);
	}

	/**
	 * Renders our network admin settings page
	 *
	 * @uses $this->render_admin()
	 *
	 * @return void
	 */
	public function network_admin_menu_callback() {
		$this->render_admin( 'network-admin-setup.php', $this );
	}

	/**
	 * Set a cached list of CPTs for this blog.
	 *
	 * @return void
	 */
	protected function set_cpt_cache() {
		global $wp_post_types;

		// We only want the CPT names
		$cpts = array_keys( $wp_post_types );

		// Get/set the cache
		$cpt_cache_name = 'cpt_cache_' . get_current_blog_id();
		$cpt_cache = get_site_transient( $cpt_cache_name );
		if ( ! $cpt_cache )
			set_site_transient( $cpt_cache_name, $cpts );

	}

} // END Aggregator class

$aggregator = new Aggregator();