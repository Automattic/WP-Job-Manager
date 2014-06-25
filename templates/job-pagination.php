<?php
/**
 * Pagination - Show numbered pagination for the [jobs] shortcode
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( $max_num_pages <= 1 ) {
	return;
}
?>
<nav class="job-manager-pagination">
	<ul>
		<?php if ( $current_page && $current_page > 1 ) : ?>
			<li><a href="#" data-page="<?php echo $current_page - 1; ?>">&larr;</a></li>
		<?php endif; ?>
		
		<?php for ( $i = 1; $i <= $max_num_pages; $i++ ) : ?>
			<?php if ( $current_page === $i ) : ?>
				<li><span class="current" data-page="<?php echo $i; ?>"><?php echo $i; ?></span></li>
			<?php else : ?>
				<li><a href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a></li>
			<?php endif; ?>
		<?php endfor; ?>
		
		<?php if ( $current_page && $current_page < $max_num_pages ) : ?>
			<li><a href="#" data-page="<?php echo $current_page + 1; ?>">&rarr;</a></li>
		<?php endif; ?>
	</ul>
</nav>