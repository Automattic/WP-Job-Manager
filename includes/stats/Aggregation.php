<?php

namespace WP_Job_Manager\Stats;

class Aggregation
{
	private int $id;

	public array $data;

	public function __construct(int $id, array $data)
	{
		$this->id = $id;
		$this->data = $data;
	}

	public function get( string $key )  {
		global $wpdb;
		$meta_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->wpjm_statmeta} WHERE {$wpdb->wpjm_stats}_id = %d AND meta_key = %s",
				$this->id,
				$key
			)
		);
		return $meta_value ? absint( $meta_value ) : false;
	}

	public function has( string $key ) {
		global $wpdb;
		$meta = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count( id ) FROM {$wpdb->wpjm_statmeta} WHERE {$wpdb->wpjm_stats}_id = %d AND meta_key = %s",
				$this->id,
				$key
			)
		);
		return absint( $meta ) > 0;
	}

	public function set( string $key, $value ) {
		global $wpdb;
		return $wpdb->replace(
				$wpdb->wpjm_statmeta,
				[
					$wpdb->wpjm_stats."_id" => $this->id,
					'meta_key' => $key,
					'meta_value' => maybe_serialize( $value ),
				],
				[
					'%d',
					'%s',
					'%s',
				]
			) > 0;
	}

	public function add( string $key, $value ) {
		global $wpdb;
		return $wpdb->insert(
				$wpdb->wpjm_statmeta,
				[
					$wpdb->wpjm_stats."_id" => $this->id,
					'meta_key' => $key,
					'meta_value' => maybe_serialize( $value ),
				],
				[
					'%d',
					'%s',
					'%s',
				]
			) > 0;
	}

	public function delete( string $key ) {
		global $wpdb;
		return $wpdb->delete(
				$wpdb->wpjm_statmeta,
				[
					$wpdb->wpjm_stats."_id"  => $this->id,
					'meta_key' => $key,
				],
				[
					'%d',
					'%s',
				]
			) > 0;
	}

	public function delete_all() {
		global $wpdb;
		return $wpdb->delete(
				$wpdb->wpjm_statmeta,
				[
					'event_id' => $this->id,
				],
				[
					'%d',
				]
			) > 0;
	}

	public function query( array $query ) {
		global $wpdb;
		$sql = get_meta_sql( $query, 'wpjm_stat', $wpdb->wpjm_stats, 'id' );
		// We can if we want
		var_dump($sql);
	}


	public function count( array $query ) {
		global $wpdb;
		$sql = get_meta_sql( $query, 'wpjm_stat', $wpdb->wpjm_stats, 'id' );
		// We can if we want
		var_dump($sql);
	}
}
