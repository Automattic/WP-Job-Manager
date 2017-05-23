<?php
/**
 * The default Mixtape_Interfaces_Model Implementation
 *
 * @package Mixtape/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_Model
 */
class WPJM_REST_Model implements WPJM_REST_Interfaces_Model {

	/**
	 * Our data
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Our raw data
	 *
	 * @var array
	 */
	private $raw_data;

	/**
	 * Our fields
	 *
	 * @var array the model fields Mixtape_Model_Field_Declaration
	 */
	private $fields;
	/**
	 * Our definition
	 *
	 * @var WPJM_REST_Model_Definition
	 */
	private $definition;

	/**
	 * Mixtape_Model constructor.
	 *
	 * @param WPJM_REST_Model_Definition $definition The Definition.
	 * @param array                    $data The data array.
	 *
	 * @throws WPJM_REST_Exception Throws when data is not an array.
	 */
	function __construct( $definition, $data = array() ) {
		WPJM_REST_Expect::that( is_array( $data ), '$data should be an array' );

		$this->definition = $definition;
		$this->fields = $this->definition->get_field_declarations();
		$this->data = array();

		$this->raw_data = $data;
		$data_keys = array_keys( $data );

		foreach ( $data_keys as $key ) {
			$this->set( $key, $this->raw_data[ $key ] );
		}
	}

	/**
	 * Gets the value of a previously defined field.
	 *
	 * @param string $field_name The field name.
	 * @param array  $args Any args.
	 *
	 * @return mixed
	 * @throws WPJM_REST_Exception Fails when field is unknown.
	 */
	public function get( $field_name, $args = array() ) {
		WPJM_REST_Expect::that( $this->has( $field_name ), 'Field ' . $field_name . 'is not defined' );
		$field_declaration = $this->fields[ $field_name ];
		$this->set_field_if_unset( $field_declaration );

		return $this->prepare_value( $field_declaration );
	}

	/**
	 * Sets a field value.
	 *
	 * @param string $field The field name.
	 * @param mixed  $value The new field value.
	 *
	 * @return $this
	 * @throws WPJM_REST_Exception Throws when trying to set an unknown field.
	 */
	public function set( $field, $value ) {
		WPJM_REST_Expect::that( $this->has( $field ), 'Field ' . $field . 'is not defined' );
		/**
		 * The declaration.
		 *
		 * @var WPJM_REST_Model_Field_Declaration $field_declaration The declaration.
		 */
		$field_declaration = $this->fields[ $field ];
		if ( null !== $field_declaration->before_model_set() ) {
			$val = $this->get_declaration()->call( $field_declaration->before_model_set(), array( $this, $value ) );
		} else {
			$val = $field_declaration->cast_value( $value );
		}
		$this->data[ $field_declaration->get_name() ] = $val;
		return $this;
	}

	/**
	 * Check if this model has a field
	 *
	 * @param string $field The field name to check.
	 * @return bool
	 */
	public function has( $field ) {
		return isset( $this->fields[ $field ] );
	}

	/**
	 * Get unique identifier.
	 *
	 * @return mixed
	 */
	public function get_id() {
		return $this->get_declaration()->get_id( $this );
	}

	/**
	 * Set unique identifier.
	 *
	 * @param mixed $id A unique identifier.
	 *
	 * @return WPJM_REST_Interfaces_Model $this
	 */
	public function set_id( $id ) {
		$this->get_declaration()->set_id( $this, $id );
		return $this;
	}

	/**
	 * Validate this Model's current state.
	 *
	 * @return bool|WP_Error Either true or WP_Error on failure.
	 */
	public function validate() {
		$validation_errors = array();

		foreach ( $this->fields as $key => $field_declaration ) {
			$is_valid = $this->run_field_validations( $field_declaration );
			if ( is_wp_error( $is_valid ) ) {
				$validation_errors[] = $is_valid->get_error_data();
			}
		}
		if ( count( $validation_errors ) > 0 ) {
			return $this->validation_error( $validation_errors );
		}
		return true;
	}

	/**
	 * Sanitize this Model's current data.
	 *
	 * @return WPJM_REST_Interfaces_Model $this
	 */
	public function sanitize() {
		foreach ( $this->fields as $key => $field_declaration ) {
			$field_name = $field_declaration->get_name();
			$value = $this->get( $field_name );
			$custom_sanitization = $field_declaration->get_sanitize();
			if ( ! empty( $custom_sanitization ) ) {
				$value = $this->get_declaration()->call( $custom_sanitization, array( $this, $value ) );
			} else {
				$value = $field_declaration->get_type_definition()->sanitize( $value );
			}
			$this->set( $field_name, $value );
		}
		return $this;
	}

	/**
	 * We got a Validation Error
	 *
	 * @param array $error_data The details.
	 * @return WP_Error
	 */
	protected function validation_error( $error_data ) {
		return new WP_Error( 'validation-error', 'validation-error', $error_data );
	}

	/**
	 * Run Validations for this field.
	 *
	 * @param WPJM_REST_Model_Field_Declaration $field_declaration The field.
	 *
	 * @return bool|WP_Error
	 */
	protected function run_field_validations( $field_declaration ) {
		if ( $field_declaration->is_derived_field() ) {
			return true;
		}
		$value = $this->get( $field_declaration->get_name() );
		if ( $field_declaration->is_required() && empty( $value ) ) {
			// translators: %s is usually a field name.
			$message = sprintf( __( '%s cannot be empty', 'mixtape' ), $field_declaration->get_name() );
			return new WP_Error( 'required-field-empty', $message );
		} elseif ( ! $field_declaration->is_required() && ! empty( $value ) ) {
			$validation_data = new WPJM_REST_Model_ValidationData( $value, $this, $field_declaration );
			foreach ( $field_declaration->get_validations() as $validation ) {
				$result = $this->get_declaration()->call( $validation, array( $validation_data ) );
				if ( is_wp_error( $result ) ) {
					$result->add_data(array(
						'reason' => $result->get_error_messages(),
						'field' => $field_declaration->get_data_transfer_name(),
						'value' => $value,
					) );
					return $result;
				}
			}
		}
		return true;
	}

	/**
	 * Prepare the value associated with this declaraton for output.
	 *
	 * @param WPJM_REST_Model_Field_Declaration $field_declaration The declaration to use.
	 * @return mixed
	 */
	private function prepare_value( $field_declaration ) {
		$key = $field_declaration->get_name();
		$value = $this->data[ $key ];
		$before_return = $field_declaration->get_before_return();
		if ( isset( $before_return ) && ! empty( $before_return ) ) {
			$value = $this->get_declaration()->call( $before_return, array( $this, $key, $value ) );
		}

		return $value;
	}

	/**
	 * Get our declaration
	 *
	 * @return WPJM_REST_Interfaces_Model_Declaration
	 */
	private function get_declaration() {
		return $this->definition->get_model_declaration();
	}

	/**
	 * Sets this field's value. Used for derived fields.
	 *
	 * @param WPJM_REST_Model_Field_Declaration $field_declaration The field declaration.
	 */
	private function set_field_if_unset( $field_declaration ) {
		$field_name = $field_declaration->get_name();
		if ( ! isset( $this->data[ $field_name ] ) ) {
			if ( $field_declaration->is_derived_field() ) {
				$map_from = $field_declaration->get_map_from();
				$value    = $this->get_declaration()->call( $map_from, array( $this ) );
				$this->set( $field_name, $value );
			} else {
				$this->set( $field_name, $field_declaration->get_default_value() );
			}
		}
	}
}
