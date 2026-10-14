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
 * Tells the board's admins that the starter templates repository has new or changed
 * templates, from the check the board can switch on (see starter_templates::check()).
 * One notification per set of changes; it links to the Starter templates page in the ACP.
 */
class starter_templates extends \phpbb\notification\type\base
{
	protected $language_key = 'WIKI_NOTIFICATION_STARTER_TEMPLATES';
	protected $notify_icon = 'notify_phpbb';
	protected $permission = 'a_board';

	public static $notification_option = array(
		'lang'	=> 'WIKI_NOTIFICATION_STARTER_TEMPLATES_OPTION',
		'group'	=> 'NOTIFICATION_GROUP_ADMINISTRATION',
	);

	public function get_type()
	{
		return 'phpbbmodders.wiki.notification.type.starter_templates';
	}

	public function is_available()
	{
		return $this->auth->acl_get($this->permission);
	}

	public static function get_item_id($notification_data)
	{
		return (int) $notification_data['item_id'];
	}

	public static function get_item_parent_id($notification_data)
	{
		return 0;
	}

	/**
	 * Everyone who may change the board's settings, and so open the Starter templates page.
	 *
	 * @param array $notification_data
	 * @param array $options
	 * @return array
	 */
	public function find_users_for_notification($notification_data, $options = array())
	{
		$users = array();
		foreach ($this->auth->acl_get_list(false, $this->permission, false) as $forum => $permissions)
		{
			foreach ($permissions[$this->permission] ?? array() as $user_id)
			{
				$users[] = (int) $user_id;
			}
		}

		return $this->check_user_notification_options(array_unique($users), $options);
	}

	public function users_to_query()
	{
		return array();
	}

	public function get_title()
	{
		return $this->user->lang($this->language_key, (int) $this->get_data('count'));
	}

	public function get_url()
	{
		return append_sid("{$this->phpbb_root_path}adm/index.{$this->php_ext}", 'i=-phpbbmodders-wiki-acp-templates_module&amp;mode=settings');
	}

	/**
	 * Where opening the notification goes. A plain & rather than &amp;: phpBB 3.3 sends this
	 * through redirect(), which decodes &amp;, but phpBB 4.0 puts it straight into the
	 * redirect, where &amp; would reach the ACP as a parameter called "amp;mode".
	 *
	 * @return string
	 */
	public function get_redirect_url()
	{
		return append_sid("{$this->phpbb_root_path}adm/index.{$this->php_ext}", 'i=-phpbbmodders-wiki-acp-templates_module&mode=settings', false);
	}

	/**
	 * No email: the notice is for the board, and the check runs again in a few days anyway.
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
		$this->set_data('count', (int) $notification_data['count']);
		parent::create_insert_array($notification_data, $pre_create_data);
	}
}
