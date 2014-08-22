<?php

/**
 * Defines an Aggregator job.
 *
 * Serves details of the post types, taxonomies and terms to be synced between
 * two specified blogs in a multisite network
 */
Class Aggregator_Job {

	/**
	 * @var int ID of the portal blog
	 */
	public $portal;

	/**
	 * @var int ID of the source blog
	 */
	public $source;

	/**
	 * @var int ID of the post used to store sync settings
	 */
	protected $post_id;

	/**
	 * @var int ID of the blog we're currently on
	 */
	protected $current_blog;

	/**
	 * @var array Post types to sync
	 */
	protected $post_types = array();

	/**
	 * @var array Taxonomies to sync
	 */
	protected $taxonomies = array();

	/**
	 * @var array Taxonomy terms to sync
	 */
	protected $terms = array();

	/**
	 * @var string An ID constructed as {$source_id}_{$post_id}
	 */
	public $job_id;

	/**
	 * Set up all the things
	 *
	 * @param int $portal ID of the portal blog
	 * @param int $source ID of the source blog
	 */
	public function __construct( $portal, $source ) {

		// Validate portal and source IDs
		if ( ! intval( $portal ) || ! intval( $source ) )
			return;

		// Get the current blog ID ($this->current_blog)
		$this->current_blog = get_current_blog_id();

		// Get the details of the portal blog for this job and store in $this->portal
		$this->portal = get_blog_details( $portal );

		// Get the details of the source blog for this job and store in $this->source
		$this->source = get_blog_details( $source );

		// Switch to source blog if not already on it
		$this->switch_to_blog( $source );

		// Fetch the post of type 'aggregator_job' where;
		//  * post meta 'portal' is equal to $portal
		$jobs_query = new WP_Query( array(
			'post_type' => 'aggregator_job',
			'meta_key' => '_aggregator_portal',
			'meta_value' => $this->get_portal_blog_id(),
			'posts_per_page' => 1,
		) );
		if ( $jobs_query->have_posts() ) {
			while ( $jobs_query->have_posts() ) {
				$jobs_query->the_post();

				// Store the post ID for later
				$this->post_id = get_the_ID();

				// Store post types for later
				$this->post_types = get_post_meta( get_the_ID(), '_aggregator_post_types', true );

				// Store taxonomies for later
				$this->taxonomies = get_post_meta( get_the_ID(), '_aggregator_taxonomies', true );

				// Store terms for later
				$this->terms = wp_get_object_terms( get_the_ID(), $this->taxonomies );
			}
		}

		$this->restore_current_blog();

		// Create a "Job ID" using source and post IDs
		$this->job_id = $this->get_source_blog_id() . '_' . $this->post_id;

	}

	/**
	 * Get the name of the portal blog, for use in admin screens
	 *
	 * @return string Name of the portal blog
	 */
	public function get_portal_blog_name() {

		return '';

	}

	/**
	 * Get the ID of the portal blog.
	 *
	 * @return int ID of the portal blog
	 */
	public function get_portal_blog_id() {

		return $this->portal->blog_id;

	}

	/**
	 * Get the name of the source blog, for use in admin screens
	 *
	 * @return string Name of the source blog
	 */
	public function get_source_blog_name() {

		return '';

	}

	/**
	 * Get the ID of the source blog.
	 *
	 * @return int ID of the source blog
	 */
	public function get_source_blog_id() {

		return '';

	}

	/**
	 * Get the post types that will be synced under this job.
	 *
	 * @return array An array of post type names
	 */
	public function get_post_types() {

		return array();

	}

	/**
	 * Set the post types that are to be included in this job
	 *
	 * @param array $post_types An array of post type names
	 *
	 * @return bool Report on success or failure
	 */
	public function set_post_types( $post_types ) {

		// Update the post meta of $this->post_id

		// Update $this->post_types

	}

	/**
	 * Get a list of taxonomies to sync under this job
	 *
	 * @return array An array of taxonomy names
	 */
	public function get_taxonomies() {

		return array();

	}

	/**
	 * Set the taxonomies that are to be included in this job
	 *
	 * @param array $taxonomies Array of taxonomy names
	 *
	 * @return bool Report on success or failure
	 */
	public function set_taxonomies( $taxonomies ) {

		// Update the taxonomies meta field for $this->post_id

		// Update $this->taxonomies

	}

	/**
	 * Get a list of terms to sync under this job
	 *
	 * @param string $taxonomy The taxonomy whose terms to return
	 *
	 * @return array An array of term names
	 */
	public function get_terms( $taxonomy ) {

		return array();

	}

	/**
	 * Set the terms that are to be included in this job
	 *
	 * @param array $terms Array of taxonomy => terms
	 *
	 * @return bool Success (true) or failure (false)
	 */
	public function set_terms( $terms ) {

		// Update the terms for $this->post_id

		// Update $this->terms

	}

	/**
	 * Get the author to use on the portal site for all pushed posts
	 *
	 * @return int ID of the author
	 */
	public function get_author() {

		return 1;

	}

	/**
	 * Set the author that is to be assigned to pushed posts on the portal
	 *
	 * @param int $author_id ID of an author on the portal site
	 *
	 * @return bool Success (true) or failure (false)
	 */
	public function set_author( $author_id ) {

		// Update the author post meta for $this->post_id

		// Update $this->author

	}

	/**
	 * Get the edit link for this post
	 *
	 * @todo switch_to_blog
	 *
	 * @return string The edit URL
	 */
	public function get_edit_post_link() {

		$url = get_edit_post_link( $this->post_id );

		// Add the portal ID as a parameter to the URL
		$url = add_query_arg( 'portal', $this->get_portal_blog_id(), $url );

		return esc_url( $url );

	}

	/**
	 * Get the delete link for this post
	 *
	 * @todo switch_to_blog
	 *
	 * @return string The delete URL
	 */
	public function get_delete_post_link() {

		$url = get_delete_post_link( $this->post_id, '', true );

		return esc_url( $url );

	}

	protected function switch_to_blog( $blog_id ) {

		if ( $this->current_blog != $blog_id )
			switch_to_blog( $blog_id );

	}

	protected function restore_current_blog() {

		if ( get_current_blog_id() != $this->current_blog )
			restore_current_blog();

	}

}