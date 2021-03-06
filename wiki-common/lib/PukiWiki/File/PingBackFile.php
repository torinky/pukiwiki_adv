<?php
/**
 * PingBackファイルクラス
 *
 * @package   PukiWiki\File
 * @access    public
 * @author    Logue <logue@hotmail.co.jp>
 * @copyright 2013 PukiWiki Advance Developers Team
 * @create    2012/03/19
 * @license   GPL v2 or (at your option) any later version
 * @version   $Id: RefererFile.php,v 1.0.0 2013/03/19 19:54:00 Logue Exp $
 */
namespace PukiWiki\File;

use PukiWiki\Utility;

/**
 * PingBackファイルクラス
 */
class PingBackFile extends AbstractFile{
	public static $dir = REFERER_DIR;
	public static $pattern = '/^((?:[0-9A-F]{2})+)\.pb$/';

	/**
	 * コンストラクタ
	 * @param string $page
	 */
	public function __construct($page) {

		if (empty($page)){
			throw new Exception('RefererFile::__construct(): Page name is missing!');
		}
		if (!is_string($page)){
			throw new Exception('RefererFile::__construct(): Page name must be string!');
		}
		$this->page = $page;
		parent::__construct(self::$dir . Utility::encode($page) . '.pb');
	}
}

/* End of file RefererFile.php */
/* Location: /vendor/PukiWiki/Lib/File/RefererFile.php */
