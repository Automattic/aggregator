<?php
/**
 * File holds the list table class for showing jobs in the admin
 *
 * @package Aggregator
 */

if ( class_exists( 'WP_List_Table' ) ) {

	/**
	 * Class Aggregator_Jobs_List_Table
	 */
	class Aggregator_Jobs_List_Table extends WP_List_Table {

		/**
		 * Constructor, we override the parent to pass our own arguments
		 */
		public function __construct() {
			parent::__construct( array(
				'singular' => 'wp_list_aggregator_job', // Singular label
				'plural' => 'wp_list_aggregator_jobs', // plural label, also this well be one of the table css class
				'ajax'   => false,// We won't support Ajax for this table.
			) );

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
			return array(
				'col_source' => __( 'Sites' ),
				'col_syncing' => __( 'Syncing' ),
				'col_author' => __( 'Author' ),
			);
		}

		/**
		 * Sets up the items to display.
		 *
		 * @todo pagination
		 */
		public function prepare_items() {
			global $aggregator;

			// Make sure we have an array for $this->items.
			if ( ! is_array( $this->items ) ) {
				$this->items = array(); }

			// Get all the jobs for this portal.
			$jobs = $aggregator->get_jobs();

			if ( ! empty( $jobs ) ) {
				$this->items = $jobs; }

		}

		/**
		 * Display the table rows.
		 */
		public function display_rows() {

			if ( empty( $this->items ) ) {
				$this->no_items(); }

			// Get the columns registered in the get_columns and get_sortable_columns methods.
			list( $columns, $hidden ) = $this->get_column_info();

			foreach ( $this->items as $job ) {

				echo '<tr id="record_' . esc_attr( $job->job_id ) . '">';

				foreach ( $columns as $column_name => $column_display_name ) {

					// Set up attributes.
					$html_attributes = array();

					// Style attributes for each col.
					$html_attributes['class'] = esc_attr( "$column_name column-$column_name" );
					if ( in_array( $column_name, $hidden, true ) ) {
						$html_attributes['style'] = 'display:none';
					}

					// Display the cell.
					switch ( $column_name ) {

						case 'col_source':

							// Define the action links order.
							$actions = array(
								'edit' => '',
								'delete' => '',
							);

							// Create the links.
							$actions['edit'] = '<span class="edit"><a href="' . esc_url( $job->get_edit_post_link() ) . '">' . esc_html__( 'Edit Job' ) . '</a></span>';

							// Provide custom link for delete.
							$delete_url = network_admin_url( sprintf(
								'settings.php?page=aggregator&action=delete&portal=%d&source=%d',
								rawurlencode( $job->portal->blog_id ),
								rawurlencode( $job->source->blog_id )
							) );
							$actions['delete']	= '<span class="delete"><a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete' ) . '</a></span>';

							echo "<td $attributes>" . esc_html( $job->source->blogname ) . ' (' . esc_html( $job->source->domain ) . ')' . $this->row_actions( $actions ) . '</td>'; // XSS ok.
							echo '<td ';
							foreach ( $html_attributes as $attribute_name => $attribute_value ) {
								echo sprintf(
									'%s="%s"',
									esc_attr( $attribute_name ),
									esc_attr( $attribute_value )
								);
							}
							echo '>';

							break;

						case 'col_syncing':

							echo "<td $attributes><p>"; // XSS ok.

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
								absint( $job->get_term_count() )
							);

							echo '</p></td>';

							break;

						case 'col_author':

							echo "<td $attributes><p>"; // XSS ok.

							echo esc_html( get_user_by( 'id', $job->get_author() )->display_name );

							echo '</p></td>';

							break;

					}
				}

				echo '</tr>';

			}

		}

		/**
		 * Account for no jobs.
		 */
		public function no_items() {
			esc_html_e( 'Sorry, no jobs were found.' );
		}
	}

}
