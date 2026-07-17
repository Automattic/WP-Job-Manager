<?php
/**
 * Tools → Logo Cleanup admin page: a batched UI over Attachment_Deduplicator for
 * admins who cannot run WP-CLI.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a Tools → Logo Cleanup page that scans for and removes duplicate
 * company-logo attachments in batches over AJAX.
 *
 * @internal
 */
class Logo_Cleanup_Admin {

	const PAGE_SLUG   = 'wpjm-logo-cleanup';
	const AJAX_ACTION = 'wpjm_dedupe_logos';
	const NONCE       = 'wpjm_logo_cleanup';

	/**
	 * Owners processed per AJAX batch.
	 */
	const OWNERS_PER_BATCH = 10;

	/**
	 * Capability required to run the tool.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Hooks the admin page and its AJAX handler.
	 */
	public static function init() {
		add_action( 'admin_menu', [ self::class, 'register_page' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'handle_ajax' ] );
	}

	/**
	 * Registers the page under the core Tools menu.
	 */
	public static function register_page() {
		add_management_page(
			__( 'Logo Cleanup', 'wp-job-manager' ),
			__( 'Logo Cleanup', 'wp-job-manager' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}

	/**
	 * Renders the tool page and enqueues its inline script.
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-job-manager' ) );
		}

		wp_register_script( 'wpjm-logo-cleanup', false, [ 'jquery' ], JOB_MANAGER_VERSION, true );
		wp_enqueue_script( 'wpjm-logo-cleanup' );
		wp_localize_script(
			'wpjm-logo-cleanup',
			'wpjmLogoCleanup',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'action'  => self::AJAX_ACTION,
				'i18n'    => [
					'scanning'   => __( 'Scanning…', 'wp-job-manager' ),
					'removing'   => __( 'Removing duplicates…', 'wp-job-manager' ),
					'scanDone'   => __( 'Scan complete.', 'wp-job-manager' ),
					'doneClean'  => __( 'Cleanup complete.', 'wp-job-manager' ),
					'error'      => __( 'Something went wrong. Please try again.', 'wp-job-manager' ),
					// translators: %d is a number of duplicate attachments.
					'foundFmt'   => __( 'Found %d duplicate logo attachment(s) that can be safely removed.', 'wp-job-manager' ),
					// translators: %d is a number of deleted attachments.
					'removedFmt' => __( 'Removed %d duplicate logo attachment(s).', 'wp-job-manager' ),
					'confirm'    => __( 'This permanently deletes the duplicate attachments after re-pointing every listing to the copy that is kept. Continue?', 'wp-job-manager' ),
				],
			]
		);
		wp_add_inline_script( 'wpjm-logo-cleanup', self::inline_script() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Logo Cleanup', 'wp-job-manager' ); ?></h1>
			<p>
				<?php esc_html_e( 'Company logos uploaded through the job submission form can accumulate duplicate copies in the Media Library. This tool finds identical logos owned by the same user and collapses them to a single attachment, re-pointing every listing before deleting the redundant copies.', 'wp-job-manager' ); ?>
			</p>
			<p>
				<button type="button" class="button button-secondary" id="wpjm-logo-scan"><?php esc_html_e( 'Scan for duplicates', 'wp-job-manager' ); ?></button>
				<button type="button" class="button button-primary" id="wpjm-logo-remove" disabled><?php esc_html_e( 'Remove duplicates', 'wp-job-manager' ); ?></button>
			</p>
			<p>
				<progress id="wpjm-logo-progress" value="0" max="100" style="width:320px;display:none;"></progress>
			</p>
			<p id="wpjm-logo-status" role="status" aria-live="polite"></p>
		</div>
		<?php
	}

	/**
	 * Processes one batch of owners and returns progress as JSON.
	 */
	public static function handle_ajax() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to run this tool.', 'wp-job-manager' ) ], 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified via check_ajax_referer above.
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$live   = ! empty( $_POST['live'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$deduplicator = new Attachment_Deduplicator();
		$owner_ids    = $deduplicator->get_logo_owner_ids();
		$total        = count( $owner_ids );
		$batch        = array_slice( $owner_ids, $offset, self::OWNERS_PER_BATCH );

		$tally = [
			'groups'     => 0,
			'duplicates' => 0,
			'repointed'  => 0,
			'deleted'    => 0,
		];
		foreach ( $batch as $owner_id ) {
			$report               = $deduplicator->run(
				[
					'dry_run' => ! $live,
					'user_id' => (int) $owner_id,
				]
			);
			$tally['groups']     += $report['groups'];
			$tally['duplicates'] += $report['duplicates'];
			$tally['repointed']  += $report['references_repointed'];
			$tally['deleted']    += $report['attachments_deleted'];
		}

		$processed = min( $offset + self::OWNERS_PER_BATCH, $total );

		wp_send_json_success(
			array_merge(
				$tally,
				[
					'processed' => $processed,
					'total'     => $total,
					'done'      => $processed >= $total,
				]
			)
		);
	}

	/**
	 * The inline batching script.
	 *
	 * @return string JavaScript.
	 */
	private static function inline_script() {
		return <<<'JS'
( function ( $ ) {
	var cfg = window.wpjmLogoCleanup || {};
	var $scan = $( '#wpjm-logo-scan' );
	var $remove = $( '#wpjm-logo-remove' );
	var $progress = $( '#wpjm-logo-progress' );
	var $status = $( '#wpjm-logo-status' );

	function run( live, onDone ) {
		var totals = { groups: 0, duplicates: 0, repointed: 0, deleted: 0 };
		$scan.prop( 'disabled', true );
		$remove.prop( 'disabled', true );
		$progress.show().attr( 'value', 0 );
		$status.text( live ? cfg.i18n.removing : cfg.i18n.scanning );

		function batch( offset ) {
			$.post( cfg.ajaxUrl, {
				action: cfg.action,
				nonce: cfg.nonce,
				offset: offset,
				live: live ? 1 : 0
			} ).done( function ( res ) {
				if ( ! res || ! res.success ) {
					$status.text( cfg.i18n.error );
					$scan.prop( 'disabled', false );
					return;
				}
				var d = res.data;
				totals.groups += d.groups;
				totals.duplicates += d.duplicates;
				totals.repointed += d.repointed;
				totals.deleted += d.deleted;
				$progress.attr( 'value', d.total ? Math.round( ( d.processed / d.total ) * 100 ) : 100 );
				if ( d.done ) {
					onDone( totals );
				} else {
					batch( d.processed );
				}
			} ).fail( function () {
				$status.text( cfg.i18n.error );
				$scan.prop( 'disabled', false );
			} );
		}
		batch( 0 );
	}

	$scan.on( 'click', function () {
		run( false, function ( totals ) {
			$status.text( cfg.i18n.foundFmt.replace( '%d', totals.duplicates ) );
			$scan.prop( 'disabled', false );
			$remove.prop( 'disabled', totals.duplicates === 0 );
		} );
	} );

	$remove.on( 'click', function () {
		if ( ! window.confirm( cfg.i18n.confirm ) ) {
			return;
		}
		run( true, function ( totals ) {
			$status.text( cfg.i18n.removedFmt.replace( '%d', totals.deleted ) );
			$scan.prop( 'disabled', false );
			$remove.prop( 'disabled', true );
		} );
	} );
}( jQuery ) );
JS;
	}
}
