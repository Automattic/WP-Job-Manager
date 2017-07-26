<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles taxonomy meta custom fields. Just used for job type.
 *
 * @package wp-job-manager
 * @since 1.28.0
 */
class WP_Job_Manager_Taxonomy_Meta {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.28.0
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.28.0
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
	 * WP_Job_Manager_Taxonomy_Meta constructor.
	 */
	public function __construct() {
		if ( wpjm_job_listing_employment_type_enabled() ) {
			add_action( 'job_listing_type_edit_form_fields', array( $this, 'display_schema_org_employment_type_field' ), 10, 2 );
			add_action( 'job_listing_type_add_form_fields', array( $this, 'add_form_display_schema_org_employment_type_field' ), 10 );
			add_action( 'edited_job_listing_type', array( $this, 'set_schema_org_employment_type_field' ), 10, 2 );
			add_action( 'created_job_listing_type', array( $this, 'set_schema_org_employment_type_field' ), 10, 2 );
			add_filter( 'manage_edit-job_listing_type_columns', array( $this, 'add_employment_type_column' ) );
			add_filter( 'manage_job_listing_type_custom_column', array( $this, 'add_employment_type_column_content' ), 10, 3 );
			add_filter( 'manage_edit-job_listing_type_sortable_columns', array( $this, 'add_employment_type_column_sortable' ) );
	    }
	}

	/**
	 * Set the employment type field when creating/updating a job type item.
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id   Taxonomy type ID.
	 */
	public function set_schema_org_employment_type_field( $term_id, $tt_id ) {
		$employment_types = wpjm_job_listing_employment_type_options();
		if( isset( $_POST['employment_type'] ) && isset( $employment_types[ $_POST['employment_type'] ] ) ){
			update_term_meta( $term_id, 'employment_type', $_POST['employment_type'] );
		} elseif ( isset( $_POST['employment_type'] ) ) {
			delete_term_meta( $term_id, 'employment_type' );
		}
	}

	/**
	 * Add the option to select schema.org employmentType for job type on the edit meta field form.
	 *
	 * @param WP_Term $term     Term object.
	 * @param string  $taxonomy Taxonomy slug.
	 */
	public function display_schema_org_employment_type_field( $term, $taxonomy ) {
		$employment_types = wpjm_job_listing_employment_type_options();
		$current_employment_type = get_term_meta( $term->term_id, 'employment_type', true );

		if ( ! empty( $employment_types ) ) {
			?>
			<tr class="form-field term-group-wrap">
			<th scope="row"><label for="feature-group"><?php _e( 'Employment Type', 'wp-job-manager' ); ?></label></th>
			<td><select class="postform" id="employment_type" name="employment_type">
					<option value=""></option>
					<?php foreach ( $employment_types as $key => $employment_type ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_employment_type, $key ); ?>><?php echo esc_html( $employment_type ); ?></option>
					<?php endforeach; ?>
				</select></td>
			</tr><?php
		}
	}

	/**
	 * Add the option to select schema.org employmentType for job type on the add meta field form.
	 *
	 * @param string       $taxonomy Taxonomy slug.
	 */
	public function add_form_display_schema_org_employment_type_field( $taxonomy ) {
		$employment_types = wpjm_job_listing_employment_type_options();

		if ( ! empty( $employment_types ) ) {
			?>
			<div class="form-field term-group">
			<label for="feature-group"><?php _e( 'Employment Type', 'wp-job-manager' ); ?></label>
			<select class="postform" id="employment_type" name="employment_type">
					<option value=""></option>
					<?php foreach ( $employment_types as $key => $employment_type ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $employment_type ); ?></option>
					<?php endforeach; ?>
				</select>
			</div><?php
		}
	}

	/**
	 * Adds the Employment Type column when listing job type terms in WP Admin.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function add_employment_type_column( $columns ) {
		$columns['employment_type'] = __( 'Employment Type', 'wp-job-manager' );
		return $columns;
	}

	/**
	 * Adds the Employment Type column as a sortable column when listing job type terms in WP Admin.
	 *
	 * @param array $sortable
	 * @return array
	 */
	public function add_employment_type_column_sortable( $sortable ) {
		$sortable['employment_type'] = 'employment_type';
		return $sortable;
	}

	/**
	 * Adds the Employment Type column content when listing job type terms in WP Admin.
	 *
	 * @param string $content
	 * @param string $column_name
	 * @param int    $term_id
	 * @return string
	 */
	public function add_employment_type_column_content( $content, $column_name, $term_id ) {
		if( 'employment_type' !== $column_name ){
			return $content;
		}
		$employment_types = wpjm_job_listing_employment_type_options();
		$term_id = absint( $term_id );
		$term_employment_type = get_term_meta( $term_id, 'employment_type', true );

		if ( ! empty( $term_employment_type ) && isset( $employment_types[ $term_employment_type ] ) ) {
			$content .= esc_attr( $employment_types[ $term_employment_type ] );
		}
		return $content;
	}
}

WP_Job_Manager_Taxonomy_Meta::instance();