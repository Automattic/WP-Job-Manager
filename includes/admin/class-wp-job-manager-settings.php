<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handles the management of plugin settings.
 *
 * @package wp-job-manager
 * @since 1.0.0
 */
class WP_Job_Manager_Settings {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $_instance = null;

	/**
	 * Our Settings.
	 *
	 * @var array Settings.
	 */
	protected $settings = array();

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
	 * @static
	 * @return self Main instance.
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
		$this->settings_group = 'job_manager';
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Get Job Manager Settings
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( 0 === count( $this->settings ) ) {
			$this->init_settings();
		}
		return $this->settings;
	}

	/**
	 * Initializes the configuration for the plugin's setting fields.
	 *
	 * @access protected
	 */
	protected function init_settings() {
		// Prepare roles option.
		$roles         = get_editable_roles();
		$account_roles = array();

		foreach ( $roles as $key => $role ) {
			if ( 'administrator' === $key ) {
				continue;
			}
			$account_roles[ $key ] = $role['name'];
		}

		$this->settings = apply_filters( 'job_manager_settings',
			array(
				'job_listings' => array(
					__( 'Job Listings', 'wp-job-manager' ),
					array(
						array(
							'name'        => 'job_manager_per_page',
							'std'         => '10',
							'placeholder' => '',
							'label'       => __( 'Listings Per Page', 'wp-job-manager' ),
							'desc'        => __( 'Number of job listings to display per page.', 'wp-job-manager' ),
							'attributes'  => array()
						),
						array(
							'name'       => 'job_manager_hide_filled_positions',
							'std'        => '0',
							'label'      => __( 'Filled Positions', 'wp-job-manager' ),
							'cb_label'   => __( 'Hide filled positions', 'wp-job-manager' ),
							'desc'       => __( 'Filled positions will not display in your archives.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_hide_expired',
							'std'        => get_option( 'job_manager_hide_expired_content' ) ? '1' : '0', // back compat
							'label'      => __( 'Hide Expired Listings', 'wp-job-manager' ),
							'cb_label'   => __( 'Hide expired listings in job archives/search', 'wp-job-manager' ),
							'desc'       => __( 'Expired job listings will not be searchable.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_hide_expired_content',
							'std'        => '1',
							'label'      => __( 'Hide Expired Listings Content', 'wp-job-manager' ),
							'cb_label'   => __( 'Hide content in expired single job listings', 'wp-job-manager' ),
							'desc'       => __( 'Your site will display the titles of expired listings, but not the content of the listings. Otherwise, expired listings display their full content minus the application area.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_enable_categories',
							'std'        => '0',
							'label'      => __( 'Categories', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable listing categories', 'wp-job-manager' ),
							'desc'       => __( 'This lets users select from a list of categories when submitting a job. Note: an admin has to create categories before site users can select them.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_enable_default_category_multiselect',
							'std'        => '0',
							'label'      => __( 'Multi-select Categories', 'wp-job-manager' ),
							'cb_label'   => __( 'Default to category multiselect', 'wp-job-manager' ),
							'desc'       => __( 'The category selection box will default to allowing multiple selections on the [jobs] shortcode. Without this, users will only be able to select a single category when submitting jobs.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_category_filter_type',
							'std'        => 'any',
							'label'      => __( 'Category Filter Type', 'wp-job-manager' ),
							'desc'       => __( 'Determines the logic used to display jobs when selecting multiple categories.', 'wp-job-manager' ),
							'type'       => 'radio',
							'options' => array(
								'any'  => __( 'Jobs will be shown if within ANY selected category', 'wp-job-manager' ),
								'all' => __( 'Jobs will be shown if within ALL selected categories', 'wp-job-manager' ),
							)
						),
						array(
							'name'       => 'job_manager_enable_types',
							'std'        => '1',
							'label'      => __( 'Types', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable listing types', 'wp-job-manager' ),
							'desc'       => __( 'This lets users select from a list of types when submitting a job. Note: an admin has to create types before site users can select them.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_multi_job_type',
							'std'        => '0',
							'label'      => __( 'Multi-select Listing Types', 'wp-job-manager' ),
							'cb_label'   => __( 'Allow multiple types for listings', 'wp-job-manager' ),
							'desc'       => __( 'This allows users to select more than one type when submitting a job. The metabox on the post editor and the selection box on the front-end job submission form will both reflect this.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_date_format',
							'std'        => 'relative',
							'label'      => __( 'Date Format', 'wp-job-manager' ),
							'desc'       => __( 'Choose how you want the published date for jobs to be displayed on the front-end.', 'wp-job-manager' ),
							'type'       => 'radio',
							'options'    => array(
								'relative' => __( 'Relative to the current date (e.g., 1 day, 1 week, 1 month ago)', 'wp-job-manager' ),
								'default'   => __( 'Default date format as defined in Settings', 'wp-job-manager' ),
							)
						),
						array(
							'name'       => 'job_manager_google_maps_api_key',
							'std'        => '',
							'label'      => __( 'Google Maps API Key', 'wp-job-manager' ),
							'desc'       => sprintf( __( 'Google requires an API key to retrieve location information for job listings. Acquire an API key from the <a href="%s">Google Maps API developer site</a>.', 'wp-job-manager' ), 'https://developers.google.com/maps/documentation/geocoding/get-api-key' ),
							'attributes' => array()
						),
					),
				),
				'job_submission' => array(
					__( 'Job Submission', 'wp-job-manager' ),
					array(
						array(
							'name'       => 'job_manager_user_requires_account',
							'std'        => '1',
							'label'      => __( 'Account Required', 'wp-job-manager' ),
							'cb_label'   => __( 'Require an account to submit listings', 'wp-job-manager' ),
							'desc'       => __( 'Limits job listing submissions to registered, logged-in users.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_enable_registration',
							'std'        => '1',
							'label'      => __( 'Account Creation', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable account creation during submission', 'wp-job-manager' ),
							'desc'       => __( 'Includes account creation on the listing submission form, to allow non-registered users to create an account and submit a job listing simultaneously.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_generate_username_from_email',
							'std'        => '1',
							'label'      => __( 'Account Username', 'wp-job-manager' ),
							'cb_label'   => __( 'Generate usernames from email addresses', 'wp-job-manager' ),
							'desc'       => __( 'Automatically generates usernames for new accounts from the registrant\'s email address. If this is not enabled, a "username" field will display instead.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_use_standard_password_setup_email',
							'std'        => '1',
							'label'      => __( 'Account Password', 'wp-job-manager' ),
							'cb_label'   => __( 'Email new users a link to set a password', 'wp-job-manager' ),
							'desc'       => __( 'Sends an email to the user with their username and a link to set their password. If this is not enabled, a "password" field will display instead, and their email address won\'t be verified.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_registration_role',
							'std'        => 'employer',
							'label'      => __( 'Account Role', 'wp-job-manager' ),
							'desc'       => __( 'Any new accounts created during submission will have this role. If you haven\'t enabled account creation during submission in the options above, your own method of assigning roles will apply.', 'wp-job-manager' ),
							'type'       => 'select',
							'options'    => $account_roles
						),
						array(
							'name'       => 'job_manager_submission_requires_approval',
							'std'        => '1',
							'label'      => __( 'Moderate New Listings', 'wp-job-manager' ),
							'cb_label'   => __( 'Require admin approval of all new listing submissions', 'wp-job-manager' ),
							'desc'       => __( 'Sets all new submissions to "pending." They will not appear on your site until an admin approves them.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_user_can_edit_pending_submissions',
							'std'        => '0',
							'label'      => __( 'Allow Pending Edits', 'wp-job-manager' ),
							'cb_label'   => __( 'Allow editing of pending listings', 'wp-job-manager' ),
							'desc'       => __( 'Users can continue to edit pending listings until they are approved by an admin.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_submission_duration',
							'std'        => '30',
							'label'      => __( 'Listing Duration', 'wp-job-manager' ),
							'desc'       => __( 'Listings will display for the set number of days, then expire. Leave this field blank if you don\'t want listings to have an expiration date.', 'wp-job-manager' ),
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_allowed_application_method',
							'std'        => '',
							'label'      => __( 'Application Method', 'wp-job-manager' ),
							'desc'       => __( 'Choose the application method job listers will need to provide. Specify URL or email address only, or allow listers to choose which they prefer.', 'wp-job-manager' ),
							'type'       => 'radio',
							'options'    => array(
								''      => __( 'Email address or website URL', 'wp-job-manager' ),
								'email' => __( 'Email addresses only', 'wp-job-manager' ),
								'url'   => __( 'Website URLs only', 'wp-job-manager' ),
							)
						),
					)
				),
				'job_pages' => array(
					__( 'Pages', 'wp-job-manager' ),
					array(
						array(
							'name' 		=> 'job_manager_submit_job_form_page_id',
							'std' 		=> '',
							'label' 	=> __( 'Submit Job Form Page', 'wp-job-manager' ),
							'desc'		=> __( 'Select the page where you\'ve used the [submit_job_form] shortcode. This lets the plugin know the location of the form.', 'wp-job-manager' ),
							'type'      => 'page'
						),
						array(
							'name' 		=> 'job_manager_job_dashboard_page_id',
							'std' 		=> '',
							'label' 	=> __( 'Job Dashboard Page', 'wp-job-manager' ),
							'desc'		=> __( 'Select the page where you\'ve used the [job_dashboard] shortcode. This lets the plugin know the location of the dashboard.', 'wp-job-manager' ),
							'type'      => 'page'
						),
						array(
							'name' 		=> 'job_manager_jobs_page_id',
							'std' 		=> '',
							'label' 	=> __( 'Job Listings Page', 'wp-job-manager' ),
							'desc'		=> __( 'Select the page where you\'ve used the [jobs] shortcode. This lets the plugin know the location of the job listings page.', 'wp-job-manager' ),
							'type'      => 'page'
						),
					)
				)
			)
		);
	}

	/**
	 * Registers the plugin's settings with WordPress's Settings API.
	 */
	public function register_settings() {
		$this->init_settings();

		foreach ( $this->settings as $section ) {
			foreach ( $section[1] as $option ) {
				if ( isset( $option['std'] ) )
					add_option( $option['name'], $option['std'] );
				register_setting( $this->settings_group, $option['name'] );
			}
		}
	}

	/**
	 * Shows the plugin's settings page.
	 */
	public function output() {
		$this->init_settings();
		?>
		<div class="wrap job-manager-settings-wrap">
			<form method="post" action="options.php">

				<?php settings_fields( $this->settings_group ); ?>

			    <h2 class="nav-tab-wrapper">
			    	<?php
			    		foreach ( $this->settings as $key => $section ) {
			    			echo '<a href="#settings-' . sanitize_title( $key ) . '" class="nav-tab">' . esc_html( $section[0] ) . '</a>';
			    		}
			    	?>
			    </h2>

				<?php
					if ( ! empty( $_GET['settings-updated'] ) ) {
						flush_rewrite_rules();
						echo '<div class="updated fade job-manager-updated"><p>' . __( 'Settings successfully saved', 'wp-job-manager' ) . '</p></div>';
					}

					foreach ( $this->settings as $key => $section ) {

						echo '<div id="settings-' . sanitize_title( $key ) . '" class="settings_panel">';

						echo '<table class="form-table">';

						foreach ( $section[1] as $option ) {

							$placeholder    = ( ! empty( $option['placeholder'] ) ) ? 'placeholder="' . $option['placeholder'] . '"' : '';
							$class          = ! empty( $option['class'] ) ? $option['class'] : '';
							$value          = get_option( $option['name'] );
							$option['type'] = ! empty( $option['type'] ) ? $option['type'] : '';
							$attributes     = array();

							if ( ! empty( $option['attributes'] ) && is_array( $option['attributes'] ) )
								foreach ( $option['attributes'] as $attribute_name => $attribute_value )
									$attributes[] = esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"';

							echo '<tr valign="top" class="' . $class . '"><th scope="row"><label for="setting-' . $option['name'] . '">' . $option['label'] . '</a></th><td>';

							switch ( $option['type'] ) {

								case "checkbox" :

									?><label><input id="setting-<?php echo $option['name']; ?>" name="<?php echo $option['name']; ?>" type="checkbox" value="1" <?php echo implode( ' ', $attributes ); ?> <?php checked( '1', $value ); ?> /> <?php echo $option['cb_label']; ?></label><?php

									if ( $option['desc'] )
										echo ' <p class="description">' . $option['desc'] . '</p>';

								break;
								case "textarea" :

									?><textarea id="setting-<?php echo $option['name']; ?>" class="large-text" cols="50" rows="3" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?>><?php echo esc_textarea( $value ); ?></textarea><?php

									if ( $option['desc'] )
										echo ' <p class="description">' . $option['desc'] . '</p>';

								break;
								case "select" :

									?><select id="setting-<?php echo $option['name']; ?>" class="regular-text" name="<?php echo $option['name']; ?>" <?php echo implode( ' ', $attributes ); ?>><?php
										foreach( $option['options'] as $key => $name )
											echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $name ) . '</option>';
									?></select><?php

									if ( $option['desc'] ) {
										echo ' <p class="description">' . $option['desc'] . '</p>';
									}

								break;
								case "radio":
									?><fieldset>
										<legend class="screen-reader-text">
											<span><?php echo esc_html( $option['label'] ); ?></span>
										</legend><?php

									if ( $option['desc'] ) {
										echo '<p class="description">' . $option['desc'] . '</p>';
									}

									foreach( $option['options'] as $key => $name )
										echo '<label><input name="' . esc_attr( $option['name'] ) . '" type="radio" value="' . esc_attr( $key ) . '" ' . checked( $value, $key, false ) . ' />' . esc_html( $name ) . '</label><br>';

									?></fieldset><?php

								break;
								case "page" :

									$args = array(
										'name'             => $option['name'],
										'id'               => $option['name'],
										'sort_column'      => 'menu_order',
										'sort_order'       => 'ASC',
										'show_option_none' => __( '--no page--', 'wp-job-manager' ),
										'echo'             => false,
										'selected'         => absint( $value )
									);

									echo str_replace(' id=', " data-placeholder='" . __( 'Select a page&hellip;', 'wp-job-manager' ) .  "' id=", wp_dropdown_pages( $args ) );

									if ( $option['desc'] ) {
										echo ' <p class="description">' . $option['desc'] . '</p>';
									}

								break;
								case "password" :

									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="password" name="<?php echo $option['name']; ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

									if ( $option['desc'] ) {
										echo ' <p class="description">' . $option['desc'] . '</p>';
									}

								break;
								case "number" :
									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="number" name="<?php echo $option['name']; ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

									if ( $option['desc'] ) {
										echo ' <p class="description">' . $option['desc'] . '</p>';
									}
								break;
								case "" :
								case "input" :
								case "text" :
									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="text" name="<?php echo $option['name']; ?>" value="<?php echo esc_attr( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

									if ( $option['desc'] ) {
										echo ' <p class="description">' . $option['desc'] . '</p>';
									}
								break;
								default :
									do_action( 'wp_job_manager_admin_field_' . $option['type'], $option, $attributes, $value, $placeholder );
								break;

							}

							echo '</td></tr>';
						}

						echo '</table></div>';

					}
				?>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Changes', 'wp-job-manager' ); ?>" />
				</p>
		    </form>
		</div>
		<script type="text/javascript">
			jQuery('.nav-tab-wrapper a').click(function() {
				jQuery('.settings_panel').hide();
				jQuery('.nav-tab-active').removeClass('nav-tab-active');
				jQuery( jQuery(this).attr('href') ).show();
				jQuery(this).addClass('nav-tab-active');
				return false;
			});
			var goto_hash = window.location.hash;
			if ( goto_hash ) {
				var the_tab = jQuery( 'a[href="' + goto_hash + '"]' );
				if ( the_tab.length > 0 ) {
					the_tab.click();
				} else {
					jQuery( '.nav-tab-wrapper a:first' ).click();
				}
			} else {
				jQuery( '.nav-tab-wrapper a:first' ).click();
			}
			var $use_standard_password_setup_email = jQuery('#setting-job_manager_use_standard_password_setup_email');
			var $generate_username_from_email = jQuery('#setting-job_manager_generate_username_from_email');
			var $job_manager_registration_role = jQuery('#setting-job_manager_registration_role');

			jQuery('#setting-job_manager_enable_registration').change(function(){
				if ( jQuery( this ).is(':checked') ) {
					$job_manager_registration_role.closest('tr').show();
					$use_standard_password_setup_email.closest('tr').show();
					$generate_username_from_email.closest('tr').show();
				} else {
					$job_manager_registration_role.closest('tr').hide();
					$use_standard_password_setup_email.closest('tr').hide();
					$generate_username_from_email.closest('tr').hide();
				}
			}).change();

			// If generate username is enabled on page load, assume use_standard_password_setup_email has been cleared.
			// Default is true, so let's sneakily set it to that before it gets cleared and disabled.
			if ( $generate_username_from_email.is(':checked') ) {
				$use_standard_password_setup_email.prop('checked', true);
			}

			$generate_username_from_email.change(function() {
				if ( jQuery( this ).is(':checked') ) {
				    $use_standard_password_setup_email.data('original-state', $use_standard_password_setup_email.is(':checked')).prop('checked', true).prop('disabled', true);
				} else {
					$use_standard_password_setup_email.prop('disabled', false);
					if ( undefined !== $use_standard_password_setup_email.data('original-state') ) {
						$use_standard_password_setup_email.prop('checked', $use_standard_password_setup_email.data('original-state'));
					}
				}
			}).change();
		</script>
		<?php
	}
}
