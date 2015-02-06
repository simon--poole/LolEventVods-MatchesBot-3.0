<?php
class Config {
	static $Settings = array(
		"subreddit"=>"LEVbeta",				//Subreddit for bot to run on
		"spoilers"=>array("final","ro8","ro16","playoff","iem","tiebreaker"),
	);
	//Wikipage that bot will access to load sidebar details
	//Make sure your bot account has access to it
	static $wiki = array(
		"page"=>"sidebarbot",
		"template"=>"%%matches%%",
	);
	//User account details
	//These aren't real, don't bother trying them
	static $User = array(
		"user"=>'LEVMatchesBot',
		"passwd"=>"rl0bUf{9EtTCpnU",
		"api_type"=>"json",
	);
	//For some odd reason Riot's API sometimes leaves the shortName field empty, so when it does we refer to this array
	static $Teams = array(
		"Energy PACEMAKER"=>"EP",
		"Team WE"=>"WE",
	);
}
?>