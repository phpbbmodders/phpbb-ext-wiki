<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace phpbbmodders\wiki\controller;

/**
 * "Everything this member has saved": every version they wrote, across all articles.
 */
class contributions
{
	/** Status of a version, as the page words it */
	const STATUS_LIVE = 'live';
	const STATUS_REPLACED = 'replaced';
	const STATUS_PENDING = 'pending';

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\pagination */
	protected $pagination;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string article_table */
	protected $article_table;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string php_ext */
	protected $php_ext;

	/**
	 * @param \phpbb\auth\auth					$auth
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\controller\helper			$helper
	 * @param \phpbb\pagination					$pagination
	 * @param \phpbb\request\request			$request
	 * @param \phpbb\template\template			$template
	 * @param \phpbb\user						$user
	 * @param string							$article_table
	 * @param string							$phpbb_root_path
	 * @param string							$php_ext
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\pagination $pagination, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, $article_table, $phpbb_root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->helper = $helper;
		$this->pagination = $pagination;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->article_table = $article_table;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * What a version is, in relation to its article's live version.
	 *
	 * @param int $approved       The version's article_approved flag (1 for the live version)
	 * @param int $last_edit      When the version was saved
	 * @param int $live_last_edit When the live version was saved, or 0 if the article has none
	 * @return string One of the STATUS_ constants
	 */
	public static function status($approved, $last_edit, $live_last_edit)
	{
		if ((int) $approved === 1)
		{
			return self::STATUS_LIVE;
		}

		// Older than what is live now: it was replaced. Anything else was never
		// approved, or its article is switched off.
		return ((int) $live_last_edit > (int) $last_edit) ? self::STATUS_REPLACED : self::STATUS_PENDING;
	}

	/**
	 * Show a member's contributions.
	 *
	 * @param string $username
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function view($username)
	{
		$this->user->add_lang_ext('phpbbmodders/wiki', 'common');

		if (!$this->auth->acl_get('u_wiki_view'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		include_once($this->phpbb_root_path . 'includes/functions_content.' . $this->php_ext);

		$member = $this->get_member($username);
		if (!$member)
		{
			trigger_error('NO_USER');
		}

		// Versions waiting for approval, or of switched-off articles, are for
		// moderators and for the author; everyone else sees live and replaced ones.
		$sees_hidden = $this->auth->acl_get('m_wiki_view_inactive') || (int) $member['user_id'] === (int) $this->user->data['user_id'];

		$where = 'a.article_user_id = ' . (int) $member['user_id'];
		if (!$sees_hidden)
		{
			$where .= ' AND (a.article_approved = 1 OR EXISTS (
				SELECT 1 FROM ' . $this->article_table . ' b
				WHERE b.article_url = a.article_url
					AND b.article_approved = 1
					AND b.article_last_edit > a.article_last_edit))';
		}

		$sql = 'SELECT COUNT(a.article_id) AS total
			FROM ' . $this->article_table . ' a
			WHERE ' . $where;
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$per_page = max(1, (int) $this->config['topics_per_page']);
		$start = $this->pagination->validate_start($this->request->variable('start', 0), $per_page, $total);

		$sql = 'SELECT a.article_id, a.article_title, a.article_url, a.article_last_edit, a.article_approved, a.article_edit_reason
			FROM ' . $this->article_table . ' a
			WHERE ' . $where . '
			ORDER BY a.article_last_edit DESC, a.article_id DESC';
		$result = $this->db->sql_query_limit($sql, $per_page, $start);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		$live = $this->get_live_times(array_unique(array_column($rows, 'article_url')));

		foreach ($rows as $row)
		{
			$status = self::status($row['article_approved'], $row['article_last_edit'], isset($live[$row['article_url']]) ? $live[$row['article_url']] : 0);

			$this->template->assign_block_vars('contributions', array(
				'ARTICLE_TITLE'		=> $row['article_title'],
				'EDIT_REASON'		=> $row['article_edit_reason'],
				'EDIT_TIME'			=> $this->user->format_date($row['article_last_edit']),
				'STATUS'			=> $this->user->lang('CONTRIBUTION_' . strtoupper($status)),
				'S_LIVE'			=> $status === self::STATUS_LIVE,
				'U_VERSION'			=> $this->helper->route('phpbbmodders_wiki_article', array('id' => $row['article_id'])),
				'U_ARTICLE'			=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $row['article_url'])),
			));
		}

		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $this->user->lang('WIKI'),
			'U_VIEW_FORUM'	=> $this->helper->route('phpbbmodders_wiki_index', array()),
		));

		$pagination_url = $this->helper->route('phpbbmodders_wiki_contributions', array('username' => htmlspecialchars_decode($member['username'], ENT_QUOTES)));
		$this->pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $total, $per_page, $start);

		$name = get_username_string('no_profile', $member['user_id'], $member['username'], $member['user_colour']);
		$this->template->assign_vars(array(
			'CONTRIBUTIONS_TITLE'	=> $this->user->lang('CONTRIBUTIONS_OF', $member['username']),
			'CONTRIBUTIONS_EXPLAIN_TEXT'	=> $this->user->lang('CONTRIBUTIONS_EXPLAIN', $name),
			'TOTAL_ITEMS'			=> $this->user->lang('TOTAL_ITEMS', $total),
		));

		return $this->helper->render('contributions.html', $this->user->lang('CONTRIBUTIONS_OF', $member['username']));
	}

	/**
	 * The member a page is asked for. Names are matched the way phpBB matches them
	 * at login, so case and look-alike characters do not matter.
	 *
	 * @param string $username
	 * @return array|false user_id, username, user_colour
	 */
	protected function get_member($username)
	{
		if (!function_exists('utf8_clean_string'))
		{
			include($this->phpbb_root_path . 'includes/utf/utf_tools.' . $this->php_ext);
		}

		$sql = 'SELECT user_id, username, user_colour
			FROM ' . USERS_TABLE . "
			WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'
				AND user_type <> " . USER_IGNORE;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	/**
	 * When the live version of each article was saved.
	 *
	 * @param string[] $urls Article URL names
	 * @return array URL name => timestamp; articles with no live version are left out
	 */
	protected function get_live_times(array $urls)
	{
		if (!$urls)
		{
			return array();
		}

		$sql = 'SELECT article_url, MAX(article_last_edit) AS live_last_edit
			FROM ' . $this->article_table . '
			WHERE article_approved = 1
				AND ' . $this->db->sql_in_set('article_url', $urls) . '
			GROUP BY article_url';
		$result = $this->db->sql_query($sql);

		$times = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$times[$row['article_url']] = (int) $row['live_last_edit'];
		}
		$this->db->sql_freeresult($result);

		return $times;
	}
}
