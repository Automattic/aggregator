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
	 * Set up all the things
	 *
	 * @param int $portal ID of the portal blog
	 * @param int $source ID of the source blog
	 */
	public function __construct( $portal, $source ) {

		// Validate portal and source IDs

		// Get the current blog ID ($this->current_blog)

		// Get the details of the portal blog for this job and store in $this->portal

		// Get the details of the source blog for this job and store in $this->source

		// Switch to source blog and fetch the settings storing them in properties

		// Store in $this->post_id the ID of the custom post type used to store info

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
	 * Get the name of the source blog, for use in admin screens
	 *
	 * @return string Name of the source blog
	 */
	public function get_source_blog_name() {

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

}