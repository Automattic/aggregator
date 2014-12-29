<?php

// Get the blog ID from the URL, if set
$id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

// Determine/set the action to perform
$action = ( isset( $_REQUEST['action'] ) ) ? esc_attr( $_REQUEST['action'] ) : 'list';

switch ( $action ) {

	case "add":

		// Just print a dropdown which we can redirect to the edit page.
		// @todo Take account of wp_is_large_network() and AJAX paginate/search accordingly
		$blogs = wp_get_sites( array( 'public' => 1 ) );
		?>
		<div class="wrap">
			<h2><?php _e('Add New Sync Job'); ?></h2>
			<form class="new_aggregator" action="" method="get">
				<p>
					<label for="portal"><?php _e('Choose the site that will act as the "portal" site:'); ?> </label>
					<select name="portal" id="portal">
						<option selected="selected">-- Select a blog --</option>
						<?php
						foreach ( $blogs as $blog ) {
							?>
							<option value="<?php echo $blog['blog_id']; ?>"><?php echo ( SUBDOMAIN_INSTALL ) ? $blog['domain'] : $blog['path']; ?></option><?php
						}
						?>
					</select>
				</p>

				<p>
					<label for="source"><?php _e('Choose the site that will act as the "source" site:'); ?> </label>
					<select name="source" id="source">
						<option selected="selected">-- Select a blog --</option>
						<?php
						foreach ( $blogs as $blog ) {
							?>
							<option value="<?php echo $blog['blog_id']; ?>"><?php echo ( SUBDOMAIN_INSTALL ) ? $blog['domain'] : $blog['path']; ?></option><?php
						}
						?>
					</select>
				</p>
				<?php submit_button( __('Save &amp; Continue') ); ?>
			</form>
		</div>
		<?php

		break;

	case "delete":

		if ( ! $id )
			wp_die( __('Invalid site ID.') );

		$details = get_blog_details( $id );
		if ( ! can_edit_network( $details->site_id ) )
			wp_die( __( 'You do not have permission to access this page.' ) );

		// Get the list of sync blogs for the portal sync we're deleting
		$sync_blogs = get_site_option( "aggregator_portal_{$id}_blogs", array() );

		// Loop through, removing this portal from the blog option
		foreach ( $sync_blogs as $sync_blog ) {

			switch_to_blog( $sync_blog );

			// Get the existing option
			$push_blogs = get_option( 'aggregator_push_blogs' );

			// Get the key containing this portal site
			$key = array_search( $id, $push_blogs );

			// Remove the key
			if ( $key !== false )
				unset( $push_blogs[ $key ] );

			// If the array is now empty, we may as well delete the option entirely
			if ( empty( $push_blogs ) ) {

				$delete = delete_option( 'aggregator_push_blogs' );
				if ( ! $delete )
					wp_die( __("Oh I'm sorry, something went wrong when updating the database.") );

			} else {

				// Update the existing option
				$update = update_option( 'aggregator_push_blogs', $push_blogs );
				if ( ! $update )
					wp_die( __("Oh I'm sorry, something went wrong when updating the database.") );

			}

			// Clear above vars for sanity
			unset( $push_blogs, $key, $update );

			// Switch back to the network admin
			restore_current_blog();

		}

		// Remove the option for the portal now too
		$delete = delete_site_option( "aggregator_portal_{$id}_blogs" );
		if ( ! $delete )
			wp_die( __("Oh I'm sorry, something went wrong when updating the database.") );

		// Set a success message, because we're winners
		$messages = array(
			sprintf( __('Sync job for %s successfully deleted.'), $details->domain ),
		);

		break;

}

if ( ! isset( $action ) || ( 'edit' != $action && 'add' != $action ) ) {

	echo '<div class="wrap">';

	echo '<h2>' . get_admin_page_title();

	// Allow network admins to add new Aggregator Jobs
	if ( current_user_can( 'manage_sites') ) : ?>
		<a href="<?php echo network_admin_url( 'settings.php?page=aggregator&action=add' ); ?>" class="add-new-h2"><?php echo esc_html__( 'Add New Job' ); ?></a>
	<?php endif;

	echo '</h2>';

	if ( ! empty( $messages ) ) {
		foreach ( $messages as $msg )
			echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
	}

	$this->list_table->prepare_items();
	$this->list_table->display();

	echo '</div>';

}