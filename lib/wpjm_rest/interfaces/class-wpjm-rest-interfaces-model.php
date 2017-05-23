<?php
/**
 * Model
 *
 * This is the model.
 *
 * @package Mixtape/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Mixtape_Interfaces_Model
 */
interface WPJM_REST_Interfaces_Model {
	/**
	 * Get this model's unique identifier
	 *
	 * @return mixed a unique identifier
	 */
	function get_id();


	/**
	 * Set this model's unique identifier
	 *
	 * @param mixed $new_id The new Id.
	 * @return WPJM_REST_Model $model This model.
	 */
	function set_id( $new_id );

	/**
	 * Get a field for this model
	 *
	 * @param string $field_name The field name.
	 * @param array  $args The args.
	 *
	 * @return mixed|null
	 */
	function get( $field_name, $args = array() );

	/**
	 * Set a field for this model
	 *
	 * @param string $field The field name.
	 * @param mixed  $value The value.
	 *
	 * @return WPJM_REST_Interfaces_Model $this;
	 */
	function set( $field, $value );

	/**
	 * Check if this model has a field
	 *
	 * @param string $field The field name.
	 *
	 * @return bool
	 */
	function has( $field );

	/**
	 * Validate this Model instance.
	 *
	 * @throws WPJM_REST_Exception Throws.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	function validate();

	/**
	 * Sanitize this Model's field values
	 *
	 * @throws WPJM_REST_Exception Throws.
	 *
	 * @return WPJM_REST_Interfaces_Model
	 */
	function sanitize();
}
