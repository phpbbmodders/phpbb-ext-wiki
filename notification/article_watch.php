<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace phpbbmodders\wiki\notification;

/**
 * Tells the members watching an article that a new version of it went live.
 *
 * Unlike the "awaiting approval" notice, which goes to moderators, this one goes
 * to any member who chose to watch the article and may still view the wiki. The
 * member who saved the new version is never told about their own edit.
 */
class article_watch extends article_edit
{
	protected $language_key = 'WIKI_WATCHED_ARTICLE_LIVE';

	protected $permission = 'u_wiki_view';

	public static $notification_option = array(
		'lang'	=> 'WIKI_WATCHED_ARTICLE',
		'group'	=> 'NOTIFICATION_GROUP_MISCELLANEOUS',
	);

	/** @var \phpbbmodders\wiki\wiki\watch */
	protected $watch;

	/**
	 * @param \phpbbmodders\wiki\wiki\watch $watch
	 * @return void
	 */
	public function set_watch(\phpbbmodders\wiki\wiki\watch $watch)
	{
		$this->watch = $watch;
	}

	/**
	 * Who is told: the article's watchers who may view the wiki, minus the editor.
	 *
	 * @param array $notification_data article_id, article_title, article_url, user_id (the editor)
	 * @param array $options
	 * @return array
	 */
	public function find_users_for_notification($notification_data, $options = array())
	{
		$options = array_merge(array(
			'ignore_users'	=> array(),
		), $options);

		$watchers = array_diff($this->watch->get_watchers($notification_data['article_url']), array((int) $notification_data['user_id']));
		if (!$watchers)
		{
			return array();
		}

		$allowed = $this->auth->acl_get_list($watchers, $this->permission);
		$users = !empty($allowed[0][$this->permission]) ? $allowed[0][$this->permission] : array();

		return $this->check_user_notification_options(array_unique($users), $options);
	}

	/**
	 * @return string
	 */
	public function get_type()
	{
		return 'phpbbmodders.wiki.notification.type.article_watch';
	}

	/**
	 * @return string
	 */
	public function get_email_template()
	{
		return '@phpbbmodders_wiki/mail_article_watch';
	}
}
