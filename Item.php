<?php

namespace Servdebt\Social;

class Item
{
	public $id;
	public $source;
	public $url;
	public $author;
	public $timestamp;
	public $text = null;
	public $interactions;
	public $media = [];

	public function __construct()
	{

		$this->author       = new \stdClass();
		$this->author->name = $this->author->user = $this->author->picture = null;

		$this->interactions                 = new \stdClass();
		$this->interactions->user           = new \stdClass();
		$this->interactions->alien          = new \stdClass();
		$this->interactions->user->liked    = false;
		$this->interactions->user->shared   = false;
		$this->interactions->alien->likes   = null;
		$this->interactions->alien->shares  = null;
		$this->interactions->alien->replies = null;

	}

	public function pushMedia(Media $media): void
	{
		$this->media[] = $media;
	}

}
