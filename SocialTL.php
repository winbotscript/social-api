<?php
namespace Servdebt\Social;
class SocialTL extends TL
{

	const API_REQUEST_SLEEP = 0;

	private $networks = [];
	private $queries = [];
	public $fetchLimit;

	public $gatheredCursors = [];
	private $gatheredTimelines = [];

	private $shortestTL;

	public function __construct($fetchItemLimit = 10)
	{
		$this->fetchLimit = $fetchItemLimit;
		parent::__construct();
	}

	public function loadNetwork($name, callable $network)
	{
		$this->networks[$name] = $network();
	}

	public function loadQuery($name, callable $query)
	{
		$this->queries[$name] = $query;
	}

	public function runQuery($name, $moveCursor = true)
	{
		if (!isset($this->gatheredCursors[$name]))
			$this->gatheredCursors[$name] = null;

		$timeline = $this->queries[$name]($this->networks[$name], $this->gatheredCursors[$name], $this->fetchLimit);
		if ($moveCursor)
			$this->gatheredCursors[$name] = $timeline->cursors->next;

		$this->gatheredTimelines[$name] = $timeline;

		sleep(self::API_REQUEST_SLEEP);
	}

	public function fetch($itemMin = 4)
	{
		foreach ($this->networks as $name => $connection)
			$this->runQuery($name);

		// Min interval to set timeline baseline
		$eligibleTimelines = array_filter($this->gatheredTimelines, function ($timeline) use ($itemMin) {
			return (count($timeline->items) >= $itemMin);
		});

		$minDelta     = min(array_column(array_column($eligibleTimelines, "time"), "delta"));
		$minDeltaItem = array_filter($this->gatheredTimelines, function ($timeline) use ($minDelta) {
			return ($timeline->time->delta === $minDelta);
		});

		$this->shortestTL  = key($minDeltaItem);
		$this->time->start = $this->gatheredTimelines[$this->shortestTL]->time->start;
		$this->time->end   = $this->gatheredTimelines[$this->shortestTL]->time->end;

		// Merge timelines
		$this->items = array_merge(...array_column($this->gatheredTimelines, "items"));

		// Filter final timeline by date
		$this->items = array_filter($this->items, function ($item) {
			return ($item->timestamp >= $this->time->end /*&& $item->timestamp <= $this->timeline["start"]*/);
		});

		// Order final timeline
		$this->sort();
		$this->calculateTimes();

	}

}
