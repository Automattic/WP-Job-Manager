<?php

namespace WP_Job_Manager\Stats;

use WP_Job_Manager\Singleton;

class Stats
{
	use Singleton;

	private array $aggregations = [];

	private array $events = [];

	/**
	 * @var Period[]
	 */
	private array $periods = [];

	private function __construct() {
		$this->initialize_wpdb();
		$this->migrate_db();
		$this->register_event( 'page_view' );
		$this->register_period( new Period( 'minute', 'd F Y H:i:00' ) );
		$this->register_period( new Period( 'hourly', 'd F Y H:00:00' ) );
		$this->register_period( new Period( 'daily', 'd F Y 00:00:00' ) );
		$this->register_period( new Period( 'monthly', '1 F Y 00:00:00' ) );
		$this->register_period( new Period( 'yearly', '1 \J\a\n\u\a\r\y Y 00:00:00' ) );
		$this->register_aggregation( new UniqueVisitors() );
	}

	private function initialize_wpdb() {
		global $wpdb;
		$wpdb->wpjm_stats = $wpdb->prefix . 'job_manager_stats';
		$wpdb->wpjm_statmeta = $wpdb->prefix . 'job_manager_stats_meta';
	}

	private function migrate_db() {
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( [
			"CREATE TABLE {$wpdb->wpjm_stats} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				slug varchar(255) NOT NULL,
				period_type varchar(20) NOT NULL,
				entity_type varchar(20) NULL,
				entity_id bigint(20) unsigned NULL,
				timestamp DATETIME NOT NULL,
				data longtext NOT NULL,
				PRIMARY KEY (id),
				KEY record_id (slug, period_type, entity_type, entity_id, timestamp)
			) {$collate}",
			"CREATE TABLE {$wpdb->wpjm_statmeta} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				{$wpdb->wpjm_stats}_id bigint(20) unsigned NOT NULL,
				meta_key varchar(255) NOT NULL,
				meta_value longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY record_id_slug ({$wpdb->wpjm_stats}_id, meta_key)
			) {$collate}",
		]);
	}

	public function register_event( string $slug ) {
		if ( isset( $this->events[ $slug ] ) ) {
			return new \WP_Error( 'invalid-event-slug', "Cannot add event of slug {$slug}, because it already exists" );
		}
		$this->events[ $slug ] = true;
		return true;
	}

	public function register_period( Period $period ) {
		if ( isset( $this->periods[ $period->get_period_type() ] ) ) {
			return new \WP_Error( 'invalid-period-type', "Cannot add period of type {$period->get_period_type()}, because it already exists" );
		}
		$this->periods[ $period->get_period_type() ] = $period;
		return true;
	}

	public function register_aggregation(AggregationProcessor $aggregation_processor) {
		$slug = $aggregation_processor->get_slug();
		if ( isset( $this->aggregations[$slug] ) ) {
			return new \WP_Error( 'invalid-aggregation-slug', "Cannot add aggregation of slug $slug, because it already exists" );
		}
		$dependencies = $aggregation_processor->get_dependencies();
		foreach ( $dependencies as $dependency ) {
			if ( ! isset( $this->events[$dependency] ) ) {
				return new \WP_Error( 'invalid-aggregation-dependency', "Cannot add aggregation of slug $slug, because it depends on event $dependency which does not exist" );
			}
		}
		if ( count( $dependencies ) > count( array_unique( $dependencies ) ) ) {
			return new \WP_Error( 'invalid-aggregation-dependency', "Cannot add aggregation of slug $slug, because it depends on duplicate events" );
		}
		$this->aggregations[$slug] = $aggregation_processor;
	}


	public function collect(string $slug, array $data, $entity_type = null, $entity_id = null ) {
		if ( isset( $this->aggregations[$slug] ) ) {
			return new \WP_Error( 'wp-job-manager-invalid-event-slug', "Cannot record event of slug $slug, because it overrides aggregation" );
		}
		if ( ! isset( $this->events[$slug] ) ) {
			return new \WP_Error( 'wp-job-manager-invalid-event-slug', "Cannot record event of slug $slug, because it does not exist" );
		}
		/** Should we allow the user to set metas? */
		global $wpdb;
		$result = $wpdb->insert( $wpdb->wpjm_stats, [
			'slug' => $slug,
			'data' => maybe_serialize( $data ),
			'entity_type' => $entity_type,
			'entity_id' => $entity_id,
			'period_type' => 'single-event',
			'timestamp' => (new \DateTimeImmutable())->format( 'Y-m-d H:i:s' ),
		], [
			'%s',
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
		] );
		if (false === $result ) {
			return new \WP_Error( 'wp-job-manager-stats-event-insert-failed', "Failed to insert event of slug $slug",
			['wpdb' => $wpdb->last_error] );
		}
		return true;
	}

	public function update_aggregation() {
		global $wpdb;
		/** @var array<string, AggregationProcessor> $processors_by_event */
		$processors_by_event = [];
		foreach ( $this->aggregations as $aggregation ) {
			$dependencies = $aggregation->get_dependencies();
			foreach ( $dependencies as $dependency ) {
				if ( ! isset( $processors_by_event[$dependency] ) ) {
					$processors_by_event[$dependency] = [];
				}
				$processors_by_event[$dependency][] = $aggregation;
			}
		}
		// should we use FOR UPDATE here and put everything in a single transaction?
		$events = $wpdb->get_results( "SELECT * FROM {$wpdb->wpjm_stats} WHERE period_type='single-event' " );
		foreach ( $events as $event ) {
			if ( ! isset ($this->events[$event->slug] ) ) {
				continue;
			}
			$object = new Event( $event->slug, maybe_unserialize( $event->data ), $event->entity_type, $event->entity_id, new \DateTimeImmutable( $event->timestamp ));
			foreach ( $this->periods as $period) {
				$this->process_event($object, $processors_by_event, $period);
			}
			$wpdb->delete( $wpdb->wpjm_stats, [
				'id' => $event->id,
			], [
				'%d',
			] );
		}
	}

	private function process_event( Event $event, array $processors_by_event, Period $period) {
		global $wpdb;
		foreach ($processors_by_event[$event->get_slug()] as $processor ) {
			/** @var AggregationProcessor $aggregation */
			$entity_data = $processor->get_entity( $event );
			$aggregation_timestamp = $period->adjust( $event->get_timestamp() );
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, data FROM {$wpdb->wpjm_stats} WHERE slug=%s AND period_type=%s AND entity_type=%s AND entity_id=%d AND timestamp=%s",
					$processor->get_slug(),
					$period->get_period_type(),
					$entity_data['entity_type'],
					$entity_data['entity_id'],
					$aggregation_timestamp->format( 'Y-m-d H:i:s' )
				)
			);
			if ( ! $row ) {
				$row = new \stdClass();
				$row->data = maybe_serialize([]);
				$wpdb->insert(
					$wpdb->wpjm_stats,
					[
						'slug' => $processor->get_slug(),
						'period_type' => $period->get_period_type(),
						'entity_type' => $entity_data['entity_type'],
						'entity_id' => $entity_data['entity_id'],
						'data' => $row->data,
						'timestamp' => $aggregation_timestamp->format( 'Y-m-d H:i:s' ),
					],
					[
						'%s',
						'%s',
						'%s',
						'%d',
						'%s',
						'%s',
					]
				);
				$row->id = $wpdb->insert_id;
			}
			$aggregation = new Aggregation( $row->id, maybe_unserialize( $row->data ) );
			$aggregation = $processor->aggregate( $aggregation, $event );
			// TODO: optimize this logic to group updates to the same $row->id in a single update. The main
			// challenge is that we need to avoid out of memory errors.
			$wpdb->update(
				$wpdb->wpjm_stats,
				[
					'data' => maybe_serialize( $aggregation->data ),
				],
				[
					'id' => $row->id,
				],
				[
					'%s',
				],
				[
					'%d',
				]
			);
		}
	}

}
