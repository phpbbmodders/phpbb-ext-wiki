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
	'NO_ARTICLE'					=> 'Der gewünschte Artikel existiert nicht.<br>Du bist nicht berechtigt den Artikel anzulegen.',
	'MODERATOR_CONTROLS'			=> 'Schnellmoderation',
	'NO_ARTICLE_REDIRECT'			=> '<p><strong>Dieser Artikel wurde ersetzt.</strong> Siehe stattdessen <a href="%1$s">%2$s</a>.</p>',
	'LAST_EDIT'						=> 'Zuletzt bearbeitet',
	'EDIT_WIKI'						=> 'Artikel bearbeiten',
	'VERSIONS_WIKI'					=> 'Versionen anzeigen',
	'WIKI'							=> 'KB',
	'BACK_TO_ARTICLE'				=> 'Zurück zum Artikel',
	'BACK_TO_WIKI'					=> 'Zurück zur Knowledge Base',
	'EDIT_ARTICLE_SUCCESS'			=> 'Der Artikel wurde erfolgreich bearbeitet.',
	'EDIT_ARTICLE_SUCCESS_INACTIVE'	=> 'Der Artikel wurde erfolgreich bearbeitet, er muss aber noch freigeschaltet werden bevor er öffentlich angezeit wird.',
	'VERSIONS_OF_ARTICLE'			=> 'Versionsgeschichte',
	'VERSION_COMPARE_HEADLINE'		=> 'Versionsunterschied von Version <a href="%3$s">%1$d</a> zu <a href="%4$s">%2$d</a>',
	'COMPARE'						=> 'Vergleichen',
	'COMPARE_EXPLAIN'				=> 'Hier werden alle Versionen des Artikels aufgelistet. Wähle zwei Versionen aus um sie zu vergleichen.',
	'VERSION'						=> 'Version',
	'TITLE'							=> 'Titel',
	'REASON_EDIT'					=> 'Änderungsgrund',
	'VIEW_DISCUSION'				=> 'Diskussion',
	'TOPIC_ID'						=> 'Diskusions Thema ID',
	'TOPIC_ID_EXPLAIN'				=> 'Gib die ID des Themas an das als Diskusion zu dem Artikel dient.',
	'CONTRIBUTIONS_OF'				=> 'Beiträge von %s',
	'CONTRIBUTIONS_EXPLAIN'			=> 'Alle Versionen, die %s gespeichert hat, die neuesten zuerst.',
	'CONTRIBUTIONS_ARTICLE'			=> 'Artikel',
	'CONTRIBUTIONS_SAVED'			=> 'Gespeichert',
	'CONTRIBUTIONS_STATUS'			=> 'Status',
	'CONTRIBUTIONS_NONE'			=> 'Es gibt keine Versionen, die angezeigt werden können.',
	'WIKI_WATCH'					=> 'Artikel beobachten',
	'WIKI_UNWATCH'					=> 'Nicht mehr beobachten',
	'WIKI_NOW_WATCHING'				=> 'Du beobachtest diesen Artikel jetzt. Du wirst benachrichtigt, wenn eine neue Version aktiv wird.',
	'WIKI_NO_LONGER_WATCHING'		=> 'Du beobachtest diesen Artikel nicht mehr.',
	'WIKI_RECENT_CHANGES'			=> 'Letzte Änderungen',
	'WIKI_RECENT_CHANGES_EXPLAIN'	=> 'Jede im Wiki gespeicherte Version, die aktiv ist oder war, die neuesten zuerst.',
	'WIKI_FEED_RSS_LINK'			=> 'RSS-Feed',
	'WIKI_FEED_ATOM_LINK'			=> 'Atom-Feed',
	'WIKI_FEED_TITLE'				=> '%s – Letzte Änderungen im Wiki',
	'WIKI_FEED_TITLE_SHORT'			=> 'Letzte Änderungen im Wiki',
	'WIKI_FEED_SAVED_BY'			=> 'Gespeichert von %s.',
	'WIKI_FEED_REASON'				=> 'Grund: %s',
	'CONTRIBUTION_LIVE'				=> 'Aktive Version',
	'CONTRIBUTION_REPLACED'			=> 'Durch eine neuere Version ersetzt',
	'CONTRIBUTION_PENDING'			=> 'Wartet auf Freigabe oder inaktiv',
	'WIKI_FILES'					=> 'Dateien',
	'WIKI_NO_FILES'					=> 'Zu diesem Artikel wurden keine Dateien hochgeladen.',
	'WIKI_UPLOAD_FILE'				=> 'Datei hochladen',
	'WIKI_DELETE_FILE'				=> 'Datei löschen',
	'WIKI_FILE_UPLOADED'			=> 'Die Datei „%s“ wurde hochgeladen.',
	'WIKI_FILE_EXISTS'				=> 'Dieser Artikel hat bereits eine Datei namens „%s“. Lösche sie zuerst oder benenne die neue Datei um.',
	'WIKI_FILE_DELETED'				=> 'Die Datei „%s“ wurde gelöscht.',
	'WIKI_FILE_NOT_FOUND'			=> 'Die Datei existiert nicht.',
	'WIKI_CONFIRM_DELETE_FILE'		=> 'Bist du sicher, dass du die Datei „%s“ löschen möchtest?',
	'WIKI_FILE_MISSING'				=> 'Die Datei „%s“ wurde nicht zu diesem Artikel hochgeladen.',
	'START_DISCUSSION'				=> 'Diskussion starten',
	'DISCUSSION_READ_ARTICLE'		=> 'Artikel lesen',
	'DISCUSSION_NO_FORUM'			=> 'Für das Wiki wurde kein Diskussionsforum eingerichtet.',
	'CONFIRM_DELETE_VERSION'		=> 'Bist du sicher dass du die Version endgültig löschen möchtest?',
	'DELETE_VERSION_SUCCESS'		=> 'Die Version wurde erfolgreich gelöscht',
	'NO_DELETE_ACTIVE_VERSION'		=> 'Du kannst die aktive Version eines Artikels nicht löschen!',
	'WIKI_FOOTER'					=> 'Knowledge Base by <a href="%1$s">%2$s</a>',
	'SOURCES'						=> 'Quellen',
	'SOURCES_EXPLAIN'				=> 'Gib URLs als Quellen für den Artikel an. Schreibe jede URL in eine eigene Zeile.',
	'INVALID_SOURCE_URL'			=> 'Eine der Quellen ist keine gültige URL.',
	'ACTIVATE_VERSION_SUCCESS'		=> 'Die Artikel Version wurde erfolgreich als aktiv gesetzt.',
	'CONFIRM_ACTIVATE_VERSION'		=> 'Bist du sicher das du die ausgewählte Version als aktive Version für den Artikel setzen willst?',
	'ARTICLE_HAS_NEW'				=> 'Zu dem Artikel ist eine neuere Version vorhanden.',
	'SET_ACTIVE'					=> 'Setze aktiv',
	'IS_ACTIVE'						=> 'Aktive Version',
	'DELETE_ARTICLE'				=> 'Artikel löschen',
	'DELETE_VERSION'				=> 'Version löschen',
	'ARTICLE_DESCRIPTION'			=> 'Artikel Beschreibung',
	'ARTICLE_DESCRIPTION_EXPLAIN'	=> 'Gib eine kurze Beschreibung (max. 255 Zeichen) für den Artikel ein.',
	'SET_REDIRECT'					=> 'Artikel weiterleiten',
	'SET_REDIRECT_EXPLAIN'			=> 'Gib hier einen anderen Artikel an um den Artikel dort hin weiter zu leiten.',
	'CONFIRM_DELETE_ARTICLE'		=> 'Bist du sicher das du den Artikel <strong>und alle seine Versionen</strong> unwiederruflich löschen möchtest?',
	'DELETE_ARTICLE_SUCCESS'		=> 'Der Artikel wurde erfolgreich gelöscht!',
	'NO_ARTICLE_DIFF'				=> 'Es gibt keine Veränderungen an dem Artikel.',
	'NO_SOURCE_DIFF'				=> 'Die Quellen wurden nicht geändert.',
	'SET_STICKY'					=> 'Artikel ist wichtig',
	'SET_INACTIV'					=> 'Artikel inaktiv setzen',
	'CONFIRM_DEACTIVATE_ARTICLE'	=> 'Bist du sicher das du den kompletten Artikel inaktiv setzen willst?',
	'DEACTIVATE_ARTICLE_SUCCESS'	=> 'Der Artikel wurde auf inaktiv gesetzt!',
	'TOTAL_ITEMS'		=>  array(
			1 => '1 Eintrag',
			2 => '%s Einträge',
		),
	'ARTICLE_VIEWS_TEXT'			=> 'Der Artikel wurde <strong>%d</strong> mal angeschaut.'
));
