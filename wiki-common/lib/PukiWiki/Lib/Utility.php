<?php
// PukiWiki Advance - Yet another WikiWikiWeb clone.
// $Id: Utility.php,v 1.0.0 2012/12/31 18:18:00 Logue Exp $
// Copyright (C)
//   2012 PukiWiki Advance Developers Team
// License: GPL v2 or (at your option) any later version
namespace PukiWiki\Lib;

use Zend\Math\Rand;
use PukiWiki\Lib\TimeZone;
use PukiWiki\Lib\Lang\AcceptLanguage;
use PukiWiki\Lib\Router;
use PukiWiki\Lib\Renderer\InlineFactory;

class Utility{
	// InterWikiName
	const INTERWIKINAME_PATTERN = '(\[\[)?((?:(?!\s|:|\]\]).)+):(.+)(?(1)\]\])';
	// WikiName
	const WIKINAME_PATTERN = '(?:[A-Z][a-z][¡-ÿ][Ā-ſ]+){2,}(?!\w)';
	// BracketName
	const BRAKETNAME_PATTERN = '(?!\s):?[^\r\n\t\f\[\]<>#&":]+:?(?<!\s)';
	// Note
	const NOTE_PATTERN = '\(\(((?:(?>(?:(?!\(\()(?!\)\)(?:[^\)]|$)).)+)|(?R))*)\)\)';
	// チケット名
	const TICKET_NAME = 'ticket';

	/**
	 * 基準となる時刻を設定する
	 * @global type $language
	 * @global type $use_local_time
	 * @return array
	 */
	public static function initTime() {
		global $language, $use_local_time;

		if ($use_local_time) {
			list($zone, $zonetime) = self::setTimeZone( DEFAULT_LANG );
		} else {
			list($zone, $zonetime) = self::setTimeZone( $language );
			list($l_zone, $l_zonetime) = self::getTimeZoneLocal();
			if ($l_zonetime != '' && $zonetime != $l_zonetime) {
				$zone = $l_zone;
				$zonetime = $l_zonetime;
			}
		}

		foreach(array('UTIME'=>time(),'MUTIME'=>getmicrotime(),'ZONE'=>$zone,'ZONETIME'=>$zonetime) as $key => $value ){
			defined($key) or define($key,$value);
		}
		return array($zone, $zonetime);
	}
	/**
	 * 言語からTimeZoneを指定
	 * @param string $lang 言語
	 * @return array
	 */
	static function setTimeZone($lang='')
	{
		if (empty($lang)) {
			return array('UTC', 0);
		}
		$l = AcceptLanguage::splitLocaleStr( $lang );

		// When the name of a country is uncertain (国名が不明な場合)
		if (empty($l[2])) {
			$obj_l2c = new Lang2Country();
			$l[2] = $obj_l2c->getLang2Country($l[1]);
			if (empty($l[2])) {
				return array('UTC', 0);
			}
		}

		$obj = new TimeZone();
		$obj->set_datetime(UTIME); // Setting at judgment time. (判定時刻の設定)
		$obj->set_country($l[2]); // The acquisition country is specified. (取得国を指定)

		// With the installation country in case of the same
		// 設置者の国と同一の場合
		if ($lang == DEFAULT_LANG) {
			if (defined('DEFAULT_TZ_NAME')) {
				$obj->set_tz_name(DEFAULT_TZ_NAME);
			}
		}

		list($zone, $zonetime) = $obj->get_zonetime();

		if ($zonetime == 0 || empty($zone)) {
			return array('UTC', 0);
		}

		return array($zone, $zonetime);
	}
	/**
	 * ローカルのTimeZoneを取得
	 * @return array
	 */
	static function getTimeZoneLocal()
	{
		if (! isset($_COOKIE['timezone'])) return array('','');

		$tz = trim($_COOKIE['timezone']);

		$offset = substr($tz,0,1);
		switch ($offset) {
			case '-':
			case '+':
				$tz = substr($tz,1);
				break;
			default:
				$offset = '+';
		}

		$h = substr($tz,0,2);
		$i = substr($tz,2,2);

		$zonetime = ($h * 3600) + ($i * 60);
		$zonetime = ($offset == '-') ? $zonetime * -1 : $zonetime;

		return array($offset.$tz, $zonetime);
	}
	/**
	 * ページ作成の所要時間を計算
	 * @return string
	 */
	public static function getTakeTime(){
		// http://pukiwiki.sourceforge.jp/dev/?BugTrack2%2F251
		return sprintf('%01.03f', getmicrotime() - MUTIME);
	}
	/**
	 * IPアドレスを取得
	 * @return string
	 */
	public static function getRemoteIp(){
		static $array_var = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REMOTE_ADDR','REMOTE_ADDR'); // HTTP_X_FORWARDED_FOR
		foreach($array_var as $x){
			if (isset($_SERVER[$x])) return $_SERVER[$x];
		}
		return '';
	}
	/**
	 * htmlspacialcharsのエイリアス（PHP5.4対策）
	 * @param string $string 文字列
	 * @param int $flags 変換する文字
	 * @param string $charset エンコード
	 * @return string
	 */
	public static function htmlsc($string = '', $flags = ENT_QUOTES, $charset = 'UTF-8'){
		// Sugar with default settings
		return htmlspecialchars($string, $flags, $charset);	// htmlsc()
	}
	/**
	 * ページ名をファイル格納用の名前にする（FrontPage→46726F6E7450616765）
	 * @param string $str
	 * @return string
	 */
	public static function encode($str) {
		$value = strval($str);
		return empty($value) ? null : strtoupper(bin2hex($value));
	}
	/**
	 * ファイル格納用の名前からページ名を取得する（46726F6E7450616765→FrontPage）
	 * @param string $str
	 * @return string
	 */
	public static function decode($str) {
		return hex2bin($str);
	}
	/**
	 * 見出しを作る
	 * @param string $str 入力文字列
	 * @param boolean $strip 見出し編集用のアンカーを削除する
	 * @return string
	 */
	public static function setHeading(& $str, $strip = TRUE)
	{
		// Cut fixed-heading anchors
		$id = '';
		$matches = array();
		if (preg_match('/^(\*{0,3})(.*?)\[#([A-Za-z][\w-]+)\](.*?)$/m', $str, $matches)) {	// 先頭が*から始まってて、なおかつ[#...]が存在する
			$str = $matches[2] . $matches[4];
			$id  = & $matches[3];
		} else {
			$str = preg_replace('/^\*{0,3}/', '', $str);
		}

		// Cut footnotes and tags
		if ($strip === TRUE)
			$str = self::stripHtmlTags(InlineFactory::factory(preg_replace('/'.self::NOTE_PATTERN.'/ex', '', $str)));

		return $id;
	}
	/**
	 * 文字列がURLかをチェック
	 * @param string $str
	 * @param boolean $only_http HTTPプロトコルのみを判定にするか
	 * @return boolean
	 */
	public static function isUri($str, $only_http = FALSE){
		// URLでありえない文字はfalseを返す
		if ( preg_match( '|[^-/?:#@&=+$,\w.!~*;\'()%]|', $str ) ) {
			return FALSE;
		}

		// 許可するスキーマー
		$scheme = $only_http ? 'https?' : 'https?|ftp|news';

		// URLマッチパターン
		$pattern = (
			'!^(?:'.$scheme.')://'					// scheme
			. '(?:\w+:\w+@)?'						// ( user:pass )?
			. '('
			. '(?:[-_0-9a-z]+\.)+(?:[a-z]+)\.?|'	// ( domain name |
			. '\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}|'	//   IP Address  |
			. 'localhost'							//   localhost )
			. ')'
			. '(?::\d{1,5})?(?:/|$)!iD'				// ( :Port )?
		);
		// 正規処理
		$ret = preg_match($pattern, $str);
		// マッチしない場合は0が帰るのでFALSEにする
		return ($ret === 0) ? FALSE : $ret;
	}
	/**
	 * WebDAVからのアクセスか
	 */
	public static function isWebDAV()
	{
		global $log_ua;
		static $status = false;
		if ($status) return true;

		static $ua_dav = array(
			'Microsoft-WebDAV-MiniRedir\/',
			'Microsoft Data Access Internet Publishing Provider',
			'MS FrontPage',
			'^WebDrive',
			'^WebDAVFS\/',
			'^gnome-vfs\/',
			'^XML Spy',
			'^Dreamweaver-WebDAV-SCM1',
			'^Rei.Fs.WebDAV',
		);

		switch($_SERVER['REQUEST_METHOD']) {
			case 'OPTIONS':
			case 'PROPFIND':
			case 'MOVE':
			case 'COPY':
			case 'DELETE':
			case 'PROPPATCH':
			case 'MKCOL':
			case 'LOCK':
			case 'UNLOCK':
				$status = true;
				return $status;
			default:
				continue;
		}

		$matches = array();
		foreach($ua_dav as $pattern) {
			if (preg_match('/'.$pattern.'/', $log_ua, $matches)) {
				$status = true;
				return true;
			}
		}
		return false;
	}
	/**
	 * InterWikiNameかをチェック
	 * @param string $str
	 * @return boolean
	 */
	public static function isInterWiki($str){
		return preg_match('/^' . self::INTERWIKINAME_PATTERN . '$/', $str);
	}
	/**
	 * ブラケット名か
	 * @param string $str
	 * @return boolean
	 */
	public static function isBracketName($str){
		return preg_match('/^(?!\/)' . self::BRAKETNAME_PATTERN . '$(?<!\/$)/', $str);
	}
	/**
	 * Wiki名か
	 * @param string $str
	 * @return boolean
	 */
	public static function isWikiName($str){
		return preg_match('/^' . self::WIKINAME_PATTERN . '$/', $str);
	}
	/**
	 * Remove null(\0) bytes from variables
	 * NOTE: PHP had vulnerabilities that opens "hoge.php" via fopen("hoge.php\0.txt") etc.
	 * [PHP-users 12736] null byte attack
	 * http://ns1.php.gr.jp/pipermail/php-users/2003-January/012742.html
	 *
	 * 2003-05-16: magic quotes gpcの復元処理を統合
	 * 2003-05-21: 連想配列のキーはbinary safe
	 *
	 * @param string $param
	 * @return string
	 */
	public static function stripNullBytes($param)
	{
		static $magic_quotes_gpc = NULL;
		if ($magic_quotes_gpc === NULL)
			$magic_quotes_gpc = get_magic_quotes_gpc();

		if (is_array($param)) {
			return array_map('input_filter', $param);
		}
		$result = str_replace('\0', '', $param);
		if ($magic_quotes_gpc) $result = stripslashes($result);
		return $result;
	}
	/**
	 * ブラケット（[[ ]]）を取り除く
	 * @param string $str
	 * @return string
	 */
	public static function stripBracket($str)
	{
		$match = array();
		return preg_match('/^\[\[(.*)\]\]$/', $str, $match) ? $match[1] : $str;
	}
	/**
	 * WikiNameからHTMLタグを除く
	 * @param $str string 入力文字
	 * @param $all boolean 全てのタグかaタグのみか
	 * @return string
	 */
	public static function stripHtmlTags($str, $all = true)
	{
		global $_symbol_noexists;
		static $noexists_pattern;

		if (! isset($noexists_pattern))
			$noexists_pattern = '#<span class="noexists">([^<]*)<a[^>]+>' . preg_quote($_symbol_noexists, '#') . '</a></span>#';

		// Strip Dagnling-Link decoration (Tags and "$_symbol_noexists")
		$str = preg_replace($noexists_pattern, '$1', $str);

		return $all ?
			preg_replace('#<[^>]+>#', '', $str) :		// All other HTML tags
			preg_replace('#<a[^>]+>|</a>#i', '', $str);	// All other anchor-tags only
	}
	/**
	 * 自動リンクを削除
	 * @param string $str 入力文字
	 * @return string
	 */
	public static function stripAutolink($str)
	{
		return preg_replace('#<!--autolink--><a [^>]+>|</a><!--/autolink-->#', '', $str);
	}
	/**
	 * 乱数を生成して暗号化時のsaltを生成する
	 * @param boolean $flush 再生成するか
	 * @return string
	 */
	public static function getTicket($flush = FALSE)
	{
		global $cache;
		static $ticket;

		if ($flush){
			unset($ticket);
			$cache['wiki']->removeItem(self::TICKET_NAME);
		}

		if (isset($ticket)){
			return $ticket;
		}else if ($cache['wiki']->hasItem(self::TICKET_NAME)) {
			$ticket = $cache['wiki']->getItem(self::TICKET_NAME);
		}else{
			// 32バイトの乱数を生成
			$ticket = Rand::getString(32);
			$cache['wiki']->setItem(self::TICKET_NAME, $ticket);
		}
		return $ticket;
	}
	/**
	 * ページリンクからページ名とリンクを取得（アンカーは削除）
	 * @param string $page
	 * @param boolean $strict_editable
	 * @return type
	 */
	public static function explodeAnchor($page, $strict_editable = FALSE)
	{
		// Separate a page-name(or URL or null string) and an anchor
		// (last one standing) without sharp
		$pos = strrpos($page, '#');
		if ($pos === FALSE) return array($page, '', FALSE);

		// Ignore the last sharp letter
		if ($pos + 1 == strlen($page)) {
			$pos = strpos(substr($page, $pos + 1), '#');
			if ($pos === FALSE) return array($page, '', FALSE);
		}

		$s_page = substr($page, 0, $pos);
		$anchor = substr($page, $pos + 1);

		return $strict_editable === TRUE && preg_match('/^[a-z][a-f0-9]{7}$/', $anchor) ?
			array ($s_page, $anchor, TRUE) : // Seems fixed-anchor
			array ($s_page, $anchor, FALSE);
	}
	/**
	 * エラーメッセージを表示
	 * @param string $msg エラーメッセージ
	 * @param string $title エラーのタイトル
	 * @param int $http_code 出力するヘッダー
	 */
	public static function dieMessage($msg, $error_title='', $http_code = 500){
		global $skin_file, $page_title, $_string, $_title, $_button, $vars;

		$title = !empty($error_title) ? $error_title : $_title['error'];
		$page = $_title['error'];

		if (PKWK_WARNING !== true){	// PKWK_WARNINGが有効でない場合は、詳細なエラーを隠す
			$msg = $_string['error_msg'];
		}
		$ret = array();
		$ret[] = '<p>[ ';
		if ( isset($vars['page']) && !empty($vars['page']) ){
			$ret[] = '<a href="' . get_page_location_uri($vars['page']) .'">'.$_button['back'].'</a> | ';
			$ret[] = '<a href="' . Router::get_cmd_uri('edit',$vars['page']) . '">Try to edit this page</a> | ';
		}
		$ret[] = '<a href="' . get_cmd_uri() . '">Return to FrontPage</a> ]</p>';
		$ret[] = '<div class="message_box ui-state-error ui-corner-all">';
		$ret[] = '<p style="padding:0 .5em;"><span class="ui-icon ui-icon-alert" style="display:inline-block;"></span> <strong>' . $_title['error'] . '</strong> ' . $msg . '</p>';
		$ret[] = '</div>';
		$body = join("\n",$ret);

		global $trackback;
		$trackback = 0;

		if (!headers_sent()){
			pkwk_common_headers(0,0, $http_code);
		}

		if(defined('SKIN_FILE')){
			if (file_exists(SKIN_FILE) && is_readable(SKIN_FILE)) {
				catbody($page, $title, $body);
			} elseif ( !empty($skin_file) && file_exists($skin_file) && is_readable($skin_file)) {
				define('SKIN_FILE', $skin_file);
				catbody($page, $title, $body);
			}
		}else{
			$html = array();
			$html[] = '<!doctype html>';
			$html[] = '<html>';
			$html[] = '<head>';
			$html[] = '<meta charset="utf-8">';
			$html[] = '<meta name="robots" content="NOINDEX,NOFOLLOW" />';
			$html[] = '<link rel="stylesheet" href="http://code.jquery.com/ui/' . JQUERY_UI_VER . '/themes/base/jquery-ui.css" type="text/css" />';
			$html[] = '<title>' . $page . ' - ' . $page_title . '</title>';
			$html[] = '</head>';
			$html[] = '<body>' . $body . '</body>';
			$html[] = '</html>';
			echo join("\n",$html);
		}
		pkwk_common_suffixes();
		die();
	}
	/**
	 * リダイレクト
	 * @param string $url リダイレクト先
	 */
	public static function redirect($url = '', $time = 0){
		global $vars;
		if (empty($url)){
			$url = isset($vars['page']) ? Router::get_page_uri($vars['page']) : Router::get_script_uri();
		}
		$s_url = self::htmlsc($url);
		pkwk_headers_sent();
		header('Status: 301 Moved Permanently');
		if (!DEBUG){
			header('Location: ' . $s_url);
		}
		$html = array();
		$html[] = '<!doctype html>';
		$html[] = '<html>';
		$html[] = '<head>';
		$html[] = '<meta charset="utf-8">';
		$html[] = '<meta name="robots" content="NOINDEX,NOFOLLOW" />';
		if (!DEBUG){
			$html[] = '<meta http-equiv="refresh" content="'.$time.'; URL='.$s_url.'" />';
		}
		$html[] = '<link rel="stylesheet" href="http://code.jquery.com/ui/' . JQUERY_UI_VER . '/themes/base/jquery-ui.css" type="text/css" />';
		$html[] = '<title>301 Moved Permanently</title>';
		$html[] = '</head>';
		$html[] = '<body>';
		$html[] = '<div class="message_box ui-state-highlight ui-corner-all">';
		$html[] = '<p style="padding:0 .5em;">';
		$html[] = '<span class="ui-icon ui-icon-alert" style="display:inline-block;"></span>';
		$html[] = 'The requested page has moved to a new URL. <br />';
		$html[] = 'Please click <a href="'.$s_url.'">here</a> if you do not want to move even after a while.</p>';
		$html[] = '</div>';
		$html[] = '</body>';
		$html[] = '</html>';
		echo join("\n",$html);
		exit;
	}
}

// hex2bin -- Converts the hex representation of data to binary
// (PHP 5.4.0)
// Inversion of bin2hex()
if (! function_exists('hex2bin')) {
	function hex2bin($hex_string) {
		// preg_match : Avoid warning : pack(): Type H: illegal hex digit ...
		// (string)   : Always treat as string (not int etc). See BugTrack2/31
		return preg_match('/^[0-9a-f]+$/i', $hex_string) ?
			pack('H*', (string)$hex_string) : $hex_string;
	}
}

/* End of file Utility.php */
/* Location: /vender/PukiWiki/Lib/Utility.php */