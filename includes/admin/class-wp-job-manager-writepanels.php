<?php
/**
 * File containing the class WP_Job_Manager_Writepanels.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the management of Job Listing meta fields.
 *
 * @since 1.0.0
 */
class WP_Job_Manager_Writepanels {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 1, 2 );
		add_action( 'job_manager_save_job_listing', array( $this, 'save_job_listing_data' ), 20, 2 );
	}

	/**
	 * Returns configuration for custom fields on Job Listing posts.
	 *
	 * @return array
	 */
	public function job_listing_fields() {
		global $post_id;

		$current_user = wp_get_current_user();
		$fields_raw   = WP_Job_Manager_Post_Types::get_job_listing_fields();
		$fields       = array();

		if ( $current_user->has_cap( 'edit_others_job_listings' ) ) {
			$fields['_job_author'] = array(
				'label'    => __( 'Posted by', 'wp-job-manager' ),
				'type'     => 'author',
				'priority' => 0,
			);
		}

		foreach ( $fields_raw as $meta_key => $field ) {
			$show_in_admin = $field['show_in_admin'];
			if ( is_callable( $show_in_admin ) ) {
				$show_in_admin = (bool) call_user_func( $show_in_admin, true, $meta_key, $post_id, $current_user->ID );
			}

			if ( ! $show_in_admin ) {
				continue;
			}

			/**
			 * Check auth callback. Mirrors first 4 params of WordPress core's `auth_{$object_type}_meta_{$meta_key}` filter.
			 *
			 * @param bool   $allowed   Whether the user can edit the job listing meta. Default false.
			 * @param string $meta_key  The meta key.
			 * @param int    $object_id Object ID.
			 * @param int    $user_id   User ID.
			 */
			if ( ! call_user_func( $field['auth_edit_callback'], false, $meta_key, $post_id, $current_user->ID ) ) {
				continue;
			}

			$fields[ $meta_key ] = $field;
		}

		if ( isset( $fields['_job_expires'] ) && ! isset( $fields['_job_expires']['value'] ) ) {
			$job_expires = get_post_meta( $post_id, '_job_expires', true );

			if ( ! empty( $job_expires ) ) {
				$fields['_job_expires']['placeholder'] = null;
				$fields['_job_expires']['value']       = date( 'Y-m-d', strtotime( $job_expires ) );
			} else {
				$fields['_job_expires']['placeholder'] = date_i18n( get_option( 'date_format' ), strtotime( calculate_job_expiry( $post_id ) ) );
				$fields['_job_expires']['value']       = '';
			}
		}

		if ( isset( $fields['_application'] ) && ! isset( $fields['_application']['default'] ) && 'url' !== get_option( 'job_manager_allowed_application_method' ) ) {
			$fields['_application']['default'] = $current_user->user_email;
		}

		/**
		 * Filters job listing data fields shown in WP admin.
		 *
		 * To add job listing data fields, use the `job_manager_job_listing_data_fields` found in `includes/class-wp-job-manager-post-types.php`.
		 *
		 * @since 1.33.0
		 *
		 * @param array    $fields  Job listing fields for WP admin. See `job_manager_job_listing_data_fields` filter for more information.
		 * @param int|null $post_id Post ID to get fields for. May be null.
		 */
		$fields = apply_filters( 'job_manager_job_listing_wp_admin_fields', $fields, $post_id );

		uasort( $fields, array( __CLASS__, 'sort_by_priority' ) );

		return $fields;
	}

	/**
	 * Sorts array of custom fields by priority value.
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	protected static function sort_by_priority( $a, $b ) {
		if ( ! isset( $a['priority'] ) || ! isset( $b['priority'] ) || $a['priority'] === $b['priority'] ) {
			return 0;
		}

		return ( $a['priority'] < $b['priority'] ) ? -1 : 1;
	}

	/**
	 * Handles the hooks to add custom field meta boxes.
	 */
	public function add_meta_boxes() {
		global $wp_post_types;

		// translators: Placeholder %s is the singular name for a job listing post type.
		add_meta_box( 'job_listing_data', sprintf( __( '%s Data', 'wp-job-manager' ), $wp_post_types['job_listing']->labels->singular_name ), array( $this, 'job_listing_data' ), 'job_listing', 'normal', 'high' );
		if ( ! get_option( 'job_manager_enable_types' ) || 0 === intval( wp_count_terms( 'job_listing_type' ) ) ) {
			remove_meta_box( 'job_listing_typediv', 'job_listing', 'side' );
		} elseif ( false === job_manager_multi_job_type() ) {
			remove_meta_box( 'job_listing_typediv', 'job_listing', 'side' );
			$job_listing_type = get_taxonomy( 'job_listing_type' );
			add_meta_box( 'job_listing_type', $job_listing_type->labels->menu_name, array( $this, 'job_type_single_meta_box' ), 'job_listing', 'side', 'core' );
		}
	}

	/**
	 * Displays job listing metabox.
	 *
	 * @param int|WP_Post $post
	 */
	public function job_type_single_meta_box( $post ) {
		// Set up the taxonomy object and get terms.
		$taxonomy_name = 'job_listing_type';

		// Get all the terms for this taxonomy.
		$terms     = get_terms(
			array(
				'taxonomy'   => $taxonomy_name,
				'hide_empty' => 0,
			)
		);
		$postterms = get_the_terms( $post->ID, $taxonomy_name );
		$current   = $postterms ? array_pop( $postterms ) : false;
		$current   = $current ? $current->term_id : 0;

		$field_name = 'tax_input[' . $taxonomy_name . ']';
		?>
		<div id="taxonomy-<?php echo esc_attr( $taxonomy_name ); ?>" class="categorydiv">
			<!-- Display taxonomy terms -->
			<div id="<?php echo esc_attr( $taxonomy_name ); ?>-all" class="editor-post-taxonomies__hierarchical-terms-list">
				<ul id="<?php echo esc_attr( $taxonomy_name ); ?>checklist" class="list:<?php echo esc_attr( $taxonomy_name ); ?> categorychecklist form-no-clear">
					<?php
					foreach ( $terms as $term ) {
						$id = $taxonomy_name . '-' . $term->term_id;
						echo '<li id="' . esc_attr( $id ) . '"><label class="selectit">';
						echo '<input type="radio" id="in-' . esc_attr( $id ) . '" name="' . esc_attr( $field_name ) . '" ' . checked( $current, $term->term_id, false ) . ' value="' . esc_attr( $term->term_id ) . '" />' . esc_attr( $term->name ) . '<br />';
						echo '</label></li>';
					}
					?>
				</ul>
			</div>

		</div>
		<?php
	}

	/**
	 * Displays label and file input field.
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_file( $key, $field ) {
		if ( empty( $field['placeholder'] ) ) {
			$field['placeholder'] = 'https://';
		}
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?>:
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span>
			<?php endif; ?>
			</label>
			<?php
			if ( ! empty( $field['multiple'] ) ) {
				foreach ( (array) $field['value'] as $value ) {
					?>
					<span class="file_url"><input type="text" name="<?php echo esc_attr( $name ); ?>[]" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $value ); ?>" /><button class="button button-small wp_job_manager_upload_file_button" data-uploader_button_text="<?php esc_attr_e( 'Use file', 'wp-job-manager' ); ?>"><?php esc_html_e( 'Upload', 'wp-job-manager' ); ?></button><button class="button button-small wp_job_manager_view_file_button"><?php esc_html_e( 'View', 'wp-job-manager' ); ?></button></span>
					<?php
				}
			} else {
				?>
				<span class="file_url"><input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" /><button class="button button-small wp_job_manager_upload_file_button" data-uploader_button_text="<?php esc_attr_e( 'Use file', 'wp-job-manager' ); ?>"><?php esc_html_e( 'Upload', 'wp-job-manager' ); ?></button><button class="button button-small wp_job_manager_view_file_button"><?php esc_html_e( 'View', 'wp-job-manager' ); ?></button></span>
				<?php
			}
			if ( ! empty( $field['multiple'] ) ) {
				?>
				<button class="button button-small wp_job_manager_add_another_file_button" data-field_name="<?php echo esc_attr( $key ); ?>" data-field_placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" data-uploader_button_text="<?php esc_attr_e( 'Use file', 'wp-job-manager' ); ?>" data-uploader_button="<?php esc_attr_e( 'Upload', 'wp-job-manager' ); ?>" data-view_button="<?php esc_attr_e( 'View', 'wp-job-manager' ); ?>"><?php esc_html_e( 'Add file', 'wp-job-manager' ); ?></button>
				<?php
			}
			?>
		</p>
		<?php
	}

	/**
	 * Displays label and text input field.
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_text( $key, $field ) {
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		if ( ! empty( $field['classes'] ) ) {
			$classes = implode( ' ', is_array( $field['classes'] ) ? $field['classes'] : array( $field['classes'] ) );
		} else {
			$classes = '';
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?>:
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span>
			<?php endif; ?>
			</label>
			<input type="text" autocomplete="off" name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
		</p>
		<?php
	}

	/**
	 * Just displays information.
	 *
	 * @since 1.27.0
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_info( $key, $field ) {
		self::input_hidden( $key, $field );
	}

	/**
	 * Displays information and/or hidden input.
	 *
	 * @since 1.27.0
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_hidden( $key, $field ) {
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		if ( ! empty( $field['classes'] ) ) {
			$classes = implode( ' ', is_array( $field['classes'] ) ? $field['classes'] : array( $field['classes'] ) );
		} else {
			$classes = '';
		}
		if ( 'hidden' === $field['type'] ) {
			if ( empty( $field['label'] ) ) {
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" class="' . esc_attr( $classes ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $field['value'] ) . '" />';
				return;
			}
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?>:
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span>
			<?php endif; ?>
			</label>
			<?php if ( ! empty( $field['information'] ) ) : ?>
				<span class="information"><?php echo wp_kses( $field['information'], array( 'a' => array( 'href' => array() ) ) ); ?></span>
			<?php endif; ?>
			<?php echo '<input type="hidden" name="' . esc_attr( $name ) . '" class="' . esc_attr( $classes ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $field['value'] ) . '" />'; ?>
		</p>
		<?php
	}

	/**
	 * Displays label and textarea input field.
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_textarea( $key, $field ) {
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?>:
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span>
			<?php endif; ?>
			</label>
			<textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"><?php echo esc_html( $field['value'] ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Displays label and select input field.
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_select( $key, $field ) {
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>">
				<?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?>:
				<?php if ( ! empty( $field['description'] ) ) : ?>
					<span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span>
				<?php endif; ?>
			</label>
			<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $field['options'] as $key => $value ) : ?>
					<option
						value="<?php echo esc_attr( $key ); ?>"
						<?php
						if ( isset( $field['value'] ) ) {
							selected( $field['value'], $key );
						}
						?>
					><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Displays label and multi-select input field.
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_multiselect( $key, $field ) {
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?>:
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<span class="tips" data-tip="<?php echo esc_attr( $field['description'] ); ?>">[?]</span>
			<?php endif; ?>
			</label>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="">
			<select multiple="multiple" name="<?php echo esc_attr( $name ); ?>[]" id="<?php echo esc_attr( $key ); ?>">
				<?php foreach ( $field['options'] as $key => $value ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>"
					<?php
					if ( ! empty( $field['value'] ) && is_array( $field['value'] ) ) {
						// phpcs:ignore WordPress.PHP.StrictInArray
						selected( in_array( $key, $field['value'] ), true );
					}
					?>
				><?php echo esc_html( $value ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Displays label and checkbox input field.
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_checkbox( $key, $field ) {
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field form-field-checkbox">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?></label>
			<input type="checkbox" class="checkbox" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $field['value'], 1 ); ?> />
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<span class="description"><?php echo wp_kses_post( $field['description'] ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Displays label and author select field.
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_author( $key, $field ) {
		global $thepostid, $post;

		if ( ! $post || $thepostid !== $post->ID ) {
			$the_post  = get_post( $thepostid );
			$author_id = $the_post->post_author;
		} else {
			$author_id = $post->post_author;
		}

		$posted_by = get_user_by( 'id', $author_id );
		$name      = ! empty( $field['name'] ) ? $field['name'] : $key;
		?>
		<p class="form-field form-field-author">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?>:</label>
			<span class="current-author">
				<?php
				if ( $posted_by ) {
					$user_string = sprintf(
						// translators: Used in user select. %1$s is the user's display name; #%2$s is the user ID; %3$s is the user email.
						esc_html__( '%1$s (#%2$s – %3$s)', 'wp-job-manager' ),
						htmlentities( $posted_by->display_name ),
						absint( $posted_by->ID ),
						$posted_by->user_email
					);
					echo '<a href="' . esc_url( admin_url( 'user-edit.php?user_id=' . absint( $author_id ) ) ) . '">#' . absint( $author_id ) . ' &ndash; ' . esc_html( $posted_by->user_login ) . '</a>';
				} else {
					$user_string = __( 'Guest User', 'wp-job-manager' );
					echo esc_html( $user_string );
				}
				?>
				<a href="#" class="change-author button button-small"><?php esc_html_e( 'Change', 'wp-job-manager' ); ?></a>
			</span>
			<span class="hidden change-author">
				<select class="wpjm-user-search" id="job_manager_user_search" name="<?php echo esc_attr( $name ); ?>" data-placeholder="<?php esc_attr_e( 'Guest', 'wp-job-manager' ); ?>" data-allow_clear="true">
					<option value="<?php echo esc_attr( $author_id ); ?>" selected="selected"><?php echo esc_html( htmlspecialchars( $user_string ) ); ?></option>
				</select>
			</span>
		</p>
		<?php
	}

	/**
	 * Displays label and radio input field.
	 *
	 * @param string $key
	 * @param array  $field
	 */
	public static function input_radio( $key, $field ) {
		if ( ! empty( $field['name'] ) ) {
			$name = $field['name'];
		} else {
			$name = $key;
		}
		?>
		<p class="form-field form-field-checkbox">
			<label><?php echo esc_html( wp_strip_all_tags( $field['label'] ) ); ?></label>
			<?php foreach ( $field['options'] as $option_key => $value ) : ?>
				<label><input type="radio" class="radio" name="<?php echo esc_attr( isset( $field['name'] ) ? $field['name'] : $key ); ?>" value="<?php echo esc_attr( $option_key ); ?>" <?php checked( $field['value'], $option_key ); ?> /> <?php echo esc_html( $value ); ?></label>
			<?php endforeach; ?>
			<?php if ( ! empty( $field['description'] ) ) : ?>
				<span class="description"><?php echo wp_kses_post( $field['description'] ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Displays metadata fields for Job Listings.
	 *
	 * @param int|WP_Post $post
	 */
	public function job_listing_data( $post ) {
		global $post, $thepostid, $wp_post_types;

		$thepostid = $post->ID;

		echo '<div class="wp_job_manager_meta_data">';

		wp_nonce_field( 'save_meta_data', 'job_manager_nonce' );

		do_action( 'job_manager_job_listing_data_start', $thepostid );

		foreach ( $this->job_listing_fields() as $key => $field ) {
			$type = ! empty( $field['type'] ) ? $field['type'] : 'text';

			if ( ! isset( $field['value'] ) && metadata_exists( 'post', $thepostid, $key ) ) {
				$field['value'] = get_post_meta( $thepostid, $key, true );
			}

			if ( ! isset( $field['value'] ) && isset( $field['default'] ) ) {
				$field['value'] = $field['default'];
			}

			if ( has_action( 'job_manager_input_' . $type ) ) {
				do_action( 'job_manager_input_' . $type, $key, $field );
			} elseif ( method_exists( $this, 'input_' . $type ) ) {
				call_user_func( array( $this, 'input_' . $type ), $key, $field );
			}
		}

		$user_edited_date = get_post_meta( $post->ID, '_job_edited', true );
		if ( $user_edited_date ) {
			echo '<p class="form-field">';
			// translators: %1$s is placeholder for singular name of the job listing post type; %2$s is the intl formatted date the listing was last modified.
			echo '<em>' . sprintf( esc_html__( '%1$s was last modified by the user on %2$s.', 'wp-job-manager' ), esc_html( $wp_post_types['job_listing']->labels->singular_name ), esc_html( date_i18n( get_option( 'date_format' ), $user_edited_date ) ) ) . '</em>';
			echo '</p>';
		}

		do_action( 'job_manager_job_listing_data_end', $thepostid );

		echo '</div>';
	}

	/**
	 * Handles `save_post` action.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		if ( empty( $post_id ) || empty( $post ) || empty( $_POST ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( is_int( wp_is_post_revision( $post ) ) ) {
			return;
		}
		if ( is_int( wp_is_post_autosave( $post ) ) ) {
			return;
		}
		if (
			empty( $_POST['job_manager_nonce'] )
			|| ! wp_verify_nonce( wp_unslash( $_POST['job_manager_nonce'] ), 'save_meta_data' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
		) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( 'job_listing' !== $post->post_type ) {
			return;
		}

		do_action( 'job_manager_save_job_listing', $post_id, $post );
	}

	/**
	 * Handles the actual saving of job listing data fields.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post (Unused).
	 */
	public function save_job_listing_data( $post_id, $post ) {
		// These need to exist.
		add_post_meta( $post_id, '_filled', 0, true );
		add_post_meta( $post_id, '_featured', 0, true );

		// Save fields.
		foreach ( $this->job_listing_fields() as $key => $field ) {
			if ( isset( $field['type'] ) && 'info' === $field['type'] ) {
				continue;
			}

			// Checkboxes that aren't sent are unchecked.
			if ( 'checkbox' === $field['type'] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by WP core.
				if ( ! empty( $_POST[ $key ] ) ) {
					$_POST[ $key ] = 1;
				} else {
					$_POST[ $key ] = 0;
				}
			}

			// Expirey date.
			if ( '_job_expires' === $key ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by WP core.
				if ( empty( $_POST[ $key ] ) ) {
					if ( get_option( 'job_manager_submission_duration' ) ) {
						update_post_meta( $post_id, $key, calculate_job_expiry( $post_id ) );
					} else {
						delete_post_meta( $post_id, $key );
					}
				} else {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by WP core.
					update_post_meta( $post_id, $key, date( 'Y-m-d', strtotime( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) ) );
				}
			} elseif ( '_job_author' === $key ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by WP core.
				if ( empty( $_POST[ $key ] ) ) {
					$_POST[ $key ] = 0;
				}
				$job_data                = array();
				$job_data['ID']          = $post_id;
				$job_data['post_author'] = $_POST[ $key ] > 0 ? intval( $_POST[ $key ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by WP core.

				remove_action( 'job_manager_save_job_listing', array( $this, 'save_job_listing_data' ), 20 );
				wp_update_post( $job_data );
				add_action( 'job_manager_save_job_listing', array( $this, 'save_job_listing_data' ), 20, 2 );
			} elseif ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by WP core.
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Input sanitized in registered post meta config; see WP_Job_Manager_Post_Types::register_meta_fields() and WP_Job_Manager_Post_Types::get_job_listing_fields() methods.
				update_post_meta( $post_id, $key, wp_unslash( $_POST[ $key ] ) );
			}
		}

		/* Set Post Status To Expired If Already Expired */
		$expiry_date            = get_post_meta( $post_id, '_job_expires', true );
		$today_date             = date( 'Y-m-d', current_time( 'timestamp' ) );
		$is_job_listing_expired = $expiry_date && $today_date > $expiry_date;
		if ( $is_job_listing_expired && ! $this->is_job_listing_status_changing( null, 'draft' ) ) {
			remove_action( 'job_manager_save_job_listing', array( $this, 'save_job_listing_data' ), 20 );
			if ( $this->is_job_listing_status_changing( 'expired', 'publish' ) ) {
				update_post_meta( $post_id, '_job_expires', calculate_job_expiry( $post_id ) );
			} else {
				$job_data = array(
					'ID'          => $post_id,
					'post_status' => 'expired',
				);
				wp_update_post( $job_data );
			}
			add_action( 'job_manager_save_job_listing', array( $this, 'save_job_listing_data' ), 20, 2 );
		}
	}

	/**
	 * Checks if the job listing status is being changed from $from_status to $to_status.
	 *
	 * @param string|null $from_status Status to test if it is changing from. NULL if anything.
	 * @param string      $to_status   Status to test if it is changing to.
	 *
	 * @return bool True if status is changing from $from_status to $to_status.
	 */
	private function is_job_listing_status_changing( $from_status, $to_status ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce check handled by WP core.
		return isset( $_POST['post_status'] )
				&& isset( $_POST['original_post_status'] )
				&& $_POST['original_post_status'] !== $_POST['post_status']
				&& (
					null === $from_status
					|| $from_status === $_POST['original_post_status']
				)
				&& $to_status === $_POST['post_status'];
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}

WP_Job_Manager_Writepanels::instance();
