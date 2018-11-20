<?php

include "vendor/autoload.php";

use Abraham\TwitterOAuth\TwitterOAuth;
use Carbon\Carbon;
use InstagramAPI\Instagram;
use Servdebt\Social\{FeedlyTL, InstagramTL, SocialTL, TwitterTL};

$sg = new SocialTL();

$sg->loadNetwork("twitter", function () {
	return new TwitterOAuth("...", "...", "...", "...");
});

$sg->loadQuery("twitter", function ($name, TwitterOAuth $connection, ?int $cursor, int $fetchLimit) {

	$queryOptions = [
		"count"           => $fetchLimit,
		"exclude_replies" => false,
		"max_id"          => $cursor,
		"tweet_mode"      => "extended"
	];

	if ($queryOptions["max_id"] === null)
		unset($queryOptions["max_id"]);

	$rawTimeline = $connection->get("statuses/home_timeline", $queryOptions);
	if (is_object($rawTimeline))
		throw new Exception("Twitter Rate Limit!");

	return new TwitterTL($name, $rawTimeline, $cursor);

});

$sg->loadReaction("twitter", function ($name, TwitterOAuth $connection, int $mediaId, bool $type = true) {
	if ($type)
		$connection->post("favorites/create", ["id" => $mediaId]);
	else
		$connection->post("favorites/destroy", ["id" => $mediaId]);
});

$sg->loadNetwork("feed", function () {

	$token = "...";

	$client = new GuzzleHttp\Client([
		"base_uri" => "http://cloud.feedly.com/v3/",
		"headers"  => [
			"Authorization" => "OAuth {$token}"
		]
	]);

	// Get user ID
	$res  = $client->request("GET", "profile");
	$user = json_decode($res->getBody());

	return (object)compact("user", "client");

});

$sg->loadQuery("feed", function ($name, \stdClass $connection, $cursor, int $fetchLimit) {

	$queryOptions = [
		"query" => [
			"count"        => $fetchLimit,
			"streamId"     => "user/{$connection->user->id}/category/global.all",
			"unreadOnly"   => "false",
			"continuation" => $cursor
		]
	];

	if ($queryOptions["query"]["continuation"] === null)
		unset($queryOptions["query"]["continuation"]);

	$res         = $connection->client->request("GET", "streams/contents", $queryOptions);
	$rawTimeline = json_decode($res->getBody());

	return new FeedlyTL($name, $rawTimeline);

});

$sg->loadNetwork("instagram", function () {

	Instagram::$allowDangerousWebUsageAtMyOwnRisk = true;
	$connection = new Instagram(false, false);
	$connection->login("...", "...");

	return $connection;

});

$sg->loadQuery("instagram", function ($name, Instagram $connection, $cursor, int $fetchLimit) {

	$rawTimeline = $connection->timeline->getTimelineFeed($cursor);

	return new InstagramTL($name, $rawTimeline);

});

$sg->loadReaction("instagram", function ($name, Instagram $connection, $mediaId, bool $type = true) {
	if ($type)
		$connection->media->like($mediaId, "feed_timeline");
	else
		$connection->media->unlike($mediaId, "feed_timeline");
});

$sg->gatheredCursors = [
	"twitter"   => null,
	"instagram" => null,
	"feed"      => null
];

$sg->fetch();

// Like some media!
//$sg->react("instagram", "media_id", true);
//$sg->react("twitter", "media_id", true);

$finalTL = $sg->getItems();

// Debug print of retrieved items
print PHP_EOL;
foreach ($finalTL as $item) {
	$debug[] = (object)[
		"id"     => $item->id,
		"source" => $item->source,
		"date"   => Carbon::parse($item->timestamp)
		                  ->format("d-m-Y H:i:s"),
		"user"   => $item->author->name,
	];

	switch ($item->source) {
		case "twitter":
			print "\e[1;34mTW";
			break;
		case "instagram":
			print "IG";
			break;
		case "feed":
			print "\e[1;32mFY";
			break;
		default:
			print "X";
	}

	print " > " . Carbon::parse($item->timestamp)
	                    ->format("d-m-Y H:i:s");
	print " - " . $item->author->name . "\033[0m";
	print PHP_EOL;
	print " " . $item->url;
	print PHP_EOL;
	print " (" . $item->id . ")";
	print PHP_EOL;

}
