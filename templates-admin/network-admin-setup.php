<?php
/**
 * Templating for the network admin screen
 *
 * @package Aggregator
 */

// Get the blog ID from the URL, if set.
$portal_id = isset( $_GET['portal'] ) ? intval( $_GET['portal'] ) : 0; // Input var okay.
$source_id = isset( $_GET['source'] ) ? intval( $_GET['source'] ) : 0; // Input var okay.

// Determine/set the action to perform.
$action = ( isset( $_GET['action'] ) ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // Input var okay.

switch ( $action ) {

	case 'add':

		// Just print a dropdown which we can redirect to the edit page.
		$blogs = Aggregator::get_sites();
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Add New Sync Job' ); ?></h2>
			<form class="new_aggregator" action="" method="get">
				<p>
					<label for="portal"><?php esc_html_e( 'Choose the site that will act as the "portal" site:' ); ?> </label>
					<select name="portal" id="portal">
						<option selected="selected">-- Select a blog --</option>
						<?php
						foreach ( $blogs as $blog ) {
							?>
							<option value="<?php echo esc_attr( $blog->blog_id ); ?>"><?php echo esc_html( ( SUBDOMAIN_INSTALL ) ? $blog->domain : $blog->path ); ?></option><?php
						}
						?>
					</select>
				</p>

				<p>
					<label for="source"><?php esc_html_e( 'Choose the site that will act as the "source" site:' ); ?> </label>
					<select name="source" id="source">
						<option selected="selected">-- Select a blog --</option>
						<?php
						foreach ( $blogs as $blog ) {
							?>
							<option value="<?php echo esc_attr( $blog->blog_id ); ?>"><?php echo esc_html( ( SUBDOMAIN_INSTALL ) ? $blog->domain : $blog->path ); ?></option><?php
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

		// Check we have valid portal and source IDs.
		if ( ! $portal_id || ! $source_id ) {
			wp_die( esc_html__( 'Invalid site ID(s).' ) ); }

		// Get the job to be deleted.
		$job = new Aggregator_Job( $portal_id, $source_id );

		// Do the deletion.
		$job->delete_job();

		// Return to Aggregator Setup and print a message.
		wp_redirect( network_admin_url( 'settings.php?page=aggregator&deleted=1' ) );

		break;

}

if ( ! isset( $action ) || ( 'edit' !== $action && 'add' !== $action ) ) {

	echo '<div class="wrap">';

	echo '<h2>' . esc_html( get_admin_page_title() );

	// Allow network admins to add new Aggregator Jobs.
	if ( current_user_can( 'manage_sites' ) ) : ?>
		<a href="<?php echo esc_url( network_admin_url( 'settings.php?page=aggregator&action=add' ) ); ?>" class="add-new-h2"><?php echo esc_html__( 'Add New Job' ); ?></a>
	<?php endif;

	echo '</h2>';

	// Print a deletion success message.
	if ( isset( $_GET['deleted'] ) ) { // Input var okay.
		printf(
			'<div id="message" class="updated below-h2"><p>%s</p></div>',
			sprintf( '%d jobs permanently deleted.', intval( $_GET['deleted'] ) ) // Input var okay.
		); }

	$this->list_table->prepare_items();
	$this->list_table->display();

	echo '</div>';

}
