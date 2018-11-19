<?php
namespace Servdebt\Social;
class Media
{

	const TYPE_IMAGE = 1;
	const TYPE_VIDEO = 2;
	const TYPE_GIF   = 3;

	public $thumb;
	public $url;
	public $type = self::TYPE_IMAGE;
	public $variants = [];

}
