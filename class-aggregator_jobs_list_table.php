<?php

/*
  Copyright 2014 Code for the People Ltd

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

if ( class_exists( 'WP_List_Table' ) ) {

	class Aggregator_Jobs_List_Table extends WP_List_Table {

		/**
		 * Constructor, we override the parent to pass our own arguments
		 */
		public function __construct() {
			parent::__construct( array(
				'singular' => 'wp_list_aggregator_job', // Singular label
				'plural' => 'wp_list_aggregator_jobs', // plural label, also this well be one of the table css class
				'ajax'   => false,// We won't support Ajax for this table
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
		 * @todo pagination
		 */
		public function prepare_items() {
			global $aggregator;

			// Make sure we have an array for $this->items
			if ( ! is_array( $this->items ) ) {
				$this->items = array(); }

			// Get all the jobs for this portal
			$jobs = $aggregator->get_jobs();

			if ( ! empty( $jobs ) ) {
				$this->items = $jobs; }

		}

		public function display_rows() {

			if ( empty( $this->items ) ) {
				$this->no_items(); }

			// Get the columns registered in the get_columns and get_sortable_columns methods
			list( $columns, $hidden ) = $this->get_column_info();

			foreach ( $this->items as $job ) {

				echo '<tr id="record_' . $job->job_id . '">';
				foreach ( $columns as $column_name => $column_display_name ) {

					// Style attributes for each col
					$class = "class='$column_name column-$column_name'";
					$style = '';
					if ( in_array( $column_name, $hidden ) ) { $style = ' style="display:none;"'; }
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
							$actions['edit'] = '<span class="edit"><a href="' . esc_url( $job->get_edit_post_link() ) . '">' . __( 'Edit Job' ) . '</a></span>';

							// Provide custom link for delete
							$delete_url = network_admin_url( sprintf(
								'settings.php?page=aggregator&action=delete&portal=%d&source=%d',
								$job->portal->blog_id,
								$job->source->blog_id
							) );
							$actions['delete']	= '<span class="delete"><a href="' . esc_url( $delete_url ) . '">' . __( 'Delete' ) . '</a></span>';

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
								$job->get_term_count()
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
			_e( 'Sorry, no jobs were found.' );
		}
	}

}
