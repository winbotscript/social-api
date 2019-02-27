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
                if ($m !== null)
                    $entry->pushMedia($m);
            }

            $this->items[] = $entry;
        }

        $this->sort();
        $this->calculateTimes();

        $this->cursors->previous = $cursor;
        $this->cursors->next     = $data->continuation;
    }

    protected function parseMedia(\stdClass $media): ?Media
    {
        if (empty($media->width) || empty($media->height))
            return null;

        $parsed       = new Media();
        $parsed->type = Media::TYPE_IMAGE;
        $variants     = &$parsed->variants;

        $variants->thumb->width  = $media->width;
        $variants->thumb->height = $media->height;
        $variants->thumb->url    = $media->url;

        $variants->medium = clone $variants->thumb;
        $variants->large  = clone $variants->thumb;

        return $parsed;

    }

}
