<?php

namespace Servdebt\Social;

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

			$tweet                  = new Item();
			$tweet->source          = $sourceName;
			$tweet->id              = $status->id;
			$tweet->url             = "https://twitter.com/{$status->user->screen_name}/status/{$status->id}/";
			$tweet->author->name    = $status->user->name;
			$tweet->author->user    = $status->user->screen_name;
			$tweet->author->picture = $status->user->profile_image_url_https;
			$tweet->timestamp       = strtotime($status->created_at);
			$tweet->text            = $status->full_text;

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

	protected function parseMedia(\stdClass $media): Media
	{

		$parsedMedia           = new Media();
		$parsedMedia->type     = self::$mediaTypeMap[$media->type];
		$parsedMedia->variants = [];

		if ($parsedMedia->type === Media::TYPE_IMAGE) {
			foreach ($media->sizes as $size => $values) {
				$parsedVariant           = new \stdClass();
				$parsedVariant->width    = $values->w;
				$parsedVariant->height   = $values->h;
				$parsedVariant->url      = $media->media_url_https . ":" . $size;
				$parsedMedia->variants[] = $parsedVariant;
			}

			$this->sortMediaVariants($parsedMedia->variants);

			$parsedMedia->thumbIdx = \count($parsedMedia->variants) - 2;
			$parsedMedia->largeIdx = 0;
		}

		if ($parsedMedia->type === Media::TYPE_VIDEO || $parsedMedia->type === Media::TYPE_GIF) {
			foreach ($media->video_info->variants as $variant) {
				$parsedVariant           = new \stdClass();
				$parsedVariant->width    = $media->sizes->large->w;
				$parsedVariant->height   = $media->sizes->large->h;
				$parsedVariant->url      = $variant->url;
				$parsedMedia->variants[] = $parsedVariant;
			}

			$thumbVariant            = new \stdClass();
			$thumbVariant->width     = $media->sizes->small->w;
			$thumbVariant->height    = $media->sizes->small->h;
			$thumbVariant->url       = $media->media_url_https;
			$parsedMedia->variants[] = $thumbVariant;

			$parsedMedia->thumbIdx = \count($parsedMedia->variants) - 1;
			$parsedMedia->largeIdx = 0;
		}

		return $parsedMedia;

	}

}
