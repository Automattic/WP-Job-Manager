<?php
/**
 * File containing the view for displaying the list of extensions available to extend WP Job Manager.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo '<h1 class="screen-reader-text">' . esc_html__( 'WP Job Manager Marketplace', 'wp-job-manager' ) . '</h1>';
echo '<div class="wpjm-extensions-filter-search">';
if ( ! empty( $categories ) ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
	$current_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '_all';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
	if ( ! empty( $_GET['search'] ) ) {
		$current_category = null;
	}
	echo '<ul class="subsubsub">';
	foreach ( $categories as $category ) {
		?>
		<li>
			<a class="<?php echo $current_category === $category->slug ? 'current' : ''; ?>"
				href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-marketplace&category=' . esc_attr( $category->slug ) ) ); ?>">
				<?php echo esc_html( $category->label ); ?>
			</a>
		</li>
		<?php
	}
	echo '</ul>';
}
?>
<form class="extension-search" method="get" action="<?php esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons' ) ); ?>">
	<input type="hidden" name="post_type" value="job_listing" />
	<input type="hidden" name="page" value="job-manager-marketplace" />
	<input class="wpjm-extension-search-input" type="text" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search', 'wp-job-manager' ); ?>" />
	<input class="wpjm-extension-search-button button" type="submit" value="<?php echo esc_attr__( 'Search', 'wp-job-manager' ); ?>" />
</form>
<?php

echo '</div>';
echo '<br class="clear" />';

if ( empty( $add_ons ) ) {
	echo '<div class="notice notice-warning below-h2"><p><strong>' . esc_html__( 'No extensions were found.', 'wp-job-manager' ) . '</strong></p></div>';
} else {
	echo '<ul class="products">';
	foreach ( $add_ons as $add_on ) {
		$class     = '';
		$link_args = [
			'utm_source'   => 'product',
			'utm_medium'   => 'addonpage',
			'utm_campaign' => 'wpjmplugin',
			'utm_content'  => 'listing',
		];
		?>
		<li class="product">

			<div class="add-on-header">
			<?php if ( 'https://wpjobmanager.com/add-ons/bundle/' === $add_on->link ) : ?>
				<?php
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					echo ( new WP_Job_Manager_Addons() )->get_animated_logo();
				?>
				<?php else : ?>
					<?php if ( ! empty( $add_on->image ) && 'https://wpjobmanager.com/add-ons/bundle/' !== $add_on->link ) : ?>
						<img class="<?php echo esc_attr( $class ); ?>" src="<?php echo esc_url( remove_query_arg( [ 'w', 'h', 'crop' ], $add_on->image ) ); ?>" />
					<?php endif; ?>
				<?php endif; ?>



				<div class="product-info">
					<div class="title"><?php echo esc_html( $add_on->title ); ?></div>
					<?php if ( ! empty( $add_on->vendor_name ) && ! empty( $add_on->vendor_link ) ) : ?>
						<div class="author"><?php esc_html_e( 'By', 'wp-job-manager' ); ?> <a target="_blank" href="<?php echo esc_url( add_query_arg( $link_args, $add_on->vendor_link ) ); ?>"><?php echo esc_html( $add_on->vendor_name ); ?></a></div>
					<?php endif; ?>
				</div>

				<a class="button-secondary" target="_blank" href="<?php echo esc_url( add_query_arg( $link_args, $add_on->link ), [ 'http', 'https' ] ); ?>"><?php esc_html_e( 'Get Extension', 'wp-job-manager' ); ?></a>

			</div>

			<div class="add-on-body">
				<p><?php echo esc_html( $add_on->excerpt ); ?></p>
			</div>
			<div class="add-on-footer">
				<?php if ( ! empty( $add_on->price ) ) : ?>
					<strong><?php esc_html_e( 'Paid Add-on', 'wp-job-manager' ); ?></strong>
				<?php endif; ?>

				<?php if ( ! empty( $add_on->documentation ) ) : ?>
					<a target="_blank" href="<?php echo esc_url( add_query_arg( $link_args, $add_on->documentation ) ); ?>"><?php esc_html_e( 'More details', 'wp-job-manager' ); ?></a>
				<?php endif; ?>
			</div>

		</li>
		<?php
	}
	echo '</ul>';
}
