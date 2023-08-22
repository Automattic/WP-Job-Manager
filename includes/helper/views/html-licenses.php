<?php
/**
 * File containing the view to show the table for managing license keys.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_section_first = 'plugin-license-section--first';
?>
<h1 class="screen-reader-text"><?php esc_html_e( 'Licenses', 'wp-job-manager' ); ?></h1>
<div class="wpjm-licenses">
	<?php if ( ! empty( $licensed_plugins ) ) : ?>
		<?php
		if ( ! empty( $show_bulk_activate ) ) :
			$notices   = WP_Job_Manager_Helper::instance()->get_messages( 'bulk-activate' );
			$has_error = in_array( 'error', array_column( $notices, 'type' ), true );
			?>
		<div class="wpjm-bulk-activate">
			<b class="wpjm-bulk-activate--title">
				<?php esc_html_e( 'Activate Job Manager Licenses', 'wp-job-manager' ); ?>
			</b>
			<div class="wpjm-bulk-activate--description">
				<?php esc_html_e( 'Activate all licenses at once. Easy, everything in one place.', 'wp-job-manager' ); ?>
			</div>

			<form method="post" class="wpjm-bulk-activate--form">
				<input type="hidden" name="action" value="bulk_activate" />
				<?php wp_nonce_field( 'wpjm-manage-license' ); ?>
				<?php
				foreach ( $licensed_plugins as $product_slug => $plugin_data ) :
					$license = WP_Job_Manager_Helper::instance()->get_plugin_license( $product_slug );
					if ( empty( $license['license_key'] ) ) :
						?>
					<input type="hidden" name="product_slugs[]" value="<?php echo esc_attr( $product_slug ); ?>"/>
						<?php
					endif;
				endforeach;
				?>
				<input type="text" name="license_key" class="wpjm-bulk-activate--field<?php echo $has_error ? ' wpjm-bulk-activate--field-error' : ''; ?>" placeholder="<?php esc_attr_e( 'ENTER YOUR LICENSE KEY', 'wp-job-manager' ); ?>"/>
				<input type="submit" class="button button-primary wpjm-bulk-activate--button" value="<?php esc_attr_e( 'Activate License', 'wp-job-manager' ); ?>" />
			</form>
			<?php
			foreach ( $notices as $message ) {
				echo '<div class="notice inline notice-' . esc_attr( $message['type'] ) . ' wpjm-bulk-activate--notice"><p>' . wp_kses_post( $message['message'] ) . '</p></div>';
			}
			?>
		</div>
		<?php endif; ?>
		<form method="post" class="plugin-license-search">
			<input type="search" class="plugin-license-search-field" name="s" value="<?php echo esc_attr( $search_term ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Search', 'wp-job-manager' ); ?>" />
			<input type="submit" class="button plugin-license-search-button" value="<?php esc_attr_e( 'Search', 'wp-job-manager' ); ?>" />
		</form>
		<?php if ( ! empty( $active_plugins ) ) : ?>
		<div class='plugin-license-section <?php echo esc_attr( $plugin_section_first ); ?>'>
			<?php
			$plugin_section_first = '';
			// translators: placeholder is the number of active addons, which will never be zero.
			printf( esc_html__( 'Active (%d)', 'wp-job-manager' ), count( $active_plugins ) );
			?>
		</div>
			<?php foreach ( $active_plugins as $product_slug => $plugin_data ) : ?>
				<?php
				$license = WP_Job_Manager_Helper::instance()->get_plugin_license( $product_slug );
				?>
		<div class="license-row">
				<?php // translators: placeholder is the addon name. ?>
			<img src="<?php echo esc_url( JOB_MANAGER_PLUGIN_URL . '/assets/images/wpjm-logo.png' ); ?>" aria-hidden="true" alt="<?php echo esc_attr( sprintf( __( 'Plugin Icon for %s', 'wp-job-manager' ), $plugin_data['Name'] ) ); ?>" class="plugin-license-icon"/>
			<div class="plugin-info">
				<?php echo esc_html( $plugin_data['Name'] ); ?>
				<div class="plugin-author">
					<?php
					$author = $plugin_data['Author'];
					if ( ! empty( $plugin_data['AuthorURI'] ) ) {
						$author = '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '">' . wp_kses_post( $plugin_data['Author'] ) . '</a>';
					}
					echo wp_kses_post( $author );
					?>
				</div>
			</div>
			<div class="plugin-license">
				<?php
				$notices = WP_Job_Manager_Helper::instance()->get_messages( $product_slug );
				if ( empty( $notices ) && ! empty( $license['errors'] ) ) {
					$notices = [];
					foreach ( $license['errors'] as $key => $error_message ) {
						$notices[] = [
							'type'    => 'error',
							'message' => $error_message,
						];
					}
				}
				if ( apply_filters( 'wpjm_display_license_form_for_addon', true, $product_slug ) ) {
					?>
					<form method="post" class="plugin-license-form">
						<?php wp_nonce_field( 'wpjm-manage-license' ); ?>
						<img src="<?php echo esc_url( JOB_MANAGER_PLUGIN_URL . '/assets/images/icons/checkmark-icon.svg' ); ?>" class='plugin-license-checkmark' aria-hidden='true' alt='<?php esc_attr_e( 'Plugin is activated', 'wp-job-manager' ); ?>'/>
						<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_action" name="action" value="deactivate"/>
						<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_plugin" name="product_slug" value="<?php echo esc_attr( $product_slug ); ?>"/>

						<label for="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_license_key" class="plugin-license-label"><?php esc_html_e( 'LICENSE', 'wp-job-manager' ); ?></label>
						<input type="text" disabled="disabled" class="plugin-license-field" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_license_key" name="license_key" placeholder="XXXX-XXXX-XXXX-XXXX" value="<?php echo esc_attr( $license['license_key'] ); ?>"/>

						<input type="submit" class="button plugin-license-button" name="submit" value="<?php esc_attr_e( 'Deactivate License', 'wp-job-manager' ); ?>" />
					</form>
					<?php
				}
				do_action( 'wpjm_manage_license_page_after_license_form', $product_slug );
				?>
			</div>
				<?php
				foreach ( $notices as $message ) {
					echo '<div class="notice inline notice-' . esc_attr( $message['type'] ) . ' plugin-license-notice"><p>' . wp_kses_post( $message['message'] ) . '</p></div>';
				}
				?>
		</div>
	<?php endforeach; ?>
		<?php endif; ?>
		<?php if ( ! empty( $inactive_plugins ) ) : ?>
			<div class='plugin-license-section <?php echo esc_attr( $plugin_section_first ); ?>'>
			<?php
				$plugin_section_first = '';
				// translators: placeholder is the number of inactive addons, which will never be zero.
				printf( esc_html__( 'Inactive (%d)', 'wp-job-manager' ), count( $inactive_plugins ) );
			?>
				</div>
			<?php foreach ( $inactive_plugins as $product_slug => $plugin_data ) : ?>
				<?php
				$license = WP_Job_Manager_Helper::instance()->get_plugin_license( $product_slug );
				?>
				<div class="license-row">
					<?php // translators: placeholder is the addon name. ?>
					<img src="<?php echo esc_url( JOB_MANAGER_PLUGIN_URL . '/assets/images/wpjm-logo.png' ); ?>" alt="<?php echo esc_attr( sprintf( __( 'Plugin Icon for %s', 'wp-job-manager' ), $plugin_data['Name'] ) ); ?>" class="plugin-license-icon"/>
					<div class="plugin-info">
						<?php echo esc_html( $plugin_data['Name'] ); ?>
						<div class="plugin-author">
							<?php
							$author = $plugin_data['Author'];
							if ( ! empty( $plugin_data['AuthorURI'] ) ) {
								$author = '<a href="' . esc_url( $plugin_data['AuthorURI'] ) . '">' . wp_kses_post( $plugin_data['Author'] ) . '</a>';
							}
							echo wp_kses_post( $author );
							?>
						</div>
					</div>
					<div class="plugin-license">
						<?php
						$notices = WP_Job_Manager_Helper::instance()->get_messages( $product_slug );
						if ( empty( $notices ) && ! empty( $license['errors'] ) ) {
							$notices = [];
							foreach ( $license['errors'] as $key => $error_message ) {
								$notices[] = [
									'type'    => 'error',
									'message' => $error_message,
								];
							}
						}
						if ( apply_filters( 'wpjm_display_license_form_for_addon', true, $product_slug ) ) {
							$has_error = in_array( 'error', array_column( $notices, 'type' ), true );
							?>
							<form method="post" class='plugin-license-form'>
								<?php wp_nonce_field( 'wpjm-manage-license' ); ?>
								<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_action" name="action" value="activate"/>
								<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_plugin" name="product_slug" value="<?php echo esc_attr( $product_slug ); ?>"/>
								<label for="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_license_key" class="plugin-license-label"><?php esc_html_e( 'LICENSE', 'wp-job-manager' ); ?></label>
								<input type="text" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_license_key" class="plugin-license-field<?php echo $has_error ? ' plugin-license-field--error' : ''; ?>" name="license_key" placeholder="XXXX-XXXX-XXXX-XXXX"/>
								<input type="submit" class="button plugin-license-button" name="submit" value="<?php esc_attr_e( 'Activate License', 'wp-job-manager' ); ?>" />
							</form>
							<?php
						}
						do_action( 'wpjm_manage_license_page_after_license_form', $product_slug );
						?>
					</div>
					<?php
					foreach ( $notices as $message ) {
						echo '<div class="notice inline notice-' . esc_attr( $message['type'] ) . ' plugin-license-notice"><p>' . wp_kses_post( $message['message'] ) . '</p></div>';
					}
					?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php // translators: Placeholder %s is the lost license key URL. ?>
		<p><?php printf( wp_kses_post( __( 'Lost your license key? <a href="%s">Retrieve it here</a>.', 'wp-job-manager' ) ), 'https://wpjobmanager.com/lost-license-key/' ); ?></p>
	<?php else : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'No plugins are activated that have licenses managed by WP Job Manager.', 'wp-job-manager' ); ?></p></div>
	<?php endif; ?>
</div>
