<?php

namespace Servdebt\Social;

use Carbon\Carbon;

class TwitterTL extends TL
{

	private static $mediaTypeMap = [
		"photo"        => Media::TYPE_IMAGE,
		"video"        => Media::TYPE_VIDEO,
		"animated_gif" => Media::TYPE_GIF
	];

	public function __construct($sourceName, array $twitterTimeline, ?int $cursor = null)
	{

		parent::__construct();

		foreach ($twitterTimeline as $status) {

			$tweet               = new Item();
			$tweet->source       = $sourceName;
			$tweet->id           = $status->id;
			$tweet->url          = "https://twitter.com/{$status->user->screen_name}/status/{$status->id}/";
			$tweet->author->name = $status->user->name;
			$tweet->author->user = $status->user->screen_name;
			$tweet->timestamp    = Carbon::parse($status->created_at)->timestamp;
			$tweet->text         = $status->full_text;

			$tweet->interactions->user->liked   = (bool)$status->favorited;
			$tweet->interactions->user->shared  = (bool)$status->retweeted;
			$tweet->interactions->alien->likes  = $status->favorite_count;
			$tweet->interactions->alien->shares = $status->retweet_count;

			// Parse media
			if (isset($status->extended_entities)) {
				foreach ($status->extended_entities->media as $media) {
					$media = $this->parseMedia($media);
					$tweet->pushMedia($media);
				}
			}

			$this->items[] = $tweet;
		}

		if ($cursor !== null)
			array_shift($this->items); // Because we already have the first item!

		$this->sort();
		$this->calculateTimes();

		$this->cursors->previous = $cursor;
		$this->cursors->next     = end($this->items)->id;

	}

	protected function parseMedia($media): Media
	{

		$parsedMedia           = new Media();
		$parsedMedia->thumb    = $media->media_url_https;
		$parsedMedia->url      = $media->media_url_https;
		$parsedMedia->type     = self::$mediaTypeMap[$media->type];
		$parsedMedia->variants = [];

		if ($parsedMedia->type === Media::TYPE_IMAGE) {
			$parsedVariant           = new \stdClass();
			$parsedVariant->width    = $media->sizes->large->w;
			$parsedVariant->height   = $media->sizes->large->h;
			$parsedVariant->url      = $parsedMedia->url;
			$parsedMedia->variants[] = $parsedVariant;
		}

		if ($parsedMedia->type === Media::TYPE_VIDEO || $parsedMedia->type === Media::TYPE_GIF) {
			foreach ($media->video_info->variants as $variant) {
				$parsedVariant           = new \stdClass();
				$parsedVariant->width    = $media->sizes->large->w;
				$parsedVariant->height   = $media->sizes->large->h;
				$parsedVariant->url      = $variant->url;
				$parsedMedia->variants[] = $parsedVariant;
			}

			$parsedMedia->url = $parsedMedia->variants[0]->url;
		}

		return $parsedMedia;

	}

}
