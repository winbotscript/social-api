<?php

namespace Servdebt\Social;

use Carbon\Carbon;

class InstagramTL extends TL
{

	private static $mediaTypeMap = [
		1 => Media::TYPE_IMAGE,
		2 => Media::TYPE_VIDEO,
	];

	public function __construct($sourceName, $data, ?string $cursor = null)
	{

		parent::__construct();

		$instagramTimeline = json_decode((string)$data->httpResponse->getBody());
		foreach ($instagramTimeline->feed_items as $item) {

			// Exclude anything that isn't a post - stories, suggested_users, netego etc.
			if (!isset($item->media_or_ad))
				continue;

			$item = $item->media_or_ad;

			$ii                  = new Item();
			$ii->id              = $item->id;
			$ii->source          = $sourceName;
			$ii->url             = "https://instagram.com/p/{$item->code}/";
			$ii->author->name    = $item->user->full_name;
			$ii->author->user    = $item->user->username;
			$ii->author->picture = $item->user->profile_pic_url;
			$ii->timestamp       = Carbon::parse($item->taken_at)->timestamp;

			if (isset($item->has_liked))
				$ii->interactions->user->liked = $item->has_liked;

			if (isset($item->like_count))
				$ii->interactions->alien->likes = $item->like_count;

			if (isset($item->comment_count))
				$ii->interactions->alien->replies = $item->comment_count;

			if (isset($item->caption->text))
				$ii->text = $item->caption->text;

			// Single media item
			if (isset($item->image_versions2->candidates)) {
				$ii->pushMedia($this->parseMedia($item));
			}

			// Media item
			if (isset($item->carousel_media)) {
				foreach ($item->carousel_media as $media) {
					$ii->pushMedia($this->parseMedia($media));
				}
			}

			$this->items[] = $ii;

		}

		$this->sort();
		$this->calculateTimes();

		$this->cursors->previous = $cursor;
		$this->cursors->next     = $data->getNextMaxId();


	}

	protected function parseMedia($item): Media
	{

		$candidates = $item->image_versions2->candidates;
		$thumb      = end($candidates); // Thumb - last item on the candidates list

		$media        = new Media();
		$media->thumb = $candidates[0]->url;
		$media->url   = $thumb->url;
		$media->type  = self::$mediaTypeMap[$item->media_type];

		$media->variants = [];
		foreach ($candidates as $variant) {
			$parsedVariant         = new \stdClass();
			$parsedVariant->width  = $variant->width;
			$parsedVariant->height = $variant->height;
			$parsedVariant->url    = $variant->url;
			$media->variants[]     = $parsedVariant;
		}

		return $media;


	}

	public function push(InstagramTL $timeline): void
	{
		$this->items = \array_merge($this->items, $timeline->getItems());
		$this->sort();
		$this->calculateTimes();
		$this->cursors->next = $timeline->getCursors()->next;
	}

	public function pushMultiple(array $timelines): void
	{

		foreach ($timelines as $timeline) {
			if ($timeline instanceof InstagramTL) {
				$this->items = \array_merge($this->items, $timeline->getItems());
			}
		}

		$this->sort();
		$this->calculateTimes();
		$this->cursors->next = end($timelines)->getCursors()->previous;

	}

}
