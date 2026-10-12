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
	'WIKI_ICON'							=> 'Symbol des Kopfzeilen-Links',
	'WIKI_ICON_EXPLAIN'					=> 'Gib den Namen eines <a href="https://fontawesome.com/v4.7.0/icons/" target="_blank"><strong>Font-Awesome</strong></a>-Symbols für den Wiki-Link in der Kopfzeile des Forums ein. Standard ist <code>wikipedia-w</code>.<br> Lass das Feld leer, um kein Symbol anzuzeigen.',
	'WIKI_ICON_INVALID'					=> 'Das Symbol des Wiki-Links enthält ungültige Zeichen.',
	'WIKI_AUTO_WATCH'					=> 'Bearbeitete Artikel beobachten',
	'WIKI_AUTO_WATCH_EXPLAIN'			=> 'Wenn aktiv, beobachtet ein Mitglied einen Artikel automatisch, sobald es eine Bearbeitung speichert, und wird benachrichtigt, wenn eine neue Version aktiv wird. Das Beobachten kann im Artikel beendet werden.',
	'WIKI_FEED_FORMAT'					=> 'Feed der letzten Änderungen',
	'WIKI_FEED_FORMAT_EXPLAIN'			=> 'Welche Feed-Formate die Seite „Letzte Änderungen“ anbietet und die Kopfzeile Feed-Readern mitteilt.',
	'WIKI_FEED_FORMAT_BOTH'				=> 'RSS und Atom',
	'WIKI_FEED_FORMAT_RSS'				=> 'Nur RSS',
	'WIKI_FEED_FORMAT_ATOM'				=> 'Nur Atom',
	'WIKI_FEED_FORMAT_INVALID'			=> 'Das gewählte Feed-Format steht nicht zur Auswahl.',
	'WIKI_DISCUSSION_FORUM'				=> 'Diskussionsforum',
	'WIKI_DISCUSSION_FORUM_EXPLAIN'		=> 'Das Forum, in dem die Schaltfläche „Diskussion starten“ bei einem Artikel das Diskussionsthema erstellt. Wähle „Keines (deaktiviert)“, um die Schaltfläche auszublenden.',
	'WIKI_DISCUSSION_FORUM_NONE'		=> '-- Keines (deaktiviert) --',
	'WIKI_DISCUSSION_FORUM_INVALID'		=> 'Das gewählte Forum existiert nicht oder kann keine Themen enthalten.',
));
