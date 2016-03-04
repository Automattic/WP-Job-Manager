<?php

/**
 * WP_Job_Manager_Form_Submit_Job class.
 */
class WP_Job_Manager_Form_Submit_Job extends WP_Job_Manager_Form {

	public    $form_name = 'submit-job';
	protected $job_id;
	protected $preview_job;

	/** @var WP_Job_Manager_Form_Submit_Job The single instance of the class */
	protected static $_instance = null;

	/**
	 * Main Instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'process' ) );

		$this->steps  = (array) apply_filters( 'submit_job_steps', array(
			'submit' => array(
				'name'     => __( 'Submit Details', 'wp-job-manager' ),
				'view'     => array( $this, 'submit' ),
				'handler'  => array( $this, 'submit_handler' ),
				'priority' => 10
				),
			'preview' => array(
				'name'     => __( 'Preview', 'wp-job-manager' ),
				'view'     => array( $this, 'preview' ),
				'handler'  => array( $this, 'preview_handler' ),
				'priority' => 20
			),
			'done' => array(
				'name'     => __( 'Done', 'wp-job-manager' ),
				'view'     => array( $this, 'done' ),
				'priority' => 30
			)
		) );

		uasort( $this->steps, array( $this, 'sort_by_priority' ) );

		// Get step/job
		if ( isset( $_POST['step'] ) ) {
			$this->step = is_numeric( $_POST['step'] ) ? max( absint( $_POST['step'] ), 0 ) : array_search( $_POST['step'], array_keys( $this->steps ) );
		} elseif ( ! empty( $_GET['step'] ) ) {
			$this->step = is_numeric( $_GET['step'] ) ? max( absint( $_GET['step'] ), 0 ) : array_search( $_GET['step'], array_keys( $this->steps ) );
		}

		$this->job_id = ! empty( $_REQUEST['job_id'] ) ? absint( $_REQUEST[ 'job_id' ] ) : 0;

		// Allow resuming from cookie.
		if ( ! $this->job_id && ! empty( $_COOKIE['wp-job-manager-submitting-job-id'] ) && ! empty( $_COOKIE['wp-job-manager-submitting-job-key'] ) ) {
			$job_id     = absint( $_COOKIE['wp-job-manager-submitting-job-id'] );
			$job_status = get_post_status( $job_id );

			if ( 'preview' === $job_status && get_post_meta( $job_id, '_submitting_key', true ) === $_COOKIE['wp-job-manager-submitting-job-key'] ) {
				$this->job_id = $job_id;
			}
		}

		// Load job details
		if ( $this->job_id ) {
			$job_status = get_post_status( $this->job_id );
			if ( 'expired' === $job_status ) {
				if ( ! job_manager_user_can_edit_job( $this->job_id ) ) {
					$this->job_id = 0;
					$this->step   = 0;
				}
			} elseif ( ! in_array( $job_status, apply_filters( 'job_manager_valid_submit_job_statuses', array( 'preview' ) ) ) ) {
				$this->job_id = 0;
				$this->step   = 0;
			}
		}
	}

	/**
	 * Get the submitted job ID
	 * @return int
	 */
	public function get_job_id() {
		return absint( $this->job_id );
	}

	/**
	 * init_fields function.
	 */
	public function init_fields() {
		if ( $this->fields ) {
			return;
		}

		$allowed_application_method = get_option( 'job_manager_allowed_application_method', '' );
		switch ( $allowed_application_method ) {
			case 'email' :
				$application_method_label       = __( 'Application email', 'wp-job-manager' );
				$application_method_placeholder = __( 'you@yourdomain.com', 'wp-job-manager' );
			break;
			case 'url' :
				$application_method_label       = __( 'Application URL', 'wp-job-manager' );
				$application_method_placeholder = __( 'http://', 'wp-job-manager' );
			break;
			default :
				$application_method_label       = __( 'Application email/URL', 'wp-job-manager' );
				$application_method_placeholder = __( 'Enter an email address or website URL', 'wp-job-manager' );
			break;
		}

		$this->fields = apply_filters( 'submit_job_form_fields', array(
			'job' => array(
				'job_title' => array(
					'label'       => __( 'Job Title', 'wp-job-manager' ),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 1
				),
				'job_location' => array(
					'label'       => __( 'Location', 'wp-job-manager' ),
					'description' => __( 'Leave this blank if the location is not important', 'wp-job-manager' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'e.g. "London"', 'wp-job-manager' ),
					'priority'    => 2
				),
				'job_type' => array(
					'label'       => __( 'Job type', 'wp-job-manager' ),
					'type'        => 'term-select',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 3,
					'default'     => 'full-time',
					'taxonomy'    => 'job_listing_type'
				),
				'job_category' => array(
					'label'       => __( 'Job category', 'wp-job-manager' ),
					'type'        => 'term-multiselect',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 4,
					'default'     => '',
					'taxonomy'    => 'job_listing_category'
				),
				'job_description' => array(
					'label'       => __( 'Description', 'wp-job-manager' ),
					'type'        => 'wp-editor',
					'required'    => true,
					'placeholder' => '',
					'priority'    => 5
				),
				'application' => array(
					'label'       => $application_method_label,
					'type'        => 'text',
					'required'    => true,
					'placeholder' => $application_method_placeholder,
					'priority'    => 6
				)
			),
			'company' => array(
				'company_name' => array(
					'label'       => __( 'Company name', 'wp-job-manager' ),
					'type'        => 'text',
					'required'    => true,
					'placeholder' => __( 'Enter the name of the company', 'wp-job-manager' ),
					'priority'    => 1
				),
				'company_website' => array(
					'label'       => __( 'Website', 'wp-job-manager' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'http://', 'wp-job-manager' ),
					'priority'    => 2
				),
				'company_tagline' => array(
					'label'       => __( 'Tagline', 'wp-job-manager' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'Briefly describe your company', 'wp-job-manager' ),
					'maxlength'   => 64,
					'priority'    => 3
				),
				'company_video' => array(
					'label'       => __( 'Video', 'wp-job-manager' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( 'A link to a video about your company', 'wp-job-manager' ),
					'priority'    => 4
				),
				'company_twitter' => array(
					'label'       => __( 'Twitter username', 'wp-job-manager' ),
					'type'        => 'text',
					'required'    => false,
					'placeholder' => __( '@yourcompany', 'wp-job-manager' ),
					'priority'    => 5
				),
				'company_logo' => array(
					'label'       => __( 'Logo', 'wp-job-manager' ),
					'type'        => 'file',
					'required'    => false,
					'placeholder' => '',
					'priority'    => 6,
					'ajax'        => true,
					'multiple'    => false,
					'allowed_mime_types' => array(
						'jpg'  => 'image/jpeg',
						'jpeg' => 'image/jpeg',
						'gif'  => 'image/gif',
						'png'  => 'image/png'
					)
				)
			)
		) );

		if ( ! get_option( 'job_manager_enable_categories' ) || wp_count_terms( 'job_listing_category' ) == 0 ) {
			unset( $this->fields['job']['job_category'] );
		}
	}

	/**
	 * Validate the posted fields
	 *
	 * @return bool on success, WP_ERROR on failure
	 */
	protected function validate_fields( $values ) {
		foreach ( $this->fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				if ( $field['required'] && empty( $values[ $group_key ][ $key ] ) ) {
					return new WP_Error( 'validation-error', sprintf( __( '%s is a required field', 'wp-job-manager' ), $field['label'] ) );
				}
				if ( ! empty( $field['taxonomy'] ) && in_array( $field['type'], array( 'term-checklist', 'term-select', 'term-multiselect' ) ) ) {
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						$check_value = $values[ $group_key ][ $key ];
					} else {
						$check_value = array( $values[ $group_key ][ $key ] );
					}
					foreach ( $check_value as $term ) {
						if ( ! term_exists( $term, $field['taxonomy'] ) ) {
							return new WP_Error( 'validation-error', sprintf( __( '%s is invalid', 'wp-job-manager' ), $field['label'] ) );
						}
					}
				}
				if ( 'file' === $field['type'] && ! empty( $field['allowed_mime_types'] ) ) {
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						$check_value = array_filter( $values[ $group_key ][ $key ] );
					} else {
						$check_value = array_filter( array( $values[ $group_key ][ $key ] ) );
					}
					if ( ! empty( $check_value ) ) {
						foreach ( $check_value as $file_url ) {
							$file_url = current( explode( '?', $file_url ) );

							if ( ( $info = wp_check_filetype( $file_url ) ) && ! in_array( $info['type'], $field['allowed_mime_types'] ) ) {
								throw new Exception( sprintf( __( '"%s" (filetype %s) needs to be one of the following file types: %s', 'wp-job-manager' ), $field['label'], $info['ext'], implode( ', ', array_keys( $field['allowed_mime_types'] ) ) ) );
							}
						}
					}
				}
			}
		}

		// Application method
		if ( isset( $values['job']['application'] ) && ! empty( $values['job']['application'] ) ) {
			$allowed_application_method = get_option( 'job_manager_allowed_application_method', '' );
			$values['job']['application'] = str_replace( ' ', '+', $values['job']['application'] );
			switch ( $allowed_application_method ) {
				case 'email' :
					if ( ! is_email( $values['job']['application'] ) ) {
						throw new Exception( __( 'Please enter a valid application email address', 'wp-job-manager' ) );
					}
				break;
				case 'url' :
					// Prefix http if needed
					if ( ! strstr( $values['job']['application'], 'http:' ) && ! strstr( $values['job']['application'], 'https:' ) ) {
						$values['job']['application'] = 'http://' . $values['job']['application'];
					}
					if ( ! filter_var( $values['job']['application'], FILTER_VALIDATE_URL ) ) {
						throw new Exception( __( 'Please enter a valid application URL', 'wp-job-manager' ) );
					}
				break;
				default :
					if ( ! is_email( $values['job']['application'] ) ) {
						// Prefix http if needed
						if ( ! strstr( $values['job']['application'], 'http:' ) && ! strstr( $values['job']['application'], 'https:' ) ) {
							$values['job']['application'] = 'http://' . $values['job']['application'];
						}
						if ( ! filter_var( $values['job']['application'], FILTER_VALIDATE_URL ) ) {
							throw new Exception( __( 'Please enter a valid application email address or URL', 'wp-job-manager' ) );
						}
					}
				break;
			}
		}

		return apply_filters( 'submit_job_form_validate_fields', true, $this->fields, $values );
	}

	/**
	 * job_types function.
	 */
	private function job_types() {
		$options = array();
		$terms   = get_job_listing_types();
		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}
		return $options;
	}

	/**
	 * Submit Step
	 */
	public function submit() {
		$this->init_fields();

		// Load data if neccessary
		if ( $this->job_id ) {
			$job = get_post( $this->job_id );
			foreach ( $this->fields as $group_key => $group_fields ) {
				foreach ( $group_fields as $key => $field ) {
					switch ( $key ) {
						case 'job_title' :
							$this->fields[ $group_key ][ $key ]['value'] = $job->post_title;
						break;
						case 'job_description' :
							$this->fields[ $group_key ][ $key ]['value'] = $job->post_content;
						break;
						case 'job_type' :
							$this->fields[ $group_key ][ $key ]['value'] = current( wp_get_object_terms( $job->ID, 'job_listing_type', array( 'fields' => 'ids' ) ) );
						break;
						case 'job_category' :
							$this->fields[ $group_key ][ $key ]['value'] = wp_get_object_terms( $job->ID, 'job_listing_category', array( 'fields' => 'ids' ) );
						break;
						default:
							$this->fields[ $group_key ][ $key ]['value'] = get_post_meta( $job->ID, '_' . $key, true );
						break;
					}
				}
			}

			$this->fields = apply_filters( 'submit_job_form_fields_get_job_data', $this->fields, $job );

		// Get user meta
		} elseif ( is_user_logged_in() && empty( $_POST['submit_job'] ) ) {
			if ( ! empty( $this->fields['company'] ) ) {
				foreach ( $this->fields['company'] as $key => $field ) {
					$this->fields['company'][ $key ]['value'] = get_user_meta( get_current_user_id(), '_' . $key, true );
				}
			}
			if ( ! empty( $this->fields['job']['application'] ) ) {
				$allowed_application_method = get_option( 'job_manager_allowed_application_method', '' );
				if ( $allowed_application_method !== 'url' ) {
					$current_user = wp_get_current_user();
					$this->fields['job']['application']['value'] = $current_user->user_email;
				}
			}
			$this->fields = apply_filters( 'submit_job_form_fields_get_user_data', $this->fields, get_current_user_id() );
		}

		wp_enqueue_script( 'wp-job-manager-job-submission' );

		get_job_manager_template( 'job-submit.php', array(
			'form'               => $this->form_name,
			'job_id'             => $this->get_job_id(),
			'action'             => $this->get_action(),
			'job_fields'         => $this->get_fields( 'job' ),
			'company_fields'     => $this->get_fields( 'company' ),
			'step'               => $this->get_step(),
			'submit_button_text' => apply_filters( 'submit_job_form_submit_button_text', __( 'Preview', 'wp-job-manager' ) )
		) );
	}

	/**
	 * Submit Step is posted
	 */
	public function submit_handler() {
		try {
			// Init fields
			$this->init_fields();

			// Get posted values
			$values = $this->get_posted_fields();

			if ( empty( $_POST['submit_job'] ) ) {
				return;
			}

			// Validate required
			if ( is_wp_error( ( $return = $this->validate_fields( $values ) ) ) ) {
				throw new Exception( $return->get_error_message() );
			}

			// Account creation
			if ( ! is_user_logged_in() ) {
				$create_account = false;

				if ( job_manager_enable_registration() ) {
					if ( job_manager_user_requires_account() ) {
						if ( ! job_manager_generate_username_from_email() && empty( $_POST['create_account_username'] ) ) {
							throw new Exception( __( 'Please enter a username.', 'wp-job-manager' ) );
						}
						if ( empty( $_POST['create_account_email'] ) ) {
							throw new Exception( __( 'Please enter your email address.', 'wp-job-manager' ) );
						}
					}
					if ( ! empty( $_POST['create_account_email'] ) ) {
						$create_account = wp_job_manager_create_account( array(
							'username' => empty( $_POST['create_account_username'] ) ? '' : $_POST['create_account_username'],
							'email'    => $_POST['create_account_email'],
							'role'     => get_option( 'job_manager_registration_role' )
						) );
					}
				}

				if ( is_wp_error( $create_account ) ) {
					throw new Exception( $create_account->get_error_message() );
				}
			}

			if ( job_manager_user_requires_account() && ! is_user_logged_in() ) {
				throw new Exception( __( 'You must be signed in to post a new listing.' ) );
			}

			// Update the job
			$this->save_job( $values['job']['job_title'], $values['job']['job_description'], $this->job_id ? '' : 'preview', $values );
			$this->update_job_data( $values );

			// Successful, show next step
			$this->step ++;

		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Update or create a job listing from posted data
	 *
	 * @param  string $post_title
	 * @param  string $post_content
	 * @param  string $status
	 * @param  array $values
	 * @param  bool $update_slug
	 */
	protected function save_job( $post_title, $post_content, $status = 'preview', $values = array(), $update_slug = true ) {
		$job_data = array(
			'post_title'     => $post_title,
			'post_content'   => $post_content,
			'post_type'      => 'job_listing',
			'comment_status' => 'closed'
		);

		if ( $update_slug ) {
			$job_slug   = array();

			// Prepend with company name
			if ( apply_filters( 'submit_job_form_prefix_post_name_with_company', true ) && ! empty( $values['company']['company_name'] ) ) {
				$job_slug[] = $values['company']['company_name'];
			}

			// Prepend location
			if ( apply_filters( 'submit_job_form_prefix_post_name_with_location', true ) && ! empty( $values['job']['job_location'] ) ) {
				$job_slug[] = $values['job']['job_location'];
			}

			// Prepend with job type
			if ( apply_filters( 'submit_job_form_prefix_post_name_with_job_type', true ) && ! empty( $values['job']['job_type'] ) ) {
				$job_slug[] = $values['job']['job_type'];
			}

			$job_slug[]            = $post_title;
			$job_data['post_name'] = sanitize_title( implode( '-', $job_slug ) );
		}

		if ( $status ) {
			$job_data['post_status'] = $status;
		}

		$job_data = apply_filters( 'submit_job_form_save_job_data', $job_data, $post_title, $post_content, $status, $values );

		if ( $this->job_id ) {
			$job_data['ID'] = $this->job_id;
			wp_update_post( $job_data );
		} else {
			$this->job_id = wp_insert_post( $job_data );

			if ( ! headers_sent() ) {
				$submitting_key = uniqid();

				setcookie( 'wp-job-manager-submitting-job-id', $this->job_id, false, COOKIEPATH, COOKIE_DOMAIN, false );
				setcookie( 'wp-job-manager-submitting-job-key', $submitting_key, false, COOKIEPATH, COOKIE_DOMAIN, false );

				update_post_meta( $this->job_id, '_submitting_key', $submitting_key );
			}
		}
	}

	/**
	 * Set job meta + terms based on posted values
	 *
	 * @param  array $values
	 */
	protected function update_job_data( $values ) {
		// Set defaults
		add_post_meta( $this->job_id, '_filled', 0, true );
		add_post_meta( $this->job_id, '_featured', 0, true );

		$maybe_attach = array();

		// Loop fields and save meta and term data
		foreach ( $this->fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				// Save taxonomies
				if ( ! empty( $field['taxonomy'] ) ) {
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						wp_set_object_terms( $this->job_id, $values[ $group_key ][ $key ], $field['taxonomy'], false );
					} else {
						wp_set_object_terms( $this->job_id, array( $values[ $group_key ][ $key ] ), $field['taxonomy'], false );
					}

				// Save meta data
				} else {
					update_post_meta( $this->job_id, '_' . $key, $values[ $group_key ][ $key ] );
				}

				// Handle attachments
				if ( 'file' === $field['type'] ) {
					// Must be absolute
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						foreach ( $values[ $group_key ][ $key ] as $file_url ) {
							if ( strstr( $file_url, WP_CONTENT_URL ) ) {
								$maybe_attach[] = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $file_url );
							}
						}
					} else {
						if ( strstr( $values[ $group_key ][ $key ], WP_CONTENT_URL ) ) {
							$maybe_attach[] = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, $values[ $group_key ][ $key ] );
						}
					}
				}
			}
		}

		// Handle attachments
		if ( sizeof( $maybe_attach ) ) {
			/** WordPress Administration Image API */
			include_once( ABSPATH . 'wp-admin/includes/image.php' );

			// Get attachments
			$attachments     = get_posts( 'post_parent=' . $this->job_id . '&post_type=attachment&fields=ids&post_mime_type=image&numberposts=-1' );
			$attachment_urls = array();

			// Loop attachments already attached to the job
			foreach ( $attachments as $attachment_key => $attachment ) {
				$attachment_urls[] = str_replace( WP_CONTENT_URL, WP_CONTENT_DIR, wp_get_attachment_url( $attachment ) );
			}

			foreach ( $maybe_attach as $attachment_url ) {
				if ( ! in_array( $attachment_url, $attachment_urls ) ) {
					$attachment = array(
						'post_title'   => get_the_title( $this->job_id ),
						'post_content' => '',
						'post_status'  => 'inherit',
						'post_parent'  => $this->job_id,
						'guid'         => $attachment_url
					);

					if ( $info = wp_check_filetype( $attachment_url ) ) {
						$attachment['post_mime_type'] = $info['type'];
					}

					$attachment_id = wp_insert_attachment( $attachment, $attachment_url, $this->job_id );

					if ( ! is_wp_error( $attachment_id ) ) {
						wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $attachment_url ) );
					}
				}
			}
		}

		// And user meta to save time in future
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), '_company_name', isset( $values['company']['company_name'] ) ? $values['company']['company_name'] : '' );
			update_user_meta( get_current_user_id(), '_company_website', isset( $values['company']['company_website'] ) ? urlencode_deep($values['company']['company_website']): '' );
			update_user_meta( get_current_user_id(), '_company_tagline', isset( $values['company']['company_tagline'] ) ? $values['company']['company_tagline'] : '' );
			update_user_meta( get_current_user_id(), '_company_twitter', isset( $values['company']['company_twitter'] ) ? urlencode_deep($values['company']['company_twitter']) : '' );
			update_user_meta( get_current_user_id(), '_company_logo', isset( $values['company']['company_logo'] ) ? urlencode_deep($values['company']['company_logo']) : '' );
			update_user_meta( get_current_user_id(), '_company_video', isset( $values['company']['company_video'] ) ? urlencode_deep($values['company']['company_video']) : '' );
		}

		do_action( 'job_manager_update_job_data', $this->job_id, $values );
	}

	/**
	 * Preview Step
	 */
	public function preview() {
		global $post, $job_preview;

		if ( $this->job_id ) {
			$job_preview       = true;
			$action            = $this->get_action();
			$post              = get_post( $this->job_id );
			setup_postdata( $post );
			$post->post_status = 'preview';
			?>
			<form method="post" id="job_preview" action="<?php echo esc_url( $action ); ?>">
				<div class="job_listing_preview_title">
					<input type="submit" name="continue" id="job_preview_submit_button" class="button" value="<?php echo apply_filters( 'submit_job_step_preview_submit_text', __( 'Submit Listing', 'wp-job-manager' ) ); ?>" />
					<input type="submit" name="edit_job" class="button" value="<?php _e( 'Edit listing', 'wp-job-manager' ); ?>" />
					<input type="hidden" name="job_id" value="<?php echo esc_attr( $this->job_id ); ?>" />
					<input type="hidden" name="step" value="<?php echo esc_attr( $this->step ); ?>" />
					<input type="hidden" name="job_manager_form" value="<?php echo $this->form_name; ?>" />
					<h2>
						<?php _e( 'Preview', 'wp-job-manager' ); ?>
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
	public function preview_handler() {
		if ( ! $_POST ) {
			return;
		}

		// Edit = show submit form again
		if ( ! empty( $_POST['edit_job'] ) ) {
			$this->step --;
		}

		// Continue = change job status then show next screen
		if ( ! empty( $_POST['continue'] ) ) {
			$job = get_post( $this->job_id );

			if ( in_array( $job->post_status, array( 'preview', 'expired' ) ) ) {
				// Reset expiry
				delete_post_meta( $job->ID, '_job_expires' );

				// Update job listing
				$update_job                  = array();
				$update_job['ID']            = $job->ID;
				$update_job['post_status']   = get_option( 'job_manager_submission_requires_approval' ) ? 'pending' : 'publish';
				$update_job['post_date']     = current_time( 'mysql' );
				$update_job['post_date_gmt'] = current_time( 'mysql', 1 );
				wp_update_post( $update_job );
			}

			$this->step ++;
		}
	}

	/**
	 * Done Step
	 */
	public function done() {
		do_action( 'job_manager_job_submitted', $this->job_id );
		get_job_manager_template( 'job-submitted.php', array( 'job' => get_post( $this->job_id ) ) );
	}
}
