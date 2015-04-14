<?php
date_default_timezone_set("UTC");
class MatchesBot {
	private $userhash;
	public function __construct() {
		require 'config.php';
		require 'lib/snoopy.php';
		$this->snoopy = new \Snoopy;
		$this->limit = 8;
	}
	public function run(){
		$matches = $this->getMatches();
		if(is_null($matches)){
			$sidebar = $this->loadSidebar("No upcoming matches.");
			echo "<pre>$sidebar</pre>";
			$this->post($sidebar);
		} else {
			$matches = $this->sortMatches($matches);
			//var_dump($matches);
			$result = $this->parseMatches($matches);
			$sidebar = $this->loadSidebar($result);
			echo "<pre>$sidebar</pre>";
			$this->post($sidebar);
		}
	}
	private function login() {
			$this->snoopy->submit("http://reddit.com/api/login/".Config::$User['user'], Config::$User);
			$login = json_decode($this->snoopy->results);
			$this->snoopy->cookies['reddit_session'] = $login->json->data->cookie;
			$this->userhash = $login->json->data->modhash;
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
		$result = "| | | |\n:--|:--:|--:";
		$previous_label = "";
		$count = 0;
		$now = new DateTime();
		foreach($matches as $match){
			$sections = explode(" - ", $match[6]);
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
				$result .= PHP_EOL;
				for($i = 0; $i < 2; $i++){
					$result .= "**".$sections[$i]."** |";
				}
				$result .= $time;
			}
			$previous_label = $match[6];
			if($spoiler)
				$result .= PHP_EOL."[$match[1]](/spoiler) | vs. | [$match[2]](/spoiler)";
			else	{
				$icon1 = $this->getIcon($match[1]);
				$icon2 = $this->getIcon($match[2]);
				$result .= PHP_EOL."$match[1] [](#$icon1)| vs. |[](#$icon2) $match[2]";
			}
			if(++$count == $this->limit)
					break;
		}
		return $result;
	}
	public function loadSidebar($matches) {
		$this->login();
		$this->snoopy->fetch("http://www.reddit.com/r/".Config::$Settings['subreddit']."/wiki/".Config::$wiki['page'].".json");
		$sidebar = json_decode($this->snoopy->results);
		$sidebar = $sidebar->data->content_md;
		$sidebar = str_replace("&gt;", ">", $sidebar);
		$sidebar = str_replace("&amp;", "&", $sidebar);
		$sidebar = str_replace(Config::$wiki['template'], $matches, $sidebar);
		return $sidebar;
	}
 
	protected function post($content) {
		$this->snoopy->fetch("http://www.reddit.com/r/".Config::$Settings['subreddit']."/about/edit/.json");
		$about = json_decode($this->snoopy->results);
		$data = $about->data;
		$parameters['sr'] = $data->subreddit_id;
		$parameters['title'] = $data->title;
		$parameters['public_description'] = $data->public_description;
		$parameters['lang'] = $data->language;
		$parameters['type'] = 'restricted';
		$parameters['link_type'] = 'self';
		$parameters['wikimode'] = $data->wikimode;
		$parameters['wiki_edit_karma'] = $data->wiki_edit_karma;
		$parameters['wiki_edit_age'] = $data->wiki_edit_age;
		$parameters['allow_top'] = 'on';
		$parameters['header-title'] = '';
		$parameters['id'] = '#sr-form';
		$parameters['r'] = Config::$Settings['subreddit'];
		$parameters['renderstyle'] = 'html';
		$parameters['comment_score_hide_mins'] = $data->comment_score_hide_mins;
		$parameters['public_traffic'] = $data->public_traffic;
		$parameters['spam_comments'] = $data->spam_comments;
		$parameters['spam_links'] = $data->spam_links;
		$parameters['spam_selfposts'] = $data->spam_selfposts;
		$parameters['description'] = $content;
		$parameters['uh'] = $this->userhash;
 		$parameters['show_media'] = $data->show_media;
		$this->snoopy->submit("http://www.reddit.com/api/site_admin?api_type=json", $parameters);
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
