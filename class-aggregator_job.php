<?php

/**
 * Defines an Aggregator job.
 *
 * Serves details of the post types, taxonomies and terms to be synced between
 * two specified blogs in a multisite network
 */
Class Aggregator_Job {

	/**
	 * @var ID of the portal blog
	 */
	public $portal;

	/**
	 * @var ID of the source blog
	 */
	public $source;

	/**
	 * @var ID of the blog we're currently on
	 */
	protected $current_blog;

	/**
	 * Set up all the things
	 *
	 * @param int $portal ID of the portal blog
	 * @param int $source ID of the source blog
	 */
	public function __construct( $portal, $source ) {

		// Validate portal and source IDs

		// Get the current blog ID ($this->current_blog)

		// Get the details of the portal site for this sync job and store in $this->portal

		// Get the details of the source of posts for this job and store in $this->source

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
	 * Get a list of taxonomies to sync under this job
	 *
	 * @return array An array of taxonomy names
	 */
	public function get_taxonomies() {

		return array();

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
	 * Get the author to use on the portal site for all pushed posts
	 *
	 * @return int ID of the author
	 */
	public function get_author() {

		return 1;

	}

}