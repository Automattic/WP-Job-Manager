<?php
/**
 * File containing the view for displaying the list of add-ons available to extend WP Job Manager.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '<h1 class="screen-reader-text">' . esc_html__( 'WP Job Manager Add-ons', 'wp-job-manager' ) . '</h1>';
if ( ! empty( $messages ) ) {
	foreach ( $messages as $message ) {
		if ( empty( $message->message ) ) {
			continue;
		}
		$message_type = 'info';
		if ( isset( $message->type )
		&& in_array( $message->type, [ 'info', 'success', 'warning', 'error' ], true ) ) {
			$message_type = $message->type;
		}
		$action_label  = isset( $message->action_label ) ? esc_attr( $message->action_label ) : __( 'More Information &rarr;', 'wp-job-manager' );
		$action_url    = isset( $message->action_url ) ? esc_url( $message->action_url, [ 'http', 'https' ] ) : false;
		$action_target = isset( $message->action_target ) && 'self' === $message->action_target ? '_self' : '_blank';
		$action_str    = '';
		if ( $action_url ) {
			$action_str = ' <a href="' . esc_url( $action_url ) . '" target="' . esc_attr( $action_target ) . '" class="button">' . esc_html( $action_label ) . '</a>';
		}

		echo '<div class="notice notice-' . esc_attr( $message_type ) . ' below-h2"><p><strong>' . esc_html( $message->message ) . '</strong>' . wp_kses_post( $action_str ) . '</p></div>';
	}
}
if ( ! empty( $categories ) ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
	$current_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '_all';
	echo '<ul class="subsubsub">';
	foreach ( $categories as $category ) {
		?>
		<li>
			<a class="<?php echo $current_category === $category->slug ? 'current' : ''; ?>"
				href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&category=' . esc_attr( $category->slug ) ) ); ?>">
				<?php echo esc_html( $category->label ); ?>
			</a>
		</li>
		<?php
	}
	echo '</ul>';
}

echo '<br class="clear" />';

if ( empty( $add_ons ) ) {
	echo '<div class="notice notice-warning below-h2"><p><strong>' . esc_html__( 'No add-ons were found.', 'wp-job-manager' ) . '</strong></p></div>';
} else {
	echo '<ul class="products">';
	foreach ( $add_ons as $add_on ) {
		$url = add_query_arg(
			[
				'utm_source'   => 'product',
				'utm_medium'   => 'addonpage',
				'utm_campaign' => 'wpjmplugin',
				'utm_content'  => 'listing',
			],
			$add_on->link
		);
		?>
		<li class="product">
			<a href="<?php echo esc_url( $url, [ 'http', 'https' ] ); ?>">
				<?php if ( ! empty( $add_on->image ) ) : ?>
					<img src="<?php echo esc_url( $add_on->image ); ?>" />
				<?php endif; ?>
				<h2><?php echo esc_html( $add_on->title ); ?></h2>
				<p><?php echo esc_html( $add_on->excerpt ); ?>
				<?php if ( ! empty( $add_on->price ) ) : ?>
					<span class="price"><?php echo esc_html( $add_on->price ); ?></span>
				<?php endif; ?>
				</p>
			</a>
		</li>
		<?php
	}
	echo '</ul>';
}
