<?php

namespace WP_Job_Manager\Stats;

class Period
{
	private string $period_type;

	private string $format;

	/**
	 * @param string $period_type
	 * @param string $format
	 */
	public function __construct(string $period_type, string $format)
	{
		$this->period_type = $period_type;
		$this->format = $format;
	}

	public function get_period_type(): string
	{
		return $this->period_type;
	}

	public function adjust(\DateTimeImmutable $date): \DateTimeImmutable
	{
		return \DateTimeImmutable::createFromFormat('d F Y H:i:s', $date->format( $this->format ) );
	}

}
