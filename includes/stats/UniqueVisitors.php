<?php

namespace WP_Job_Manager\Stats;

class UniqueVisitors implements AggregationProcessor
{

	public function get_slug() : string
	{
		return 'unique_visitors';
	}

	/**
	 * Gets the keys of the events that this aggregation depends on.
	 *
	 * @return array<string> The keys of the events that this aggregation depends on.
	 */
	public function get_dependencies() : array
	{
		return [
			'page_view',
		];
	}

	/**
	 * Gets the entity type and ID for the given event, or null if the event should not be aggregated by entity.
	 *
	 * @param Event $event The event to aggregate.
	 * @return array{entity_type: string, entity_id: int} Returns an array with keys 'entity_type' and 'entity_id'.
	 */
	public function get_entity(Event $event) : ?array
	{
		return [
			'entity_type' => $event->get_entity_type(),
			'entity_id' => $event->get_entity_id(),
		];
	}

	public function aggregate(Aggregation $aggregation, Event $event) : Aggregation
	{
		$visitor = 'visitor-' . $event->get_data()['visitor_id'];
		if ( ! $aggregation->has( $visitor ) ) {
			// TODO: Probably can rely on $aggregation->count?
			var_dump($aggregation->set( $visitor, true ));
			if ( ! isset( $aggregation->data['unique_visitors'] ) ) {
				$aggregation->data['unique_visitors'] = 0;
			}
			$aggregation->data['unique_visitors'] = $aggregation->data['unique_visitors']+ 1;
		}
		return $aggregation;
	}
}
