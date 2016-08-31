<?php

// Get the blog ID from the URL, if set
$portal_id = isset( $_GET['portal'] ) ? intval( $_GET['portal'] ) : 0;
$source_id = isset( $_GET['source'] ) ? intval( $_GET['source'] ) : 0;

// Determine/set the action to perform
$action = ( isset( $_GET['action'] ) ) ? sanitize_text_field( $_GET['action'] ) : 'list';

switch ( $action ) {

	case 'add':

		// Just print a dropdown which we can redirect to the edit page.
		// @todo Take account of wp_is_large_network() and AJAX paginate/search accordingly
		$blogs = wp_get_sites( array( 'public' => 1 ) );
		?>
		<div class="wrap">
			<h2><?php _e( 'Add New Sync Job' ); ?></h2>
			<form class="new_aggregator" action="" method="get">
				<p>
					<label for="portal"><?php _e( 'Choose the site that will act as the "portal" site:' ); ?> </label>
					<select name="portal" id="portal">
						<option selected="selected">-- Select a blog --</option>
						<?php
						foreach ( $blogs as $blog ) {
							?>
							<option value="<?php echo esc_attr( $blog['blog_id'] ); ?>"><?php echo ( SUBDOMAIN_INSTALL ) ? esc_html( $blog['domain'] ) : esc_html( $blog['path'] ); ?></option><?php
						}
						?>
					</select>
				</p>

				<p>
					<label for="source"><?php _e( 'Choose the site that will act as the "source" site:' ); ?> </label>
					<select name="source" id="source">
						<option selected="selected">-- Select a blog --</option>
						<?php
						foreach ( $blogs as $blog ) {
							?>
							<option value="<?php echo esc_attr( $blog['blog_id'] ); ?>"><?php echo ( SUBDOMAIN_INSTALL ) ? esc_html( $blog['domain'] ) : esc_html( $blog['path'] ); ?></option><?php
						}
						?>
					</select>
				</p>
				<?php submit_button( esc_html__( 'Save &amp; Continue' ) ); ?>
			</form>
		</div>
		<?php

		break;

	case 'delete':

		// Check we have valid portal and source IDs
		if ( ! $portal_id || ! $source_id ) {
			wp_die( __( 'Invalid site ID(s).' ) ); }

		// Get the job to be deleted
		$job = new Aggregator_Job( $portal_id, $source_id );

		// Do the deletion
		$job->delete_job();

		// Return to Aggregator Setup and print a message
		wp_redirect( network_admin_url( 'settings.php?page=aggregator&deleted=1' ) );

		break;

}

if ( ! isset( $action ) || ( 'edit' != $action && 'add' != $action ) ) {

	echo '<div class="wrap">';

	echo '<h2>' . get_admin_page_title();

	// Allow network admins to add new Aggregator Jobs
	if ( current_user_can( 'manage_sites' ) ) : ?>
		<a href="<?php echo network_admin_url( 'settings.php?page=aggregator&action=add' ); ?>" class="add-new-h2"><?php echo esc_html__( 'Add New Job' ); ?></a>
	<?php endif;

	echo '</h2>';

	// Print a deletion success message
	if ( isset( $_GET['deleted'] ) ) {
		printf(
			'<div id="message" class="updated below-h2"><p>%s</p></div>',
			sprintf( '%d jobs permanently deleted.', intval( $_GET['deleted'] ) )
		); }

	$this->list_table->prepare_items();
	$this->list_table->display();

	echo '</div>';

}
