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
			<form action="<?php echo esc_url( network_admin_url( 'settings.php?page=aggregator&action=edit' ) ); ?>" method="get">
				<input name="page" value="aggregator" type="hidden" />
				<input name="action" value="edit" type="hidden" />
				<label for="id"><?php _e('Choose the site that will act as the "portal" site:'); ?> </label>
				<select name="id" id="id">
					<?php
					foreach ( $blogs as $blog ) {
						?>
						<option value="<?php echo $blog['blog_id']; ?>"><?php echo $blog['domain']; ?></option><?php
					}
					?>
				</select>
				<?php submit_button( __('Save &amp; Continue') ); ?>
			</form>
		</div>
		<?php

		break;

	case "edit":

		if ( ! $id )
			wp_die( __('Invalid blog ID.') );

		$details = get_blog_details( $id );
		if ( ! can_edit_network( $details->site_id ) )
			wp_die( __( 'You do not have permission to access this page.' ) );

		// Get portal and sync sites info
		$portal = get_blog_details( $id );
		$sync_blogs = $this->get_push_blogs( $id );

		echo '<h2>' . sprintf( __('Edit Sync Job for %s'), $portal->domain ) . '</h2>';

		?>
		<div class="wrap">
			<form action="settings.php?page=aggregator&action=update" method="post">
				<?php wp_nonce_field( 'edit-sync-job' ); ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ) ?>" />
				<table class="form-table">
					<tbody>
					<tr>
						<th scope="row"><?php _e( 'Blogs to pull from' ); ?></th>
						<td>
							<?php echo sprintf( __('Any posts submitted on the blogs below will be pushed to %s. Only public blogs are available.'), $portal->domain ); ?><br/>
							<?php
							// List each blog with a checkbox
							// @todo Take account of wp_is_large_network() and AJAX paginate/search accordingly
							$blogs = wp_get_sites( array( 'public' => 1 ) );
							foreach ( $blogs as $blog ) {
								$current = in_array( intval( $blog['blog_id'] ), $sync_blogs ) ? $blog['blog_id'] : false;
								?>
								<label><input name="sync_blogs[]" type="checkbox" id="sync_blog_<?php echo $blog['blog_id']; ?>" value="<?php echo $blog['blog_id']; ?>" <?php checked( $blog['blog_id'], $current ) ?>> <?php echo $blog['domain']; ?></label><br/><?php
								unset($current);
							}
							?>
						</td>
					</tr>
					</tbody>
				</table>
				<?php submit_button(); ?>

			</form>
		</div>
		<?php
		break;

	case "update":

		check_admin_referer( 'edit-sync-job' );

		if ( ! $id )
			wp_die( __('Invalid blog ID.') );

		$details = get_blog_details( $id );
		if ( ! can_edit_network( $details->site_id ) )
			wp_die( __( 'You do not have permission to access this page.' ) );

		// Check they selected something at least
		if ( ! isset( $_POST['sync_blogs'] ) )
			wp_die( __("Oops, you didn't select any blogs!") );

		// It should be an array. No idea why it wouldn't be
		if ( ! is_array( $_POST['sync_blogs'] ) )
			wp_die( __("Oops, you didn't select any blogs!") );

		$sync_blogs = $_POST['sync_blogs'];

		// Validate the blog IDs
		array_walk( $sync_blogs, 'intval' );

		// Update the DB
		$update = update_site_option( "aggregator_portal_{$id}_blogs", $sync_blogs );
		if ( ! $update )
			wp_die( __("Oh I'm sorry, something went wrong when updating the database. Perhaps you didn't change anything?") );

		// Now update the sync settings for each sync blog
		// Here, we loop through each blog that we're pushing FROM and ensure that there is an option set IN that
		// site specifying which portals it should push TO.
		foreach ( $sync_blogs as $sync_blog ) {

			switch_to_blog( $sync_blog );

			// Find existing push sites option, if it exists
			$push_blogs = get_option( 'aggregator_push_blogs' );

			// There is an option, so add to it
			if ( false !== $push_blogs ) {
				// Only add this blog if it isn't there already
				if ( ! in_array( $id, $push_blogs ) ) {
					$push_blogs[] = $id; // Added
					$updated = update_option( 'aggregator_push_blogs', $push_blogs );
				}
			}

			// We need to create the option
			else {
				add_option( 'aggregator_push_blogs', array( $id ) );
			}

			unset( $push_blogs );

			// We also need to check if there are push settings, and set them up if not
			$default_push_settings = array(
				'post_types' => array( 'post' ),
				'taxonomies' => array( 'category', 'post_tag' ),
			);
			$push_settings = get_option( 'aggregator_push_settings' );
			if ( ! $push_settings ) {
				// Apply the defaults to create new settings
				$push_settings = $default_push_settings;

				// Allow the settings to be filtered
				// @todo full inline docs
				$push_settings = apply_filters( 'aggregator_push_settings', $push_settings, $sync_blog, $id );

				// Add the settings to the DB
				add_option( 'aggregator_push_settings', $push_settings );
			}

			else {

				// Allow the settings to be filtered
				// @todo full inline docs
				$push_settings = apply_filters( 'aggregator_push_settings', $push_settings, $sync_blog, $id );

				// Update the settings in the DB
				update_option( 'aggregator_push_settings', $push_settings );

			}

			// Cleanup
			unset( $push_blogs, $push_settings );

			restore_current_blog();

		}

		// Set a success message, because we're winners
		$messages = array(
			sprintf( __('Sync job for %s successfully updated.'), $details->domain ),
		);

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

			// Now update the option
			$update = update_option( 'aggregator_push_blogs', $push_blogs );
			if ( ! $update )
				wp_die( __("Oh I'm sorry, something went wrong when updating the database.") );

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

	if ( current_user_can( 'manage_sites') ) : ?>
		<a href="<?php echo network_admin_url( 'settings.php?page=aggregator&action=add' ); ?>" class="add-new-h2"><?php echo esc_html__( 'Add New' ); ?></a>
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