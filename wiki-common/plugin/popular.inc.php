<?php
// PukiWiki - Yet another WikiWikiWeb clone
// $Id: popular.inc.php,v 1.20.9 2011/11/29 20:01:00 Logue Exp $
// Copyright (C)
//   2010-2011 PukiWiki Advance Developers Team
//   2005-2007 PukiWiki Plus! Team
//   2003-2005, 2007,2011 PukiWiki Developers Team
//   2002 Kazunori Mizushima <kazunori@uc.netyou.jp>
//
// Popular pages plugin: Show an access ranking of this wiki
// -- like recent plugin, using counter plugin's count --
use PukiWiki\Auth\Auth;
use PukiWiki\Factory;
use PukiWiki\Time;
use PukiWiki\Utility;
use PukiWiki\Listing;

/*
 * 通算および今日に別けて一覧を作ることができます。
 *
 * [Usage]
 *   #popular
 *   #popular(20)
 *   #popular(20,FrontPage|MenuBar)
 *   #popular(20,FrontPage|MenuBar,today)
 *   #popular(20,FrontPage|MenuBar,total)
 *   #popular(20,FrontPage|MenuBar,yesterday)
 *   #popular(20,FrontPage|MenuBar,recent)
 *
 * [Arguments]
 *   1 - 表示する件数                             default 10
 *   2 - 表示させないページの正規表現             default なし
 *   3 - 通算(total)か今日(today)か昨日(yesterday)か最近(recent)かのフラグ  default total
 */

define('PLUGIN_POPULAR_DEFAULT', 10);

function plugin_popular_init()
{
	$msg = array(
		'_popular_msg'=>array(
			'popular'	=> T_('popular(%d)'),
			'today'		=> T_('today\'s(%d)'),
			'yesterday'	=> T_('yesterday\'s(%d)'),
			'recent'	=> T_('recent\'s(%d)')
		)
	);
	set_plugin_messages($msg);
}

function plugin_popular_convert()
{
	global $vars, $_popular_msg;
//	global $_popular_plugin_frame, $_popular_plugin_today_frame;

	$_page = isset($vars['page']) ? $vars['page'] : '';

	if (!IS_MOBILE){
		$_popular_plugin_frame				= sprintf('<h5>%s</h5><div>%%s</div>', $_popular_msg['popular']);
		$_popular_plugin_today_frame		= sprintf('<h5>%s</h5><div>%%s</div>', $_popular_msg['today']);
		$_popular_plugin_yesterday_frame	= sprintf('<h5>%s</h5><div>%%s</div>', $_popular_msg['yesterday']);
		$_popular_plugin_recent_frame		= sprintf('<h5>%s</h5><div>%%s</div>', $_popular_msg['recent']);
	}else{
		$_popular_plugin_frame				= sprintf('<ul data-role="listview"><li data-theme="a">%s</li>'."\n".'%%s</ul>', $_popular_msg['popular']);
		$_popular_plugin_today_frame		= sprintf('<ul data-role="listview"><li data-theme="a">%s</li>'."\n".'%%s</ul>', $_popular_msg['today']);
		$_popular_plugin_yesterday_frame	= sprintf('<ul data-role="listview"><li data-theme="a">%s</li>'."\n".'%%s</ul>', $_popular_msg['yesterday']);
		$_popular_plugin_recent_frame		= sprintf('<ul data-role="listview"><li data-theme="a">%s</li>'."\n".'%%s</ul>', $_popular_msg['recent']);
	}
	$view   = 'total';
	$max    = PLUGIN_POPULAR_DEFAULT;
	$except = '';

	$array = func_get_args();
	switch (func_num_args()) {
	case 3:
		switch ($array[2]) {
		case 'today':
		case 'true' :
			$view = 'today';
			break;
		case 'yesterday':
			$view = 'yesterday';
			break;
		case 'recent':
			$view = 'recent';
			break;
		case 'total':
		case 'false':
		default:
			$view = 'total';
			break;
		}
	case 2: $except = $array[1];
	case 1: $max    = $array[0];
	}

	$counters = plugin_popular_getlist($view,$max,$except);

	$items = '';
	if (! empty($counters)) {
		$items = (!IS_MOBILE) ? '<ul class="popular_list">' . "\n" : '';

		foreach ($counters as $page=>$count) {
			$page = substr($page, 1);
			$wiki = Factory::Wiki($page);
			$counter = (!IS_MOBILE) ? 
				'<span class="counter">(' . $count .')</span>' :
				'<span class="ui-li-count">' . $count .'</span>';

			$s_page = Utility::htmlsc($page);
			
			if ($page === $_page) {
				// No need to link itself, notifies where you just read
				$pg_passage = $wiki->passage(false,false);
				$items .= ' <li data-theme="e"><span title="' . $s_page . ' ' . $pg_passage . '">' . $s_page . $counter . '</span></li>' . "\n";
			} else {
				$items .= ' <li>' . $wiki->link() . ' ' . $counter . '</li>' . "\n";
			}
		}
		$items .= (!IS_MOBILE) ? '</ul>' . "\n" : '';
	}

	switch ($view) {
		case 'today':
			$frame = $_popular_plugin_today_frame;
			break;
		case 'yesterday':
			$frame = $_popular_plugin_yesterday_frame;
			break;
		case 'recent':
			$frame = $_popular_plugin_recent_frame;
			break;
		case 'total':
		default:
			$frame = $_popular_plugin_frame;
			break;
	}
	return sprintf($frame, count($counters), $items);
}

function plugin_popular_action(){
	global $vars, $_popular_msg;

	$view	= isset($vars['view'])		? $vars['view'] : 'total';
	$except	= isset($vars['except'])	? $vars['except'] : '';
	$max	= isset($vars['max'])		? $vars['max'] : PLUGIN_POPULAR_DEFAULT;
	
	switch ($view) {
		case 'today':
			$frame = $_popular_plugin_today_frame;
			break;
		case 'yesterday':
			$frame = $_popular_plugin_yesterday_frame;
			break;
		case 'recent':
			$frame = $_popular_plugin_recent_frame;
			break;
		case 'total':
		default:
			$frame = $_popular_plugin_frame;
			break;
	}

	pkwk_common_headers();
	$obj = array(
		'title'		=> sprintf($frame, count($counters), $items),
		'counters'	=> plugin_popular_getlist($view, $max, $except)
	);
	header("Content-Type: application/json; charset=".CONTENT_CHARSET);
	echo json_encode($obj);
	exit;
}

function plugin_popular_getlist($view, $max = PLUGIN_POPULAR_DEFAULT, $except){
	static $localtime;
	if (! isset($localtime)) {
		list($zone, $zonetime) = Time::setTimeZone(DEFAULT_LANG);
		$localtime = UTIME + $zonetime;
	}

	$today = gmdate('Y/m/d', $localtime);
	// $yesterday = gmdate('Y/m/d', strtotime('yesterday', $localtime));
	$yesterday = gmdate('Y/m/d',gmmktime(0,0,0, gmdate('m',$localtime), gmdate('d',$localtime)-1, gmdate('Y',$localtime)));
	
	$counters = array();
	foreach (Listing::pages() as $page) {
		if (!empty($except) && preg_match("/".$except."/", $page))
			continue;
		$wiki = Factory::Wiki($page);
		if (!$wiki->isReadable() || $wiki->isHidden() ||! $wiki->isValied())
			continue;

		//$count_file = COUNTER_DIR . str_replace('.txt','.count', $file);
		$count_file = COUNTER_DIR . Utility::encode($page).'.count';

		if (file_exists($count_file)){
			$array = file($count_file);
			$count = rtrim($array[0]);
			$date  = rtrim($array[1]);
			$today_count = rtrim($array[2]);
			$yesterday_count = rtrim($array[3]);
	
			$counters['_' . $page] = 0;
			if ($view == 'today' or $view == 'recent') {
				// $pageが数値に見える(たとえばencode('BBS')=424253)とき、
				// array_splice()によってキー値が変更されてしまうのを防ぐ
				// ため、キーに '_' を連結する
				if ($today == $date) $counters['_' . $page] = $today_count;
			} 
			if ($view == 'yesterday' or $view == 'recent') {
				if ($today == $date) {
					$counters['_' . $page] += $yesterday_count;
				} elseif ($yesterday == $date) {
					$counters['_' . $page] += $today_count;
				}
			}
			if ($view == 'total') {
				$counters['_' . $page] = $count;
			}
			if ($counters['_' . $page] == 0) {
				unset($counters['_' . $page]);
			}
		}
	}
	asort($counters, SORT_NUMERIC);

	// BugTrack2/106: Only variables can be passed by reference from PHP 5.0.5
	$counters = array_reverse($counters, TRUE); // with array_splice()
	if ($max && $max!= 0) { $counters = array_splice($counters, '0', $max); }

	return $counters;
}
/* End of file popular.inc.php */
/* Location: ./wiki-common/plugin/popular.inc.php */
