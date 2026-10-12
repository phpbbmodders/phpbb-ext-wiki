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
	'ACP_WIKI_SETTINGS_EXPLAIN'			=> 'Settings for the Wiki.',
	'WIKI_DISCUSSION_FORUM'				=> 'Discussion forum',
	'WIKI_DISCUSSION_FORUM_EXPLAIN'		=> 'The forum where the “Start discussion” button on an article creates its discussion topic. Choose “None (disabled)” to hide the button.',
	'WIKI_DISCUSSION_FORUM_NONE'		=> '-- None (disabled) --',
	'WIKI_DISCUSSION_FORUM_INVALID'		=> 'The forum you chose does not exist or cannot hold topics.',
));
