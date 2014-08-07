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
	 * A flag to say whether we're currently recursing, or not.
	 *
	 * @var type boolean
	 */
	public $recursing;

	/**
	 * A version number for cache busting, etc.
	 *
	 * @var type boolean
	 */
	public $version;

	protected $list_table;

	/**
	 * Initiate!
	 *
	 * @return void
	 **/
	public function __construct() {
		$this->setup( 'aggregator' );

		$this->add_action( 'network_admin_menu' );
		$this->add_action( 'admin_init' );

		if ( is_admin() ) {
			$this->add_action( 'save_post', null, null, 2 );
			$this->add_action( 'load-post.php', 'load_post_edit' );
			$this->add_action( 'load-post-new.php', 'load_post_edit' );
		}

		$this->add_action( 'aggregator_import_terms', 'process_import_terms' );
		$this->add_action( 'template_redirect' );
		$this->add_filter( 'post_link', null, null, 2 );
		$this->add_filter( 'aggregator_sync_meta_key', 'sync_meta_key', null, 2 );
		$this->add_filter( 'post_row_actions', null, 9999, 2 );

		$this->recursing = false;
		$this->version = 1;
	}

	function admin_init() {
		$this->list_table = new Aggregator_List_Table();
	}
	
	function load_post_edit() {
		if ( ! is_main_site() ) {
			wp_enqueue_script( 'aggregator-admin', $this->url( '/js/admin.js' ), array( 'jquery' ), $this->version );
			$data = array(
				'this_site_only' => __( 'This site only', 'aggregator' ),
				'this_site_plus' => __( 'This site and the main site', 'aggregator' ),
			);
			wp_localize_script( 'aggregator-admin', 'aggregator', $data );
			wp_enqueue_style( 'aggregator-admin', $this->url( '/css/admin.css' ), array(), $this->version );
			return;
		}
		
		$screen = get_current_screen();
		$post_id = isset( $_GET[ 'post' ] ) ? absint( $_GET[ 'post' ] ) : false;
		
		$this->process_import_terms( $post_id );
		
		if ( $orig_blog_id = get_post_meta( $post_id, '_aggregator_orig_blog_id', true ) ) {
			$orig_post_id = get_post_meta( $post_id, '_aggregator_orig_post_id', true );
			$blog_details = get_blog_details( array( 'blog_id' => $orig_blog_id ) );
			$edit_url = get_home_url( $orig_blog_id ) . '/wp-admin/post.php?action=edit&post=' . absint( $orig_post_id );
			$edit_link = '<a href="' . esc_url( $edit_url ) . '">' . __( 'edit post', 'aggregator' ) . '</a>';
			$message = sprintf( __( 'Sorry, you must edit this post from the %1$s site: %2$s', 'aggregator' ), $blog_details->blogname, $edit_link );
			wp_die( $message );
		}
	}
	
	function post_row_actions( $actions, $post ) {
		if ( ! is_main_site() )
			return $actions;
		if ( $orig_blog_id = get_post_meta( $post->ID, '_aggregator_orig_blog_id', true ) ) {
			foreach ( $actions as $action_name => & $action ) {
				if ( 'view' != $action_name )
					unset( $actions[ $action_name ] );
			}
		}
		return $actions;
	}

	/**
	 * Hooks the WP save_post action, fired after a post has been inserted/updated in the
	 * database, to duplicate the posts in the index site.
	 *
	 * @param int $orig_post_id The ID of the post being saved 
	 * @param object $orig_post A WP Post object of unknown type
	 * @return void
	 **/
	public function save_post( $orig_post_id, $orig_post ) {

		// Are we syncing anything from this site? If not, stop.
		if ( ! $this->get_push_blogs() )
			return;

		// Check if we should be pushing this post, don't if not
		if ( ! $this->push_post_type( $orig_post ) )
			return;

		// Only push published posts
		if ( 'publish' == $orig_post->post_status )
			$this->push_post_data_to_blogs( $orig_post_id, $orig_post );
		else
			$this->delete_pushed_posts( $orig_post_id, $orig_post );
		
	}

	/**
	 * Hooks the WP post_link filter to provide the original
	 * permalink (stored in post meta) when a permalink
	 * is requested from the index blog.
	 *
	 * @param string $permalink The permalink
	 * @param object $post A WP Post object 
	 * @return string A permalink
	 **/
	public function post_link( $permalink, $post ) {
		global $blog_id;
		
		if ( ! is_main_site() )
			return $permalink;

		if ( $original_permalink = get_post_meta( $post->ID, '_aggregator_permalink', true ) )
			return $original_permalink;
		
		return $permalink;
	}

	/**
	 * Hooks the aggregator_sync_meta_key filter from this class which checks
	 * if a meta_key should be synced. If we return false, it won't be.
	 *
	 * @param array $meta_keys The meta_keys which should be unsynced
	 * @return array The meta_keys which should be unsynced
	 **/
	function sync_meta_key( $sync, $meta_key ) {
		$sync_not = array(
			'_edit_last', // Related to edit lock, should be individual to translations
			'_edit_lock', // The edit lock, should be individual to translations
			'_bbl_default_text_direction', // The text direction, should be individual to translations
			'_wp_trash_meta_status',
			'_wp_trash_meta_time',
		);
		if ( in_array( $meta_key, $sync_not ) )
			$sync = false;
		return $sync;
	}

	function template_redirect() {
		$original_permalink = get_post_meta( get_the_ID(), '_aggregator_permalink', true );
		if ( is_single() && is_main_site() && $original_permalink ) {
			wp_redirect( $original_permalink, 301 );
			exit;
		}
	}
	
	// UTILITIES
	// =========

	/**
	 * Pushes the saved post to the relevant portal blogs
	 *
	 * Assembles the post data required for submitting a new post in the portal sites, grabs a list
	 * of portal sites to push to and then runs through each, submitting the post data as a new post.
	 *
	 * @param int $orig_post_id ID of the saved post
	 * @param object $orig_post WP_Post object for the saved post
	 *
	 * @return void
	 */
	function push_post_data_to_blogs( $orig_post_id, $orig_post ) {
		global $current_site, $current_blog;

		if ( $this->recursing )
			return;
		$this->recursing = true;

		// Get post data
		$orig_post_data = get_post( $orig_post_id, ARRAY_A );
		unset( $orig_post_data[ 'ID' ] );

		$orig_post_data = apply_filters( 'aggregator_orig_post_data', $orig_post_data, $orig_post_id );
		
		// Get metadata
		$orig_meta_data = get_post_meta( $orig_post_id );
		
		// Check if there's a featured image
		if ( isset( $orig_meta_data['_thumbnail_id'] ) ){
			$orig_thumbnail = array_shift( wp_get_attachment_image_src( intval( $orig_meta_data['_thumbnail_id'][0] ), 'full' ) );
		}
		
		// Remove any meta keys we explicitly don't want to sync
		foreach ( $orig_meta_data as $meta_key => $meta_rows ) {
			if ( ! $this->allow_sync_meta_key( $meta_key ) )
				unset( $orig_meta_data[ $meta_key ] );
		}
		// Note the following have to be one item arrays, to fit in with the
		// output of get_post_meta.
		$orig_meta_data[ '_aggregator_permalink' ] = array( get_permalink( $orig_post_id ) );
		$orig_meta_data[ '_aggregator_orig_post_id' ] = array( $orig_post_id );
		$orig_meta_data[ '_aggregator_orig_blog_id' ] = array( $current_blog->blog_id );
		
		$orig_meta_data = apply_filters( 'aggregator_orig_meta_data', $orig_meta_data, $orig_post_id );

		// Get terms
		$taxonomies = get_object_taxonomies( $orig_post );
		$orig_terms = array();
		foreach ( $taxonomies as $taxonomy ) {
			$orig_terms[ $taxonomy ] = array();
			$terms = wp_get_object_terms( $orig_post_id, $taxonomy );
			foreach ( $terms as & $term )
				$orig_terms[ $taxonomy ][ $term->slug ] = $term->name;
		}

		/**
		 * Alter the taxonomy terms to be synced.
		 *
		 * Allows plugins or themes to modify the list of taxonomy terms that are due to be pushed
		 * up to the 'portal' site.
		 *
		 * @param array $orig_terms The list of taxonomy terms
		 * @param int $orig_post_id ID of the originating post
		 */
		$orig_terms = apply_filters( 'aggregator_orig_terms', $orig_terms, $orig_post_id );

		// Get the array of sites to sync to
		$sync_destinations = $this->get_push_blogs();

		// Loop through all destinations to perform the sync
		foreach ( $sync_destinations as $sync_destination ) {

			// Just double-check it's an int so we get no nasty errors
			if ( ! intval( $sync_destination ) )
				continue;

			// It should never be, but just check the sync site isn't the current site.
			// That'd be horrific (probably).
			if ( $sync_destination == get_current_blog_id() )
				continue;

			// Okay, fine, switch sites and do the synchronisation dance.
			switch_to_blog( $sync_destination );

			// Acquire ID and update post (or insert post and acquire ID)
			if ( $target_post_id = $this->get_portal_blog_post_id( $orig_post_id, $current_blog->blog_id ) ) {
				$orig_post_data[ 'ID' ] = $target_post_id;
				wp_update_post( $orig_post_data );
			} else {
				$target_post_id = wp_insert_post( $orig_post_data );
			}

			// Delete all metadata
			$target_meta_data = get_post_meta( $target_post_id );
			foreach ( $target_meta_data as $meta_key => $meta_rows )
				delete_post_meta( $target_post_id, $meta_key );

			// Re-add metadata
			foreach ( $orig_meta_data as $meta_key => $meta_rows ) {
				$unique = ( count( $meta_rows ) == 1 );
				foreach ( $meta_rows as $meta_row )
					add_post_meta( $target_post_id, $meta_key, $meta_row, $unique );
			}

			// Migrate the featured image
			if ( isset( $orig_thumbnail ) ) {
				// Get the image from the original site and download to new
				$target_thumbnail = media_sideload_image( $orig_thumbnail, 	$target_post_id );

				// Strip the src out of the IMG tag
				$array = array();
				preg_match( "/src='([^']*)'/i", $target_thumbnail, $array ) ;

				// Get the ID of the attachment, and generate thumbnail
				$target_thumbnail = $this->get_image_id( $array[1] );
				$target_thumbnail_data = wp_generate_attachment_metadata( $target_thumbnail, get_attached_file( $target_thumbnail ) );


				// Add the featured image to the post
				update_post_meta( $target_post_id, '_thumbnail_id', $target_thumbnail );
			}

			// Set terms in the meta data, then schedule a Cron to come along and import them
			// We cannot import them here, as switch_to_blog doesn't affect taxonomy setup,
			// meaning we have the wrong taxonomies in the Global scope.
			update_post_meta( $target_post_id, '_orig_terms', $orig_terms );
			wp_schedule_single_event( time(), 'aggregator_import_terms', array( $target_post_id ) );

			restore_current_blog();

		}
		
		$this->recursing = false;
	}
	
	function get_image_id($image_url) {
		global $wpdb;
		$attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM " . $wpdb->prefix . "posts" . " WHERE guid='%s';", $image_url ));
		return $attachment[0];
	}
	
	function process_import_terms( $target_post_id ) {
		if ( ! $orig_terms = get_post_meta( $target_post_id, '_orig_terms', true ) )
			return;
		foreach ( $orig_terms as $taxonomy => & $terms ) {
			if ( ! taxonomy_exists( $taxonomy ) )
				continue;
			$target_terms = array();
			foreach ( $terms as $slug => $name ) {
				if ( $term = get_term_by( 'name', $name, $taxonomy ) ) {
					$term_id = $term->term_id;
				} else {
					$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
					if ( !is_wp_error( $result ) )
						$term_id = $result[ 'term_id' ];
					else 
						$term_id = 0;
				}
				$target_terms[] = absint( $term_id );
			}
			wp_set_object_terms( $target_post_id, $target_terms, $taxonomy );
		}
	}

	
	function delete_pushed_posts( $orig_post_id, $orig_post ) {
		global $current_site, $current_blog;

		if ( $this->recursing )
			return;
		$this->recursing = true;

		// Get the portal blogs we've pushed this post to
		$portals = $this->get_push_blogs();

		// Loop through each portal and delete this post
		foreach ( $portals as $portal ) {

			switch_to_blog( $portal );

			// Acquire ID and update post (or insert post and acquire ID)
			if ( $target_post_id = $this->get_portal_blog_post_id( $orig_post_id, $current_blog->blog_id ) )
				wp_delete_post ( $target_post_id, true );

			restore_current_blog();

		}
		
		$this->recursing = false;
	}
	
	function get_portal_blog_post_id( $orig_post_id, $orig_blog_id ) {
		$args = array(
			'post_type' => 'post',
			'post_status' => 'any',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_aggregator_orig_post_id',
					'value' => $orig_post_id,
					'type' => 'numeric'
				),
				array(
					'key' => '_aggregator_orig_blog_id',
					'value' => $orig_blog_id,
					'type' => 'numeric',
				)
			),
		);
		$query = new WP_Query( $args );

		if ( $query->have_posts() )
			return $query->post->ID;

		return false;
	}
	
	function allow_sync_meta_key( $meta_key ) {
		// FIXME: Not now, but ultimately should this take into account Babble meta key syncing bans?
		
		switch ( $meta_key ) {
			
			case "_thumbnail_id":
				$allow = false;
				break;
				
			default:
				$allow = true;
			
		}
		
		return apply_filters( 'aggregator_sync_meta_key', $allow, $meta_key );
	}

	protected function get_push_blogs( $portal_id = NULL ) {

		// Grab the current push sites from our site option
		if ( is_null( $portal_id ) ) {
			$sync = get_option( 'aggregator_push_blogs', array() );
		} else { // @todo CHECK THIS - is it right? Do these two options do the same thing?
			$sync = get_site_option( "aggregator_portal_{$portal_id}_blogs", array() );
		}

		/**
		 * Filters the list of blogs to push to.
		 *
		 * Called when a post is saved, this filter can be used to change the sites
		 * that the post is pushed to, overriding the settings.
		 *
		 * @param array $sync Array of site IDs to sync to
		 * @param int $portal_id Blog ID of the portal site
		 */
		$sync = apply_filters( 'aggregator_sync_blogs', $sync, $portal_id );

		return $sync;

	}

	/**
	 * Retrieve the push settings for the current blog.
	 *
	 * Returns an array containing the post types and taxonomies that should be synced
	 * when a post on the current site is saved.
	 *
	 * @return bool|array|WP_Error Errors when used in network admin, false if there are no settings for this
	 *                             blog and an array of settings on success.
	 */
	protected function get_push_settings() {
		global $current_blog;

		// This function is useless in network admin
		if ( is_network_admin() )
			return new WP_Error( 'not_in_network_admin', __("You can't use this function in network admin.") );

		// Set default push settings here
		$defaults = array(
			'post_types' => array( 'post' ),
			'taxonomies' => array( 'category', 'post_tag' ),
		);

		// Get the option
		$push_settings = get_option( 'aggregator_push_settings', $defaults );

		/**
		 * Filters the settings that determine what gets pushed.
		 *
		 * Allows for on-the-fly changes to be made to the settings governing which post types and
		 * taxonomies are synced to a portal. The $current_blog parameter can be used to do this on
		 * a blog-by-blog basis.
		 *
		 * @param array $push_settings Array of settings - see $defaults
		 * @param int $current_blog ID of the blog we're pushing from
		 */
		$push_settings = apply_filters( 'aggregator_push_settings', $push_settings, $current_blog );

		// Just check we have something to return
		if ( $push_settings )
			return $push_settings;

		// Just in case
		return false;

	}

	/**
	 * Check if the given post type should be pushed.
	 *
	 * Queries the push settings to decide whether or not the given post
	 * should be pushed.
	 *
	 * @param WP_Post $post A WP_Post object for the post to check against settings
	 *
	 * @uses $this->get_push_settings()
	 *
	 * @return bool Whether (true) or not (false) a post of this type should be pushed
	 */
	protected function push_post_type( $post ) {

		// Get the push settings
		$push_settings = $this->get_push_settings();

		// Check if this post's type is in the list of types to sync
		if ( in_array( $post->post_type, $push_settings['post_types'] ) )
			return true; // Yep, we should sync this post type

		return false; // Nope

	}

	/**
	 * Sets up our network admin settings page
	 *
	 * @return void
	 */
	public function network_admin_menu() {
		$page = add_submenu_page(
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
	
} // END Aggregator class

$aggregator = new Aggregator();