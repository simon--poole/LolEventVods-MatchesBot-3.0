<?php
date_default_timezone_set("UTC");
class MatchesBot {
	private $userhash;
	public function __construct() {
		require 'config.php';
		require 'lib/httpful.phar';
		$this->limit = 8;
	}
	public function run(){
		$matches = $this->getMatches();
		if(is_null($matches)){
			$sidebar = $this->loadSidebar("* No upcoming matches.");
			echo "<pre>$sidebar</pre>";
			$this->post($sidebar);
		} else {
			$matches = $this->sortMatches($matches);
			$result = $this->parseMatches($matches);
			$sidebar = $this->loadSidebar($result);
			echo "<pre>$sidebar</pre>";
			$this->post($sidebar);
		}
	}
	private function login() {
			$array = array(
				"grant_type" => "password",
				"username" => Config::$User['user'],
				"password" => Config::$User['password']
			);
			$response = \Httpful\Request::post("https://www.reddit.com/api/v1/access_token")
				->sendsType(\Httpful\Mime::FORM)
				->expectsJson()
				->body($array)
				->authenticateWith(Config::$User['client_id'], Config::$User['client_secret'])
				->userAgent(Config::$Settings['useragent'])
				->send();
			$this->token = $response->body->access_token;
	}
	private function getMatches(){
		$today = date('Y-m-d',strtotime('today'));
		$programming = json_decode(file_get_contents("http://na.lolesports.com:80/api/programmingWeek/$today/0.json"));
		if($programming->containsMatch != true)
			return null;
		foreach($programming->programming_block as $key=>$value){
			$block[] = $value->id;
		}
		
		foreach($block as $id){
			$data = json_decode(file_get_contents("http://na.lolesports.com:80/api/programming/$id.json?expand_matches=1"));
			$label = $data->label;
			$count = 0;
			foreach($data->matches as $match){
				$tournament = $match->tournament->name;
				if(!empty($match->contestants)){
					$redLong = $match->contestants->red->name;
					$blueLong = $match->contestants->blue->name;
					$redTeam = (!empty($match->contestants->red->acronym)) ? $match->contestants->red->acronym : $this->shortenName($redLong);
					$blueTeam = (!empty($match->contestants->blue->acronym)) ? $match->contestants->blue->acronym : $this->shortenName($blueLong);
				} else {
					$redTeam = "TBD";
					$blueTeam = "TBD";
				}
				$time = ($match->isLive) ? null : new DateTime($match->dateTime);
				if($match->isFinished == "0")
					$matches[] = array($tournament,$redTeam,$blueTeam,$time, $redLong, $blueLong, $label);
			}
		}
		return $matches;
	}
	private function sortMatches($matches){
		usort($matches, function($key1,$key2) {
			if(is_null($key1[3]) && is_null($key2[3]))
				return 0;
			else if (is_null($key1[3]) && !is_null($key2[3]))
				return -1;
			else if (!is_null($key1[3]) && is_null($key2[3]))
				return 1;
			else
				return ($key1[3]<$key2[3])?-1:1;
		});
		return $matches;
	}
	private function parseMatches($matches){
		$result = "";
		$previous_label = "";
		$count = 0;
		$now = new DateTime();
		foreach($matches as $match){
			$spoiler = $this->checkSpoiler($match[6], $match[1], $match[2]);
			if(is_null($match[3]))
				$time = "**LIVE**";
			else {
				if($match[3]->getTimestamp() < time())
					continue 1;
				$interval = $now->diff($match[3]);
				$time = $interval->format("Starting in %dd, %hh, %im");
			}
			$time = str_replace(array(" 0d,", " 0h,"), "", $time);
			if($previous_label != $match[6]){
				if($count != 0) $result .= PHP_EOL;
				$result .= "* [".$match[6]."](#title)";
				$result .= PHP_EOL;
				$result .= "* [".$time."](#countdown)";
			}
			$previous_label = $match[6];
			if($spoiler)
				$result .= PHP_EOL."* [$match[2]](#spoiler) [](#default) vs [](#default) [$match[1]](#spoiler)";
			else	{
				$icon1 = $this->getIcon($match[1]);
				$icon2 = $this->getIcon($match[2]);
				$result .= PHP_EOL."* [$match[2]](#team) [](#$icon2) vs [](#$icon1) [$match[1]](#team)";
			}
			if(++$count == $this->limit)
					break;
		}
		return $result;
	}
	public function loadSidebar($matches) {
		$this->login();
		$response = \Httpful\Request::get("https://oauth.reddit.com/r/".Config::$Settings['subreddit']."/wiki/sidebar")
				->expectsJson()
				->addHeader('Authorization', "bearer $this->token") 
				->userAgent(Config::$Settings['useragent'])
				->send();
		$sidebar = str_replace(Config::$wiki['template'], $matches, $response->body->data->content_md);
		return $sidebar;
	}
 
	protected function post($content) {
		$response = \Httpful\Request::get("https://oauth.reddit.com/r/".Config::$Settings['subreddit']."/about/edit/.json")
				->expectsJson()
				->addHeader('Authorization', "bearer $this->token") 
				->userAgent(Config::$Settings['useragent'])
				->send();
		$settings = (array) $response->body->data;
		$settings['description'] = htmlspecialchars_decode($content);
		$settings['sr'] = $settings['subreddit_id'];
		$settings['link_type'] = $settings['content_options'];
		$settings['type'] = $settings['subreddit_type'];
		$settings['over_18'] = "false";
		unset($settings['hide_ads']);
		$response = \Httpful\Request::post("https://oauth.reddit.com/api/site_admin?api_type=json")
				->sendsType(\Httpful\Mime::FORM)
				->expectsJson()
				->body($settings)
				->addHeader('Authorization', "bearer $this->token") 
				->userAgent(Config::$Settings['useragent'])
				->send();
	}
	
	private function getIcon($team){
		return strtolower($team);
	}
	
	//v Handlers
	private function shortenName($name){
		return Config::$Teams[$name];
	}
	private function checkSpoiler($title, $t1, $t2){
		if($t1 == "TBD" && $t2 == "TBD")
			return false;
		foreach(Config::$Settings['spoilers'] as $spoiler){
			if(strpos(strtolower($title), strtolower($spoiler)) !== false)
				return true;
		}
		return false;
	}	
}
?>
