<?php

class WP_Die_Exception extends Exception {
	private $wp_die_args = null;

	public function set_wp_die_args( $message, $title, $args ) {
		$this->wp_die_args = array(
			'message' => $message,
			'title'   => $title,
			'args'    => $args,
		);
	}

	public function get_wp_die_args() {
		return $this->wp_die_args;
	}
}

