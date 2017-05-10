<?php

class WP_Job_Manager_Models_Settings extends Mixtape_Model_Declaration {
    /**
     * @param Mixtape_Model_Definition $def
     * @return array
     */
    function declare_fields( $def ) {

        return array(
            $def->field( 'job_manager_per_page' )
                ->of_type( 'integer' )
                ->with_default( 125 )
                ->dto_name( 'per_page' )
                ->with_sanitize( 'as_uint' ),

            $def->field( 'job_manager_hide_filled_positions' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'hide_filled_positions' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_hide_expired_content' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'hide_expired_content' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_enable_categories' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'enable_categories' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_enable_default_category_multiselect' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'enable_default_category_multiselect' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_category_filter_type' )
                ->of_type( 'string' )
                ->with_default( 'any' )
                ->dto_name( 'category_filter_type' ),

            $def->field( 'job_manager_enable_types' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'enable_types' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_multi_job_type' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'multi_job_type' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_user_requires_account' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'user_requires_account' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_enable_registration' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'user_requires_account' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_generate_username_from_email' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'generate_username_from_email' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_registration_role' )
                ->of_type( 'string' )
                ->with_default( 'employer' )
                ->dto_name( 'registration_role' ),
            $def->field( 'job_manager_submission_requires_approval' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'submission_requires_approval' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),
            $def->field( 'job_manager_user_can_edit_pending_submissions' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'can_edit_pending_submissions' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),
            $def->field( 'job_manager_submission_duration' )
                ->of_type( 'integer' )
                ->with_default( 30 )
                ->dto_name( 'submission_duration' ),

            $def->field( 'job_manager_allowed_application_method' )
                ->of_type( 'boolean' )
                ->with_default( false )
                ->dto_name( 'allowed_application_method' )
                ->with_serializer( 'bool_to_bit' )
                ->with_deserializer( 'bit_to_bool' )
                ->with_sanitize( 'as_bool' ),

            $def->field( 'job_manager_submit_job_form_page_id' )
                ->of_type( 'integer' )
                ->dto_name( 'submit_job_form_page_id' ),

            $def->field( 'job_manager_job_dashboard_page_id' )
                ->of_type( 'integer' )
                ->dto_name( 'job_dashboard_page_id' ),

            $def->field( 'job_manager_jobs_page_id' )
                ->of_type( 'integer' )
                ->dto_name( 'jobs_page_id' ),
        );
    }

    function get_id( $model ) {
        // settings have no id
        return null;
    }

    function bool_to_bit( $value ) {
        return ( ! empty( $value ) && 'false' !== $value) ? '1' : '';
    }

    function bit_to_bool( $value ) {
        return ( ! empty( $value ) && '0' !== $value ) ? true : false;
    }
}