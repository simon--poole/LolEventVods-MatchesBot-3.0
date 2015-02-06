<?php
class Config {
	static $Settings = array(
		"subreddit"=>"LEVbeta",				//Subreddit for bot to run on
		"spoilers"=>array("final","ro8","ro16","playoff","iem","tiebreaker"),
		"no_games"=>"No upcoming matches",		//Message when no upcoming matc
		"error_msg"=>"Beep Boop. Something broke!",	//Something broke
		"error_user"=>"SatansF4TE",			//User to send error messages to.
	);
	static $wiki = array(
		"page"=>"sidebarbot",
		"template"=>"%%matches%%",
	);
	static $User = array(
		"user"=>'LEVMatchesBot',
		"passwd"=>"rl0bUf{9EtTCpnU",
		"api_type"=>"json",
	);
	static $Teams = array(
		"Energy PACEMAKER"=>"EP",
		"Team WE"=>"WE",
	);
}
?>