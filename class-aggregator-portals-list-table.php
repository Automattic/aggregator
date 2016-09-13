<?php
/**
 * Contains the Aggregator_Portals_List_Table for the network admin
 *
 * @package Aggregator
 */

if ( class_exists( 'WP_List_Table' ) ) {

	/**
	 * List Table class fo rshowing Portals in network admin
	 */
	class Aggregator_Portals_List_Table extends WP_List_Table {

		/**
		 * Constructor, we override the parent to pass our own arguments
		 */
		public function __construct() {
			parent::__construct( array(
				'singular' => 'wp_list_aggregator_site', // Singular label.
				'plural' => 'wp_list_aggregator_sites', // plural label, also this well be one of the table css class.
				'ajax'   => false, // We won't support Ajax for this table.
			) );
		}

		/**
		 * Define the columns that are going to be used in the table
		 *
		 * @return array $columns, the array of columns to use with the table
		 */
		public function get_columns() {
			return array(
				'col_site_domain' => __( 'Portals' ),
				'col_sync_sites' => __( 'Sources' ),
			);
		}

		/**
		 * Prepare our list items for display
		 *
		 * @todo pagination
		 */
		public function prepare_items() {

			// Make sure we have an array for $this->items.
			if ( ! is_array( $this->items ) ) {
				$this->items = array(); }

			// Get all the blogs.
			$blogs = wp_get_sites( array( 'public' => 1 ) );

			// Our array of portals.
			$portals = array();

			// Check if we have sync jobs for those sites.
			foreach ( $blogs as $blog ) {

				if ( $sync_blogs = get_site_option( "aggregator_{$blog['blog_id']}_source_blogs" ) ) {
					$portals[ $blog['blog_id'] ] = $sync_blogs;
				}

				unset( $sync_blogs );

			}

			if ( ! empty( $portals ) ) {
				$this->items = $portals;
			} else { 				$this->items = array(); }

		}

		/**
		 * Display the table rows
		 */
		public function display_rows() {

			// Get the sync sites to display.
			$portals = $this->items;
			if ( empty( $portals ) ) {
				$this->no_items(); }

			// Get the columns registered in the get_columns and get_sortable_columns methods.
			list( $columns, $hidden ) = $this->get_column_info();

			foreach ( $portals as $portal => $sync_blogs ) {

				// Get the site info.
				$portal = get_blog_details( $portal );

				// Open the line.
				echo '<tr id="record_' . esc_attr( $portal->blog_id ) . '">';
				foreach ( $columns as $column_name => $column_display_name ) {

					// Style attributes for each col.
					$class = "class='$column_name column-$column_name'";
					$style = ( in_array( $column_name, $hidden, true ) ) ? ' style="display:none;"' : '';
					$attributes = $class . $style;

					// Display the cell.
					switch ( $column_name ) {

						case 'col_site_domain':

							// Define the action links order.
							$actions = array(
								'edit' => '',
							);

							// Create the links.
							$actions['edit'] = '<span class="edit"><a href="' . esc_url( network_admin_url( 'settings.php?page=aggregator&action=edit&id=' . $portal->blog_id ) ) . '">' . __( 'Edit' ) . '</a></span>';

							echo "<td $attributes>" . esc_html( $portal->domain ) . $this->row_actions( $actions ) . '</td>'; // XSS ok.
							break;

						case 'col_sync_sites':
							echo "<td $attributes>"; // XSS ok.

							// Loop through each sync site getting relevant details for output.
							foreach ( $sync_blogs as $sync_blog ) {

								$sync_blog = get_blog_details( $sync_blog );

								echo esc_html( $sync_blog->domain ) . '<br/>';

							}

							echo '</td>';
							break;
					}
				}
			}

		}

		/**
		 * Show a message when there are no rows
		 */
		function no_items() {
			esc_html_e( 'No sync settings found.' );
		}
	}

}
