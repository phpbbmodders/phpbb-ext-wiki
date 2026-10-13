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
	'WIKI_ICON'							=> 'Header link icon',
	'WIKI_ICON_EXPLAIN'					=> 'Enter the name of a <a href="https://fontawesome.com/v4.7.0/icons/" target="_blank"><strong>Font Awesome</strong></a> icon to use for the Wiki link in the board header. The default is <code>wikipedia-w</code>.<br> Leave this field blank for no icon.',
	'WIKI_ICON_INVALID'					=> 'The Wiki link icon contains invalid characters.',
	'WIKI_AUTO_WATCH'					=> 'Watch edited articles',
	'WIKI_AUTO_WATCH_EXPLAIN'			=> 'When on, a member who saves an edit also starts watching that article and is notified when a new version goes live. They can stop watching from the article.',
	'WIKI_FEED_FORMAT'					=> 'Recent changes feed',
	'WIKI_FEED_FORMAT_EXPLAIN'			=> 'Which feed formats the Recent changes page offers, and the header advertises to feed readers.',
	'WIKI_FEED_FORMAT_BOTH'				=> 'RSS and Atom',
	'WIKI_FEED_FORMAT_RSS'				=> 'RSS only',
	'WIKI_FEED_FORMAT_ATOM'				=> 'Atom only',
	'WIKI_FEED_FORMAT_INVALID'			=> 'The feed format you chose is not one of the choices.',
	'WIKI_DISCUSSION_FORUM'				=> 'Discussion forum',
	'WIKI_DISCUSSION_FORUM_EXPLAIN'		=> 'The forum where the “Start discussion” button on an article creates its discussion topic. Choose “None (disabled)” to hide the button.',
	'WIKI_DISCUSSION_FORUM_NONE'		=> '-- None (disabled) --',
	'ACP_WIKI_SEARCH'					=> 'Search',
	'WIKI_SEARCH_SETTINGS_EXPLAIN'		=> 'The wiki has its own search. Choose how it finds articles and build its index; the search covers the live version of each article and keeps itself current when articles are saved, approved or deleted.',
	'WIKI_SEARCH_ENABLE'				=> 'Enable wiki search',
	'WIKI_SEARCH_ENABLE_EXPLAIN'		=> 'Lets members search the wiki. When off, the search page and the search box are hidden.',
	'WIKI_PER_PAGE_SEARCH'				=> 'Results per page',
	'WIKI_PER_PAGE_SEARCH_EXPLAIN'		=> 'How many articles the wiki search results page shows at a time.',
	'WIKI_SEARCH_INDEX_EXPLAIN'			=> 'Build the index of the search backend you use. Until it is built the native backend finds only articles saved since it was installed, and the MySQL and PostgreSQL backends find nothing. Switching backend does not delete the other one’s index.',
	'LOG_WIKI_CONFIG_SEARCH'			=> '<strong>Altered wiki search settings</strong>',
	'WIKI_DISCUSSION_FORUM_INVALID'		=> 'The forum you chose does not exist or cannot hold topics.',
));
