<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<h1 class="screen-reader-text"><?php esc_html_e( 'Licenses', 'wp-job-manager' ); ?></h1>
<div class="wpjm-licences">
	<?php if ( ! empty( $licenced_plugins ) ) : ?>
		<?php foreach ( $licenced_plugins as $product_slug => $plugin_data ) : ?>
			<?php
			$licence = WP_Job_Manager_Helper::get_plugin_licence( $product_slug );
			?>
		<div class="licence-row">
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
					$notices = array();
					foreach ( $licence['errors'] as $key => $error ) {
						$notices[] = array(
							'type'    => 'error',
							'message' => $error,
						);
					}
				}
				foreach ( $notices as $message ) {
					echo '<div class="notice inline notice-' . esc_attr( $message['type'] ) . '"><p>' . wp_kses_post( $message['message'] ) . '</p></div>';
				}
				?>
				<form method="post">
				<?php wp_nonce_field( 'wpjm-manage-licence' ); ?>
				<?php
				if ( ! empty( $licence['licence_key'] ) && ! empty( $licence['email'] ) ) {
					?>
					<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_action" name="action" value="deactivate"/>
					<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_plugin" name="product_slug" value="<?php echo esc_attr( $product_slug ); ?>"/>

					<label for="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_licence_key"><?php esc_html_e( 'License', 'wp-job-manager' ); ?>:
						<input type="text" disabled="disabled" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_licence_key" name="licence_key" placeholder="XXXX-XXXX-XXXX-XXXX" value="<?php echo esc_attr( $licence['licence_key'] ); ?>"/>
					</label>
					<label for="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_email"><?php esc_html_e( 'Email', 'wp-job-manager' ); ?>:
						<input type="email" disabled="disabled" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_email" name="email" placeholder="Email address" value="<?php echo esc_attr( $licence['email'] ); ?>"/>
					</label>

					<input type="submit" class="button" name="submit" value="<?php esc_attr_e( 'Deactivate License', 'wp-job-manager' ); ?>" />
					<?php
				} else { // licence is not active.
					?>
					<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_action" name="action" value="activate"/>
					<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_plugin" name="product_slug" value="<?php echo esc_attr( $product_slug ); ?>"/>
					<label for="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_licence_key"><?php esc_html_e( 'License', 'wp-job-manager' ); ?>:
						<input type="text" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_licence_key" name="licence_key" placeholder="XXXX-XXXX-XXXX-XXXX"/>
					</label>
					<label for="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_email"><?php esc_html_e( 'Email', 'wp-job-manager' ); ?>:
						<input type="email" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_email" name="email" placeholder="Email address" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"/>
					</label>
					<input type="submit" class="button" name="submit" value="<?php esc_attr_e( 'Activate License', 'wp-job-manager' ); ?>" />
					<?php
				} // end if : else licence is not active.
				?>
				</form>
			</div>
		</div>
	<?php endforeach; ?>
		<div class="notice notice-info inline"><p><?php printf( 'Lost your license key? <a href="%s">Retrieve it here</a>.', 'https://wpjobmanager.com/lost-licence-key/' ); ?></p></div>
	<?php else : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'No plugins are activated that have licenses managed by WP Job Manager.', 'wp-job-manager' ); ?></p></div>
	<?php endif; ?>
</div>
