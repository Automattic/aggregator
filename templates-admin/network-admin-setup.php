<?php

// Get the blog ID from the URL, if set
$id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

// Determine/set the action to perform
$action = ( isset( $_REQUEST['action'] ) ) ? esc_attr( $_REQUEST['action'] ) : 'list';

switch ( $action ) {

	case "add":

		// Just print a dropdown which we can redirect to the edit page.
		// @todo Take account of wp_is_large_network() and AJAX paginate/search accordingly
		$sites = wp_get_sites( array( 'public' => 1 ) );
		?>
		<div class="wrap">
			<h2><?php _e('Add New Sync Site'); ?></h2>
			<form action="<?php echo esc_url( network_admin_url( 'settings.php?page=aggregator&action=edit' ) ); ?>" method="get">
				<input name="page" value="aggregator" type="hidden" />
				<input name="action" value="edit" type="hidden" />
				<label for="id"><?php _e('Choose the site that will act as the "portal" site:'); ?> </label>
				<select name="id" id="id">
					<?php
					foreach ( $sites as $site ) {
						?>
						<option value="<?php echo $site['blog_id']; ?>"><?php echo $site['domain']; ?></option><?php
					}
					?>
				</select>
				<?php submit_button( __('Continue') ); ?>
			</form>
		</div>
		<?php

		break;

	case "edit":

		if ( ! $id )
			wp_die( __('Invalid site ID.') );

		$details = get_blog_details( $id );
		if ( ! can_edit_network( $details->site_id ) )
			wp_die( __( 'You do not have permission to access this page.' ) );

		// Get portal and sync sites info
		$portal = get_blog_details( $id );
		$sync_sites = $this->get_push_sites( $id );

		echo '<h2>' . sprintf( __('Edit Site Sync for %s'), $portal->domain ) . '</h2>';

		?>
		<div class="wrap">
			<form action="settings.php?page=aggregator&action=update" method="post">
				<?php wp_nonce_field( 'edit-sync-site' ); ?>
				<input type="hidden" name="id" value="<?php echo esc_attr( $id ) ?>" />
				<table class="form-table">
					<tbody>
					<tr>
						<th scope="row"><?php _e( 'Sites to pull from' ); ?></th>
						<td>
							<?php echo sprintf( __('Any posts submitted on the sites below will be pushed to %s. Only public sites will be available.'), $portal->domain ); ?><br/>
							<?php
							// List each site with a checkbox
							// @todo Take account of wp_is_large_network() and AJAX paginate/search accordingly
							$sites = wp_get_sites( array( 'public' => 1 ) );
							foreach ( $sites as $site ) {
								$current = in_array( $site['blog_id'], $sync_sites ) ? $site['blog_id'] : false;
								?>
								<label><input name="sync_sites[]" type="checkbox" id="sync_site_<?php echo $site['blog_id']; ?>" value="<?php echo $site['blog_id']; ?>" <?php checked( $site['blog_id'], $current ) ?>> <?php echo $site['domain']; ?></label><br/><?php
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

		check_admin_referer( 'edit-sync-site' );

		if ( ! $id )
			wp_die( __('Invalid site ID.') );

		$details = get_blog_details( $id );
		if ( ! can_edit_network( $details->site_id ) )
			wp_die( __( 'You do not have permission to access this page.' ) );

		// Check they selected something at least
		if ( ! isset( $_POST['sync_sites'] ) )
			wp_die( __("Oops, you didn't select any sites!") );

		// It should be an array. No idea why it wouldn't be
		if ( ! is_array( $_POST['sync_sites'] ) )
			wp_die( __("Oops, you didn't select any sites!") );

		$new_sync_sites = $_POST['sync_sites'];

		// Validate the site IDs
		array_walk( $new_sync_sites, 'intval' );

		// Get existing sync_sites settings
		$sync_sites = get_site_option( 'aggregator_sync_sites', array() );

		// Update the sync sites for this portal. Will override any previous setting.
		$sync_sites[ $id ] = $new_sync_sites;

		// Update the DB
		$update = update_site_option( 'aggregator_sync_sites', $sync_sites );
		if ( ! $update )
			wp_die( __("Oh I'm sorry, something went wrong when updating the database. Perhaps you didn't change anything?") );

		// Set a success message, because we're winners
		$messages = array(
			sprintf( __('Sync sites for %s successfully updated.'), $details->domain ),
		);

		break;

	case "delete":

		if ( ! $id )
			wp_die( __('Invalid site ID.') );

		$details = get_blog_details( $id );
		if ( ! can_edit_network( $details->site_id ) )
			wp_die( __( 'You do not have permission to access this page.' ) );

		// Get existing sync_sites settings
		$sync_sites = get_site_option( 'aggregator_sync_sites', array() );

		// Remove this portal from the sync sites
		unset( $sync_sites[ $id ] );

		// Update the DB
		$update = update_site_option( 'aggregator_sync_sites', $sync_sites );
		if ( ! $update )
			wp_die( __("Oh I'm sorry, something went wrong when updating the database.") );

		// Set a success message, because we're winners
		$messages = array(
			sprintf( __('Sync sites for %s successfully deleted.'), $details->domain ),
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