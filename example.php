<?php

include "vendor/autoload.php";

use Abraham\TwitterOAuth\TwitterOAuth;
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
	$connection->post("favorites/" . ($type ? "create" : "destroy"), ["id" => $mediaId]);
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

	$coldTl = $connection->timeline->getTimelineFeed($cursor);
	$coldTl = new InstagramTL($name, $coldTl);

	if ($fetchLimit > 8) {
		$tls   = [];
		$next  = $coldTl->getCursors()->next;
		$calls = ceil(($fetchLimit - 8) / 13);
		$calls = $calls < 1 ? 1 : $calls;
		if ($coldTl->getCursors()->previous !== null)
			$calls++;

		for ($i = 0; $i < $calls; $i++) {
			$warmTl = $connection->timeline->getTimelineFeed($next);
			$warmTl = new InstagramTL($name, $warmTl, $next);
			$next   = $warmTl->getCursors()->next;
			$tls[]  = $warmTl;
		}

		$coldTl->pushMultiple($tls);

	}

	return $coldTl;

});

$sg->loadReaction("instagram", function ($name, Instagram $connection, $mediaId, bool $type = true) {
	$connection->media->{($type ? "like" : "unlike")}($mediaId, "feed_timeline");
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
		"date"   => date("d-m-Y H:i:s", $item->timestamp),
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

	print " > " . date("d-m-Y H:i:s", $item->timestamp);
	print " - " . $item->author->name . "\033[0m";
	//		print PHP_EOL;
	//		print " " . $item->url;
	//		print PHP_EOL;
	//		print " (" . $item->id . ")";
	print PHP_EOL;

}
