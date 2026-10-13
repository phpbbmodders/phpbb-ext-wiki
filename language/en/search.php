<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

/**
 * DO NOT CHANGE
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
	'WIKI_SEARCH'				=> 'Search the wiki',
	'WIKI_SEARCH_BOX'			=> 'Search the wiki',
	'WIKI_SEARCH_NOT_BUILT'		=> 'The wiki search has not been set up yet, so it may find nothing.',
	'WIKI_SEARCH_BUILD'			=> 'Build the search index',
	'WIKI_SEARCH_IN'			=> 'Search in',
	'WIKI_SEARCH_IN_ALL'		=> 'Titles, descriptions and text',
	'WIKI_SEARCH_IN_TITLES'		=> 'Titles only',
	'WIKI_SEARCH_IN_TEXT'		=> 'Text only',
	'WIKI_SORT_DATE'			=> 'Last edited',
	'WIKI_SORT_TITLE'			=> 'Title',
	'WIKI_SEARCH_NO_RESULTS'	=> 'No article matches your search.',
	'WIKI_SEARCH_FOUND'			=> array(
		0	=> 'No article found',
		1	=> 'Found 1 article',
		2	=> 'Found %d articles',
	),
));
