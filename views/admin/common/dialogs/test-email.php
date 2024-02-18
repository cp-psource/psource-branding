<?php
$current_user = wp_get_current_user();
?>
<div class="sui-modal sui-modal-sm pstoolkit-test-email">
	<div class="sui-modal-content" id="<?php echo esc_attr( $id ); ?>" aria-labelledby="<?php echo esc_attr( $id ) . '-title'; ?>" aria-describedby="<?php echo esc_attr( $id ) . '-description'; ?>" role="dialog" aria-modal="true">
		<div class="sui-box" role="document">
			<div class="sui-box-header sui-content-center  sui-flatten">
				<button class="sui-button-icon sui-button-float--right" data-modal-close>
					<i class="sui-icon-close sui-md" aria-hidden="true"></i>
					<span class="sui-screen-reader-text"><?php esc_html_e( 'Schließe dieses Modal', 'ub' ); ?></span>
				</button>
				<h3 class="sui-box-title sui-lg" id="<?php echo esc_attr( $id ) . '-title'; ?>"><?php esc_html_e( 'Test-Email', 'ub' ); ?></h3>
			</div>
			<div class="sui-box-body sui-content-center sui-flatten">
				<p class="sui-description" id="<?php echo esc_attr( $id ) . '-description'; ?>"><?php echo esc_html( $description ); ?></p>
				<div class="sui-form-field">
					<label for="pstoolkit-smtp-test-email" class="sui-label"><?php esc_html_e( 'E-Mail-Addresse', 'ub' ); ?></label>
					<input type="email" class="sui-form-control" placeholder="<?php echo esc_attr( $current_user->user_email ); ?>" required="required" value="<?php echo esc_attr( $current_user->user_email ); ?>" />
					<span class="hidden"><?php esc_html_e( 'Test-E-Mail darf nicht leer sein!', 'ub' ); ?></span>
				</div>
			</div>
			<div class="sui-box-footer sui-flatten">
				<div class="sui-form-field sui-actions-right">
					<button class="sui-button" type="button"
						data-nonce="<?php echo esc_attr( $nonce ); ?>"
						data-action="<?php echo esc_attr( $action ); ?>"
					>
						<span class="sui-loading-text"><?php esc_html_e( 'Senden', 'ub' ); ?></span>
						<i class="sui-icon-loader sui-loading" aria-hidden="true"></i>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

