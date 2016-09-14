<?php
/**
 * Contains the main worker class for Aggregator
 *
 * @package Aggregator
 */

require_once( 'class-plugin.php' );

/**
 * Class Aggregate - the main worker performing content aggregation
 *
 * @package Aggregator
 */
class Aggregate extends Aggregator_Plugin {

	/**
	 * An Aggregator_Job instance describing the settings for syncing.
	 *
	 * @var Aggregator_Job
	 */
	protected $job;

	/**
	 * A flag to say whether we're currently recursing, or not.
	 *
	 * @var boolean
	 */
	protected $recursing;

	/**
	 * Make it so!
	 *
	 * @return \Aggregate
	 */
	public function __construct() {
		$this->setup( 'aggregator' );

		// Get the aggregator object for some functions.
		global $aggregator;
		$this->aggregator = $aggregator;

		if ( is_admin() ) {
			$this->add_action( 'save_post', null, 11, 2 );
			$this->add_action( 'load-post.php', 'force_term_import' );
			$this->add_action( 'load-post-new.php', 'force_term_import' );
		}

		$this->add_action( 'aggregator_import_terms', 'process_import_terms' );
		$this->add_filter( 'aggregator_sync_meta_key', 'sync_meta_key', null, 2 );

		$this->recursing = false;
		$this->version = 1;

	}

	/**
	 * Check if a meta key should be pushed to the portal.
	 *
	 * By default, Aggregator will push all meta data to a portal. However, this function will prevent some
	 * meta data from syncing, based on the key. The decision is then filtered, allowing plugins/themes to
	 * check for their own meta data and stop it syncing, if necessary.
	 *
	 * @param string $meta_key Meta data key name to check for.
	 *
	 * @return bool Returns true if the meta data should sync, false if not
	 */
	protected function allow_sync_meta_key( $meta_key ) {
		// TODO: Not now, but ultimately should this take into account Babble meta key syncing bans?
		switch ( $meta_key ) {

			case '_thumbnail_id':
				$allow = false;
				break;

			default:
				$allow = true;

		}

		/**
		 * Decide whether to push some meta data to a portal site.
		 *
		 * Based on the meta key, decide whether or not that post meta data should be pushed up to a portal
		 * along with the rest of the post data.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $allow Whether to allow the meta data to be synced
		 * @param string $meta_key The meta key for this piece of meta data
		 */
		return apply_filters( 'aggregator_sync_meta_key', $allow, $meta_key );
	}

	/**
	 * Check if the given post type should be pushed.
	 *
	 * Queries the push settings to decide whether or not the given post
	 * should be pushed.
	 *
	 * @param string $post_type The post type to check.
	 * @param array  $allowed_types An array of allowed post types.
	 *
	 * @return bool Whether (true) or not (false) a post of this type should be pushed
	 */
	protected function allowed_post_type( $post_type, $allowed_types ) {

		/**
		 * Override the allowed post types as set by a sync job.
		 *
		 * Triggers during a push, allowing for last minute-override of the allowed post types. As such,
		 * the ID of the blog doing the 'pushing' is provided for per-blog overrides.
		 *
		 * @param array $allowed_types Array of allowed post types
		 * @param int $blog_id ID of the blog currently pushing a post
		 */
		$allowed_types = apply_filters( 'aggregator_allowed_post_types', $allowed_types, get_current_blog_id() );

		if ( ! is_array( $allowed_types ) ) {
			$allowed_types = array();
		}

		// Check if this post's type is in the list of types to sync.
		if ( in_array( $post_type, $allowed_types, true ) ) {
			return true; // Yep, we should sync this post type.
		}
		return false; // Nope.

	}

	/**
	 * Reduces the taxonomies down to only those allowed.
	 *
	 * Takes the full list of taxonomy terms and removes any taxonomy terms not whitelisted by settings.
	 *
	 * @param array $taxonomy_terms The taxonomy => term pairs ready to push.
	 *
	 * @return array Filtered list of taxonomy terms to push
	 */
	protected function allowed_taxonomies( array $taxonomy_terms ) {

		// Siphon off the taxonomies we shoud always sync.
		$tax_whitelist = (array) $this->taxonomy_whitelist();

		foreach ( $tax_whitelist as $tax => $terms ) {
			// Copy any terms to the whitelist.
			if ( array_key_exists( $tax, $taxonomy_terms ) ) {
				$tax_whitelist[ $tax ] = $taxonomy_terms[ $tax ];
				unset( $taxonomy_terms[ $tax ] );
			}
		}

		// Now check each taxonomy.
		foreach ( $taxonomy_terms as $taxonomy => $terms ) {

			// Not allowed? Remove it.
			if ( ! $this->allowed_taxonomy( $taxonomy ) ) {
				unset( $taxonomy_terms[ $taxonomy ] ); }
		}

		/**
		 * Allow overriding of non-whitelisted taxonomies and terms.
		 *
		 * @param array $taxonomy_terms Multi-dimensional array of taxonomies and terms
		 * @param int $blog_id ID of the current blog
		 */
		$taxonomy_terms = apply_filters( 'aggregator_taxonomy_terms', $taxonomy_terms, get_current_blog_id() );

		// Now merge back in the whitelisted taxonomies we copied earlier.
		$taxonomy_terms = array_merge( $taxonomy_terms, $tax_whitelist );

		// Send back our butchered list.
		return $taxonomy_terms;

	}

	/**
	 * Checks if a taxonomy is allowed by the settings of the current job.
	 *
	 * @param string $taxonomy Name of the taxonomy.
	 *
	 * @return bool True if the taxonomy is allowed, false otherwise
	 */
	protected function allowed_taxonomy( $taxonomy ) {

		// Is the taxonomy in the list of user-chosen taxonomies?
		if ( ! in_array( $taxonomy, $this->job->get_taxonomies(), true ) ) {
			return false; // No.
		}
		return true; // Yes.

	}

	/**
	 * Reduces the terms down to only those allowed.
	 *
	 * Takes the full list of taxonomy terms and removes any terms not whitelisted by settings.
	 *
	 * @param array $taxonomy_terms The taxonomy => term pairs ready to push.
	 *
	 * @return array Filtered list of taxonomy terms to push
	 */
	protected function allowed_terms( array $taxonomy_terms ) {

		// Siphon off the taxonomies we should always sync.
		$tax_whitelist = (array) $this->taxonomy_whitelist();
		foreach ( $tax_whitelist as $tax => $terms ) {
			// Copy any terms to the whitelist.
			if ( array_key_exists( $tax, $taxonomy_terms ) ) {
				$tax_whitelist[ $tax ] = $taxonomy_terms[ $tax ];
				unset( $taxonomy_terms[ $tax ] );
			}
		}

		// Now check each term.
		foreach ( $taxonomy_terms as $taxonomy => $terms ) {

			// Terms might be empty. If so, all terms are allowed!
			$allowed_terms = $this->job->get_terms( $taxonomy );
			if ( empty( $allowed_terms ) ) {
				continue; // I.e. don't check each term.
			}
			// Check each term.
			foreach ( $terms as $slug => $name ) {

				// Remove the term if it's not allowed.
				if ( ! $this->allowed_term( $slug, $taxonomy ) ) {
					unset( $taxonomy_terms[ $taxonomy ][ $slug ] ); }
			}

			// If there are no terms for this taxonomy at this point, it means that *none* of our
			// white-listed terms are present, and as such we must stop the sync.
			if ( empty( $taxonomy_terms[ $taxonomy ] ) ) {
				return new WP_Error( 'term_whitelist', __( 'Post does not contain any white-listed terms' ) );
			}
		}

		/**
		 * Allow overriding of non-whitelisted taxonomies and terms.
		 *
		 * @param array $taxonomy_terms Multi-dimensional array of taxonomies and terms
		 * @param int $blog_id ID of the current blog
		 */
		$taxonomy_terms = apply_filters( 'aggregator_taxonomy_terms', $taxonomy_terms, get_current_blog_id() );

		// Now merge back in the whitelisted taxonomies we copied earlier.
		$taxonomy_terms = array_merge( $taxonomy_terms, $tax_whitelist );

		return $taxonomy_terms;

	}

	/**
	 * Checks if a term is allowed by the settings of the current job.
	 *
	 * @param string $term Slug of the taxonomy.
	 * @param string $taxonomy Slug of the taxonomy.
	 *
	 * @return bool True if the term is allowed, false otherwise
	 */
	protected function allowed_term( $term, $taxonomy ) {

		// Grab the taxonomy terms for this job.
		$tt = (array) $this->job->get_terms( $taxonomy );

		// If the list of terms is empty, it means ALL terms are allowed.
		if ( empty( $tt ) || is_null( $tt ) ) {
			return true; }

		// Pull out a list of just the terms and taxonomies.
		$taxonomies = wp_list_pluck( $tt, 'taxonomy' );
		$terms = wp_list_pluck( $tt, 'slug' );

		// Does the term exist? We use array_search because we need the key for later...
		$term_found = array_search( $term, $terms );
		if ( false === $term_found ) {
			return false; }

		// Does the taxonomy match?
		$taxonomy_found = array_search( $taxonomy, $taxonomies );
		if ( false === $taxonomy_found ) {
			return false; }

		// Double check the term we found and the taxonomy found are together.
		if ( $term_found !== $taxonomy_found ) {
			return false; }

		// We must have found the term and taxonomy provided.
		return true;

	}

	/**
	 * Check if we should definitely push posts to this destination.
	 *
	 * @param int $destination ID of the portal site we're pushing too.
	 *
	 * @return bool Whether we think it's safe (true) or not (false) to push
	 */
	protected function check_destination( $destination ) {

		// Just double-check it's an int so we get no nasty errors.
		if ( ! intval( $destination ) ) {
			return false; }

		// It should never be, but just check the sync site isn't the current site.
		// That'd be horrific (probably).
		if ( get_current_blog_id() === $destination ) {
			return false; }

		// Make sure the destination site exists! A good thing to be sure of...
		$destination = get_blog_details( $destination, true );
		if ( false === $destination || empty( $destination ) ) {
			return false; }

		return true;

	}

	/**
	 * When a post is deleted at source, delete all it's pushed clones.
	 *
	 * Searches out any versions of this post pushed to portals and deletes each one.
	 *
	 * @param int     $post_id ID of the source post.
	 * @param WP_Post $post WP_Post object of the source post.
	 */
	protected function delete_pushed_posts( $post_id, $post ) {
		// We need to know which blog we're on.
		global $current_blog;

		// Prevent recursion, which will lead to infinite loops.
		if ( $this->recursing ) {
			return; }

		$this->recursing = true;

		// Get the portal blogs we've pushed this post to.
		$portals = (array) $this->aggregator->get_portals( $current_blog->blog_id );

		// Loop through each portal and delete this post.
		foreach ( $portals as $portal ) {

			// Switch to the portal to find the pushed post.
			switch_to_blog( $portal );

			// Acquire ID and update post (or insert post and acquire ID).
			$target_post_id = $this->get_portal_blog_post_id( $post_id, $current_blog->blog_id )
			if ( false !== $target_post_id ) {
				wp_delete_post( $target_post_id, true );
			}

			// Back to the current blog.
			restore_current_blog();

		}

		// Reset recursion flag, we're done with deleting posts for now.
		$this->recursing = false;

	}

	/**
	 * Find the ID of a media item, given it's URL.
	 *
	 * @param string $image_url URL to the media item.
	 *
	 * @return int Media item's ID
	 */
	protected function get_image_id( $image_url ) {
		global $wpdb;

		// Try to retrieve the attachment ID from the cache.
		$cache_key = 'image_id_' . md5( $image_url );
		$attachment = wp_cache_get( $cache_key, 'aggregator' );
		if ( false === $attachment ) {
			// Query the DB to get the attachment ID.
			$attachment = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT ID FROM ' . $wpdb->prefix . 'posts' . " WHERE guid='%s';",
					$image_url
				)
			);

			// Store attachment ID in the cache.
			wp_cache_set( $cache_key, $attachment, 'aggregator' )
		}

		// ID should be the first element of the returned array.
		if ( is_array( $attachment ) && isset( $attachment[0] ) ) {
			return $attachment[0];
		}

		return false;

	}

	/**
	 * Get the ID of any pushed posts.
	 *
	 * Given the ID of an original post, and the source blog it came from, find and return the ID of the
	 * pushed post. To be used only when on a portal site.
	 *
	 * @param int $orig_post_id ID of the source post.
	 * @param int $orig_blog_id ID of the source blog.
	 *
	 * @return bool|int ID of the pushed post, or false if none exists
	 */
	protected function get_portal_blog_post_id( $orig_post_id, $orig_blog_id ) {

		// Default to false.
		$pushed_post_id = false;

		// Build a query, checking for the relevant meta data.
		// We don't want to cache this query as it could lead to failed syncs
		$args = array(
			'post_type' => 'post',
			'post_status' => 'any',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_aggregator_orig_post_id',
					'value' => $orig_post_id,
					'type' => 'numeric',
				),
				array(
					'key' => '_aggregator_orig_blog_id',
					'value' => $orig_blog_id,
					'type' => 'numeric',
				)
			),
		);
		$query = new WP_Query( $args );

		// If there are posts, get the ID of the first one, ignoring any others.
		if ( $query->have_posts() ) {
			$pushed_post_id = $query->post->ID;
		}

		return $pushed_post_id;
	}

	/**
	 * Allow the forcing of term import on the portal blog.
	 *
	 * When an attempt is made to edit a pushed post, this will trigger the import of terms from the
	 * original post to catch situations where the scheduled import didn't run for some reason.
	 *
	 * @return void
	 */
	public function force_term_import() {

		// Get the post ID.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : false; // Input var okay.

		// Do the import dance \o/.
		$this->process_import_terms( $post_id );

	}

	/**
	 * Grab the featured image for the source post, if one exists.
	 *
	 * @param int $post_id ID of the post to be synced.
	 *
	 * @return bool|string Returns false if no thumbnail exists, else the image URL
	 */
	protected function prepare_featured_image( $post_id ) {

		// Check if there's a featured image.
		if ( has_post_thumbnail( $post_id ) ) {

			// Get the ID of the featured image.
			$thumb_id = get_post_thumbnail_id( $post_id );
			if ( empty( $thumb_id ) ) {
				return false; }

			// Get the raw image URL (the first element of the returned array).
			$thumbnail = array_shift( wp_get_attachment_image_src( $thumb_id, 'full' ) );

			return $thumbnail;

		}

		return false;

	}

	/**
	 * Clone the source post data ready for pushing to the portal.
	 *
	 * Takes the post data (content etc) from the source blog post and tweaks it a bit in preparation
	 * for using it to create a new post on the portal site.
	 *
	 * @param int $post_id ID of the source post.
	 *
	 * @return array An array of post data
	 */
	protected function prepare_post_data( $post_id ) {

		// Get post data.
		$post_data = get_post( $post_id, ARRAY_A );
		unset( $post_data['ID'] );

		// Remove post_tag and category as they're covered later on with other taxonomies.
		unset( $post_data['tags_input'] );
		unset( $post_data['post_category'] );

		// Force the post into pending until meta data sync.
		$post_data['post_status'] = 'pending';

		/**
		 * Alter the post data before syncing.
		 *
		 * Allows plugins or themes to modify the main post data due to be pushed to the portal site.
		 *
		 * @param array $post_data Array of post data, such as title and content.
		 * @param int $post_id The ID of the original post.
		 */
		$post_data = apply_filters( 'aggregator_orig_post_data', $post_data, $post_id );

		return $post_data;

	}

	/**
	 * Clone the source post meta data ready for pushing to the portal.
	 *
	 * Takes the meta data from the source blog post and tweaks it a bit in preparation for using it to
	 * create a new post on the portal site.
	 *
	 * @param int $post_id ID of the source post.
	 *
	 * @return array An array of meta data (`meta_key => array( meta_value )`)
	 */
	protected function prepare_meta_data( $post_id ) {
		global $current_blog;

		// Get the source post meta data.
		$meta_data = get_post_meta( $post_id );

		// Remove any meta keys we explicitly don't want to sync.
		foreach ( $meta_data as $meta_key => $meta_rows ) {
			if ( ! $this->allow_sync_meta_key( $meta_key ) ) {
				unset( $meta_data[ $meta_key ] ); }
		}

		/**
		 * Alter the meta data before syncing.
		 *
		 * Allows plugins or themes to modify the post meta data due to be pushed to the portal site
		 *
		 * @param array $meta_data An array of meta data. Note that values must be arrays, even if only
		 *                         single-item arrays.
		 * @param int $post_id ID of the source post
		 */
		$meta_data = apply_filters( 'aggregator_orig_meta_data', $meta_data, $post_id );

		// Add our special Aggregator meta data. Note the following have to be one item arrays, to
		// fit in with the output of get_post_meta.
		$meta_data['_aggregator_permalink'] = array( get_permalink( $post_id ) );
		$meta_data['_aggregator_orig_post_id'] = array( $post_id );
		$meta_data['_aggregator_orig_blog_id'] = array( $current_blog->blog_id );

		return $meta_data;

	}

	/**
	 * Clone the source post terms ready for pushing to the portal.
	 *
	 * Takes the list of taxonomy terms assigned to the post and tweaks it in preparation for pushing
	 * to the portal site.
	 *
	 * @param int     $post_id ID of the source post.
	 * @param WP_Post $post Post object for the source post.
	 *
	 * @return array
	 */
	protected function prepare_terms( $post_id, $post ) {

		// Get the taxonomies for this post.
		$taxonomies = get_object_taxonomies( $post );

		// Prepare to store the taxonomy terms.
		$terms = array();

		// Check each taxonomy.
		foreach ( $taxonomies as $taxonomy ) {

			$terms[ $taxonomy ] = array();

			// Get the terms from this taxonomy attached to the post.
			$tax_terms = wp_get_object_terms( $post_id, $taxonomy );

			// Add each of the attached terms to our new array.
			foreach ( $tax_terms as & $term ) {
				$terms[ $taxonomy ][ $term->slug ] = $term->name; }
		}

		// Our custom list of attached taxonomy terms.
		return $terms;

	}

	/**
	 * Import the terms from the source post to the portal post.
	 *
	 * Runs on a schedule after posts are pushed, because we can push terms at the same time as saving the post.
	 *
	 * @param int $post_id ID of the pushed post on the portal site.
	 */
	public function process_import_terms( $post_id ) {

		// Get the original terms for this post.
		if ( ! $orig_terms = get_post_meta( $post_id, '_orig_terms', true ) ) {
			return; }

		// Check each term for stuff.
		foreach ( $orig_terms as $taxonomy => & $terms ) {

			// Make sure the taxonomy exists before importing.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue; }

			// Storage for terms of this taxonomy that will be imported.
			$target_terms = array();

			// Go through each term.
			foreach ( $terms as $slug => $name ) {

				// Get the term if it exists...
				if ( $term = get_term_by( 'name', $name, $taxonomy ) ) {
					$term_id = $term->term_id;

					// ...otherwise, create it.
				} else {
					$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
					if ( ! is_wp_error( $result ) ) {
						$term_id = $result['term_id'];
					} else {
						$term_id = 0; // Couldn't create term for some reason.
					}
				}

				// Add the term to our import array.
				$target_terms[] = absint( $term_id );
			}

			// Import the terms for this taxonomy.
			wp_set_object_terms( $post_id, $target_terms, $taxonomy );

			// The post *should* be in pending status, so publish it now we have the term data.
			wp_publish_post( $post_id );

		}

	}

	/**
	 * Pushes a featured image from the source post to the portal post.
	 *
	 * @param int    $target_post_id ID of the pushed portal post.
	 * @param string $featured_image URL of the full featured image.
	 */
	protected function push_featured_image( $target_post_id, $featured_image ) {

		// Get the image from the original site and download to new.
		$target_thumbnail = media_sideload_image( $featured_image, 	$target_post_id );
		if ( is_wp_error( $target_thumbnail ) ) {
			error_log( "Failed to add featured image to post $target_post_id" );
			return;
		}

		// Strip the src out of the IMG tag.
		$array = array();
		preg_match( "/src='([^']*)'/i", $target_thumbnail, $array );

		// Get the ID of the attachment.
		$target_thumbnail = $this->get_image_id( $array[1] );

		// Generate thumbnails, because WP usually do this for us.
		$target_thumbnail_data = wp_generate_attachment_metadata( $target_thumbnail, get_attached_file( $target_thumbnail ) );

		// Add the featured image to the target post.
		update_post_meta( $target_post_id, '_thumbnail_id', $target_thumbnail );

	}

	/**
	 * Push the meta data to the portal site.
	 *
	 * @param int   $target_post_id ID of the pushed post.
	 * @param array $orig_meta_data Meta data from the source post.
	 */
	protected function push_meta_data( $target_post_id, $orig_meta_data ) {

		// Delete all metadata from the pushed post.
		$target_meta_data = get_post_meta( $target_post_id );
		foreach ( $target_meta_data as $meta_key => $meta_rows ) {
			delete_post_meta( $target_post_id, $meta_key ); }

		// Add our prepared source meta data.
		foreach ( $orig_meta_data as $meta_key => $meta_rows ) {

			// If only a single value exists for a key, make it a unique key (see add_post_meta).
			$unique = ( count( $meta_rows ) === 1 );

			// Add each piece of meta data.
			foreach ( $meta_rows as $meta_row ) {
				add_post_meta( $target_post_id, $meta_key, $meta_row, $unique ); }
		}

	}

	/**
	 * Pushes the saved post to the relevant portal blogs
	 *
	 * Assembles the post data required for submitting a new post in the portal sites, grabs a list
	 * of portal sites to push to and then runs through each, submitting the post data as a new post.
	 *
	 * @param int    $orig_post_id ID of the saved post.
	 * @param object $orig_post WP_Post object for the saved post.
	 *
	 * @return void
	 */
	protected function push_post_data_to_blogs( $orig_post_id, $orig_post ) {
		global $current_blog;

		if ( $this->recursing ) {
			return; }
		$this->recursing = true;

		// Prepare the post data.
		$orig_post_data = $this->prepare_post_data( $orig_post_id );

		// Prepare the metadata.
		$orig_meta_data = $this->prepare_meta_data( $orig_post_id, $current_blog );

		// Prepare terms.
		$orig_terms = $this->prepare_terms( $orig_post_id, $orig_post );

		// Prepare featured image.
		$featured_image = $this->prepare_featured_image( $orig_post_id );

		// Get the array of sites to sync to.
		$sync_destinations = $this->aggregator->get_portals( $current_blog->blog_id );

		// Loop through all destinations to perform the sync.
		foreach ( $sync_destinations as $sync_destination ) {

			// Check this destination is (probably) okay to push to.
			if ( ! $this->check_destination( $sync_destination ) ) {
				continue; }

			// Get the relevant sync job, if there is one.
			$this->job = new Aggregator_Job( $sync_destination, $current_blog->blog_id );
			if ( ! $this->job->job_id ) {
				continue; // There is no job for this destination.
			}
			// Check if we should be pushing this post, don't if not.
			if ( ! $this->allowed_post_type( $orig_post->post_type, $this->job->get_post_types() ) ) {
				return; }

			// Take the list of associated taxonomy terms and remove any taxonomies not allowed.
			$orig_terms = $this->allowed_taxonomies( $orig_terms );

			// Take the list of associated taxonomy terms and remove any terms not allowed.
			$orig_terms = $this->allowed_terms( $orig_terms );
			if ( is_wp_error( $orig_terms ) ) {
				continue; // See allowed_terms().
			}
			// Okay, fine, switch sites and do the synchronisation dance.
			switch_to_blog( $sync_destination );

			// Acquire ID and update post (or insert post and acquire ID).
			$target_post_id = $this->get_portal_blog_post_id( $orig_post_id, $current_blog->blog_id )
			if ( false !== $target_post_id ) {
				$orig_post_data['ID'] = $target_post_id;
				wp_update_post( $orig_post_data );
			} else {
				$target_post_id = wp_insert_post( $orig_post_data );
			}

			// Push the meta data.
			$this->push_meta_data( $target_post_id, $orig_meta_data );

			// Push the featured image.
			if ( $featured_image ) {
				$this->push_featured_image( $target_post_id, $featured_image ); }

			// Push taxonomies and terms.
			$this->push_taxonomy_terms( $target_post_id, $orig_terms );

			$portal_site_url = get_home_url( $sync_destination );
			// Filter url of portal_site_url.
			$portal_site_url = apply_filters( 'aggregator_remote_get_url', $portal_site_url );

			// Args for wp_remote_get function.
			$args = array(
				'blocking' => false,
			);

			// If WP_CRON_LOCK_TIMEOUT is set and a number, set the curl timeout to a higher value.
			if ( defined( WP_CRON_LOCK_TIMEOUT ) && is_numeric( WP_CRON_LOCK_TIMEOUT ) ) {
				// Add 1 to time, give it a little extra time.
				$timeout = intval( WP_CRON_LOCK_TIMEOUT ) + 1;
				$args['timeout'] = $timeout;
			}

			// Filter args for wp_remote_get.
			$args = apply_filters( 'aggregator_remote_get_args', $args, $portal_site_url );

			// Ping the cron on the portal site to trigger term import now.
			wp_remote_get(
				$portal_site_url . '/wp-cron.php',
				$args
			);

			// Switch back to source blog.
			restore_current_blog();

		}

		$this->recursing = false;

	}

	/**
	 * Schedules the taxonomy terms to be pushed to the portal.
	 *
	 * We can't do it alongside saving so we need to schedule an event to happen quickly. It should
	 * happen straight away, in theory.
	 *
	 * @param int   $target_post_id ID of the pushed portal post.
	 * @param array $orig_terms An array of taxonomy terms to push.
	 */
	protected function push_taxonomy_terms( $target_post_id, $orig_terms ) {

		// Set terms in the meta data, then schedule a Cron to come along and import them.
		// We cannot import them here, as switch_to_blog doesn't affect taxonomy setup,
		// meaning we have the wrong taxonomies in the Global scope.
		update_post_meta( $target_post_id, '_orig_terms', $orig_terms );
		wp_schedule_single_event( time(), 'aggregator_import_terms', array( $target_post_id ) );

	}

	/**
	 * Kicks off the aggregation process.
	 *
	 * Starts the aggregation if there are any portals to push to.
	 *
	 * @param int     $orig_post_id The ID of the post being saved.
	 * @param WP_Post $orig_post Object for the saved post.
	 *
	 * @return void
	 **/
	public function save_post( $orig_post_id, $orig_post ) {
		global $current_blog;

		// Are we syncing anything from this site? If not, stop.
		if ( ! $this->aggregator->get_portals( $current_blog->blog_id ) ) {
			return; }

		// Only push published posts.
		if ( 'publish' === $orig_post->post_status ) {
			$this->push_post_data_to_blogs( $orig_post_id, $orig_post );
		} else {
			$this->delete_pushed_posts( $orig_post_id, $orig_post );
		}

	}

	/**
	 * Checks if a custom field should be synced.
	 *
	 * Hooks the aggregator_sync_meta_key filter from this class which checks
	 * if a meta_key should be synced. If we return false, it won't be.
	 *
	 * @todo Merge this with prepare_meta_data()
	 *
	 * @param bool   $sync Whether (true) or not (false) to sync this meta key.
	 * @param string $meta_key The meta key to make a decision about.
	 *
	 * @return bool Whether or not to sync the key
	 */
	public function sync_meta_key( $sync, $meta_key ) {

		// Specific keys we do not want to sync.
		$sync_not = array(
			'_edit_last', // Related to edit lock, should be individual to translations.
			'_edit_lock', // The edit lock, should be individual to translations.
			'_bbl_default_text_direction', // The text direction, should be individual to translations.
			'_wp_trash_meta_status',
			'_wp_trash_meta_time',
		);

		if ( in_array( $meta_key, $sync_not, true ) ) {
			$sync = false; }

		return $sync;
	}

	/**
	 * Provides a list of taxonomies that should always be synced, and not altered.
	 *
	 * @return array Array of taxonomy => terms
	 */
	protected function taxonomy_whitelist() {

		return array(
			'post_format' => array(),
			'post-collection' => array(),
			'author' => array(),
		);

	}
}

$aggregate = new Aggregate();
