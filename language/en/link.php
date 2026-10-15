<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2015 tas2580 (https://tas2580.net)
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
// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//
$lang = array_merge($lang, array(
	'WIKI'					=> 'Wiki',
	'WIKI_NEW_ARTICLE_UPDATE'	=> 'A new version of the article <strong>%1$s</strong> in the Wiki is awaiting approval.',
	'WIKI_ARTICLE_UPDATE'			=> 'An edit of a wiki article is awaiting approval',
	'WIKI_WATCHED_ARTICLE'			=> 'A wiki article you watch has a new version',
	'WIKI_WATCHED_ARTICLE_LIVE'		=> 'A new version of the wiki article <strong>%1$s</strong> is now live.',
	'WIKI_NOTIFICATION_STARTER_TEMPLATES'	=> array(
		1	=> 'The wiki’s starter templates repository has <strong>1</strong> new or changed template.',
		2	=> 'The wiki’s starter templates repository has <strong>%d</strong> new or changed templates.',
	),
	'WIKI_NOTIFICATION_STARTER_TEMPLATES_OPTION'	=> 'The wiki’s starter templates repository has new or changed templates',
	'WIKI_EDIT_LOCK_BLOCKED'		=> '<strong>%1$s</strong> could not edit the wiki article <strong>%2$s</strong>, because %3$s holds it.',
	'WIKI_EDIT_LOCK_BLOCKED_OPTION'	=> 'An edit lock keeps someone out of a wiki article',
	// Under a protected article, and beside it in lists on any page (the board search too)
	'WIKI_PROTECTED_NOTE'			=> 'Only members of %s can edit this article.',
	'WIKI_TOC'					=> 'Contents',
	'WIKI_LINK_BUTTON'			=> 'Wiki link: [[Article name]] or [[Article name|link text]]',
	'WIKI_LINK_NEW_ARTICLE'		=> 'This article doesn’t exist yet. Follow the link to create it.',
	'WIKI_WHAT_LINKS_HERE'		=> 'What links here',
	'WIKI_TEMPLATE_LOOP'		=> 'Template loop: “%s” includes itself',
	'WIKI_TEMPLATE_TEXT'		=> 'Template text',
	'WIKI_TEMPLATE_USED_ON'		=> array(
		0	=> 'Not used in any article yet.',
		1	=> 'Used in 1 article.',
		2	=> 'Used in %d articles.',
	),
	'WIKI_TEMPLATE_TOO_DEEP'	=> 'Too many templates inside templates at “%s”',
	'WIKI_TEMPLATE_UNKNOWN_FUNCTION'	=> 'Unknown function “%s”',
	'WIKI_TIME_INVALID'			=> 'Invalid time',
	'WIKI_EXPR_ERROR'			=> 'Expression error: %s',
	'WIKI_EXPR_TOO_LONG'		=> 'the expression is too long',
	'WIKI_EXPR_UNEXPECTED'		=> 'unexpected “%s”',
	'WIKI_EXPR_MISSING'			=> 'something is missing',
	'WIKI_EXPR_DIV_ZERO'		=> 'division by zero',
	'WIKI_EXPR_NAN'				=> 'the result is not a number',

));
