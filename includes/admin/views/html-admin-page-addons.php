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
				href="<?php echo esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&category=' . esc_attr( $category->slug ) ) ); ?>">
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
	<input type="hidden" name="page" value="job-manager-addons" />
	<input class="wpjm-extension-search-input" type="text" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search', 'wp-job-manager' ); ?>" />
	<input class="wpjm-extension-search-button button" type="submit" value="<?php echo esc_attr__( 'Search', 'wp-job-manager' ); ?>" />
</form>
<?php

echo '</div>';
echo '<br class="clear" />';

if ( empty( $add_ons ) ) {
	echo '<div class="notice notice-warning below-h2"><p><strong>' . esc_html__( 'No add-ons were found.', 'wp-job-manager' ) . '</strong></p></div>';
} else {
	echo '<ul class="products">';
	foreach ( $add_ons as $add_on ) {
		$class = '';
		$url   = add_query_arg(
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

			<div class="add-on-header">
			<?php if ( 'https://wpjobmanager.com/add-ons/bundle/' === $add_on->link ) : ?>
					<div class="wp-block-group jm-logo__wrapper is-nowrap is-layout-flex">
						<div class="jm-logo">
							<svg xmlns="http://www.w3.org/2000/svg" class="jm-logo__head" fill="currentColor" viewBox="0 0 80 80">
								<path fill-rule="evenodd" d="M40 76c19.882 0 36-16.118 36-36S59.882 4 40 4 4 20.118 4 40s16.118 36 36 36Zm0 4c22.091 0 40-17.909 40-40S62.091 0 40 0 0 17.909 0 40s17.909 40 40 40Z"></path>
							</svg>
							<div class="jm-logo__inner">
								<svg xmlns="http://www.w3.org/2000/svg" class="jm-logo__face" fill="currentColor" viewBox="0 0 80 80">
									<path d="M28.842 41.536a1.611 1.611 0 0 1 2.216.53 10.466 10.466 0 0 0 8.935 5.006c3.778 0 7.09-2 8.935-5.005a1.611 1.611 0 1 1 2.747 1.685 13.689 13.689 0 0 1-11.682 6.543 13.689 13.689 0 0 1-11.682-6.543 1.611 1.611 0 0 1 .531-2.216Zm-.666-18.096a3.223 3.223 0 1 0-6.446 0 3.223 3.223 0 0 0 6.446 0Zm29.274 0a3.223 3.223 0 1 0-6.446 0 3.223 3.223 0 0 0 6.445 0Z"></path>
								</svg>
								<svg xmlns="http://www.w3.org/2000/svg" class="jm-logo__letters" fill="currentColor" viewBox="0 0 80 80">
									<path d="M33.6 42.888V28h-7.2v14.888c0 .695-.12 1.184-.358 1.466-.24.282-.576.424-1.01.424-.521 0-.934-.185-1.238-.554-.304-.391-.456-1.054-.456-1.987h-7.135c0 2.953.825 5.201 2.476 6.743 1.65 1.542 3.877 2.313 6.679 2.313 2.606 0 4.626-.706 6.059-2.117 1.455-1.434 2.183-3.53 2.183-6.288ZM62.924 51.2V28h-8.829l-4.952 13.846L44.061 28H35.2v23.2h7.232V39.565L45.983 51.2h6.19l3.551-11.635V51.2h7.2Z"></path>
								</svg>
							</div>
						</div>
					</div>
				<?php else : ?>
					<?php if ( ! empty( $add_on->image ) && 'https://wpjobmanager.com/add-ons/bundle/' !== $add_on->link ) : ?>
						<img class="<?php echo esc_attr( $class ); ?>" src="<?php echo esc_url( remove_query_arg( [ 'w', 'h', 'crop' ], $add_on->image ) ); ?>" />
					<?php endif; ?>
				<?php endif; ?>



				<div class="product-info">
					<div class="title"><?php echo esc_html( $add_on->title ); ?></div>
					<?php if ( ! empty( $add_on->vendor_name ) && ! empty( $add_on->vendor_link ) ) : ?>
						<div class="author">By <a target="_blank" href="<?php echo esc_url( $add_on->vendor_link ); ?>"><?php echo esc_html( $add_on->vendor_name ); ?></a></div>
					<?php endif; ?>
				</div>

				<a class="button-secondary" target="_blank" href="<?php echo esc_url( $url, [ 'http', 'https' ] ); ?>">Get Extension</a>

			</div>

			<div class="add-on-body">
				<p><?php echo esc_html( $add_on->excerpt ); ?></p>
			</div>
			<div class="add-on-footer">
				<?php if ( ! empty( $add_on->price ) ) : ?>
					<strong>Paid Add-on</strong>
				<?php endif; ?>

				<?php if ( ! empty( $add_on->documentation ) ) : ?>
					<a target="_blank" href="<?php echo esc_url( $add_on->documentation ); ?>">More details</a>
				<?php endif; ?>
			</div>

		</li>
		<?php
	}
	echo '</ul>';
}
