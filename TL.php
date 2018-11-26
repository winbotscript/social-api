<?php

namespace Servdebt\Social;

class TL
{

	public $items = [];
	protected $cursors;
	public $time;

	public function __construct()
	{

		$this->time        = new \stdClass();
		$this->time->start = $this->time->end = $this->time->delta = null;

		$this->cursors           = new \stdClass();
		$this->cursors->previous = $this->cursors->next = null;

	}

	public function sort()
	{
		return usort($this->items, function ($i1, $i2) {
			if ($i1->timestamp == $i2->timestamp)
				return 0;

			return ($i1->timestamp > $i2->timestamp) ? -1 : 1;
		});
	}

	public function sortMediaVariants(&$variants)
	{
		array_multisort(array_column($variants, "width"), SORT_DESC, array_column($variants, "height"), SORT_DESC, $variants);
	}

	public function calculateTimes(): void
	{
		$this->time->start = $this->items[0]->timestamp;
		$this->time->end   = end($this->items)->timestamp;
		$this->time->delta = $this->time->start - $this->time->end;
	}

	public function getCursors(): \stdClass
	{
		return $this->cursors;
	}

	public function getItems(): array
	{
		return $this->items;
	}

	public function __toString(): string
	{
		return json_encode($this->getItems());
	}

}
