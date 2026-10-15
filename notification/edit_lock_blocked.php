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
 * Tells the wiki's moderators that an article's edit lock kept someone out, so they can
 * break it if the editor who holds it has gone. Sent once per lock, not each time someone
 * tries (see wiki\edit_lock::mark_blocked_notified()). Only when the board locks articles.
 */
class edit_lock_blocked extends \phpbb\notification\type\base
{
	protected $language_key = 'WIKI_EDIT_LOCK_BLOCKED';
	protected $notify_icon = 'notify_phpbb';
	protected $permission = 'u_wiki_set_active';

	public static $notification_option = array(
		'lang'	=> 'WIKI_EDIT_LOCK_BLOCKED_OPTION',
		'group'	=> 'NOTIFICATION_GROUP_MISCELLANEOUS',
	);

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\user_loader */
	protected $user_loader;

	/**
	 * Set the user loader (injected by the service definition; see article_edit for why
	 * there is no constructor).
	 *
	 * @param \phpbb\user_loader $user_loader
	 * @return void
	 */
	public function set_user_loader(\phpbb\user_loader $user_loader)
	{
		$this->user_loader = $user_loader;
	}

	/**
	 * Set the controller helper (injected by the service definition).
	 *
	 * @param \phpbb\controller\helper $helper
	 * @return void
	 */
	public function set_helper(\phpbb\controller\helper $helper)
	{
		$this->helper = $helper;
	}

	public function get_type()
	{
		return 'phpbbmodders.wiki.notification.type.edit_lock_blocked';
	}

	public function is_available()
	{
		return $this->auth->acl_get($this->permission);
	}

	/**
	 * One item per lock: the article, its holder and when they took it.
	 *
	 * @param array $notification_data
	 * @return int
	 */
	public static function get_item_id($notification_data)
	{
		return (int) (crc32($notification_data['article_url'] . '|' . $notification_data['holder_id'] . '|' . $notification_data['lock_time']) & 0x7fffffff);
	}

	public static function get_item_parent_id($notification_data)
	{
		return 0;
	}

	/**
	 * The moderators, other than the editor who was kept out and the one holding the lock.
	 *
	 * @param array $notification_data
	 * @param array $options
	 * @return array
	 */
	public function find_users_for_notification($notification_data, $options = array())
	{
		$users = $this->auth->acl_get_list(false, $this->permission);
		$users = !empty($users[0][$this->permission]) ? array_unique(array_map('intval', $users[0][$this->permission])) : array();
		// phpBB's ignore_users option leaves out delivery methods, not whole users, so they go here
		$users = array_values(array_diff($users, array((int) $notification_data['user_id'], (int) $notification_data['holder_id'])));

		return $this->check_user_notification_options($users, $options);
	}

	public function users_to_query()
	{
		return array($this->get_data('user_id'), $this->get_data('holder_id'));
	}

	public function get_avatar()
	{
		return $this->user_loader->get_avatar($this->get_data('user_id'), true, true);
	}

	public function get_title()
	{
		return $this->language->lang(
			$this->language_key,
			$this->user_loader->get_username($this->get_data('user_id'), 'no_profile'),
			$this->get_data('article_title'),
			$this->user_loader->get_username($this->get_data('holder_id'), 'no_profile')
		);
	}

	/**
	 * The article's edit page, which offers moderators the button to break the lock.
	 *
	 * @return string
	 */
	public function get_url()
	{
		return $this->helper->route('phpbbmodders_wiki_article', array('article' => $this->get_data('article_url'), 'action' => 'edit'));
	}

	/**
	 * No email: a lock runs out by itself within the minutes the board set.
	 *
	 * @return false
	 */
	public function get_email_template()
	{
		return false;
	}

	public function get_email_template_variables()
	{
		return array();
	}

	public function create_insert_array($notification_data, $pre_create_data = array())
	{
		foreach (array('article_url', 'article_title', 'user_id', 'holder_id', 'lock_time') as $key)
		{
			$this->set_data($key, $notification_data[$key]);
		}
		parent::create_insert_array($notification_data, $pre_create_data);
	}
}
