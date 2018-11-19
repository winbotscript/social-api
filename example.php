<?php

include "vendor/autoload.php";

use Abraham\TwitterOAuth\TwitterOAuth;
use Carbon\Carbon;
use InstagramAPI\Instagram;

$sg = new SocialTL();

$sg->loadNetwork("twitter", function () {
	return new TwitterOAuth("...", "...", "...", "...");
});

$sg->loadQuery("twitter", function (TwitterOAuth $connection, ?int $cursor, int $fetchLimit) {

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

	return new TwitterTL($rawTimeline, $cursor);

});

$sg->loadNetwork("feedly", function () {

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

$sg->loadQuery("feedly", function (stdClass $connection, $cursor, int $fetchLimit) {

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

	return new FeedlyTL($rawTimeline);

});

$sg->loadNetwork("instagram", function () {

	$connection = new Instagram(false, false);
	$connection->login("...", "...");

	return $connection;

});


$sg->loadQuery("instagram", function (Instagram $connection, $cursor, int $fetchLimit) {

	$rawTimeline = $connection->timeline->getTimelineFeed($cursor);

	return new InstagramTL($rawTimeline);

});

$sg->gatheredCursors = [
	"twitter"   => null,
	"instagram" => null,
	"feedly"    => null
];

$sg->fetch();
$finalTL = $sg->getItems();


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
		case 1:
			print "\e[1;34mTW";
			break;
		case 2:
			print "IG";
			break;
		case 3:
			print "\e[1;32mFY";
			break;
		default:
			print "X";
	}

	print " > " . Carbon::parse($item->timestamp)
	                    ->format("d-m-Y H:i:s");
	print " - " . $item->author->name . "\033[0m";
	print PHP_EOL;

}
