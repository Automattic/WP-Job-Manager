<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WP_Job_Manager_Writepanels {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 1, 2 );
		add_action( 'job_manager_save_job_listing', array( $this, 'save_job_listing_data' ), 1, 2 );
	}

	/**
	 * job_listing_fields function.
	 *
	 * @access public
	 * @return void
	 */
	public function job_listing_fields() {
		return apply_filters( 'job_manager_job_listing_data_fields', array(
			'_job_location' => array(
				'label' => __( 'Job location', 'job_manager' ),
				'placeholder' => __( 'e.g. "London, UK", "New York", "Houston, TX"', 'job_manager' ),
				'description' => __( 'Leave this blank if the job can be done from anywhere (i.e. telecommuting)', 'job_manager' )
			),
			'_application' => array(
				'label' => __( 'Application email/URL', 'job_manager' ),
				'placeholder' => __( 'URL or email which applicants use to apply', 'job_manager' )
			),
			'_company_name' => array(
				'label' => __( 'Company name', 'job_manager' ),
				'placeholder' => ''
			),
			'_company_website' => array(
				'label' => __( 'Company website', 'job_manager' ),
				'placeholder' => ''
			),
			'_company_tagline' => array(
				'label' => __( 'Company tagline', 'job_manager' ),
				'placeholder' => __( 'Brief description about the company', 'job_manager' )
			),
			'_company_twitter' => array(
				'label' => __( 'Company Twitter', 'job_manager' ),
				'placeholder' => '@yourcompany'
			),
			'_company_logo' => array(
				'label' => __( 'Company logo', 'job_manager' ),
				'placeholder' => __( 'URL to the company logo', 'job_manager' ),
				'type'  => 'file'
			),
			'_filled' => array(
				'label' => __( 'Position filled?', 'job_manager' ),
				'type'  => 'checkbox'
			),
			'_featured' => array(
				'label' => __( 'Feature this job listing?', 'job_manager' ),
				'type'  => 'checkbox',
				'description' => __( 'Featured listings will be sticky during searches, and can be styled differently.', 'job_manager' )
			),
			'_job_expires' => array(
				'label'       => __( 'Job Expires', 'job_manager' ),
				'placeholder' => __( 'yyyy-mm-dd', 'job_manager' )
			)
		) );
	}

	/**
	 * add_meta_boxes function.
	 *
	 * @access public
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box( 'job_listing_data', __( 'Job Listing Data', 'job_manager' ), array( $this, 'job_listing_data' ), 'job_listing', 'normal', 'high' );
	}

	/**
	 * input_text function.
	 *
	 * @access private
	 * @param mixed $key
	 * @param mixed $field
	 * @return void
	 */
	private function input_file( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) )
			$field['value'] = get_post_meta( $thepostid, $key, true );
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>:</label>
			<input type="text" class="file_url" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?> <button class="button upload_image_button" data-uploader_button_text="<?php _e( 'Use as company logo', 'job_manager' ); ?>"><?php _e( 'Upload company logo', 'job_manager' ); ?></button>
		</p>
		<script type="text/javascript">
			// Uploading files
			var file_frame;
			var file_target_input;

			jQuery('.upload_image_button').live('click', function( event ){

			    event.preventDefault();

			    file_target_input = jQuery( this ).closest('.form-field').find('.file_url');

			    // If the media frame already exists, reopen it.
			    if ( file_frame ) {
					file_frame.open();
					return;
			    }

			    // Create the media frame.
			    file_frame = wp.media.frames.file_frame = wp.media({
					title: jQuery( this ).data( 'uploader_title' ),
					button: {
						text: jQuery( this ).data( 'uploader_button_text' ),
					},
					multiple: false  // Set to true to allow multiple files to be selected
			    });

			    // When an image is selected, run a callback.
			    file_frame.on( 'select', function() {
					// We set multiple to false so only get one image from the uploader
					attachment = file_frame.state().get('selection').first().toJSON();

					jQuery( file_target_input ).val( attachment.url );
			    });

			    // Finally, open the modal
			    file_frame.open();
			});
		</script>
		<?php
	}

	/**
	 * input_text function.
	 *
	 * @access private
	 * @param mixed $key
	 * @param mixed $field
	 * @return void
	 */
	private function input_text( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) )
			$field['value'] = get_post_meta( $thepostid, $key, true );
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>:</label>
			<input type="text" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<?php
	}

	/**
	 * input_text function.
	 *
	 * @access private
	 * @param mixed $key
	 * @param mixed $field
	 * @return void
	 */
	private function input_textarea( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) )
			$field['value'] = get_post_meta( $thepostid, $key, true );
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>:</label>
			<textarea name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"><?php echo esc_html( $field['value'] ); ?></textarea>
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<?php
	}

	/**
	 * input_checkbox function.
	 *
	 * @access private
	 * @param mixed $key
	 * @param mixed $field
	 * @return void
	 */
	private function input_checkbox( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) )
			$field['value'] = get_post_meta( $thepostid, $key, true );
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?></label>
			<input type="checkbox" class="checkbox" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $field['value'], 1 ); ?> />
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<?php
	}

	/**
	 * job_listing_data function.
	 *
	 * @access public
	 * @param mixed $post
	 * @return void
	 */
	public function job_listing_data( $post ) {
		global $post, $thepostid;

		$thepostid = $post->ID;

		echo '<div class="wp_job_manager_meta_data">';

		wp_nonce_field( 'save_meta_data', 'job_manager_nonce' );

		do_action( 'job_manager_job_listing_data_start', $thepostid );

		foreach ( $this->job_listing_fields() as $key => $field ) {
			$type = ! empty( $field['type'] ) ? $field['type'] : 'text';

			if ( method_exists( $this, 'input_' . $type ) )
				call_user_func( array( $this, 'input_' . $type ), $key, $field );
			else
				do_action( 'job_manager_input_' . $type, $key, $field );
		}

		do_action( 'job_manager_job_listing_data_end', $thepostid );

		echo '</div>';
	}

	/**
	 * save_post function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @param mixed $post
	 * @return void
	 */
	public function save_post( $post_id, $post ) {
		if ( empty( $post_id ) || empty( $post ) || empty( $_POST ) ) return;
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
		if ( is_int( wp_is_post_revision( $post ) ) ) return;
		if ( is_int( wp_is_post_autosave( $post ) ) ) return;
		if ( empty($_POST['job_manager_nonce']) || ! wp_verify_nonce( $_POST['job_manager_nonce'], 'save_meta_data' ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( $post->post_type != 'job_listing' ) return;

		do_action( 'job_manager_save_job_listing', $post_id, $post );
	}

	/**
	 * save_job_listing_data function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @param mixed $post
	 * @return void
	 */
	public function save_job_listing_data( $post_id, $post ) {
		global $wpdb;

		foreach ( $this->job_listing_fields() as $key => $field ) {
			if ( '_job_expires' == $key ) {
				if ( ! empty( $_POST[ $key ] ) ) {
					update_post_meta( $post_id, $key, date( 'Y-m-d', strtotime( sanitize_text_field( $_POST[ $key ] ) ) ) );
				} else {
					update_post_meta( $post_id, $key, '' );
				}
				continue;
			}

			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
			} elseif ( ! empty( $field['type'] ) && $field['type'] == 'checkbox' ) {
				update_post_meta( $post_id, $key, 0 );
			}
		}
	}
}

new WP_Job_Manager_Writepanels();