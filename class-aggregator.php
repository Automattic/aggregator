<?php
 
/*  Copyright 2014 Code for the People Ltd

                _____________
               /      ____   \
         _____/       \   \   \
        /\    \        \___\   \
       /  \    \                \
      /   /    /          _______\
     /   /    /          \       /
    /   /    /            \     /
    \   \    \ _____    ___\   /
     \   \    /\    \  /       \
      \   \  /  \____\/    _____\
       \   \/        /    /    / \
        \           /____/    /___\
         \                        /
          \______________________/


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
require_once( 'class-aggregator_jobs_list_table.php' );

/**
 * Sets up the basics for aggregator, like the admin interface
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
			$this->add_action( 'init', 'register_post_types', 11 );
			$this->add_action( 'wp_ajax_get_new_job_url' );
			$this->add_action( 'publish_aggregator_job', NULL, NULL, 2 );
			$this->add_action( 'before_delete_post' );
			$this->add_action( 'add_meta_boxes_aggregator_job' );
			$this->add_filter( 'manage_settings_page_aggregator-network_columns', 'aggregator_edit_columns' );
			$this->add_filter( 'coauthors_meta_box_priority' );
			$this->add_filter( 'coauthors_supported_post_types' );
		}

		$this->add_action( 'template_redirect' );
		$this->add_action( 'admin_enqueue_scripts' );
		$this->add_filter( 'post_link', null, null, 2 );
		$this->add_filter( 'post_row_actions', null, 9999, 2 );
		$this->add_filter( 'page_row_actions', 'post_row_actions', 9999, 2 );

		$this->version = 1;

	}

	function admin_init() {

		$this->list_table = new Aggregator_Jobs_List_Table();

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

	/**
	 * Get a list of portals to which a blog should push.
	 *
	 * @param int $source_id ID of the source blog, from which posts will be pushed
	 *
	 * @return bool|array False if there are no portals to sync to, otherwise an array of portal IDs
	 */
	public function get_portals( $source_id ) {

		// Grab the current portal sites from our site option
		$sync = get_site_option( "aggregator_{$source_id}_portal_blogs", array() );

		/**
		 * Filters the list of blogs to push to.
		 *
		 * Called when a post is saved, this filter can be used to change the sites
		 * that the post is pushed to, overriding the settings.
		 *
		 * @param array $sync Array of portal blog IDs to push to
		 * @param int $source_id Blog ID of the source site
		 */
		$sync = apply_filters( 'aggregator_portal_blogs', $sync, $source_id );

		if ( empty( $sync ) )
			return false;
		else
			return $sync;

	}

	/**
	 * Get a list of source blogs which push to the given portal
	 *
	 * @param int $portal_id ID of the portal blog, to which posts will be pushed
	 *
	 * @return bool|array False if there are no sources to sync from, otherwise an array of source blog IDs
	 */
	public function get_sources( $portal_id ) {

		// Grab the current source blogs from our site option
		$sync = get_site_option( "aggregator_{$portal_id}_source_blogs", array() );

		/**
		 * Filters the list of blogs to push from.
		 *
		 * This filter can be used to change the sites that the post is pushed to, overriding the settings.
		 *
		 * @param array $sync Array of source blog IDs to push from
		 * @param int $portal_id Blog ID of the portal site
		 */
		$sync = apply_filters( 'aggregator_source_blogs', $sync, $portal_id );

		if ( empty( $sync ) )
			return false;
		else
			return $sync;

	}

	/**
	 * Provide a list of sync jobs.
	 *
	 * @return array|bool An array of Aggregator_Job objects, false on error
	 */
	public function get_jobs() {

		// Get $wpdb so that we can use the network (site) ID later on
		global $wpdb;

		// Get a list of sites
		// @todo We need to consider is_large_network() at some point
		$blogs = wp_get_sites( array(
			'network_id' => $wpdb->siteid,
		) );

		// Should never be empty, but hey, let's play safe
		if ( empty( $blogs ) ) {
			return false;
		}

		// Holder for the jobs
		$jobs = array();

		// Loop through each blog, getting any sync jobs
		foreach ( $blogs as $portal ) {

			// Find any portal jobs
			// @todo There's probably a better way to do this...
			foreach ( $blogs as $source ) {

				// Don't try and find any blogs syncing to themselves
				if ( $portal['blog_id'] == $source['blog_id'] )
					continue;

				// Get any jobs
				$job = new Aggregator_Job( $portal['blog_id'], $source['blog_id'] );
				if ( is_null( $job->post_id ) ) {
					continue;
				} else {
					$jobs[] = $job;
				}

			}

		}

		return $jobs;

	}

	public function register_post_types() {

		// Now register our psot type, for managing aggregator job settings
		register_post_type( 'aggregator_job', array(
			'label' => __('Aggregator Jobs'),
			'labels' => array(
				'singular_name' => __('Aggregator Job'),
				'add_new_item' => __('Add New Sync Job'),
				'edit_item' => __('Edit Sync Job'),
				'new_item' => __('New Sync Job'),
				'view_item' => __('View Sync Job'),
				'not_found' => __('No sync jobs found'),
				'not_found_in_trash' => __('No sync jobs found in trash'),
			),
			'public' => false, // Only access through our network admin UI
			'show_ui' => true, // Otherwise we can't use post edit screens
			'show_in_menu' => false, // Otherwise uses show_ui value
			'supports' => array( 'author' ),
			'register_meta_box_cb' => array( $this, 'meta_boxes' ),
			'taxonomies' => $this->get_taxonomies_for_sync_settings(),
			'query_var' => false,
			'can_export' => false,
		) );

	}

	public function meta_boxes( $post ) {

		// Replace default submit box
		remove_meta_box( 'submitdiv', 'aggregator_job', 'side' );
		add_meta_box( 'submitdiv', __('Save'), array( $this, 'meta_box_submitdiv' ), 'aggregator_job', 'side', 'high' );

		// Description meta box
		add_meta_box( 'description', __('Description'), array( $this, 'meta_box_description' ), 'aggregator_job', 'normal', 'high' );

		// Post types meta box
		add_meta_box( 'post_types', __('Post Types'), array( $this, 'meta_box_post_types' ), 'aggregator_job', 'normal', 'core' );

		// Taxonomies meta box
		add_meta_box( 'taxonomies', __('Taxonomies'), array( $this, 'meta_box_taxonomies' ), 'aggregator_job', 'normal', 'core' );

		// Author meta box
		if ( post_type_supports($post->post_type, 'author') ) {
			$post_type_object = get_post_type_object($post->post_type);
			if ( is_super_admin() || current_user_can( $post_type_object->cap->edit_others_posts ) )
				add_meta_box('jobauthordiv', __('Author'), array( $this, 'post_author_meta_box' ), null, 'normal', 'core');
		}

	}

	public function meta_box_submitdiv( $post, $args = array() ) {

		?>
		<div id="major-publishing-actions">
		<?php
		/**
		 * Fires at the beginning of the publishing actions section of the Publish meta box.
		 *
		 * @since 2.7.0
		 */
		do_action( 'post_submitbox_start' );
		?>
		<div id="delete-action">
			<?php
			if ( current_user_can( "delete_post", $post->ID ) ) {
				if ( !EMPTY_TRASH_DAYS )
					$delete_text = __('Delete Permanently');
				else
					$delete_text = __('Move to Trash');
				?>
				<a class="submitdelete deletion" href="<?php echo get_delete_post_link($post->ID); ?>"><?php echo $delete_text; ?></a><?php
			} ?>
		</div>

		<div id="publishing-action">
			<span class="spinner"></span>
			<?php
			if ( !in_array( $post->post_status, array('publish', 'future', 'private') ) || 0 == $post->ID ) {
				?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Save') ?>" />
				<?php submit_button( __( 'Save' ), 'primary button-large', 'publish', false, array( 'accesskey' => 'p' ) ); ?>
				<?php
			} else { ?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Save') ?>" />
				<input name="save" type="submit" class="button button-primary button-large" id="publish" accesskey="p" value="<?php esc_attr_e('Save') ?>" />
			<?php
			} ?>
		</div>
		<div class="clear"></div>
		</div>
		<?php

	}

	public function meta_box_description( $post, $args = array() ) {

		// Grab the portal ID from the REQUEST vars hopefully
		$portal = intval( $_REQUEST['portal'] );
		$portal = get_blog_details( $portal );

		// and the current blog
		$source = get_blog_details( get_current_blog_id() );

		// @todo style this to remove the border, heading and background
		echo sprintf(
			__('<h1>%s to %s</h1>'),
			$source->domain . $source->path,
			$portal->domain . $portal->path
		);

		// Get the portal ID, wherever it may be
		if ( isset( $_REQUEST['portal'] ) )
			$portal = intval( $_REQUEST['portal'] );
		else
			$portal = get_post_meta( $post->ID, '_aggregator_portal', true );

		// Sneak the portal ID in here as a hidden field
		echo sprintf(
			'<input type="hidden" name="portal" value="%d">',
			$portal
		);

	}

	public function meta_box_post_types( $post, $args = array() ) {

		// Get all the post types
		$cpts = get_post_types( array( 'public' => true, ), 'objects' );

		// Get the post types for this post, if applicable
		$chosen_cpts = get_post_meta( $post->ID, '_aggregator_post_types', true );

		echo sprintf(
			'<p>%s</p>',
			__('Choose the post types to include in the sync:')
		);

		foreach ( $cpts as $cpt ) {

			// We want to check the box if this CPT has been chosen
			$checked = '';
			if ( in_array( $cpt->name, $chosen_cpts ) )
				$checked = ' checked="checked"';

			// Print the field
			echo sprintf(
				'<label for="cpt_%1$s"><input type="checkbox" name="cpts[]" id="cpt_%1$s" value="%1$s"%2$s> %3$s</label><br/>',
				$cpt->name,
				$checked,
				$cpt->labels->name
			);

		}

	}

	public function meta_box_taxonomies( $post, $args = array() ) {

		// Get all the taxonomies
		$taxos = get_taxonomies( array( 'public' => true ), 'objects' );

		// Get the taxonomies for this post, if applicable
		$chosen_taxos = get_post_meta( $post->ID, '_aggregator_taxonomies', true );

		echo sprintf(
			'<p>%s</p>',
			__('Choose the taxonomies to include in the sync:')
		);

		foreach ( $taxos as $taxo ) {

			// We want to check the box if this Taxonomy has been chosen
			$checked = '';
			if ( in_array( $taxo->name, $chosen_taxos ) )
				$checked = ' checked="checked"';

			// Print field
			echo sprintf(
				'<label for="taxo_%1$s"><input type="checkbox" name="taxos[]" id="taxo_%1$s" value="%1$s"%2$s> %3$s</label><br/>',
				$taxo->name,
				$checked,
				$taxo->labels->name
			);

		}

	}

	public function admin_enqueue_scripts() {

		// Fetch the current screen so we know what admin page we're on
		global $current_screen, $pagenow;

		// Javascript to be loaded on the post edit screen for our aggregator_job post type
		wp_register_script(
			'aggregator_job_edit',
			$this->url( 'js/aggregator_job_edit.js' ),
			array('jquery'),
			$this->version,
			true
		);

		// CSS to be loaded on the post edit screen for our aggregator_job post type
		wp_register_style(
			'aggregator_job_edit',
			$this->url( 'css/aggregator_job_edit.css' ),
			array(),
			$this->version,
			'all'
		);

		// JS for redirecting the user to the appropriate sub-blog post edit page
		wp_register_script(
			'aggregator_job_create',
			$this->url( 'js/aggregator_job_create.js' ),
			array('jquery'),
			$this->version,
			true
		);

		// Queue up only on post add/edit screen for our post type
		if ( 'aggregator_job' == $current_screen->post_type
			&& ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) ) {

			// Queue the script for inclusion
			wp_enqueue_script('aggregator_job_edit');

			// Pass to the script a list of taxonomies, rather than scan the page after loading
			wp_localize_script( 'aggregator_job_edit', 'aggregator_taxonomies', $this->get_taxonomies_for_sync_settings() );

			// Queue up our custom CSS, too
			wp_enqueue_style('aggregator_job_edit');

		}

		// Queue up only on network admin settings page
		$action = ( isset( $_REQUEST['action'] ) ) ? $_REQUEST['action'] : false;
		if ( 'settings_page_aggregator-network' == $current_screen->id && $action ) {

			// Queue up drop-down redirect JS
			wp_enqueue_script('aggregator_job_create');

			// Add the ajax_url variable
			wp_localize_script(
				'aggregator_job_create',
				'ajax_object',
				array( 'ajax_url' => admin_url( 'admin-ajax.php' ) )
			);

		}


	}

	protected function get_taxonomies_for_sync_settings() {

		// Get all the taxonomies so we can attach to our post type
		$taxonomies = get_taxonomies( array( 'public' => true ) );

		// We don't want post formats, though
		unset( $taxonomies['post_format'] );

		return $taxonomies;

	}

	public function publish_aggregator_job( $post_id, $post ) {

		// Find the portal ID
		if ( isset( $_REQUEST['portal'] ) )
			$portal = intval( $_REQUEST['portal'] );
		else
			return;

		// Create a new aggregator job. If one already exists for this porta/source combination
		// the following will load the existing settings
		$sync_job = new Aggregator_Job( $portal, get_current_blog_id() );

		// Set the post ID. If a post already exists for this job, it will be deleted.
		$sync_job->set_post_id( $post_id );

		// Defaults for the post types and taxonomies
		$cpts = $sync_job->get_post_types();
		$taxos = $sync_job->get_taxonomies();

		// Get any selected post types
		if ( isset( $_REQUEST['cpts'] ) ) {

			$cpts = $_REQUEST['cpts'];

			// Make sure there's nothing suspect
			for ( $i = 0; $i < count( $cpts ); $i++ ) {
				$cpts[$i] = esc_attr( $cpts[$i] );
			}

		}

		// Save the new/changed post types
		$sync_job->set_post_types( $cpts );

		// Get any selected taxonomies
		if ( isset( $_REQUEST['taxos'] ) ) {

			$taxos = $_REQUEST['taxos'];

			// Make sure there's nothing suspect
			for ( $i = 0; $i < count( $taxos ); $i++ ) {
				$taxos[$i] = esc_attr( $taxos[$i] );
			}

		}

		// Save the new/changed taxonomies
		$sync_job->set_taxonomies( $taxos );

		// Save the portal ID as post meta
		$sync_job->set_portal_blog_id_meta();

		// Set the author
		$sync_job->set_author( $post->post_author );

		// Update the network options for network admin pages
		$sync_job->update_network_options();

		// Redirect back to network admin settings, with a success message
		wp_redirect( network_admin_url( 'settings.php?page=aggregator' ) );
		exit;

	}

	/**
	 * Handle deletions of the aggregator_job post type
	 *
	 * @param int $post_id ID of the post to be deleted
	 * @param object $post WP_Post object of the post
	 */
	public function before_delete_post( $post_id ) {

		// Only our jobs post type
		if ( 'aggregator_job' != get_post_type( $post_id ) )
			return;

		// Get the portal that relates to this job
		$portal = get_post_meta( $post_id, '_aggregator_portal', true );
		if ( ! $portal )
			return;

		// Get the relevant job object
		$job = new Aggregator_Job( $portal, get_current_blog_id() );
		if ( ! $job )
			return;

		// Delete the job
		$job->delete_job();

		// Redirect back to network admin settings, with a success message
		wp_redirect( network_admin_url( 'settings.php?page=aggregator' ) );
		exit;

	}

	public function wp_ajax_get_new_job_url() {

		// Retrieve and sanitise blog IDs
		$portal = intval( $_POST['portal'] );
		$source = intval( $_POST['source'] );

		// Grab the admin URL
		switch_to_blog( $source );
		$url = add_query_arg( 'portal', $portal, admin_url( 'post-new.php?post_type=aggregator_job' ) );
		restore_current_blog();

		// Send back to the script
		echo $url;
		die();

	}

	/**
	 * Force the column headers for the jobs list table.
	 *
	 * The $screen variable is the same for both our list tables, so we need to manually override
	 * the column headers here for the Jobs List table. This hook accomplishes that.
	 *
	 * @param array $column_headers Unfiltered column headers
	 *
	 * @return array Filtered column headers
	 */
	public function aggregator_edit_columns( $column_headers ) {

		// Get the portal ID
		$id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : false;

		// Set the column headers
		if ( $id ) {
			$column_headers = array (
				'col_source' => __('Sites'),
				'col_syncing' => __('Syncing'),
				'col_author' => __('Author'),
			);
		}

		return $column_headers;

	}

	/**
	 * Change the priority of the meta box added by Co-Authors Plus
	 *
	 * @param string $priority Co-Authors Plus' preferred priority
	 *
	 * @return string Our preferred priority
	 */
	public function coauthors_meta_box_priority( $priority ) {
		global $current_screen;

		// Only alter priority on our job post type
		if ( 'aggregator_job' == $current_screen->post_type )
			$priority = 'default';

		return $priority;
	}

	/**
	 * Remove job post type from Co-Authors Plus
	 *
	 * Stops Co-Authors Plus from overtaking the author box on the aggregator_job post type
	 *
	 * @param array $post_types Array of post types Co-Authors Plus will consider
	 *
	 * @return array Filtered array, with aggregator_job removed
	 */
	public function coauthors_supported_post_types ( $post_types ) {

		if ( ! is_array( $post_types ) )
			$post_types = array( $post_types );

		$post_types_to_remove = array(
			'aggregator_job',
		);

		foreach ( $post_types_to_remove as $post_type_to_remove ) {

			$key = array_search( $post_type_to_remove, $post_types );
			if ( $key !== false )
				unset( $post_types[ $key ] );

		}

		return $post_types;
	}

	/**
	 * Replace the default author meta box with our own.
	 *
	 * Allows for the selection of any portal site user, not the source site users.
	 *
	 * @param WP_Post $post Post object
	 */
	public function add_meta_boxes_aggregator_job( $post ) {
		remove_meta_box( 'authordiv', $post->post_type, 'normal' );
	}

	public function post_author_meta_box( $post ) {
		global $user_ID;

		// Build args
		$args = array(
			'who' => 'authors',
			'name' => 'post_author_override',
			'selected' => empty($post->ID) ? $user_ID : $post->post_author,
			'include_selected' => true
		);

		// Find the portal ID
		if ( isset( $_REQUEST['portal'] ) )
			$args['blog_id'] = intval( $_REQUEST['portal'] );

		?>
		<p><?php _e('Choose the user to whom posts will be attributed to on the portal site.'); ?></p>
		<label class="screen-reader-text" for="post_author_override"><?php _e('Author'); ?></label>
		<?php
		wp_dropdown_users( $args );

	}

	/**
	 * Redirect aggregated posts to the original post
	 */
	function template_redirect() {

		// Get the original permalink (if any)
		$original_permalink = get_post_meta( get_the_ID(), '_aggregator_permalink', true );
		if ( empty ( $original_permalink ) ) // not aggregated
			return;

		/**
		 * Allow plugins and themes to stop Aggregator from redirecting.
		 *
		 * @param bool $should_redirect Whether (true) or not (false) we should actually perform a redirect
		 * @param string $original_permalink The permalink of the original post
		 */
		$should_redirect = apply_filters( 'aggregator_should_redirect', true, $original_permalink );

		// Only redirect individual posts, when told to
		if ( is_single() && $should_redirect ) {
			wp_redirect( $original_permalink, 301 );
			exit;
		}

	}

} // END Aggregator class

$aggregator = new Aggregator();