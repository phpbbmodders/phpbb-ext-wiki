<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'ACP_WIKI_TITLE'	=> 'Wiki',
	'ACP_WIKI_SETTINGS'	=> 'Settings',
	'ACP_WIKI_SEARCH'		=> 'Search',
	'ACP_WIKI_STARTER_TEMPLATES'	=> 'Starter templates',
	'LOG_WIKI_STARTER_CHECK_FAILED'	=> '<strong>Could not check the wiki starter templates repository for new templates</strong><br>» GitHub could not be reached, or did not answer as expected.',
	'LOG_WIKI_STARTER_INSTALLED'	=> '<strong>Installed wiki starter templates</strong><br>» %s',
));
