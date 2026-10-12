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
	'ACP_WIKI_SETTINGS_EXPLAIN'			=> 'Einstellungen für das Wiki.',
	'WIKI_DISCUSSION_FORUM'				=> 'Diskussionsforum',
	'WIKI_DISCUSSION_FORUM_EXPLAIN'		=> 'Das Forum, in dem die Schaltfläche „Diskussion starten“ bei einem Artikel das Diskussionsthema erstellt. Wähle „Keines (deaktiviert)“, um die Schaltfläche auszublenden.',
	'WIKI_DISCUSSION_FORUM_NONE'		=> '-- Keines (deaktiviert) --',
	'WIKI_DISCUSSION_FORUM_INVALID'		=> 'Das gewählte Forum existiert nicht oder kann keine Themen enthalten.',
));
