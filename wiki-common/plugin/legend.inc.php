<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// $Id: legend.inc.php,v 1.0.3 2015/01/04 21:41:00 Logue Exp $
//
// Legend plugin

use PukiWiki\Renderer\RendererFactory;
use PukiWiki\Utility;

// ----
define('PLUGIN_CODE_USAGE', 
	   '<p class="alert alert-warning">Usage:<br />#legend[(title)]{{<br />contents<br />}}</p>');

function plugin_legend_convert()
{
	$argv = func_get_args();
	$argc = func_num_args();

	if ($argc < 1) return PLUGIN_CODE_USAGE;

	$data = $argv[ --$argc ];
	array_pop($argv);
	$parm = legend_set_parm($argv);
	if (strlen($data) == 0 || empty($parm['title'])) {
		return PLUGIN_CODE_USAGE;
	}

	// FIXME:
	// class, style で指定可能であったとしても、ブラウザで正しく処理できるのは、align しかなさそう
	$align = empty($parm['align']) ? '' : ' class="pull-' . $parm['align'] . '"';
	return join("\n", array(
		'<fieldset' . $align . '>',
		'<legend>' . $parm['title'] . '</legend>',
		RendererFactory::factory($data),
		'</fieldset>'
	));

}

function legend_set_parm($argv)
{
	$parm = array();
	$parm['align'] = $parm['title'] = '';

	foreach($argv as $arg) {
		$val = explode('=', $arg);
		$val[1] = Utility::htmlsc(empty($val[1]) ? $val[0] : $val[1]);

		switch($val[0]) {
		case 'r':
		case 'right':
			$parm['align'] = 'right';
			break;
		case 'l':
		case 'left':
			$parm['align'] = 'left';
			break;
		case 'c':
		case 'center':
			$parm['align'] = 'center';
			break;
		/*
		case 't':
		case 'top':
			$parm['align'] = 'top';
			break;
		case 'b':
		case 'bottom':
			$parm['align'] = 'bottom';
			break;
		*/
		default:
			$parm['title'] = $val[1];
			// $parm[$val[0]] = $val[1];
                }
	}
	return $parm;
}
/* End of file legend.inc.php */
/* Location: ./wiki-common/plugin/legend.inc.php */
