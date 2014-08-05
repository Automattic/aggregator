<?php

if ( class_exists( 'WP_List_Table' ) ) {

	class Aggregator_List_Table extends WP_List_Table {

		/**
		 * Constructor, we override the parent to pass our own arguments
		 */
		public function __construct() {
			parent::__construct( array(
				'singular'=> 'wp_list_aggregator_site', // Singular label
				'plural' => 'wp_list_aggregator_sites', // plural label, also this well be one of the table css class
				'ajax'   => false // We won't support Ajax for this table
			) );
		}

		/**
		 * Define the columns that are going to be used in the table
		 *
		 * @return array $columns, the array of columns to use with the table
		 */
		public function get_columns() {
			pj_error_log( 'manage_{$this->screen->id}_columns during filter', $column_headers );
			$column_headers = array (
				'col_site_id' => __('ID'),
				'col_site_domain' => __('Domain'),
				'col_sync_sites' => __('Syncing From'),
			);
			pj_error_log( 'manage_{$this->screen->id}_columns during filter, modified', $column_headers );
			return $column_headers;
		}

		/**
		 * @todo pagination
		 */
		public function prepare_items() {

			// Get all the sites
			$sites = get_site_option( 'aggregator_sites' );

			if ( ! empty( $sites ) )
				$this->items = $sites;
			else
				$this->items = array(
					22 => array( 17, 18, 19, 20, 21, 23, 24, ),
				);

		}

		public function display_rows() {

			$sites = $this->items;

			// Get the columns registered in the get_columns and get_sortable_columns methods
			list( $columns, $hidden ) = $this->get_column_info();

			pj_error_log('columns', $columns );

			foreach ( $sites as $site_id => $sync_sites ) {

				// Open the line
				echo '<tr id="record_'.$site_id.'">';
				foreach ( $columns as $column_name => $column_display_name ) {

					//Style attributes for each col
					$class = "class='$column_name column-$column_name'";
					$style = "";
					if ( in_array( $column_name, $hidden ) ) $style = ' style="display:none;"';
					$attributes = $class . $style;

					//Display the cell
					switch ( $column_name ) {
						case "col_site_id":  echo '<td '.$attributes.'>'.stripslashes($site_id).'< /td>';   break;
						case "col_site_domain": echo '<td '.$attributes.'>n/a</td>'; break;
						case "col_sync_sites": echo '<td '.$attributes.'>' . var_export( $sync_sites, true ) . '</td>'; break;
					}
				}

			}

		}

		function no_items() {
			_e( 'No sync settings found.' );
		}

	}

}