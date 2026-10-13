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
namespace phpbbmodders\wiki\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbbmodders\wiki\wiki\watch */
	protected $watch;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\notification\manager */
	protected $notification_manager;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth				$auth					Auth object
	* @param \phpbb\config\config			$config					Config object
	* @param \phpbb\controller\helper		$helper					Controller helper object
	* @param \phpbb\notification\manager	$notification_manager	Notification Manager
	* @param \phpbb\template				$template				Template object
	* @param \phpbb\user					$user					User object
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\notification\manager $notification_manager, \phpbb\template\template $template, \phpbb\user $user, \phpbbmodders\wiki\wiki\watch $watch)
	{
		$this->watch = $watch;
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->notification_manager = $notification_manager;
		$this->template = $template;
		$this->user = $user;
	}

	public static  function getSubscribedEvents()
	{
		return array(
			'core.page_header'			=> 'page_header',
			'core.permissions'			=> 'permissions',
			'core.user_setup'			=> 'user_setup',
			'core.delete_user_after'	=> 'delete_user_after',
		);
	}

	/**
	 * Add permissions
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function permissions($event)
	{
		$permissions = $event['permissions'];
		$permissions += array(
			'u_wiki_view'			=> array(
				'lang'		=> 'ACL_U_WIKI_VIEW',
				'cat'		=> 'wiki'
			),
			'u_wiki_edit'			=> array(
				'lang'		=> 'ACL_U_WIKI_EDIT',
				'cat'		=> 'wiki'
			),
			'u_wiki_versions'		=> array(
				'lang'		=> 'ACL_U_WIKI_VERSIONS',
				'cat'		=> 'wiki'
			),
			'u_wiki_edit_topic'		=> array(
				'lang'		=> 'ACL_U_WIKI_EDIT_TOPIC',
				'cat'		=> 'wiki'
			),
			'u_wiki_upload'			=> array(
				'lang'		=> 'ACL_U_WIKI_UPLOAD',
				'cat'		=> 'wiki'
			),
			'u_wiki_set_sticky'		=> array(
				'lang'		=> 'ACL_U_WIKI_SET_STICKY',
				'cat'		=> 'wiki'
			),
			'u_wiki_set_redirect'		=> array(
				'lang'		=> 'ACL_U_WIKI_SET_REDIRECT',
				'cat'		=> 'wiki'
			),
			'u_wiki_set_active'		=> array(
				'lang'		=> 'ACL_U_WIKI_SET_ACTIVE',
				'cat'		=> 'wiki'
			),
			'm_wiki_delete'			=> array(
				'lang'		=> 'ACL_M_WIKI_DELETE',
				'cat'		=> 'wiki'
			),
			'm_wiki_delete_article'	=> array(
				'lang'		=> 'ACL_M_WIKI_DELETE_ARTICLE',
				'cat'		=> 'wiki'
			),
			'm_wiki_view_inactive'	=> array(
				'lang'		=> 'ACL_M_WIKI_VIEW_INACTIVE',
				'cat'		=> 'wiki'
			),
		);
		$event['permissions'] = $permissions;
		$categories['wiki'] = 'ACL_CAT_WIKI';
		$event['categories'] = array_merge($event['categories'], $categories);
	}

	/**
	 * Add notification
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function notification_add($event)
	{
		if (!$this->config['email_enable'])
		{
			return;
		}
		$notifications_data = array(
			array(
				'item_type'		=> 'phpbbmodders.wiki.notification.type.article_edit',
				'method'		=> 'notification.method.email',
			),
		);
		foreach ($notifications_data as $subscription)
		{
			$this->notification_manager->add_subscription($subscription['item_type'], 0, $subscription['method'], $event['user_id']);
		}
	}

	/**
	 * Add link to header
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function page_header($event)
	{
		if ($this->auth->acl_get('u_wiki_view'))
		{
			$this->user->add_lang_ext('phpbbmodders/wiki', 'common');
			$feed_setting = isset($this->config['wiki_feed_format']) ? $this->config['wiki_feed_format'] : \phpbbmodders\wiki\wiki\feed::BOTH;
			$this->template->assign_vars(array(
				'U_WIKI'	=> $this->helper->route('phpbbmodders_wiki_index', array()),
				'U_WIKI_RECENT_CHANGES'	=> $this->helper->route('phpbbmodders_wiki_recent_changes', array()),
				'U_WIKI_TEMPLATES'		=> $this->helper->route('phpbbmodders_wiki_templates', array()),
				'U_WIKI_CATEGORIES'		=> $this->helper->route('phpbbmodders_wiki_categories', array()),
				'U_WIKI_FEED_RSS'		=> \phpbbmodders\wiki\wiki\feed::offers($feed_setting, \phpbbmodders\wiki\wiki\feed::RSS) ? $this->helper->route('phpbbmodders_wiki_recent_changes_feed', array('_format' => 'rss')) : '',
				'U_WIKI_FEED_ATOM'		=> \phpbbmodders\wiki\wiki\feed::offers($feed_setting, \phpbbmodders\wiki\wiki\feed::ATOM) ? $this->helper->route('phpbbmodders_wiki_recent_changes_feed', array('_format' => 'atom')) : '',
				'WIKI_ICON_CLASSES'	=> \phpbbmodders\wiki\wiki\icon::classes(isset($this->config['wiki_icon']) ? $this->config['wiki_icon'] : \phpbbmodders\wiki\wiki\icon::DEFAULT_ICON),
			));
		}
	}

	/**
	 * Add language file
	 *
	 * @param	object	$event	The event object
	 * @return	null
	 * @access	public
	 */
	public function user_setup($event)
	{
		$lang_ary = $event['lang_set_ext'];
		$lang_ary[] = array(
			'ext_name'		=> 'phpbbmodders/wiki',
			'lang_set'		=> 'link',
		);
		$event['lang_set_ext'] = $lang_ary;
	}


	/**
	 * A deleted member no longer watches anything.
	 *
	 * @param \phpbb\event\data $event The event object
	 * @return void
	 */
	public function delete_user_after($event)
	{
		$this->watch->delete_for_users(array_map('intval', (array) $event['user_ids']));
	}
}
