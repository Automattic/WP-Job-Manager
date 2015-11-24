<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_Settings class.
 */
class WP_Job_Manager_Settings {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->settings_group = 'job_manager';
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * init_settings function.
	 *
	 * @access protected
	 * @return void
	 */
	protected function init_settings() {
		// Prepare roles option
		$roles         = get_editable_roles();
		$account_roles = array();

		foreach ( $roles as $key => $role ) {
			if ( $key == 'administrator' ) {
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
							'desc'        => __( 'How many listings should be shown per page by default?', 'wp-job-manager' ),
							'attributes'  => array()
						),
						array(
							'name'       => 'job_manager_hide_filled_positions',
							'std'        => '0',
							'label'      => __( 'Filled Positions', 'wp-job-manager' ),
							'cb_label'   => __( 'Hide filled positions', 'wp-job-manager' ),
							'desc'       => __( 'If enabled, filled positions will be hidden from archives.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_hide_expired_content',
							'std'        => '1',
							'label'      => __( 'Expired Listings', 'wp-job-manager' ),
							'cb_label'   => __( 'Hide content within expired listings', 'wp-job-manager' ),
							'desc'       => __( 'If enabled, the content within expired listings will be hidden. Otherwise, expired listings will be displayed as normal (without the application area).', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_enable_categories',
							'std'        => '0',
							'label'      => __( 'Categories', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable categories for listings', 'wp-job-manager' ),
							'desc'       => __( 'Choose whether to enable categories. Categories must be setup by an admin to allow users to choose them during submission.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_enable_default_category_multiselect',
							'std'        => '0',
							'label'      => __( 'Multi-select Categories', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable category multiselect by default', 'wp-job-manager' ),
							'desc'       => __( 'If enabled, the category select box will default to a multiselect on the [jobs] shortcode.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_category_filter_type',
							'std'        => 'any',
							'label'      => __( 'Category Filter Type', 'wp-job-manager' ),
							'desc'       => __( 'If enabled, the category select box will default to a multiselect on the [jobs] shortcode.', 'wp-job-manager' ),
							'type'       => 'select',
							'options' => array(
								'any'  => __( 'Jobs will be shown if within ANY selected category', 'wp-job-manager' ),
								'all' => __( 'Jobs will be shown if within ALL selected categories', 'wp-job-manager' ),
							)
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
							'cb_label'   => __( 'Submitting listings requires an account', 'wp-job-manager' ),
							'desc'       => __( 'If disabled, non-logged in users will be able to submit listings without creating an account.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_enable_registration',
							'std'        => '1',
							'label'      => __( 'Account Creation', 'wp-job-manager' ),
							'cb_label'   => __( 'Allow account creation', 'wp-job-manager' ),
							'desc'       => __( 'If enabled, non-logged in users will be able to create an account by entering their email address on the submission form.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_generate_username_from_email',
							'std'        => '1',
							'label'      => __( 'Account Username', 'wp-job-manager' ),
							'cb_label'   => __( 'Automatically Generate Username from Email Address', 'wp-job-manager' ),
							'desc'       => __( 'If enabled, a username will be generated from the first part of the user email address. Otherwise, a username field will be shown.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_registration_role',
							'std'        => 'employer',
							'label'      => __( 'Account Role', 'wp-job-manager' ),
							'desc'       => __( 'If you enable registration on your submission form, choose a role for the new user.', 'wp-job-manager' ),
							'type'       => 'select',
							'options'    => $account_roles
						),
						array(
							'name'       => 'job_manager_submission_requires_approval',
							'std'        => '1',
							'label'      => __( 'Moderate New Listings', 'wp-job-manager' ),
							'cb_label'   => __( 'New listing submissions require admin approval', 'wp-job-manager' ),
							'desc'       => __( 'If enabled, new submissions will be inactive, pending admin approval.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_user_can_edit_pending_submissions',
							'std'        => '0',
							'label'      => __( 'Allow Pending Edits', 'wp-job-manager' ),
							'cb_label'   => __( 'Submissions awaiting approval can be edited', 'wp-job-manager' ),
							'desc'       => __( 'If enabled, submissions awaiting admin approval can be edited by the user.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_submission_duration',
							'std'        => '30',
							'label'      => __( 'Listing Duration', 'wp-job-manager' ),
							'desc'       => __( 'How many <strong>days</strong> listings are live before expiring. Can be left blank to never expire.', 'wp-job-manager' ),
							'attributes' => array()
						),
						array(
							'name'       => 'job_manager_allowed_application_method',
							'std'        => '',
							'label'      => __( 'Application Method', 'wp-job-manager' ),
							'desc'       => __( 'Choose the contact method for listings.', 'wp-job-manager' ),
							'type'       => 'select',
							'options'    => array(
								''      => __( 'Email address or website URL', 'wp-job-manager' ),
								'email' => __( 'Email addresses only', 'wp-job-manager' ),
								'url'   => __( 'Website URLs only', 'wp-job-manager' ),
							)
						)
					)
				),
				'job_pages' => array(
					__( 'Pages', 'wp-job-manager' ),
					array(
						array(
							'name' 		=> 'job_manager_submit_job_form_page_id',
							'std' 		=> '',
							'label' 	=> __( 'Submit Job Form Page', 'wp-job-manager' ),
							'desc'		=> __( 'Select the page where you have placed the [submit_job_form] shortcode. This lets the plugin know where the form is located.', 'wp-job-manager' ),
							'type'      => 'page'
						),
						array(
							'name' 		=> 'job_manager_job_dashboard_page_id',
							'std' 		=> '',
							'label' 	=> __( 'Job Dashboard Page', 'wp-job-manager' ),
							'desc'		=> __( 'Select the page where you have placed the [job_dashboard] shortcode. This lets the plugin know where the dashboard is located.', 'wp-job-manager' ),
							'type'      => 'page'
						),
						array(
							'name' 		=> 'job_manager_jobs_page_id',
							'std' 		=> '',
							'label' 	=> __( 'Job Listings Page', 'wp-job-manager' ),
							'desc'		=> __( 'Select the page where you have placed the [jobs] shortcode. This lets the plugin know where the job listings page is located.', 'wp-job-manager' ),
							'type'      => 'page'
						),
					)
				)
			)
		);
	}

	/**
	 * register_settings function.
	 *
	 * @access public
	 * @return void
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
	 * output function.
	 *
	 * @access public
	 * @return void
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

									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="password" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

									if ( $option['desc'] ) {
										echo ' <p class="description">' . $option['desc'] . '</p>';
									}

								break;
								case "number" :
									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="number" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

									if ( $option['desc'] ) {
										echo ' <p class="description">' . $option['desc'] . '</p>';
									}
								break;
								case "" :
								case "input" :
								case "text" :
									?><input id="setting-<?php echo $option['name']; ?>" class="regular-text" type="text" name="<?php echo $option['name']; ?>" value="<?php esc_attr_e( $value ); ?>" <?php echo implode( ' ', $attributes ); ?> <?php echo $placeholder; ?> /><?php

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
			jQuery('.nav-tab-wrapper a:first').click();
			jQuery('#setting-job_manager_enable_registration').change(function(){
				if ( jQuery( this ).is(':checked') ) {
					jQuery('#setting-job_manager_registration_role').closest('tr').show();
					jQuery('#setting-job_manager_registration_username_from_email').closest('tr').show();
				} else {
					jQuery('#setting-job_manager_registration_role').closest('tr').hide();
					jQuery('#setting-job_manager_registration_username_from_email').closest('tr').hide();
				}
			}).change();
		</script>
		<?php
	}
}
