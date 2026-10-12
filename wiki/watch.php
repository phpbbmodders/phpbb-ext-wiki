<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace phpbbmodders\wiki\wiki;

/**
 * Watching an article: a signed-in member can ask to be told when the article's
 * live version changes. The notification itself is sent by the edit class when
 * a version goes live; this class keeps the list of who is watching.
 */
class watch
{
	/** Name of the link hash a Watch or Unwatch link carries */
	const LINK_HASH = 'wiki_watch';

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $watch_table;

	/** @var string */
	protected $article_table;

	/**
	 * @param \phpbb\auth\auth					$auth
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\controller\helper			$helper
	 * @param \phpbb\request\request			$request
	 * @param \phpbb\template\template			$template
	 * @param \phpbb\user						$user
	 * @param string							$watch_table
	 * @param string							$article_table
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, $watch_table, $article_table)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->watch_table = $watch_table;
		$this->article_table = $article_table;
	}

	/**
	 * Whether the current visitor is a signed-in member who may use the wiki.
	 *
	 * @return bool
	 */
	public function can_watch()
	{
		return (int) $this->user->data['user_id'] > ANONYMOUS
			&& empty($this->user->data['is_bot'])
			&& $this->auth->acl_get('u_wiki_view');
	}

	/**
	 * @param int    $user_id
	 * @param string $article_url
	 * @return bool
	 */
	public function is_watching($user_id, $article_url)
	{
		$sql = 'SELECT watch_id
			FROM ' . $this->watch_table . '
			WHERE user_id = ' . (int) $user_id . "
				AND article_url = '" . $this->db->sql_escape($article_url) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$found = $this->db->sql_fetchfield('watch_id') !== false;
		$this->db->sql_freeresult($result);

		return $found;
	}

	/**
	 * Start watching; does nothing when the member already is.
	 *
	 * @param int    $user_id
	 * @param string $article_url
	 * @return void
	 */
	public function add($user_id, $article_url)
	{
		if ((int) $user_id <= ANONYMOUS || $this->is_watching($user_id, $article_url))
		{
			return;
		}

		$this->db->sql_query('INSERT INTO ' . $this->watch_table . ' ' . $this->db->sql_build_array('INSERT', array(
			'user_id'		=> (int) $user_id,
			'article_url'	=> $article_url,
			'watch_time'	=> time(),
		)));
	}

	/**
	 * @param int    $user_id
	 * @param string $article_url
	 * @return void
	 */
	public function remove($user_id, $article_url)
	{
		$this->db->sql_query('DELETE FROM ' . $this->watch_table . '
			WHERE user_id = ' . (int) $user_id . "
				AND article_url = '" . $this->db->sql_escape($article_url) . "'");
	}

	/**
	 * The members watching an article.
	 *
	 * @param string $article_url
	 * @return int[] User ids
	 */
	public function get_watchers($article_url)
	{
		$sql = 'SELECT user_id
			FROM ' . $this->watch_table . "
			WHERE article_url = '" . $this->db->sql_escape($article_url) . "'";
		$result = $this->db->sql_query($sql);
		$ids = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $ids;
	}

	/**
	 * Forget everyone watching an article, as its deletion does.
	 *
	 * @param string $article_url
	 * @return void
	 */
	public function delete_for_article($article_url)
	{
		$this->db->sql_query('DELETE FROM ' . $this->watch_table . "
			WHERE article_url = '" . $this->db->sql_escape($article_url) . "'");
	}

	/**
	 * Forget what deleted members were watching.
	 *
	 * @param int[] $user_ids
	 * @return void
	 */
	public function delete_for_users(array $user_ids)
	{
		if ($user_ids)
		{
			$this->db->sql_query('DELETE FROM ' . $this->watch_table . ' WHERE ' . $this->db->sql_in_set('user_id', array_map('intval', $user_ids)));
		}
	}

	/**
	 * Put the Watch or Unwatch button on an article page.
	 *
	 * @param string $article_url
	 * @return void
	 */
	public function assign_to_template($article_url)
	{
		$can_watch = $this->can_watch();
		$watching = $can_watch && $this->is_watching($this->user->data['user_id'], $article_url);

		$this->template->assign_vars(array(
			'S_WIKI_CAN_WATCH'	=> $can_watch,
			'S_WIKI_WATCHING'	=> $watching,
			'U_WIKI_WATCH'		=> $can_watch ? $this->helper->route('phpbbmodders_wiki_article', array(
				'article'	=> $article_url,
				'action'	=> $watching ? 'unwatch' : 'watch',
				'hash'		=> generate_link_hash(self::LINK_HASH),
			)) : '',
		));
	}

	/**
	 * Handle a Watch or Unwatch click.
	 *
	 * @param string $article_url
	 * @param bool   $watching    True to watch, false to stop
	 * @return void
	 */
	public function handle($article_url, $watching)
	{
		if (!$this->can_watch())
		{
			trigger_error('NOT_AUTHORISED');
		}

		if (!check_link_hash($this->request->variable('hash', ''), self::LINK_HASH))
		{
			trigger_error('FORM_INVALID');
		}

		$sql = 'SELECT article_id
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article_url) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = $this->db->sql_fetchfield('article_id') !== false;
		$this->db->sql_freeresult($result);

		if (!$exists)
		{
			trigger_error('NO_ARTICLE');
		}

		$user_id = (int) $this->user->data['user_id'];
		if ($watching)
		{
			$this->add($user_id, $article_url);
		}
		else
		{
			$this->remove($user_id, $article_url);
		}

		$back = $this->helper->route('phpbbmodders_wiki_article', array('article' => $article_url));
		meta_refresh(3, $back);
		trigger_error($this->user->lang($watching ? 'WIKI_NOW_WATCHING' : 'WIKI_NO_LONGER_WATCHING') . '<br /><br /><a href="' . $back . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
	}
}
