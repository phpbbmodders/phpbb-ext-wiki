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
	'WIKI_SEARCH_BOARD'					=> 'Show wiki matches in the board search',
	'WIKI_SEARCH_BOARD_EXPLAIN'			=> 'Adds an “Also search the Wiki” option to the advanced search and shows the wiki articles that match a board search in a block above the forum results. Needs the wiki search to be on.',
	'WIKI_SEARCH_BOARD_MAX'				=> 'Articles in that block',
	'WIKI_SEARCH_BOARD_MAX_EXPLAIN'		=> 'The most articles the block lists. A link to all of the matches is shown below them.',
	'WIKI_SEARCH_BACKEND_UNAVAILABLE'			=> 'not available on this database',
	'WIKI_SEARCH_BACKEND_UNAVAILABLE_EXPLAIN'	=> 'This backend needs a different database than the one this board runs on, so it cannot be chosen and has no index to build.',
	'WIKI_SEARCH_INDEX_EXPLAIN'			=> 'Build the index of the search backend you use. Until it is built the native backend finds only articles saved since it was installed, and the MySQL and PostgreSQL backends find nothing. Switching backend does not delete the other one’s index.',
	'LOG_WIKI_CONFIG_SEARCH'			=> '<strong>Altered wiki search settings</strong>',
	'WIKI_DISCUSSION_FORUM_INVALID'		=> 'The forum you chose does not exist or cannot hold topics.',
	'WIKI_EDIT_CONFLICT'				=> 'When two people edit an article',
	'WIKI_EDIT_CONFLICT_EXPLAIN'		=> 'Nothing: each edit is saved as its own version, as before. Notice: the edit form names who else has the article open. Lock: the first to open the edit form holds the article, and others cannot edit it until they save or their time runs out; moderators can break a lock, and are notified when a lock keeps someone out.',
	'WIKI_EDIT_CONFLICT_OFF'			=> 'Nothing',
	'WIKI_EDIT_CONFLICT_NOTICE'			=> 'Notice',
	'WIKI_EDIT_CONFLICT_LOCK'			=> 'Lock',
	'WIKI_EDIT_CONFLICT_INVALID'		=> 'The choice for when two people edit an article is not one of the choices.',
	'WIKI_EDIT_LOCK_MINUTES'			=> 'Minutes an editor holds an article',
	'WIKI_EDIT_LOCK_MINUTES_EXPLAIN'	=> 'How long the notice names an editor, or the lock lasts, after their last sign of work. It is extended every few minutes while their edit form is open, and when they preview, so it only runs out once they have left.',

	'WIKI_TEMPLATE_NESTING'			=> 'Template nesting depth',
	'WIKI_TEMPLATE_NESTING_EXPLAIN'	=> 'How many levels of templates a template call may hold inside it, as in {{A|{{B|{{C}}}}}}. Deeper calls show as plain text. The default, 8, covers most templates copied from Wikipedia. After a change, saved articles are updated in the background over the next few page views.',
	'WIKI_TEMPLATE_NESTING_INVALID'	=> 'The template nesting depth must be a number from %1$d to %2$d.',
	'WIKI_REPARSE_PENDING'			=> 'Articles are still being updated for the last change.',

	'WIKI_ONLY_BBCODES'		=> 'BBCodes only for the wiki',
	'WIKI_ONLY_BBCODES_EXPLAIN'	=> 'Tick a custom BBCode to use it only in wiki articles. It then stops working in posts, private messages and signatures, and its button shows only in the wiki editor. Text saved before you tick it keeps its formatting until it is edited. Custom BBCodes are the ones added on the <a href="%s">Posting » BBCodes</a> page of the ACP.',
	'WIKI_ONLY_BBCODE'			=> 'Only in wiki articles',
	'WIKI_ONLY_BBCODES_NONE'	=> 'This board has no custom BBCodes yet. Add them on the <a href="%s">Posting » BBCodes</a> page of the ACP.',
	'WIKI_EDIT_LOCK_MINUTES_INVALID'	=> 'Minutes an editor holds an article must be a number from %1$d to %2$d.',

	'ACP_WIKI_STARTER_TEMPLATES_EXPLAIN'	=> 'Starter templates are ready-made templates for the wiki, adapted from Wikipedia and other wikis, from the <a href="https://github.com/phpbbmodders/wiki-templates">phpbbmodders/wiki-templates</a> repository. They are shared under the <a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY-SA 4.0</a> licence; each one says where it comes from at the top of its page. Get the templates first, then choose which to install. Nothing is installed until you press Install.',
	'WIKI_STARTER_GET'					=> 'Get the templates',
	'WIKI_STARTER_UPLOAD'				=> 'Upload a zip',
	'WIKI_STARTER_UPLOAD_EXPLAIN'		=> 'A zip of the repository, such as the one GitHub’s “Download ZIP” button makes. Only its templates folder is read.',
	'WIKI_STARTER_UPLOAD_BUTTON'		=> 'Upload',
	'WIKI_STARTER_MAX_ZIP'				=> 'Largest zip',
	'WIKI_STARTER_DOWNLOAD'				=> 'Download from GitHub',
	'WIKI_STARTER_DOWNLOAD_EXPLAIN'		=> 'Downloads the latest templates from the repository. This only gets the files; it installs nothing.',
	'WIKI_STARTER_DOWNLOAD_BUTTON'		=> 'Download latest',
	'WIKI_STARTER_FOLDER'				=> 'Or copy them by FTP',
	'WIKI_STARTER_FOLDER_EXPLAIN'		=> 'Copy the <code>.wiki</code> files from the repository’s templates folder into this folder of your board. Uploading or downloading replaces the files in it.',
	'WIKI_STARTER_FOLDER_NOT_WRITABLE'	=> 'The folder <code>store/phpbbmodders_wiki/templates/</code> could not be created or written to. Make sure the board’s <code>store/</code> folder is writable by the web server (as phpBB itself needs), then try again.',
	'WIKI_STARTER_TAKEN'				=> array(
		0	=> 'The zip held no templates.',
		1	=> 'Got 1 template. Choose below whether to install it.',
		2	=> 'Got %d templates. Choose below which to install.',
	),
	'WIKI_STARTER_DOWNLOAD_FAILED'		=> 'The templates could not be downloaded from GitHub. Try again later, or download the zip yourself and upload it here.',
	'WIKI_STARTER_ZIP_UNREADABLE'		=> 'The uploaded file could not be read.',
	'WIKI_STARTER_ZIP_INVALID'			=> 'The file is not a zip this page can read.',
	'WIKI_STARTER_ZIP_TOO_LARGE'		=> 'The zip’s templates are too large.',
	'WIKI_STARTER_ZIP_EMPTY'			=> 'The zip has no templates folder with templates in it.',

	'WIKI_STARTER_CHECK'				=> 'Check for new templates',
	'WIKI_STARTER_CHECK_ENABLE'			=> 'Check for new templates',
	'WIKI_STARTER_CHECK_ENABLE_EXPLAIN'	=> 'Asks GitHub from time to time whether the repository has templates that are new or have changed since you got yours, and notifies the board’s administrators when it does. It downloads and installs nothing; that is always done with the buttons on this page.',
	'WIKI_STARTER_CHECK_DAYS'			=> 'Days between checks',
	'WIKI_STARTER_CHECK_DAYS_EXPLAIN'	=> 'A higher number means fewer notices. Administrators are notified once for each new set of changes.',
	'WIKI_STARTER_CHECK_DAYS_INVALID'	=> 'Days between checks must be a number from %1$d to %2$d.',
	'WIKI_STARTER_LAST_CHECK'			=> 'Last check',
	'WIKI_STARTER_NEVER_CHECKED'		=> 'Never',
	'WIKI_STARTER_UPDATES'				=> 'new or changed templates',
	'WIKI_STARTER_CHECK_NOW'			=> 'Check now',
	'WIKI_STARTER_CHECK_FAILED'			=> 'GitHub could not be asked about the templates. Try again later.',
	'WIKI_STARTER_CHECK_NONE'			=> 'The repository has no templates that are new or changed since you got yours.',
	'WIKI_STARTER_CHECK_CHANGES'		=> array(
		1	=> 'The repository has 1 new or changed template: %2$s. Download the latest to get it.',
		2	=> 'The repository has %1$d new or changed templates: %2$s. Download the latest to get them.',
	),

	'WIKI_STARTER_INSTALL'				=> 'Install',
	'WIKI_STARTER_INSTALL_EXPLAIN'		=> 'Ticked templates are installed as a new version of their page, which goes live straight away with you as its author; earlier versions stay in the page’s history. A page someone on this board has edited since it was installed is not ticked, so their changes are not replaced unless you choose to.',
	'WIKI_STARTER_INSTALL_COLUMN'		=> 'Install',
	'WIKI_STARTER_TEMPLATE'				=> 'Template',
	'WIKI_STARTER_TEMPLATE_PREFIX'		=> 'Template:',
	'WIKI_STARTER_STATUS'				=> 'What installing does',
	'WIKI_STARTER_STATUS_NEW'			=> 'Creates the page',
	'WIKI_STARTER_STATUS_UPDATE'		=> 'Updates the page',
	'WIKI_STARTER_STATUS_UNCHANGED'		=> 'Already installed',
	'WIKI_STARTER_STATUS_EDITED'		=> 'Replaces a page edited on this board',
	'WIKI_STARTER_STATUS_REFUSED'		=> 'Cannot be installed',
	'WIKI_STARTER_EDITED_WARNING'		=> 'Installing replaces this board’s changes with the repository’s version. The old text stays in the page’s history.',
	'WIKI_STARTER_BAD_NAME'				=> 'The file name is not a valid article name.',
	'WIKI_STARTER_NO_ATTRIBUTION'		=> 'The file has no header saying where it comes from and under which licence.',
	'WIKI_STARTER_NOT_ADAPTED'			=> 'The file has not been adapted for this wiki yet.',
	'WIKI_STARTER_INSTALL_BUTTON'		=> 'Install ticked templates',
	'WIKI_STARTER_NONE'					=> 'There are no templates to install yet. Get them above first.',
	'WIKI_STARTER_NOTHING_CHOSEN'		=> 'No templates were installed. Tick the ones to install first.',
	'WIKI_STARTER_INSTALLED'			=> array(
		1	=> 'Installed 1 template: %2$s.',
		2	=> 'Installed %1$d templates: %2$s.',
	),
	'WIKI_STARTER_EDIT_REASON'			=> 'Installed from the starter templates',
));
