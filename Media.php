<?php

namespace Servdebt\Social;

class Media
{
    const TYPE_IMAGE = 1;
    const TYPE_VIDEO = 2;
    const TYPE_GIF   = 3;

    public $type = self::TYPE_IMAGE;
    public $variants;

    public function __construct()
    {
        $this->variants         = new \stdClass();
        $this->variants->thumb  = new MediaVariant();
        $this->variants->medium = new MediaVariant();
        $this->variants->large  = new MediaVariant();
    }
}
