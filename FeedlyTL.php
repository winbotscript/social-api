<?php

namespace Servdebt\Social;

class FeedlyTL extends TL
{

	public function __construct($sourceName, \stdClass $data, ?int $cursor = null)
	{

		parent::__construct();

		foreach ($data->items as $item) {

			$entry               = new Item();
			$entry->source       = $sourceName;
			$entry->id           = $item->id;
			$entry->author->name = $item->origin->title;
			$entry->author->user = $item->origin->htmlUrl;
			$entry->timestamp    = (int)round($item->crawled / 1000);
			$entry->text         = $item->title;

			if (isset($item->canonicalUrl))
				$entry->url = $item->canonicalUrl;
			elseif (isset($item->canonical))
				$entry->url = $item->canonical[0]->href;
			else
				$entry->url = $item->alternate[0]->href;

			// Parse media
			if (isset($item->visual) && $item->visual->url !== "none") {
			    $m = $this->parseMedia($item->visual);
			    if ($m !== false) {
                    $entry->pushMedia($m);
                }
            }

			$this->items[] = $entry;
		}

		$this->sort();
		$this->calculateTimes();

		$this->cursors->previous = $cursor;
		$this->cursors->next     = $data->continuation;
	}

	protected function parseMedia($media): Media
	{
        if (empty($media->width) || empty($media->height)) return false;

		$parsedMedia        = new Media();
		$parsedMedia->thumb = $media->url;
		$parsedMedia->url   = $media->url;
		$parsedMedia->type  = Media::TYPE_IMAGE;

		$parsedMedia->variants[] = (object)[
			"width"  => $media->width,
			"height" => $media->height,
			"url"    => $media->url
		];

		return $parsedMedia;
	}

}
