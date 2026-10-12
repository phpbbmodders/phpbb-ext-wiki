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
	'NO_ARTICLE'					=> 'The selected article does not exist.',
	'MODERATOR_CONTROLS'			=> 'Moderator controls',
	'ACTION'						=> 'Action',
	'APPROVE'						=> 'Approve',
	'REJECT'						=> 'Disapprove',
	'REVIEW'						=> 'Review',
	'APPROVE_VERSION'				=> 'Approve version %d',
	'REJECT_VERSION'				=> 'Disapprove version %d',
	'PENDING_OLDER_THAN_ACTIVE'		=> 'Older than the live version &ndash; approving this would replace newer content. Review the version history before approving.',
	'ARTICLE_DESCRIPTION'			=> 'Description',
	'ARTICLE_DESCRIPTION_EXPLAIN'	=> 'A short summary shown in the article listing.',
	'NEW_ARTICLE_TITLE'			=> 'New article title',
	'GO_TO_ARTICLE'					=> 'Create',
	'NO_ARTICLE_REDIRECT'			=> '<p><strong>This article has been replaced.</strong> See <a href="%1$s">%2$s</a> instead.</p>',
	'LAST_EDIT'					=> 'Last modified',
	'EDIT_WIKI'					=> 'Edit article',
	'VERSIONS_WIKI'				=> 'View versions',
	'WIKI'							=> 'Wiki',
	'BACK_TO_ARTICLE'				=> 'Back to article',
	'BACK_TO_WIKI'					=> 'Back to Wiki',
	'EDIT_ARTICLE_SUCCESS'			=> 'The article has been successfully edited.',
	'VERSIONS_OF_ARTICLE'			=> 'Version history',
	'VERSION_COMPARE_HEADLINE'	=> 'Changes from version <a href="%3$s">%1$d</a> to <a href="%4$s">%2$d</a>',
	'COMPARE'					=> 'Compare',
	'COMPARE_EXPLAIN'				=> 'All versions of the article are listed here. Choose two versions to compare them.',
	'VERSION'						=> 'Version',
	'TITLE'						=> 'Title',
	'REASON_EDIT'					=> 'Reason for change',
	'VIEW_DISCUSION'				=> 'Discussion',
	'TOPIC_ID'						=> 'Discussion topic ID',
	'CONFIRM_DELETE_VERSION'		=> 'Are you sure you want to delete the version?',
	'DELETE_VERSION_SUCCESS'		=> 'The version has been deleted successfully.',
	'WIKI_FOOTER'					=> 'Wiki by <a href="%1$s">%2$s</a>',

	'ACTIVATE_VERSION_SUCCESS'		=> 'The article version has been successfully set as active.',
	'ARTICLE_HAS_NEW'				=> 'A newer version of this article is available.',
	'ARTICLE_VIEWS_TEXT'			=> array(
		1 => 'This article has been viewed <strong>%d</strong> time.',
		2 => 'This article has been viewed <strong>%d</strong> times.',
	),
	'CONFIRM_ACTIVATE_VERSION'		=> 'Are you sure you want to set the selected version as the active version for this article?',
	'CONFIRM_DEACTIVATE_ARTICLE'	=> 'Are you sure you want to set the entire article to inactive?',
	'CONFIRM_DELETE_ARTICLE'		=> 'Are you sure you want to permanently delete this article <strong>and all its versions</strong>?',
	'DEACTIVATE_ARTICLE_SUCCESS'	=> 'The article has been set to inactive.',
	'DELETE_ARTICLE'				=> 'Delete article',
	'DELETE_ARTICLE_SUCCESS'		=> 'The article has been deleted successfully.',
	'DELETE_VERSION'				=> 'Delete version',
	'EDIT_ARTICLE_SUCCESS_INACTIVE'	=> 'The article has been successfully edited, but it still needs to be approved before it is publicly visible.',
	'INVALID_SOURCE_URL'			=> 'One of the sources is not a valid URL.',
	'IS_ACTIVE'						=> 'Active version',
	'NO_ARTICLE_DIFF'				=> 'There are no changes to this article.',
	'NO_DELETE_ACTIVE_VERSION'		=> 'You cannot delete the active version of an article.',
	'NO_SOURCE_DIFF'				=> 'The sources have not been changed.',
	'SET_ACTIVE'					=> 'Set active',
	'SET_INACTIV'					=> 'Set article inactive',
	'SET_REDIRECT'					=> 'Redirect article',
	'SET_REDIRECT_EXPLAIN'			=> 'Enter the article that this article should redirect to.',
	'SET_STICKY'					=> 'Article is important',
	'SOURCES'						=> 'Sources',
	'SOURCES_EXPLAIN'				=> 'Enter URLs as sources for the article. Write each URL on its own line.',
	'TOPIC_ID_EXPLAIN'				=> 'Enter the ID of the topic that serves as the discussion for this article.',
	'CONTRIBUTIONS_OF'				=> 'Contributions of %s',
	'CONTRIBUTIONS_EXPLAIN'			=> 'Every version %s has saved, newest first.',
	'CONTRIBUTIONS_ARTICLE'			=> 'Article',
	'CONTRIBUTIONS_SAVED'			=> 'Saved',
	'CONTRIBUTIONS_STATUS'			=> 'Status',
	'CONTRIBUTIONS_NONE'			=> 'There are no versions to show.',
	'CONTRIBUTION_LIVE'				=> 'Live version',
	'CONTRIBUTION_REPLACED'			=> 'Replaced by a newer version',
	'CONTRIBUTION_PENDING'			=> 'Awaiting approval or inactive',
	'WIKI_FILES'					=> 'Files',
	'WIKI_NO_FILES'					=> 'No files have been uploaded to this article.',
	'WIKI_UPLOAD_FILE'				=> 'Upload file',
	'WIKI_DELETE_FILE'				=> 'Delete file',
	'WIKI_FILE_UPLOADED'			=> 'The file “%s” has been uploaded.',
	'WIKI_FILE_EXISTS'				=> 'This article already has a file called “%s”. Delete it first or rename the new one.',
	'WIKI_FILE_DELETED'				=> 'The file “%s” has been deleted.',
	'WIKI_FILE_NOT_FOUND'			=> 'The file does not exist.',
	'WIKI_CONFIRM_DELETE_FILE'		=> 'Are you sure you want to delete the file “%s”?',
	'WIKI_FILE_MISSING'				=> 'The file “%s” has not been uploaded to this article.',
	'START_DISCUSSION'				=> 'Start discussion',
	'DISCUSSION_READ_ARTICLE'		=> 'Read the article',
	'DISCUSSION_NO_FORUM'			=> 'No discussion forum has been set up for the Wiki.',
	'TOTAL_ITEMS'		=>  array(
			1 => '1 entry',
			2 => '%s entries',
		),
));
