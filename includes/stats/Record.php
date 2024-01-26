<?php

namespace WP_Job_Manager\Stats;

class Record
{
	private ?int $id;

	private ?string $key;


	private ?string $entity_type;

	private ?int $entity_id;

	private ?string $period_type;

	private ?array $data;


	private \DateTimeImmutable $timestamp;

	public function get_id(): ?int
	{
		return $this->id;
	}

	public function get_key(): ?string
	{
		return $this->key;
	}

	public function get_data(): ?array
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

	public function get_period_type(): ?string
	{
		return $this->period_type;
	}

	public function getTimestamp(): \DateTimeImmutable
	{
		return $this->timestamp;
	}


}
