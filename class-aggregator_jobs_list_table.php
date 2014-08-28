<?php

if ( class_exists( 'WP_List_Table' ) ) {

	class Aggregator_Jobs_List_Table extends WP_List_Table {

		/**
		 * Constructor, we override the parent to pass our own arguments
		 */
		public function __construct( $portal ) {
			parent::__construct( array(
				'singular'=> 'wp_list_aggregator_job', // Singular label
				'plural' => 'wp_list_aggregator_jobs', // plural label, also this well be one of the table css class
				'ajax'   => false // We won't support Ajax for this table
			) );

			// Set the portal if we're given one
			if ( isset( $portal ) )
				$this->portal = intval( $portal );
		}

		/**
		 * Define the columns that are going to be used in the table
		 *
		 * This function is in practice ineffective because we have two list tables that are operating under
		 * the same screen ID. Therefore, a hook (aggregator_edit_columns) in the Aggregator class filters
		 * the column headers to override it and provide the headers below.
		 *
		 * @return array $columns, the array of columns to use with the table
		 */
		public function get_columns() {
			return array (
				'col_source' => __('Sites'),
				'col_syncing' => __('Syncing'),
				'col_author' => __('Author'),
			);
		}

		/**
		 * @todo pagination
		 */
		public function prepare_items() {
			global $aggregator;

			// Make sure we have an array for $this->items
			if ( ! is_array( $this->items ) )
				$this->items = array();

			// Get all the jobs for this portal
			$jobs = $aggregator->get_jobs_for_portal( $this->portal );

			if ( ! empty( $jobs ) )
				$this->items = $jobs;

		}

		public function display_rows() {

			if ( empty( $this->items ) )
				$this->no_items();

			// Get the columns registered in the get_columns and get_sortable_columns methods
			list( $columns, $hidden ) = $this->get_column_info();

			foreach ( $this->items as $job ) {

				echo '<tr id="record_' . $job->job_id . '">';
				foreach ( $columns as $column_name => $column_display_name ) {

					// Style attributes for each col
					$class = "class='$column_name column-$column_name'";
					$style = "";
					if ( in_array( $column_name, $hidden ) ) $style = ' style="display:none;"';
					$attributes = $class . $style;

					// Display the cell
					switch ( $column_name ) {

						case 'col_source':

							// Define the action links order
							$actions = array(
								'edit' => '',
								'delete' => '',
							);

							// Create the links
							$actions['edit'] = '<span class="edit"><a href="' . esc_url( $job->get_edit_post_link() ) . '">' . __( 'Edit' ) . '</a></span>';
							// @todo Provide custom link for delete
							$actions['delete']	= '<span class="delete"><a href="' . esc_url( $job->get_delete_post_link() ) . '">' . __( 'Delete' ) . '</a></span>';

							echo "<td $attributes>" . $job->source->blogname . ' (' . $job->source->domain . ')' . $this->row_actions( $actions ) . '</td>';

							break;

						case 'col_syncing':

							echo "<td $attributes><p>";

							echo sprintf(
								'%d post types',
								count( $job->get_post_types() )
							);
							echo '<br/>';

							echo sprintf(
								'%d taxonomies',
								count( $job->get_taxonomies() )
							);
							echo '<br/>';

							echo sprintf(
								'%d terms',
								count( $job->get_terms() )
							);

							echo '</p></td>';

							break;

						case 'col_author':

							echo "<td $attributes><p>";

							echo get_user_by( 'id', $job->get_author() )->display_name;

							echo '</p></td>';

							break;

					}

				}

				echo '</tr>';

			}

		}

		public function no_items() {
			_e('Sorry, no jobs were found for this portal.');
		}

	}

}