<?php if (!defined ('ABSPATH')) die ('No direct access allowed'); ?>

<div class="misc-pub-section" id="fporb-promotion">
	<?php wp_nonce_field( 'fporb_status_setting', '_fporb_status_nonce' ); ?>
	<?php _e( 'Featured:', 'fporb' ); ?> 
		<span class="post-fporb-promotion-display">
			<?php if ( $promoted ) : ?>
				<?php _e( 'This site and the main site', 'fporb' ); ?>
			<?php else : ?>
				<?php _e( 'This site only', 'fporb' ); ?>
			<?php endif; ?>
		</span>
		<a href="#fporb-promotion" class="edit-fporb-promotion hide-if-no-js"><?php _e( 'Edit', 'fporb' ); ?></a>

		<div class="fporb-promotion-select hide-if-js">
			
			<?php do_action( 'fporb_pre_controls' ); ?>

			<input type="hidden" name="fporb-promotion-sequestered" class="fporb-promotion-sequestered" value="<?php echo esc_attr( $promoted ? '1' : '' ); ?>" />

			<input type="radio" name="fporb-promotion" id="fporb-promotion-radio-not-promoted" value="" <?php checked( ! $promoted ); ?> />
				<label for="fporb-promotion-radio-not-promoted" class="selectit"><?php _e( 'This site only', 'fporb' ); ?></label><br />
			<input type="radio" name="fporb-promotion" id="fporb-promotion-radio-promoted" value="promoted" <?php checked( $promoted ); ?> />
				<label for="fporb-promotion-radio-promoted" class="selectit"><?php _e( 'This site and the main site', 'fporb' ); ?></label><br />

			<p>
				<a href="#fporb-promotion" class="save-post-fporb-promotion hide-if-no-js button"><?php _e( 'OK', 'fporb' ); ?></a>
				<a href="#fporb-promotion" class="cancel-post-fporb-promotion hide-if-no-js"><?php _e( 'Cancel', 'fporb' ); ?></a>
			</p>
		</div>
		
</div>