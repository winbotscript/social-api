<?php

namespace Servdebt\Social;

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
            $ii->timestamp       = $item->taken_at;

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

    protected function parseMedia(\stdClass $media): Media
    {

        $mediaImgs = $media->image_versions2->candidates;
        array_multisort(array_column($mediaImgs, "width"), SORT_DESC, array_column($mediaImgs, "height"), SORT_DESC, $mediaImgs);

        $parsed       = new Media();
        $parsed->type = self::$mediaTypeMap[$media->media_type];
        $variants     = &$parsed->variants;

        $variants->thumb->width  = end($mediaImgs)->width;
        $variants->thumb->height = end($mediaImgs)->height;
        $variants->thumb->url    = end($mediaImgs)->url;

        $variants->medium->width  = $mediaImgs[0]->width;
        $variants->medium->height = $mediaImgs[0]->height;
        $variants->medium->url    = $mediaImgs[0]->url;

        $variants->large = clone $variants->medium;

        if ($parsed->type === Media::TYPE_VIDEO || $parsed->type === Media::TYPE_GIF) {

            usort($media->video_versions, function ($i1, $i2) {
                return $i2->width <=> $i1->width;
            });

            $variants->medium->width  = $media->video_versions[0]->width;
            $variants->medium->height = $media->video_versions[0]->height;
            $variants->medium->url    = $media->video_versions[0]->url;

            $variants->large = clone $variants->medium;
        }

        return $parsed;
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
