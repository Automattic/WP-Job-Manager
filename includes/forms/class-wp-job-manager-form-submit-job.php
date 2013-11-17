<?php

/**
 * WP_Job_Manager_Form_Submit_Job class.
 */
class WP_Job_Manager_Form_Submit_Job extends WP_Job_Manager_Form {

	public    static $form_name = 'submit-job';
	protected static $job_id;
	protected static $preview_job;
	protected static $steps;
	protected static $step = 0;

	/**
	 * Init form
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'process' ) );

		self::$steps  = (array) apply_filters( 'submit_job_steps', array(
			'submit' => array(
				'name'     => __( 'Submit Details', 'job_manager' ),
				'view'     => array( __CLASS__, 'submit' ),
				'handler'  => array( __CLASS__, 'submit_handler' ),
				'priority' => 10
				),
			'preview' => array(
				'name'     => __( 'Preview', 'job_manager' ),
				'view'     => array( __CLASS__, 'preview' ),
				'handler'  => array( __CLASS__, 'preview_handler' ),
				'priority' => 20
			),
			'done' => array(
				'name'     => __( 'Done', 'job_manager' ),
				'view'     => array( __CLASS__, 'done' ),
				'priority' => 30
			)
		) );

		uasort( self::$steps, array( __CLASS__, 'sort_by_priority' ) );

		// Get step/job
		if ( ! empty( $_REQUEST['step'] ) ) {
			self::$step = is_numeric( $_REQUEST['step'] ) ? max( absint( $_REQUEST['step'] ), 0 ) : array_search( $_REQUEST['step'], array_keys( self::$steps ) );
		}
		self::$job_id = ! empty( $_REQUEST['job_id'] ) ? absint( $_REQUEST[ 'job_id' ] ) : 0;

		// Validate job ID if set
		if ( self::$job_id && ! in_array( get_post_status( self::$job_id ), apply_filters( 'job_manager_valid_submit_job_statuses', array( 'preview' ) ) ) ) {
			self::$job_id = 0;
			self::$step   = 0;
		}
	}

	/**
	 * Get step from outside of the class
	 */
	public static function get_step() {
		return self::$step;
	}

	/**
	 * Increase step from outside of the class
	 */
	public static function next_step() {
		self::$step ++;
	}

	/**
	 * Decrease step from outside of the class
	 */
	public static function previous_step() {
		self::$step --;
	}

	/**
	 * Sort array by priority value
	 */
	private static function sort_by_priority( $a, $b ) {
		return $a['priority'] - $b['priority'];
	}

	/**
	 * Get the submitted job ID
	 * @return int
	 */
	public static function get_job_id() {
		return absint( self::$job_id );
	}

	/**
	 * init_fields function.
	 *
	 * @access public
	 * @return void
	 */
	public static function init_fields() {
		if ( self::$fields )
			return;

		self::$fields = apply_filters( 'submit_job_form_fields', array(
			'job' => array(
				'job_title' => array(
					'label'       => __( 'Job title', 'job_manager' ),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 1
				),
				'job_location' => array(
					'label'       => __( 'Job location', 'job_manager' ),
					'description' => __( 'Leave this blank if the job can be done from anywhere (i.e. telecommuting)', 'job_manager' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'e.g. "London, UK", "New York", "Houston, TX"', 'job_manager' ),
					'priority'    => 2
				),
				'job_type' => array(
					'label'       => __( 'Job type', 'job_manager' ),
					'type'        => 'select',
					'required'    => true,
					'options'     => self::job_types(),
					'placeholder' => '',
					'priority'    => 3
				),
				'job_category' => array(
					'label'       => __( 'Job category', 'job_manager' ),
					'type'        => 'select',
					'required'    => true,
					'options'     => self::job_categories(),
					'placeholder' => '',
					'priority'    => 4
				),
				'job_description' => array(
					'label'       => __( 'Description', 'job_manager' ),
					'type'        => 'job-description',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 5
				),
				'application' => array(
					'label'       => __( 'Application email/URL', 'job_manager' ),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => __( 'Enter an email address or website URL', 'job_manager' ),
					'priority'    => 6
				)
			),
			'company' => array(
				'company_name' => array(
					'label'       => __( 'Company name', 'job_manager' ),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => __( 'Enter the name of the company', 'job_manager' ),
					'priority'    => 1
				),
				'company_website' => array(
					'label'       => __( 'Website', 'job_manager' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'http://', 'job_manager' ),
					'priority'    => 2
				),
				'company_tagline' => array(
					'label'       => __( 'Tagline', 'job_manager' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'Briefly describe your company', 'job_manager' ),
					'maxlength'   => 64,
					'priority'    => 3
				),
				'company_twitter' => array(
					'label'       => __( 'Twitter username', 'job_manager' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( '@yourcompany', 'job_manager' ),
					'priority'    => 4
				),
				'company_logo' => array(
					'label'       => __( 'Logo', 'job_manager' ),
					'type'        => 'file',
					'required'    => false,
					'placeholder' => '',
					'priority'    => 5
				)
			)
		) );

		if ( ! get_option( 'job_manager_enable_categories' ) || wp_count_terms( 'job_listing_category' ) == 0 )
			unset( self::$fields['job']['job_category'] );
	}

	/**
	 * Get post data for fields
	 *
	 * @return array of data
	 */
	protected static function get_posted_fields() {
		self::init_fields();

		$values = array();

		foreach ( self::$fields as $group_key => $fields ) {
			foreach ( $fields as $key => $field ) {
				$values[ $group_key ][ $key ] = isset( $_POST[ $key ] ) ? stripslashes( $_POST[ $key ] ) : '';

				switch ( $key ) {
					case 'job_description' :
						$values[ $group_key ][ $key ] = wp_kses_post( trim( $values[ $group_key ][ $key ] ) );
					break;
					case 'company_logo' :
						$image_url = self::upload_image( 'company_logo' );
						if ( $image_url )
							$values[ $group_key ][ $key ] = $image_url;
					break;
					default:
						$values[ $group_key ][ $key ] = sanitize_text_field( $values[ $group_key ][ $key ] );
					break;
				}

				// Set fields value
				self::$fields[ $group_key ][ $key ]['value'] = $values[ $group_key ][ $key ];
			}
		}

		return $values;
	}

	/**
	 * Validate the posted fields
	 *
	 * @return bool on success, WP_ERROR on failure
	 */
	protected static function validate_fields( $values ) {
		foreach ( self::$fields as $group_key => $fields ) {
			foreach ( $fields as $key => $field ) {
				if ( $field['required'] && empty( $values[ $group_key ][ $key ] ) )
					return new WP_Error( 'validation-error', sprintf( __( '%s is a required field', 'job_manager' ), $field['label'] ) );
			}
		}

		return apply_filters( 'submit_job_form_validate_fields', true, self::$fields, $values );
	}

	/**
	 * job_types function.
	 *
	 * @access private
	 * @return void
	 */
	private static function job_types() {
		$options = array();
		$terms   = get_job_listing_types();
		foreach ( $terms as $term )
			$options[ $term->slug ] = $term->name;
		return $options;
	}

	/**
	 * job_types function.
	 *
	 * @access private
	 * @return void
	 */
	private static function job_categories() {
		$options = array();
		$terms   = get_job_listing_categories();
		foreach ( $terms as $term )
			$options[ $term->slug ] = $term->name;
		return $options;
	}

	/**
	 * Process function. all processing code if needed - can also change view if step is complete
	 */
	public static function process() {
		$keys = array_keys( self::$steps );

		if ( isset( $keys[ self::$step ] ) && is_callable( self::$steps[ $keys[ self::$step ] ]['handler'] ) ) {
			call_user_func( self::$steps[ $keys[ self::$step ] ]['handler'] );
		}
	}

	/**
	 * output function. Call the view handler.
	 */
	public static function output() {
		$keys = array_keys( self::$steps );

		self::show_errors();

		if ( isset( $keys[ self::$step ] ) && is_callable( self::$steps[ $keys[ self::$step ] ]['view'] ) ) {
			call_user_func( self::$steps[ $keys[ self::$step ] ]['view'] );
		}
	}

	/**
	 * Submit Step
	 */
	public static function submit() {
		global $job_manager, $post;

		self::init_fields();

		// Load data if neccessary
		if ( ! empty( $_POST['edit_job'] ) && self::$job_id ) {
			$job = get_post( self::$job_id );
			foreach ( self::$fields as $group_key => $fields ) {
				foreach ( $fields as $key => $field ) {
					switch ( $key ) {
						case 'job_title' :
							self::$fields[ $group_key ][ $key ]['value'] = $job->post_title;
						break;
						case 'job_description' :
							self::$fields[ $group_key ][ $key ]['value'] = $job->post_content;
						break;
						case 'job_type' :
							self::$fields[ $group_key ][ $key ]['value'] = current( wp_get_object_terms( $job->ID, 'job_listing_type', array( 'fields' => 'slugs' ) ) );
						break;
						case 'job_category' :
							self::$fields[ $group_key ][ $key ]['value'] = current( wp_get_object_terms( $job->ID, 'job_listing_category', array( 'fields' => 'slugs' ) ) );
						break;
						default:
							self::$fields[ $group_key ][ $key ]['value'] = get_post_meta( $job->ID, '_' . $key, true );
						break;
					}
				}
			}

			self::$fields = apply_filters( 'submit_job_form_fields_get_job_data', self::$fields, $job );

		// Get user meta
		} elseif ( is_user_logged_in() && empty( $_POST ) ) {
			if ( is_user_logged_in() ) {
				foreach ( self::$fields[ 'company' ] as $key => $field ) {
					self::$fields[ 'company' ][ $key ]['value'] = get_user_meta( get_current_user_id(), '_' . $key, true );
				}

				self::$fields = apply_filters( 'submit_job_form_fields_get_user_data', self::$fields, get_current_user_id() );
			}
		}

		get_job_manager_template( 'job-submit.php', array(
			'form'               => self::$form_name,
			'job_id'             => self::get_job_id(),
			'action'             => self::get_action(),
			'job_fields'         => self::get_fields( 'job' ),
			'company_fields'     => self::get_fields( 'company' ),
			'submit_button_text' => __( 'Preview job listing &rarr;', 'job_manager' )
			) );
	}

	/**
	 * Submit Step is posted
	 */
	public static function submit_handler() {
		try {

			// Get posted values
			$values = self::get_posted_fields();

			if ( empty( $_POST['submit_job'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'submit_form_posted' ) )
				return;

			// Validate required
			if ( is_wp_error( ( $return = self::validate_fields( $values ) ) ) )
				throw new Exception( $return->get_error_message() );

			// Account creation
			if ( ! is_user_logged_in() ) {
				$create_account = false;

				if ( job_manager_enable_registration() && ! empty( $_POST['create_account_email'] ) )
					$create_account = wp_job_manager_create_account( $_POST['create_account_email'] );

				if ( is_wp_error( $create_account ) )
					throw new Exception( $create_account->get_error_message() );
			}

			if ( job_manager_user_requires_account() && ! is_user_logged_in() )
				throw new Exception( __( 'You must be signed in to post a new job listing.' ) );

			// Update the job
			self::save_job( $values['job']['job_title'], $values['job']['job_description'] );
			self::update_job_data( $values );

			// Successful, show next step
			self::$step ++;

		} catch ( Exception $e ) {
			self::add_error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Update or create a job listing from posted data
	 *
	 * @param  string $post_title
	 * @param  string $post_content
	 * @param  string $status
	 */
	protected static function save_job( $post_title, $post_content, $status = 'preview' ) {
		$job_data = apply_filters( 'submit_job_form_save_job_data', array(
			'post_title'     => $post_title,
			'post_content'   => $post_content,
			'post_status'    => $status,
			'post_type'      => 'job_listing',
			'comment_status' => 'closed'
		) );

		if ( self::$job_id ) {
			$job_data['ID'] = self::$job_id;
			wp_update_post( $job_data );
		} else {
			self::$job_id = wp_insert_post( $job_data );
		}
	}

	/**
	 * Set job meta + terms based on posted values
	 *
	 * @param  array $values
	 */
	protected static function update_job_data( $values ) {

		wp_set_object_terms( self::$job_id, array( $values['job']['job_type'] ), 'job_listing_type', false );

		if ( get_option( 'job_manager_enable_categories' ) && isset( $values['job']['job_category'] ) ) {
			wp_set_object_terms( self::$job_id, array( $values['job']['job_category'] ), 'job_listing_category', false );
		}

		update_post_meta( self::$job_id, '_application', $values['job']['application'] );
		update_post_meta( self::$job_id, '_job_location', $values['job']['job_location'] );
		update_post_meta( self::$job_id, '_company_name', $values['company']['company_name'] );
		update_post_meta( self::$job_id, '_company_website', $values['company']['company_website'] );
		update_post_meta( self::$job_id, '_company_tagline', $values['company']['company_tagline'] );
		update_post_meta( self::$job_id, '_company_twitter', $values['company']['company_twitter'] );
		update_post_meta( self::$job_id, '_company_logo', $values['company']['company_logo'] );
		update_post_meta( self::$job_id, '_filled', 0 );
		update_post_meta( self::$job_id, '_featured', 0 );

		// And user meta to save time in future
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), '_company_name', $values['company']['company_name'] );
			update_user_meta( get_current_user_id(), '_company_website', $values['company']['company_website'] );
			update_user_meta( get_current_user_id(), '_company_tagline', $values['company']['company_tagline'] );
			update_user_meta( get_current_user_id(), '_company_twitter', $values['company']['company_twitter'] );
			update_user_meta( get_current_user_id(), '_company_logo', $values['company']['company_logo'] );
		}

		do_action( 'job_manager_update_job_data', self::$job_id, $values );
	}

	/**
	 * Preview Step
	 */
	public static function preview() {
		global $job_manager, $post;

		if ( self::$job_id ) {

			$post = get_post( self::$job_id );
			setup_postdata( $post );

			?>
			<form method="post" id="job_preview">
				<div class="job_listing_preview_title">
					<input type="submit" name="continue" id="job_preview_submit_button" class="button" value="<?php echo apply_filters( 'submit_job_step_preview_submit_text', __( 'Submit Listing &rarr;', 'job_manager' ) ); ?>" />
					<input type="submit" name="edit_job" class="button" value="<?php _e( '&larr; Edit listing', 'job_manager' ); ?>" />
					<input type="hidden" name="job_id" value="<?php echo esc_attr( self::$job_id ); ?>" />
					<input type="hidden" name="step" value="<?php echo esc_attr( self::$step ); ?>" />
					<input type="hidden" name="job_manager_form" value="<?php echo self::$form_name; ?>" />
					<h2>
						<?php _e( 'Preview', 'job_manager' ); ?>
					</h2>
				</div>
				<div class="job_listing_preview single_job_listing">
					<h1><?php the_title(); ?></h1>
					<?php get_job_manager_template_part( 'content-single', 'job_listing' ); ?>
				</div>
			</form>
			<?php

			wp_reset_postdata();
		}
	}

	/**
	 * Preview Step Form handler
	 */
	public static function preview_handler() {
		if ( ! $_POST )
			return;

		// Edit = show submit form again
		if ( ! empty( $_POST['edit_job'] ) ) {
			self::$step --;
		}
		// Continue = change job status then show next screen
		if ( ! empty( $_POST['continue'] ) ) {

			$job = get_post( self::$job_id );

			if ( $job->post_status == 'preview' ) {
				$update_job                = array();
				$update_job['ID']          = $job->ID;
				$update_job['post_status'] = get_option( 'job_manager_submission_requires_approval' ) ? 'pending' : 'publish';
				wp_update_post( $update_job );
			}

			self::$step ++;
		}
	}

	/**
	 * Done Step
	 */
	public static function done() {
		do_action( 'job_manager_job_submitted', self::$job_id );

		get_job_manager_template( 'job-submitted.php', array( 'job' => get_post( self::$job_id ) ) );
	}

	/**
	 * Upload Image
	 */
	public static function upload_image( $field_key ) {

		/** WordPress Administration File API */
		include_once( ABSPATH . 'wp-admin/includes/file.php' );

		/** WordPress Media Administration API */
		include_once( ABSPATH . 'wp-admin/includes/media.php' );

		if ( isset( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ]['name'] ) ) {
			$file   = $_FILES[ $field_key ];

			if ( $_FILES[ $field_key ]["type"] != "image/jpeg" && $_FILES[ $field_key ]["type"] != "image/gif" && $_FILES[ $field_key ]["type"] != "image/png" )
    			throw new Exception( __( 'Logo needs to be jpg, gif or png.', 'job_manager' ) );

			add_filter( 'upload_dir',  array( __CLASS__, 'upload_dir' ) );

			$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

			remove_filter('upload_dir', array( __CLASS__, 'upload_dir' ) );

			if ( ! empty( $upload['error'] ) ) {
				throw new Exception( $upload['error'] );
			} else {
				return $upload['url'];
			}
		}
	}

	/**
	 * Filter the upload directory
	 */
	public static function upload_dir( $pathdata ) {
		$subdir             = '/job_listing_images';
		$pathdata['path']   = str_replace( $pathdata['subdir'], $subdir, $pathdata['path'] );
		$pathdata['url']    = str_replace( $pathdata['subdir'], $subdir, $pathdata['url'] );
		$pathdata['subdir'] = str_replace( $pathdata['subdir'], $subdir, $pathdata['subdir'] );
		return $pathdata;
	}
}