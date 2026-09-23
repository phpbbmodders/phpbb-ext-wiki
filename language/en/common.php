<?php
/**
*
* @package phpBB Extension - Wiki
 * @copyright (c) 2015 tas2580 (https://tas2580.net)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
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
	'REJECT'						=> 'Reject',
	'REVIEW'						=> 'Review',
	'APPROVE_VERSION'				=> 'Approve version %d',
	'REJECT_VERSION'				=> 'Reject version %d',
	'PENDING_OLDER_THAN_ACTIVE'		=> 'Older than the live version &ndash; approving this would replace newer content. Review the version history before approving.',
	'ARTICLE_DESCRIPTION'			=> 'Description',
	'ARTICLE_DESCRIPTION_EXPLAIN'	=> 'A short summary shown in the article listing.',
	'NEW_ARTICLE_TITLE'			=> 'New article title',
	'GO_TO_ARTICLE'					=> 'Create',
	'NO_ARTICLE_REDIRECT'			=> '<strong>Article has been replaced!</strong><br><br> The requested article no longer exists. <br> It has been replaced with a new article: <a href="%1$s">%1$s</a>.<br><br>',
	'LAST_EDIT'					=> 'Last modified',
	'EDIT_WIKI'					=> 'Edit article',
	'VERSIONS_WIKI'				=> 'View versions',
	'WIKI'							=> 'Wiki',
	'BACK_TO_ARTICLE'				=> 'Back to article',
	'BACK_TO_WIKI'					=> 'Back to Wiki',
	'EDIT_ARTICLE_SUCCESS'			=> 'The article has been successfully edited',
	'VERSIONS_OF_ARTICLE'			=> 'Version history',
	'VERSION_COMPARE_HEADLINE'	=> 'Diff of version <a href="%3$s">%1$d</a> to <a href="%4$s">%2$d</a>',
	'COMPARE'					=> 'Compare',
	'COMPARE_EXPLAIN'				=> 'Here will be listed all versions of the article. Choose from two versions to compare them.',
	'VERSION'						=> 'Version',
	'TITLE'						=> 'Title',
	'REASON_EDIT'					=> 'Reason for change',
	'VIEW_DISCUSION'				=> 'Discussion',
	'TOPIC_ID'						=> 'Discussion topic ID',
	'CONFIRM_DELETE_VERSION'		=> 'Are you sure you want to delete the version?',
	'DELETE_VERSION_SUCCESS'		=> 'The version has been deleted successfully',
	'WIKI_FOOTER'					=> 'Wiki by <a href="%1$s">%2$s</a>',

	'ACTIVATE_VERSION_SUCCESS'		=> 'The article version has been successfully set as active.',
	'ARTICLE_HAS_NEW'				=> 'A newer version of this article is available.',
	'ARTICLE_VIEWS_TEXT'			=> 'This article has been viewed <strong>%d</strong> times.',
	'CONFIRM_ACTIVATE_VERSION'		=> 'Are you sure you want to set the selected version as the active version for this article?',
	'CONFIRM_DEACTIVATE_ARTICLE'	=> 'Are you sure you want to set the entire article to inactive?',
	'CONFIRM_DELETE_ARTICLE'		=> 'Are you sure you want to permanently delete this article <strong>and all its versions</strong>?',
	'DEACTIVATE_ARTICLE_SUCCESS'	=> 'The article has been set to inactive!',
	'DELETE_ARTICLE'				=> 'Delete article',
	'DELETE_ARTICLE_SUCCESS'		=> 'The article has been deleted successfully!',
	'DELETE_VERSION'				=> 'Delete version',
	'EDIT_ARTICLE_SUCCESS_INACTIVE'	=> 'The article has been successfully edited, but it still needs to be approved before it is publicly visible.',
	'INVALID_SOURCE_URL'			=> 'One of the sources is not a valid URL.',
	'IS_ACTIVE'						=> 'Active version',
	'NO_ARTICLE_DIFF'				=> 'There are no changes to this article.',
	'NO_DELETE_ACTIVE_VERSION'		=> 'You cannot delete the active version of an article!',
	'NO_SOURCE_DIFF'				=> 'The sources have not been changed.',
	'SET_ACTIVE'					=> 'Set active',
	'SET_INACTIV'					=> 'Set article inactive',
	'SET_REDIRECT'					=> 'Redirect article',
	'SET_REDIRECT_EXPLAIN'			=> 'Enter another article here to redirect this article to it.',
	'SET_STICKY'					=> 'Article is important',
	'SOURCES'						=> 'Sources',
	'SOURCES_EXPLAIN'				=> 'Enter URLs as sources for the article. Write each URL on its own line.',
	'TOPIC_ID_EXPLAIN'				=> 'Enter the ID of the topic that serves as the discussion for this article.',
	'TOTAL_ITEMS'		=>  array(
			1 => '1 entry',
			2 => '%s entries',
		),
));
