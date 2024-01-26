<?php

namespace WP_Job_Manager\Stats;

class Event
{
	private string $slug;

	private array $data;

	private ?string $entity_type;

	private ?int $entity_id;

	private \DateTimeImmutable $timestamp;

	/**
	 * @param string $key
	 * @param array $data
	 * @param string|null $entity_type
	 * @param int|null $entity_id
	 * @param \DateTimeImmutable $timestamp
	 */
	public function __construct(string $key, array $data, ?string $entity_type, ?int $entity_id, \DateTimeImmutable $timestamp)
	{
		$this->slug = $key;
		$this->data = $data;
		$this->entity_type = $entity_type;
		$this->entity_id = $entity_id;
		$this->timestamp = $timestamp;
	}

	public function get_slug(): string
	{
		return $this->slug;
	}

	public function get_data(): array
	{
		return $this->data;
	}

	public function get_entity_type(): ?string
	{
		return $this->entity_type;
	}

	public function get_entity_id(): ?int
	{
		return $this->entity_id;
	}

	public function get_timestamp(): \DateTimeImmutable
	{
		return $this->timestamp;
	}

}
