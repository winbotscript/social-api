<?php

class Item
{
	const SOURCE_TWITTER   = 1;
	const SOURCE_INSTAGRAM = 2;
	const SOURCE_FEEDLY    = 3;

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
		$this->author->name = null;
		$this->author->user = null;

		$this->interactions        = new \stdClass();
		$this->interactions->likes = $this->interactions->shares = $this->interactions->replies = null;
	}

	public function pushMedia(Media $media)
	{
		$this->media[] = $media;
	}

}
