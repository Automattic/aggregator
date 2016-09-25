<?php
/**
 * File contains the main Aggregator class
 *
 * @package Aggregator
 */

require_once( 'class-plugin.php' );

// Load our List Table Class for use in our settings page.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
require_once( 'class-aggregator-jobs-list-table.php' );

/**
 * Sets up the basics for aggregator, like the admin interface
 *
 * @package Feature Posts on Root Blog
 **/
class Aggregator extends Aggregator_Plugin {

	/**
	 * A version number for cache busting, etc.
	 *
	 * @var boolean A version tag for cache-busting
	 */
	public $version;

	/**
	 * Stores the list table instance.
	 *
	 * Holds an object of type Aggregator_Jobs_List_Table for later use
	 *
	 * @var object Aggregator_Jobs_List_Table
	 */
	protected $list_table;

	/**
	 * Initiate!
	 */
	public function __construct() {
		$this->setup( 'aggregator' );

		$this->version = 1.1;

		$this->add_action( 'network_admin_menu' );
		$this->add_action( 'admin_init' );

		if ( is_admin() ) {
			$this->add_action( 'load-post.php', 'load_post_edit' );
			$this->add_action( 'load-post-new.php', 'load_post_edit' );
			$this->add_action( 'init', 'register_post_types', 11 );
			$this->add_action( 'wp_ajax_get_new_job_url' );
			$this->add_action( 'publish_aggregator_job', null, null, 2 );
			$this->add_action( 'add_meta_boxes_aggregator_job' );
			$this->add_action( 'post_action_aggregator_detach' );
			$this->add_filter( 'manage_settings_page_aggregator-network_columns', 'aggregator_edit_columns' );
			$this->add_filter( 'coauthors_meta_box_priority' );
			$this->add_filter( 'coauthors_supported_post_types' );
			$this->add_filter( 'display_post_states', null, 10, 2 );
		}

		$this->add_action( 'template_redirect' );
		$this->add_action( 'admin_enqueue_scripts' );
		$this->add_filter( 'post_link', null, null, 2 );
		$this->add_filter( 'post_row_actions', null, 9999, 2 );
		$this->add_filter( 'page_row_actions', 'post_row_actions', 9999, 2 );

	}

	/**
	 * Initialises the Jobs list table.
	 */
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

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : false; // Input var okay.

		// Don't interfer if the user is detaching the post.
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : false; // Input var okay.
		if ( 'aggregator_detach' === $action ) {
			return;
		}

		if ( $orig_blog_id = get_post_meta( $post_id, '_aggregator_orig_blog_id', true ) ) {

			$orig_post_id = get_post_meta( $post_id, '_aggregator_orig_post_id', true );
			$blog_details = get_blog_details( array( 'blog_id' => $orig_blog_id ) );
			$edit_url = get_home_url( $orig_blog_id ) . '/wp-admin/post.php?action=edit&post=' . absint( $orig_post_id );
			$edit_link = '<a href="' . esc_url( $edit_url ) . '">' . __( 'edit post', 'aggregator' ) . '</a>';
			$message = sprintf( __( 'Sorry, you must edit this post from the %1$s site: %2$s', 'aggregator' ), $blog_details->blogname, $edit_link );
			wp_die( wp_kses_post( $message ) );

		}

	}

	/**
	 * Remove all but the "view" action link on synced posts.
	 *
	 * We don't want folks to edit synced posts on a portal site, so we want to remove the
	 * relevant action links from the posts table.
	 *
	 * @param array  $actions Action links array for filtering.
	 * @param object $post WP_Post object representing the post being displayed.
	 *
	 * @return array Filtered array of actions
	 */
	public function post_row_actions( $actions, $post ) {

		if ( $orig_blog_id = get_post_meta( $post->ID, '_aggregator_orig_blog_id', true ) ) {

			$new_actions = array();

			if ( is_array( $actions ) && array_key_exists( 'view', $actions ) ) {
				$new_actions['view'] = $actions['view'];
			}

			// Contsruct a link for detaching the post
			$edit_post_link = get_edit_post_link( $post->ID );
			$detach_post_link = str_replace( 'action=edit', 'action=aggregator_detach', $edit_post_link );

			$new_actions['detach'] = sprintf( '<a href="%s">Detach</a>', $detach_post_link );

			$actions = $new_actions;

		}

		return $actions;

	}

	/**
	 * Detaches an aggregated post for editing on the portal.
	 *
	 * @param  int $post_id Post ID.
	 * @filter post_action_aggregator_detach
	 */
	public function post_action_aggregator_detach( $post_id ) {

		if ( false === $post_id || is_null( $post_id ) ) {
			return;
		}

		// Delete the post meta that attaches this post to it's parent
		delete_post_meta( $post_id, '_aggregator_orig_post_id' );
		delete_post_meta( $post_id, '_aggregator_orig_blog_id' );

	}

	/**
	 * Hooks the WP post_link filter to provide the original
	 * permalink (stored in post meta) when a permalink
	 * is requested from the index blog.
	 *
	 * @param string $permalink The permalink.
	 * @param object $post A WP Post object.
	 *
	 * @return string A permalink
	 **/
	public function post_link( $permalink, $post ) {

		if ( $original_permalink = get_post_meta( $post->ID, '_aggregator_permalink', true ) ) {
			return $original_permalink; }

		return $permalink;
	}

	/**
	 * Indicate in the post list that a post is a synced post
	 *
	 * @param array $post_states Array of post states.
	 * @param mixed $post The post.
	 *
	 * @return array Array of post states.
	 * @filter display_post_states
	 */
	public function display_post_states( $post_states, $post = null ) {

		if ( is_null( $post ) ) {
			$post = get_post();
		}

		// Operate only on synced posts.
		if ( get_post_meta( $post->ID, '_aggregator_orig_blog_id', true ) ) {
			$post_states[] = esc_html__( 'Aggregated', 'aggregator' );
		}

		return $post_states;

	}

	// UTILITIES.
	/**
	 * Sets up our network admin settings page
	 *
	 * @return void
	 */
	public function network_admin_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Aggregator Setup' ),
			__( 'Aggregator' ),
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

		// We only want the CPT names.
		$cpts = array_keys( $wp_post_types );

		// Get/set the cache.
		$cpt_cache_name = 'cpt_cache_' . get_current_blog_id();
		$cpt_cache = get_site_transient( $cpt_cache_name );
		if ( ! $cpt_cache ) {
			set_site_transient( $cpt_cache_name, $cpts ); }

	}

	/**
	 * Get a list of portals to which a blog should push.
	 *
	 * @param int $source_id ID of the source blog, from which posts will be pushed.
	 *
	 * @return bool|array False if there are no portals to sync to, otherwise an array of portal IDs
	 */
	public function get_portals( $source_id ) {

		// Grab the current portal sites from our site option.
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

		if ( empty( $sync ) ) {
			return false;
		} else { 			return $sync; }

	}

	/**
	 * Get a list of source blogs which push to the given portal
	 *
	 * @param int $portal_id ID of the portal blog, to which posts will be pushed.
	 *
	 * @return bool|array False if there are no sources to sync from, otherwise an array of source blog IDs
	 */
	public function get_sources( $portal_id ) {

		// Grab the current source blogs from our site option.
		$sync = get_site_option( "aggregator_{$portal_id}_source_blogs", array() );

		/**
		 * Filters the list of blogs to push from.
		 *
		 * This filter can be used to change the sites that the post is pushed to, overriding the settings.
		 *
		 * @param array $sync Array of source blog IDs to push from.
		 * @param int $portal_id Blog ID of the portal site.
		 */
		$sync = apply_filters( 'aggregator_source_blogs', $sync, $portal_id );

		if ( empty( $sync ) ) {
			return false;
		} else { 			return $sync; }

	}

	/**
	 * Provide a list of sync jobs.
	 *
	 * @return array|bool An array of Aggregator_Job objects, false on error
	 */
	public function get_jobs() {

		// Get $wpdb so that we can use the network (site) ID later on.
		global $wpdb;

		// Get a list of sites.
		// @todo We need to consider is_large_network() at some point.
		$blogs = wp_get_sites( array(
			'network_id' => $wpdb->siteid,
		) );

		// Should never be empty, but hey, let's play safe.
		if ( empty( $blogs ) ) {
			return false;
		}

		// Holder for the jobs.
		$jobs = array();

		// Loop through each blog, getting any sync jobs.
		foreach ( $blogs as $portal ) {

			// Find any portal jobs.
			// @todo There's probably a better way to do this...
			foreach ( $blogs as $source ) {

				// Don't try and find any blogs syncing to themselves.
				if ( $portal['blog_id'] === $source['blog_id'] ) {
					continue; }

				// Get any jobs.
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

	/**
	 * Registers our job post type.
	 *
	 * The job post type is used to hold details of each aggregation job.
	 */
	public function register_post_types() {

		// Now register our psot type, for managing aggregator job settings.
		register_post_type( 'aggregator_job', array(
			'label' => __( 'Aggregator Jobs' ),
			'labels' => array(
				'singular_name' => __( 'Aggregator Job' ),
				'add_new_item' => __( 'Add New Sync Job' ),
				'edit_item' => __( 'Edit Sync Job' ),
				'new_item' => __( 'New Sync Job' ),
				'view_item' => __( 'View Sync Job' ),
				'not_found' => __( 'No sync jobs found' ),
				'not_found_in_trash' => __( 'No sync jobs found in trash' ),
			),
			'public' => false, // Only access through our network admin UI.
			'show_ui' => true, // Otherwise we can't use post edit screens.
			'show_in_menu' => false, // Otherwise uses show_ui value.
			'supports' => array( 'author' ),
			'register_meta_box_cb' => array( $this, 'meta_boxes' ),
			'taxonomies' => $this->get_taxonomies_for_sync_settings(),
			'query_var' => false,
			'can_export' => false,
		) );

	}

	/**
	 * Adds our custom meta boxes to the Job post type.
	 *
	 * @param object $post WP_Post The post we're editing.
	 */
	public function meta_boxes( $post ) {

		// Replace default submit box.
		remove_meta_box( 'submitdiv', 'aggregator_job', 'side' );
		$this->add_meta_box( 'submitdiv', __( 'Save' ), 'meta_box_submitdiv', 'aggregator_job', 'side', 'high' );

		// Description meta box.
		$this->add_meta_box( 'description', __( 'Description' ), 'meta_box_description', 'aggregator_job', 'normal', 'high' );

		// Post types meta box.
		$this->add_meta_box( 'post_types', __( 'Post Types' ), 'meta_box_post_types', 'aggregator_job', 'normal', 'core' );

		// Taxonomies meta box.
		$this->add_meta_box( 'taxonomies', __( 'Taxonomies' ), 'meta_box_taxonomies', 'aggregator_job', 'normal', 'core' );

		// Author meta box.
		if ( post_type_supports( $post->post_type, 'author' ) ) {
			$post_type_object = get_post_type_object( $post->post_type );
			if ( is_super_admin() || current_user_can( $post_type_object->cap->edit_others_posts ) ) {
				$this->add_meta_box( 'jobauthordiv', __( 'Author' ), 'post_author_meta_box', null, 'normal', 'core' ); }
		}

	}

	/**
	 * Output for the custom Publish meta box on Job edit screens.
	 *
	 * @param object $post WP_Post object for the post being edited.
	 * @param array  $args Array of arguments.
	 */
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
			if ( current_user_can( 'delete_post', $post->ID ) ) {
				if ( ! EMPTY_TRASH_DAYS ) {
					$delete_text = __( 'Delete Permanently' );
				} else {
					$delete_text = __( 'Move to Trash' );
				}
				?>
				<a class="submitdelete deletion" href="<?php echo esc_url( get_delete_post_link( $post->ID ) ); ?>">
					<?php echo esc_html( $delete_text ); ?>
				</a><?php
			} ?>
		</div>

		<div id="publishing-action">
			<span class="spinner"></span>
			<?php
			if ( ! in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) || 0 === $post->ID ) {
				?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Save' ) ?>" />
				<?php submit_button( __( 'Save' ), 'primary button-large', 'publish', false, array( 'accesskey' => 'p' ) ); ?>
				<?php
			} else { ?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Save' ) ?>" />
				<input name="save" type="submit" class="button button-primary button-large" id="publish" accesskey="p" value="<?php esc_attr_e( 'Save' ) ?>" />
			<?php
			} ?>
		</div>
		<div class="clear"></div>
		</div>
		<?php

	}

	/**
	 * Description meta box.
	 *
	 * Shows at the top of the post edit screen, showing the aggregation relationship of the
	 * job currently being edited.
	 *
	 * @param WP_Post $post The current post.
	 * @param array   $args Arguments.
	 */
	public function meta_box_description( $post, $args = array() ) {

		// Grab the portal ID from the REQUEST vars hopefully.
		if ( isset( $_GET['portal'] ) ) { // Input var okay.
			$portal = intval( $_GET['portal'] ); // Input var okay.
			$portal = get_blog_details( $portal );
		} else {
			return;
		}

		// ...and the current blog.
		$source = get_blog_details( get_current_blog_id() );

		// @todo style this to remove the border, heading and background.
		echo '<h1>' . sprintf(
			esc_html__( '%s to %s' ),
			esc_html( $source->domain . $source->path ),
			esc_html( $portal->domain . $portal->path )
		) . '</h1>';

		// Get the portal ID, wherever it may be.
		if ( isset( $_GET['portal'] ) ) { // Input var okay.
			$portal = intval( $_GET['portal'] ); // Input var okay.
		} else {
			$portal = get_post_meta( $post->ID, '_aggregator_portal', true );
		}

		// Sneak the portal ID in here as a hidden field.
		echo sprintf(
			'<input type="hidden" name="portal" value="%d">',
			esc_attr( $portal )
		);

	}

	/**
	 * Meta box to show available post types.
	 *
	 * @param WP_Post $post The Aggregator post object.
	 * @param array   $args Arguments.
	 */
	public function meta_box_post_types( $post, $args = array() ) {

		// Get all the post types.
		$cpts = get_post_types( array( 'public' => true ), 'objects' );

		// Get the post types for this post, if applicable.
		$chosen_cpts = get_post_meta( $post->ID, '_aggregator_post_types', true );

		if ( ! is_array( $chosen_cpts ) ) {
			$chosen_cpts = array();
		}

		echo sprintf(
			'<p>%s</p>',
			esc_html__( 'Choose the post types to include in the sync:' )
		);

		foreach ( $cpts as $cpt ) {

			// We want to check the box if this CPT has been chosen.
			$checked = false;
			if ( in_array( $cpt->name, $chosen_cpts, true ) ) {
				$checked = true;
			}

			// Print the field.
			echo sprintf(
				'<label for="cpt_%1$s"><input type="checkbox" name="cpts[]" id="cpt_%1$s" value="%1$s" %2$s> %3$s</label><br/>',
				esc_attr( $cpt->name ),
				checked( $checked, true, false ),
				esc_attr( $cpt->labels->name )
			);

		}

	}

	/**
	 * Meta box to show available taxonomies.
	 *
	 * @param WP_Post $post The Aggregator post object.
	 * @param array   $args Arguments.
	 */
	public function meta_box_taxonomies( $post, $args = array() ) {

		// Get all the taxonomies.
		$taxos = get_taxonomies( array( 'public' => true ), 'objects' );

		// Get the taxonomies for this post, if applicable.
		$chosen_taxos = get_post_meta( $post->ID, '_aggregator_taxonomies', true );

		if ( ! is_array( $chosen_taxos ) ) {
			$chosen_taxos = array();
		}

		echo sprintf(
			'<p>%s</p>',
			esc_html__( 'Choose the taxonomies to include in the sync:' )
		);

		foreach ( $taxos as $taxo ) {

			// We want to check the box if this Taxonomy has been chosen.
			$checked = false;
			if ( in_array( $taxo->name, $chosen_taxos, true ) ) {
				$checked = true;
			}

			// Print field.
			echo sprintf(
				'<label for="taxo_%1$s"><input type="checkbox" name="taxos[]" id="taxo_%1$s" value="%1$s"%2$s> %3$s</label><br/>',
				esc_attr( $taxo->name ),
				checked( $checked, true, false ),
				esc_attr( $taxo->labels->name )
			);

		}

	}

	/**
	 * Queue up our admin JS
	 */
	public function admin_enqueue_scripts() {

		// Fetch the current screen so we know what admin page we're on.
		global $current_screen, $pagenow;

		// Javascript to be loaded on the post edit screen for our aggregator_job post type.
		wp_register_script(
			'aggregator_job_edit',
			$this->url( 'js/aggregator_job_edit.js' ),
			array( 'jquery' ),
			$this->version,
			true
		);

		// CSS to be loaded on the post edit screen for our aggregator_job post type.
		wp_register_style(
			'aggregator_job_edit',
			$this->url( 'css/aggregator_job_edit.css' ),
			array(),
			$this->version,
			'all'
		);

		// JS for redirecting the user to the appropriate sub-blog post edit page.
		wp_register_script(
			'aggregator_job_create',
			$this->url( 'js/aggregator_job_create.js' ),
			array( 'jquery' ),
			$this->version,
			true
		);

		// CSS to be loaded on our network admin page.
		wp_register_style(
			'aggregator-styles',
			$this->url( 'css/admin.css' ),
			array(),
			$this->version,
			'all'
		);

		// Queue up only on post add/edit screen for our post type.
		if (
			'aggregator_job' === $current_screen->post_type
			&& ( 'post.php' === $pagenow || 'post-new.php' === $pagenow )
		) {

			// Queue the script for inclusion.
			wp_enqueue_script( 'aggregator_job_edit' );

			// Pass to the script a list of taxonomies, rather than scan the page after loading.
			wp_localize_script( 'aggregator_job_edit', 'aggregator_taxonomies', $this->get_taxonomies_for_sync_settings() );

			// Queue up our custom CSS, too.
			wp_enqueue_style( 'aggregator_job_edit' );

		}

		// Queue up only on network admin settings page.
		if ( 'settings_page_aggregator-network' === $current_screen->id ) {

			// Add our custom styling.
			wp_enqueue_style( 'aggregator-styles' );

		}

		// ...when creating a job.
		$action = ( isset( $_GET['action'] ) ) ? wp_unslash( sanitize_key( $_GET['action'] ) ) : false; // Input var okay.
		if ( 'settings_page_aggregator-network' === $current_screen->id && $action ) {

			// Queue up drop-down redirect JS.
			wp_enqueue_script( 'aggregator_job_create' );

			// Add the ajax_url variable.
			wp_localize_script(
				'aggregator_job_create',
				'ajax_object',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'get-new-job-url' ),
				)
			);

		}

	}

	/**
	 * Get a list of available taxonomies.
	 *
	 * @return array Array of taxonomies
	 */
	protected function get_taxonomies_for_sync_settings() {

		// Get all the taxonomies so we can attach to our post type.
		$taxonomies = get_taxonomies( array( 'public' => true ) );

		// We don't want post formats, though.
		unset( $taxonomies['post_format'] );

		return $taxonomies;

	}

	/**
	 * Save the aggregator job.
	 *
	 * @param int     $post_id ID of the Aggregator post.
	 * @param WP_Post $post Post object of the Aggregator post.
	 */
	public function publish_aggregator_job( $post_id, $post ) {

		// Find the portal ID.
		if ( isset( $_POST['portal'] ) ) { // Input var okay.
			$portal = intval( $_POST['portal'] ); // Input var okay.
		} else {
			return;
		}

		// Create a new aggregator job. If one already exists for this porta/source combination
		// the following will load the existing settings.
		$sync_job = new Aggregator_Job( $portal, get_current_blog_id() );

		// Set the post ID. If a post already exists for this job, it will be deleted.
		$sync_job->set_post_id( $post_id );

		// Defaults for the post types and taxonomies.
		$cpts = $sync_job->get_post_types();
		$taxos = $sync_job->get_taxonomies();

		// Get any selected post types.
		if ( isset( $_POST['cpts'] ) ) { // Input var okay.
			$cpts = array_map( 'sanitize_text_field', wp_unslash( $_POST['cpts'] ) ); // Input var okay.
		}

		// Save the new/changed post types.
		$sync_job->set_post_types( $cpts );

		// Get any selected taxonomies.
		if ( isset( $_POST['taxos'] ) ) { // Input var okay.
			$taxos = array_map( 'sanitize_text_field', wp_unslash( $_POST['taxos'] ) ); // Input var okay.
		}

		// Save the new/changed taxonomies.
		$sync_job->set_taxonomies( $taxos );

		// Save the portal ID as post meta.
		$sync_job->set_portal_blog_id_meta();

		// Set the author.
		$sync_job->set_author( $post->post_author );

		// Update the network options for network admin pages.
		$sync_job->update_network_options();

		// Redirect back to network admin settings, with a success message.
		wp_redirect( network_admin_url( 'settings.php?page=aggregator' ) );
		exit;

	}

	/**
	 * Grab the URL to create a new job.
	 */
	public function wp_ajax_get_new_job_url() {

		// Validate the request first.
		check_ajax_referer( 'get-new-job-url', 'security' );

		// Retrieve and sanitise blog IDs.
		$portal = isset( $_POST['portal'] ) ? intval( $_POST['portal'] ) : false; // Input var okay.
		$source = isset( $_POST['source'] ) ? intval( $_POST['source'] ) : false; // Input var okay.

		if ( false === $portal || false === $source ) {
			die();
		}

		// Grab the admin URL.
		// @codingStandardsIgnoreStart
		switch_to_blog( $source );
		// @codingStandardsIgnoreEnd
		$url = add_query_arg( 'portal', $portal, admin_url( 'post-new.php?post_type=aggregator_job' ) );
		restore_current_blog();

		// Send back to the script.
		echo esc_url_raw( $url );
		die();

	}

	/**
	 * Force the column headers for the jobs list table.
	 *
	 * The $screen variable is the same for both our list tables, so we need to manually override
	 * the column headers here for the Jobs List table. This hook accomplishes that.
	 *
	 * @param array $column_headers Unfiltered column headers.
	 *
	 * @return array Filtered column headers
	 */
	public function aggregator_edit_columns( $column_headers ) {

		// Get the portal ID.
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : false; // Input var okay.

		// Set the column headers.
		if ( false !== $id ) {
			$column_headers = array(
				'col_source' => __( 'Sites', 'aggregator' ),
				'col_syncing' => __( 'Syncing', 'aggregator' ),
				'col_author' => __( 'Author', 'aggregator' ),
			);
		}

		return $column_headers;

	}

	/**
	 * Change the priority of the meta box added by Co-Authors Plus
	 *
	 * @param string $priority Co-Authors Plus' preferred priority.
	 *
	 * @return string Our preferred priority
	 */
	public function coauthors_meta_box_priority( $priority ) {
		global $current_screen;

		// Only alter priority on our job post type.
		if ( 'aggregator_job' === $current_screen->post_type ) {
			$priority = 'default';
		}

		return $priority;
	}

	/**
	 * Remove job post type from Co-Authors Plus
	 *
	 * Stops Co-Authors Plus from overtaking the author box on the aggregator_job post type
	 *
	 * @param array $post_types Array of post types Co-Authors Plus will consider.
	 *
	 * @return array Filtered array, with aggregator_job removed
	 */
	public function coauthors_supported_post_types( $post_types ) {

		if ( ! is_array( $post_types ) ) {
			$post_types = array( $post_types ); }

		$post_types_to_remove = array(
			'aggregator_job',
		);

		foreach ( $post_types_to_remove as $post_type_to_remove ) {

			$key = array_search( $post_type_to_remove, $post_types );
			if ( false !== $key ) {
				unset( $post_types[ $key ] ); }
		}

		return $post_types;
	}

	/**
	 * Replace the default author meta box with our own.
	 *
	 * Allows for the selection of any portal site user, not the source site users.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function add_meta_boxes_aggregator_job( $post ) {
		remove_meta_box( 'authordiv', $post->post_type, 'normal' );
	}

	/**
	 * Meta box to select author for aggregated posts.
	 *
	 * @param WP_Post $post Post object for the Aggregator post.
	 */
	public function post_author_meta_box( $post ) {

		// Get the current user.
		$current_user = wp_get_current_user();
		if ( 0 === $current_user->ID ) {
			return;
		}

		// Build args.
		$args = array(
			'who' => 'authors',
			'name' => 'post_author_override',
			'selected' => empty( $post->ID ) ? $current_user->ID : $post->post_author,
			'include_selected' => true,
		);

		// Find the portal ID.
		if ( isset( $_GET['portal'] ) ) { // Input var okay.
			$args['blog_id'] = isset( $_GET['portal'] ) ? intval( $_GET['portal'] ) : false; // Input var okay.
		}

		if ( isset( $args['blog_id'] ) && false !== $args['blog_id'] ) :
			?>
			<p><?php esc_html_e( 'Choose the user to whom posts will be attributed to on the portal site.' ); ?></p>
			<label class="screen-reader-text" for="post_author_override"><?php esc_html_e( 'Author' ); ?></label>
			<?php
			wp_dropdown_users( $args );
		else :
			?>
			<p><?php esc_html_e( 'Portal site isn\'t set - can\'t grab authors.' ); ?></p>
			<?php
		endif;

	}

	/**
	 * Redirect aggregated posts to the original post
	 */
	function template_redirect() {

		// Get the original permalink (if any).
		$original_permalink = get_post_meta( get_the_ID(), '_aggregator_permalink', true );
		if ( empty( $original_permalink ) ) { // Not aggregated.
			return;
		}

		/**
		 * Allow plugins and themes to stop Aggregator from redirecting.
		 *
		 * @param bool $should_redirect Whether (true) or not (false) we should actually perform a redirect.
		 * @param string $original_permalink The permalink of the original post.
		 */
		$should_redirect = apply_filters( 'aggregator_should_redirect', true, $original_permalink );

		// Only redirect individual posts, when told to.
		if ( is_single() && $should_redirect ) {
			wp_redirect( $original_permalink, 301 );
			exit;
		}

	}
} // END Aggregator class

$aggregator = new Aggregator();
