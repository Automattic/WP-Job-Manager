<?php
/**
 * File containing the view to show the table for managing license keys.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h1 class="screen-reader-text"><?php esc_html_e( 'Licenses', 'wp-job-manager' ); ?></h1>
<div class="wpjm-licences">
	<?php if ( ! empty( $licenced_plugins ) ) : ?>
		<?php if ( ! empty( $show_bulk_activate ) ) : ?>
		<div class="wpjm-bulk-activate">
			<b class="wpjm-bulk-activate--title">
				<?php esc_html_e( 'Activate Job Manager Licenses', 'wp-job-manager' ); ?>
			</b>
			<div class="wpjm-bulk-activate--description">
				<?php esc_html_e( 'Activate all licenses at once. Easy, everything in one place.', 'wp-job-manager' ); ?>
			</div>

			<form method="post" class="wpjm-bulk-activate--form">
				<input type="hidden" name="action" value="bulk_activate" />
				<?php wp_nonce_field( 'wpjm-manage-licence' ); ?>
				<?php
				foreach ( $licenced_plugins as $product_slug => $plugin_data ) :
					$licence = WP_Job_Manager_Helper::get_plugin_licence( $product_slug );
					if ( empty( $licence['licence_key'] ) ) :
						?>
					<input type="hidden" name="product_slugs[]" value="<?php echo esc_attr( $product_slug ); ?>"/>
						<?php
					endif;
				endforeach;
				?>
				<input type="text" name="licence_key" class="wpjm-bulk-activate--field" placeholder="<?php esc_attr_e( 'ENTER YOUR LICENSE KEY', 'wp-job-manager' ); ?>"/>
				<input type="submit" class="button button-primary wpjm-bulk-activate--button" value="<?php esc_attr_e( 'Activate License', 'wp-job-manager' ); ?>" />
			</form>
			<?php
			$notices = WP_Job_Manager_Helper::get_messages( 'bulk-activate' );
			foreach ( $notices as $message ) {
				echo '<div class="notice inline notice-' . esc_attr( $message['type'] ) . ' wpjm-bulk-activate--notice"><p>' . wp_kses_post( $message['message'] ) . '</p></div>';
			}
			?>
		</div>
		<?php endif; ?>
		<form method='post' class='plugin-licence-search'>
			<input type='search'  class='plugin-licence-search-field' name='s' value='<?php echo esc_attr( $search_term ?? '' ); ?>' placeholder='<?php esc_attr_e( 'Search', 'wp-job-manager' ); ?>' />
			<input type='submit' class='button plugin-licence-search-button' value='<?php esc_attr_e( 'Search', 'wp-job-manager' ); ?>'/>
		</form>
		<?php foreach ( $licenced_plugins as $product_slug => $plugin_data ) : ?>
			<?php
			$licence = WP_Job_Manager_Helper::get_plugin_licence( $product_slug );
			?>
		<div class="licence-row">
			<?php // translators: placeholder is the addon name. ?>
			<img src="<?php echo esc_url( JOB_MANAGER_PLUGIN_URL . '/assets/images/addon-icon.png' ); ?>" alt="<?php echo esc_attr( sprintf( __( 'Plugin Icon for %s', 'wp-job-manager' ), $plugin_data['Name'] ) ); ?>" class="plugin-licence-icon"/>
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
			<div class="plugin-licence">
				<?php
				$notices = WP_Job_Manager_Helper::get_messages( $product_slug );
				if ( empty( $notices ) && ! empty( $licence['errors'] ) ) {
					$notices = [];
					foreach ( $licence['errors'] as $key => $error_message ) {
						$notices[] = [
							'type'    => 'error',
							'message' => $error_message,
						];
					}
				}
				if ( apply_filters( 'wpjm_display_license_form_for_addon', true, $product_slug ) ) {
					?>
					<form method="post" class='plugin-licence-form'>
						<?php wp_nonce_field( 'wpjm-manage-licence' ); ?>
						<?php
						if ( ! empty( $licence['licence_key'] ) ) {
							?>
							<span class="jm-icon plugin-licence-ok"></span>
							<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_action" name="action" value="deactivate"/>
							<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_plugin" name="product_slug" value="<?php echo esc_attr( $product_slug ); ?>"/>

							<label for="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_licence_key" class="plugin-licence-label"><?php esc_html_e( 'LICENSE', 'wp-job-manager' ); ?></label>
							<input type="text" disabled="disabled" class="plugin-licence-field" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_licence_key" name="licence_key" placeholder="XXXX-XXXX-XXXX-XXXX" value="<?php echo esc_attr( $licence['licence_key'] ); ?>"/>


							<input type="submit" class="button plugin-licence-button" name="submit" value="<?php esc_attr_e( 'Deactivate License', 'wp-job-manager' ); ?>" />
							<?php
						} else { // licence is not active.
							?>
							<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_action" name="action" value="activate"/>
							<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_plugin" name="product_slug" value="<?php echo esc_attr( $product_slug ); ?>"/>
							<label for="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_licence_key" class="plugin-licence-label"><?php esc_html_e( 'LICENSE', 'wp-job-manager' ); ?></label>
							<input type="text" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_licence_key" class="plugin-licence-field" name="licence_key" placeholder="XXXX-XXXX-XXXX-XXXX"/>
							<input type="submit" class="button plugin-licence-button" name="submit" value="<?php esc_attr_e( 'Activate License', 'wp-job-manager' ); ?>" />
							<?php
						} // end if : else licence is not active.
						?>
					</form>
					<?php
				}
				do_action( 'wpjm_manage_license_page_after_license_form', $product_slug );
				?>
			</div>
			<?php
			foreach ( $notices as $message ) {
				echo '<div class="notice inline notice-' . esc_attr( $message['type'] ) . ' plugin-licence-notice"><p>' . wp_kses_post( $message['message'] ) . '</p></div>';
			}
			?>
		</div>
	<?php endforeach; ?>
		<?php // translators: Placeholder %s is the lost license key URL. ?>
		<div class="notice notice-info inline"><p><?php printf( wp_kses_post( __( 'Lost your license key? <a href="%s">Retrieve it here</a>.', 'wp-job-manager' ) ), 'https://wpjobmanager.com/lost-license-key/' ); ?></p></div>
	<?php else : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'No plugins are activated that have licenses managed by WP Job Manager.', 'wp-job-manager' ); ?></p></div>
	<?php endif; ?>
</div>
