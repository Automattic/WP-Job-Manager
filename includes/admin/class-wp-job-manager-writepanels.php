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
		add_action( 'job_manager_save_job_listing', array( $this, 'save_job_listing_data' ), 20, 2 );
	}

	/**
	 * job_listing_fields function.
	 *
	 * @access public
	 * @return void
	 */
	public function job_listing_fields() {
		global $post;

		$current_user = wp_get_current_user();

		$fields = array(
			'_job_location' => array(
				'label' => __( 'Location', 'wp-job-manager' ),
				'placeholder' => __( 'e.g. "London"', 'wp-job-manager' ),
				'description' => __( 'Leave this blank if the location is not important.', 'wp-job-manager' ),
				'priority'    => 1
			),
			'_application' => array(
				'label'       => __( 'Application Email or URL', 'wp-job-manager' ),
				'placeholder' => __( 'URL or email which applicants use to apply', 'wp-job-manager' ),
				'description' => __( 'This field is required for the "application" area to appear beneath the listing.', 'wp-job-manager' ),
				'value'       => metadata_exists( 'post', $post->ID, '_application' ) ? get_post_meta( $post->ID, '_application', true ) : $current_user->user_email,
				'priority'    => 2
			),
			'_company_name' => array(
				'label'       => __( 'Company Name', 'wp-job-manager' ),
				'placeholder' => '',
				'priority'    => 3
			),
			'_company_website' => array(
				'label'       => __( 'Company Website', 'wp-job-manager' ),
				'placeholder' => '',
				'priority'    => 4
			),
			'_company_tagline' => array(
				'label'       => __( 'Company Tagline', 'wp-job-manager' ),
				'placeholder' => __( 'Brief description about the company', 'wp-job-manager' ),
				'priority'    => 5
			),
			'_company_twitter' => array(
				'label'       => __( 'Company Twitter', 'wp-job-manager' ),
				'placeholder' => '@yourcompany',
				'priority'    => 6
			),
			'_company_video' => array(
				'label'       => __( 'Company Video', 'wp-job-manager' ),
				'placeholder' => __( 'URL to the company video', 'wp-job-manager' ),
				'type'        => 'file',
				'priority'    => 8
			),
			'_filled' => array(
				'label'       => __( 'Position Filled', 'wp-job-manager' ),
				'type'        => 'checkbox',
				'priority'    => 9,
				'description' => __( 'Filled listings will no longer accept applications.', 'wp-job-manager' ),
			)
		);
		if ( $current_user->has_cap( 'manage_job_listings' ) ) {
			$fields['_featured'] = array(
				'label'       => __( 'Featured Listing', 'wp-job-manager' ),
				'type'        => 'checkbox',
				'description' => __( 'Featured listings will be sticky during searches, and can be styled differently.', 'wp-job-manager' ),
				'priority'    => 10
			);
			$fields['_job_expires'] = array(
				'label'       => __( 'Listing Expiry Date', 'wp-job-manager' ),
				'priority'    => 11,
				'placeholder' => _x( 'yyyy-mm-dd', 'Date format placeholder', 'wp-job-manager' ),
				'value'       => metadata_exists( 'post', $post->ID, '_job_expires' ) ? get_post_meta( $post->ID, '_job_expires', true ) : calculate_job_expiry( $post->ID ),
			);
		}
		if ( $current_user->has_cap( 'edit_others_job_listings' ) ) {
			$fields['_job_author'] = array(
				'label'    => __( 'Posted by', 'wp-job-manager' ),
				'type'     => 'author',
				'priority' => 12
			);
		}

		$fields = apply_filters( 'job_manager_job_listing_data_fields', $fields );

		uasort( $fields, array( $this, 'sort_by_priority' ) );

		return $fields;
	}

	/**
	 * Sort array by priority value
	 */
	protected function sort_by_priority( $a, $b ) {
	    if ( ! isset( $a['priority'] ) || ! isset( $b['priority'] ) || $a['priority'] === $b['priority'] ) {
	        return 0;
	    }
	    return ( $a['priority'] < $b['priority'] ) ? -1 : 1;
	}

	/**
	 * add_meta_boxes function.
	 *
	 * @access public
	 * @return void
	 */
	public function add_meta_boxes() {
		global $wp_post_types;

		add_meta_box( 'job_listing_data', sprintf( __( '%s Data', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name ), array( $this, 'job_listing_data' ), 'job_listing', 'normal', 'high' );
	}

	/**
	 * input_file function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_file( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( empty( $field['placeholder'] ) ) {
			$field['placeholder'] = 'http://';
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<?php
			if ( ! empty( $field['multiple'] ) ) {
				foreach ( (array) $field['value'] as $value ) {
					?><span class="file_url"><input type="text" name="<?php echo esc_attr( $name ); ?>[]" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $value ); ?>" /><button class="button button-small wp_job_manager_upload_file_button" data-uploader_button_text="<?php _e( 'Use file', 'wp-job-manager' ); ?>"><?php _e( 'Upload', 'wp-job-manager' ); ?></button></span><?php
				}
			} else {
				?><span class="file_url"><input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" /><button class="button button-small wp_job_manager_upload_file_button" data-uploader_button_text="<?php _e( 'Use file', 'wp-job-manager' ); ?>"><?php _e( 'Upload', 'wp-job-manager' ); ?></button></span><?php
			}
			if ( ! empty( $field['multiple'] ) ) {
				?><button class="button button-small wp_job_manager_add_another_file_button" data-field_name="<?php echo esc_attr( $key ); ?>" data-field_placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" data-uploader_button_text="<?php _e( 'Use file', 'wp-job-manager' ); ?>" data-uploader_button="<?php _e( 'Upload', 'wp-job-manager' ); ?>"><?php _e( 'Add file', 'wp-job-manager' ); ?></button><?php
			}
			?>
		</p>
		<?php
	}

	/**
	 * input_text function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_text( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
		</p>
		<?php
	}

	/**
	 * input_text function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_textarea( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"><?php echo esc_html( $field['value'] ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * input_select function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_select( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $field['options'] as $key => $value ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php if ( isset( $field['value'] ) ) selected( $field['value'], $key ); ?>><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * input_select function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_multiselect( $key, $field ) {
		global $thepostid;

		if ( ! isset( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>: <?php if ( ! empty( $field['description'] ) ) : ?><span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span><?php endif; ?></label>
			<select multiple="multiple" name="<?php echo esc_attr( $name ); ?>[]" id="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $field['options'] as $key => $value ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php if ( ! empty( $field['value'] ) && is_array( $field['value'] ) ) selected( in_array( $key, $field['value'] ), true ); ?>><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * input_checkbox function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_checkbox( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field form-field-checkbox">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?></label>
			<input type="checkbox" class="checkbox" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $field['value'], 1 ); ?> />
			<?php if ( ! empty( $field['description'] ) ) : ?><span class="description"><?php echo $field['description']; ?></span><?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Box to choose who posted the job
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_author( $key, $field ) {
		global $thepostid, $post;

		if ( ! $post || $thepostid !== $post->ID ) {
			$the_post  = get_post( $thepostid );
			$author_id = $the_post->post_author;
		} else {
			$author_id = $post->post_author;
		}

		$posted_by      = get_user_by( 'id', $author_id );
		$field['value'] = ! isset( $field['value'] ) ? get_post_meta( $thepostid, $key, true ) : $field['value'];
		$name           = ! empty( $field['name'] ) ? $field['name'] : $key;
		?>
		<p class="form-field form-field-author">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ) ; ?>:</label>
			<span class="current-author">
				<?php
					if ( $posted_by ) {
						echo '<a href="' . admin_url( 'user-edit.php?user_id=' . absint( $author_id ) ) . '">#' . absint( $author_id ) . ' &ndash; ' . $posted_by->user_login . '</a>';
					} else {
						 _e( 'Guest User', 'wp-job-manager' );
					}
				?> <a href="#" class="change-author button button-small"><?php _e( 'Change', 'wp-job-manager' ); ?></a>
			</span>
			<span class="hidden change-author">
				<input type="number" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" step="1" value="<?php echo esc_attr( $author_id ); ?>" style="width: 4em;" />
				<span class="description"><?php _e( 'Enter the ID of the user, or leave blank if submitted by a guest.', 'wp-job-manager' ) ?></span>
			</span>
		</p>
		<?php
	}

	/**
	 * input_radio function.
	 *
	 * @param mixed $key
	 * @param mixed $field
	 */
	public static function input_radio( $key, $field ) {
		global $thepostid;

		if ( empty( $field['value'] ) ) {
			$field['value'] = get_post_meta( $thepostid, $key, true );
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field form-field-checkbox">
			<label><?php echo esc_html( $field['label'] ) ; ?></label>
			<?php foreach ( $field['options'] as $option_key => $value ) : ?>
				<label><input type="radio" class="radio" name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>" value="<?php echo esc_attr( $option_key ); ?>" <?php checked( $field['value'], $option_key ); ?> /> <?php echo esc_html( $value ); ?></label>
			<?php endforeach; ?>
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

			if ( has_action( 'job_manager_input_' . $type ) ) {
				do_action( 'job_manager_input_' . $type, $key, $field );
			} elseif ( method_exists( $this, 'input_' . $type ) ) {
				call_user_func( array( $this, 'input_' . $type ), $key, $field );
			}
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

		// These need to exist
		add_post_meta( $post_id, '_filled', 0, true );
		add_post_meta( $post_id, '_featured', 0, true );

		// Save fields
		foreach ( $this->job_listing_fields() as $key => $field ) {
			// Expirey date
			if ( '_job_expires' === $key ) {
				if ( ! empty( $_POST[ $key ] ) ) {
					update_post_meta( $post_id, $key, date( 'Y-m-d', strtotime( sanitize_text_field( $_POST[ $key ] ) ) ) );
				} else {
					update_post_meta( $post_id, $key, '' );
				}
			}

			// Locations
			elseif ( '_job_location' === $key ) {
				if ( update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) ) ) {
					// Location data will be updated by hooked in methods
				} elseif ( apply_filters( 'job_manager_geolocation_enabled', true ) && ! WP_Job_Manager_Geocode::has_location_data( $post_id ) ) {
					WP_Job_Manager_Geocode::generate_location_data( $post_id, sanitize_text_field( $_POST[ $key ] ) );
				}
			}

			elseif ( '_job_author' === $key ) {
				$wpdb->update( $wpdb->posts, array( 'post_author' => $_POST[ $key ] > 0 ? absint( $_POST[ $key ] ) : 0 ), array( 'ID' => $post_id ) );
			}

			elseif ( '_application' === $key ) {
				update_post_meta( $post_id, $key, sanitize_text_field( urldecode( $_POST[ $key ] ) ) );
			}

			// Everything else
			else {
				$type = ! empty( $field['type'] ) ? $field['type'] : '';

				switch ( $type ) {
					case 'textarea' :
						update_post_meta( $post_id, $key, wp_kses_post( stripslashes( $_POST[ $key ] ) ) );
					break;
					case 'checkbox' :
						if ( isset( $_POST[ $key ] ) ) {
							update_post_meta( $post_id, $key, 1 );
						} else {
							update_post_meta( $post_id, $key, 0 );
						}
					break;
					default :
						if ( ! isset( $_POST[ $key ] ) ) {
							continue;
						} elseif ( is_array( $_POST[ $key ] ) ) {
							update_post_meta( $post_id, $key, array_filter( array_map( 'sanitize_text_field', $_POST[ $key ] ) ) );
						} else {
							update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
						}
					break;
				}
			}
		}
	}
}

new WP_Job_Manager_Writepanels();
