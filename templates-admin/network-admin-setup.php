<?php

// Get the blog ID from the URL, if set
$id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

// Determine/set the action to perform
$action = ( isset( $_REQUEST['action'] ) ) ? esc_attr( $_REQUEST['action'] ) : 'list';

switch ( $action ) {

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
								?>
						<label><input name="sync_sites[]" type="checkbox" id="sync_site_<?php echo $site['blog_id']; ?>" value="<?php echo $site['blog_id']; ?>"> <?php echo $site['domain']; ?></label><br/><?php
							}
						?>
					</td>
				</tr>
				</tbody>
			</table>
			<?php submit_button(); ?>

		</form>
		<?php
		break;

	case "update":
		// @todo Process form submission
		break;

	default:
		echo '<h2>' . get_admin_page_title() . '</h2>';
		$this->list_table->prepare_items();
		$this->list_table->display();

}