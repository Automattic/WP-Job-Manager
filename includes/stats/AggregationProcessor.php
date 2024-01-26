<?php

namespace WP_Job_Manager\Stats;

interface AggregationProcessor
{
	public function get_slug() : string;

	/**
	 * Gets the keys of the events that this aggregation depends on.
	 *
	 * @return array<string> The keys of the events that this aggregation depends on.
	 */
	public function get_dependencies() : array;

	/**
	 * Gets the entity type and ID for the given event, or null if the event should not be aggregated by entity.
	 *
	 * @param Event $event The event to aggregate.
	 * @return array{entity_type: string, entity_id: int} Returns an array with keys 'entity_type' and 'entity_id'.
	 */
	public function get_entity(Event $event) : ?array;

	public function aggregate(Aggregation $aggregation, Event $event) : Aggregation;
}
