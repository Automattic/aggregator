<?php if (!defined ('ABSPATH')) die ('No direct access allowed'); ?>

<div class="misc-pub-section" id="aggregator-promotion">
	<?php wp_nonce_field( 'aggregator_status_setting', '_aggregator_status_nonce' ); ?>
	<?php _e( 'Featured:', 'aggregator' ); ?>
		<span class="post-aggregator-promotion-display">
			<?php if ( $promoted ) : ?>
				<?php _e( 'This site and the main site', 'aggregator' ); ?>
			<?php else : ?>
				<?php _e( 'This site only', 'aggregator' ); ?>
			<?php endif; ?>
		</span>
		<a href="#aggregator-promotion" class="edit-aggregator-promotion hide-if-no-js"><?php _e( 'Edit', 'aggregator' ); ?></a>

		<div class="aggregator-promotion-select hide-if-js">
			
			<?php do_action( 'aggregator_pre_controls' ); ?>

			<input type="hidden" name="aggregator-promotion-sequestered" class="aggregator-promotion-sequestered" value="<?php echo esc_attr( $promoted ? '1' : '' ); ?>" />

			<input type="radio" name="aggregator-promotion" id="aggregator-promotion-radio-not-promoted" value="" <?php checked( ! $promoted ); ?> />
				<label for="aggregator-promotion-radio-not-promoted" class="selectit"><?php _e( 'This site only', 'aggregator' ); ?></label><br />
			<input type="radio" name="aggregator-promotion" id="aggregator-promotion-radio-promoted" value="promoted" <?php checked( $promoted ); ?> />
				<label for="aggregator-promotion-radio-promoted" class="selectit"><?php _e( 'This site and the main site', 'aggregator' ); ?></label><br />

			<p>
				<a href="#aggregator-promotion" class="save-post-aggregator-promotion hide-if-no-js button"><?php _e( 'OK', 'aggregator' ); ?></a>
				<a href="#aggregator-promotion" class="cancel-post-aggregator-promotion hide-if-no-js"><?php _e( 'Cancel', 'aggregator' ); ?></a>
			</p>
		</div>
		
</div>