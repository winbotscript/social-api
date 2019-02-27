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

                // Remove media links from the tweet text
                if (!empty($status->extended_entities->media)) {
                    $mediaLinks  = array_column($status->extended_entities->media, "url");
                    $tweet->text = str_replace($mediaLinks, "", $tweet->text);
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

        $parsed       = new Media();
        $parsed->type = self::$mediaTypeMap[$media->type];
        $variants     = &$parsed->variants;

        $variants->thumb->width  = $media->sizes->small->w;
        $variants->thumb->height = $media->sizes->small->h;
        $variants->thumb->url    = $media->media_url_https . ":small";

        $variants->medium->width  = $media->sizes->medium->w;
        $variants->medium->height = $media->sizes->medium->h;
        $variants->medium->url    = $media->media_url_https . ":medium";

        $variants->large->width  = $media->sizes->large->w;
        $variants->large->height = $media->sizes->large->h;
        $variants->large->url    = $media->media_url_https . ":large";

        if ($parsed->type === Media::TYPE_VIDEO || $parsed->type === Media::TYPE_GIF) {
            $variants->medium->url = $media->video_info->variants[0]->url;
            $variants->large->url  = $media->video_info->variants[0]->url;
        }

        return $parsed;

    }

}
