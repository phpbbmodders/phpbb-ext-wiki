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
namespace phpbbmodders\wiki\wiki;

class compare
{

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

	/** @var string $article_table */
	protected $article_table;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string php_ext */
	protected $php_ext;

	/**
	 * A version's text rendered as the article page shows it.
	 *
	 * @param string $article The article's name, as in its address
	 * @param array $row The version, with article_text, bbcode_uid and bbcode_bitfield
	 * @return string HTML
	 */
	protected function rendered($article, array $row)
	{
		// [[File:name]] and the PAGENAME words refer to this article; categories found
		// while rendering are not this page's to list
		\phpbbmodders\wiki\wiki\files::set_context($article);
		\phpbbmodders\wiki\wiki\categories::reset();
		try
		{
			return generate_text_for_display($row['article_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);
		}
		finally
		{
			\phpbbmodders\wiki\wiki\files::set_context(null);
			\phpbbmodders\wiki\wiki\categories::reset();
		}
	}

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth						$auth				Auth object
	* @param \phpbb\config\config					$config				Config object
	* @param \phpbb\db\driver\driver_interface		$db					Database object
	* @param \phpbb\controller\helper				$helper				Controller helper object
	* @param \phpbb\pagination						$pagination			Pagination object
	* @param \phpbb\request\request					$request			Request object
	* @param \phpbb\template\template				$template			Template object
	* @param \phpbb\user							$user				User object
	* @param string									$article_table
	* @param string									$phpbb_root_path
	* @param string									$php_ext
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

	public function compare_versions($article, $from, $to)
	{
		// Comparing shows the text of versions, as the version history does
		if (!$this->auth->acl_get('u_wiki_versions'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		if ($from == 0 || $to == 0)
		{
			trigger_error('NO_VERSIONS_SELECTED');
		}

		require($this->phpbb_root_path . 'includes/diff/diff.' . $this->php_ext);
		require($this->phpbb_root_path . 'includes/diff/engine.' . $this->php_ext);
		require($this->phpbb_root_path . 'includes/diff/renderer.' . $this->php_ext);

		$sql = 'SELECT article_url, article_text, bbcode_uid, bbcode_bitfield, article_sources, article_description, article_approved
			FROM ' . $this->article_table . '
			WHERE article_id = ' . (int) $from;
		$result = $this->db->sql_query($sql);
		$from_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$sql = 'SELECT article_url, article_text, bbcode_uid, bbcode_bitfield, article_sources, article_description, article_approved
			FROM ' . $this->article_table . '
			WHERE article_id = ' . (int) $to;
		$result = $this->db->sql_query($sql);
		$to_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		// Both versions must exist and belong to the article in the address
		if (!$from_row || !$to_row || $from_row['article_url'] !== $article || $to_row['article_url'] !== $article)
		{
			trigger_error('NO_VERSIONS_SELECTED');
		}

		$from_article = generate_text_for_edit($from_row['article_text'], $from_row['bbcode_uid'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);
		$to_article = generate_text_for_edit($to_row['article_text'], $to_row['bbcode_uid'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);
		$u_from = $this->helper->route('phpbbmodders_wiki_index', array('id' => $from));
		$u_to = $this->helper->route('phpbbmodders_wiki_index', array('id' => $to));

		$article_diff = new \diff($from_article['text'], $to_article['text']);
		$article_diff_empty = $article_diff->is_empty();

		$sources_diff = new \diff($from_row['article_sources'], $to_row['article_sources']);
		$sources_diff_empty = $sources_diff->is_empty();

		$description_diff = new \diff($from_row['article_description'], $to_row['article_description']);
		$description_diff_empty = $description_diff->is_empty();

		$renderer = new \diff_renderer_inline();

		// The readable view compares the versions as readers see them. A template page's
		// text does not make sense rendered on its own, so it is compared as written.
		$s_readable_possible = (strpos($article, \phpbbmodders\wiki\wiki\templates::PREFIX) !== 0);
		$s_readable = $s_readable_possible && ($this->request->variable('view', 'readable') !== 'source');
		$readable = '';
		if ($s_readable && !$article_diff_empty)
		{
			$readable = \phpbbmodders\wiki\wiki\readable_diff::render($this->rendered($article, $from_row), $this->rendered($article, $to_row));
		}

		$s_set_active = $this->auth->acl_get('u_wiki_set_active');
		$s_delete = $this->auth->acl_get('m_wiki_delete');

		$this->template->assign_vars(array(
			'HEADLINE'			=> sprintf($this->user->lang('VERSION_COMPARE_HEADLINE'), $from, $to, $u_from, $u_to),
			'DIFF'				=> ($article_diff_empty) ? '' : ($s_readable ? $readable : $renderer->get_diff_content($article_diff)),
			'DIFF_SOURCE'		=> ($sources_diff_empty) ? '' : $renderer->get_diff_content($sources_diff),
			'DIFF_DESCRIPTION'	=> ($description_diff_empty) ? '' : $renderer->get_diff_content($description_diff),
			'S_READABLE'			=> $s_readable,
			'S_READABLE_POSSIBLE'	=> $s_readable_possible,
			'U_VIEW_READABLE'		=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'compare', 'from' => (int) $from, 'to' => (int) $to)),
			'U_VIEW_SOURCE'			=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'compare', 'from' => (int) $from, 'to' => (int) $to, 'view' => 'source')),

			'APPROVE_VERSION_FROM'	=> sprintf($this->user->lang('APPROVE_VERSION'), $from),
			'REJECT_VERSION_FROM'	=> sprintf($this->user->lang('REJECT_VERSION'), $from),
			'S_SET_ACTIVE_FROM'	=> $s_set_active && !$from_row['article_approved'],
			'S_DELETE_FROM'		=> $s_delete && !$from_row['article_approved'],
			'U_SET_ACTIVE_FROM'	=> $this->helper->route('phpbbmodders_wiki_article', array('action' => 'active', 'id' => $from)),
			'U_DELETE_FROM'		=> $this->helper->route('phpbbmodders_wiki_article', array('action' => 'delete', 'id' => $from)),

			'APPROVE_VERSION_TO'	=> sprintf($this->user->lang('APPROVE_VERSION'), $to),
			'REJECT_VERSION_TO'	=> sprintf($this->user->lang('REJECT_VERSION'), $to),
			'S_SET_ACTIVE_TO'	=> $s_set_active && !$to_row['article_approved'],
			'S_DELETE_TO'		=> $s_delete && !$to_row['article_approved'],
			'U_SET_ACTIVE_TO'	=> $this->helper->route('phpbbmodders_wiki_article', array('action' => 'active', 'id' => $to)),
			'U_DELETE_TO'		=> $this->helper->route('phpbbmodders_wiki_article', array('action' => 'delete', 'id' => $to)),

			'U_BACK'			=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'versions')),
		));

		return $this->helper->render('article_compare.html', $this->user->lang('VERSIONS_OF_ARTICLE'));
	}


	public function view_versions($article)
	{
		if (!$this->auth->acl_get('u_wiki_versions'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$sql = 'SELECT *
			FROM ' . $this->article_table . '
			WHERE article_url = \'' . $this->db->sql_escape($article) . '\'
			ORDER BY article_last_edit DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$this->data = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		$this->template->assign_vars(array(
			'ARTICLE_TITLE'			=> $this->data['article_title'],
			'S_SET_ACTIVE'			=> $this->auth->acl_get('u_wiki_set_active'),
			'S_DELETE'				=> $this->auth->acl_get('m_wiki_delete'),
			'S_RESTORE'				=> $this->auth->acl_get('u_wiki_edit'),
			'S_DELETE_ARTICLE'		=> $this->auth->acl_get('m_wiki_delete_article'),
			'S_MOVE'				=> $this->auth->acl_get('u_wiki_move'),
			'U_MOVE'				=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'move')),
			'U_ACTION'				=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'compare')),
			'U_DELETE_ARTICLE'		=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'detele_article')),
			'U_SET_INACTIV'			=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'deactivate')),
		));

		if (!empty($article))
		{
			$this->template->assign_block_vars('navlinks', array(
				'FORUM_NAME'	=> $this->data['article_title'],
				'U_VIEW_FORUM'	=> $this->helper->route('phpbbmodders_wiki_article', array('article'	=> $article)),
			));
		}

		$start = $this->request->variable('start', 0);

		$sql_array = array(
			'SELECT'		=> 'a.article_id, a.article_title, a.article_last_edit, a.article_approved, a.article_edit_reason, u.user_id, u.username, u.user_colour',
			'FROM'		=> array($this->article_table => 'a'),
			'LEFT_JOIN'	=> array(
				array(
					'FROM'	=> array(USERS_TABLE => 'u'),
					'ON'	=> 'u.user_id = a.article_user_id'
				)
			),
			'WHERE'		=> 'article_url = \'' . $this->db->sql_escape($article) . '\'',
			'ORDER_BY'	=> 'a.article_last_edit DESC',
		);

		$sql = $this->db->sql_build_query('SELECT', $sql_array);
		$result = $this->db->sql_query_limit($sql, $this->config['topics_per_page'], $start);
		$result2 = $this->db->sql_query($sql);
		$row2 = $this->db->sql_fetchrowset($result2);
		$total_count = (int) sizeof($row2);
		$this->db->sql_freeresult($result2);
		unset($row2);

		$i = 1;
		$has_active_version = false;
		while ($this->data = $this->db->sql_fetchrow($result))
		{
			if (!$has_active_version)
			{
				$has_active_version = ($this->data['article_approved'] == 1) ? true : false;
			}
			$this->template->assign_block_vars('version_list', array(
				'ID'				=> $this->data['article_id'],
				'NR'				=> $i++,
				'ARTICLE_TITLE'		=> $this->data['article_title'],
				'EDIT_REASON'		=> $this->data['article_edit_reason'],
				'S_ACTIVE'			=> ($this->data['article_approved'] == 1) ? true : false,
				'USER'				=> \phpbbmodders\wiki\wiki\author::link($this->helper, $this->data['user_id'], $this->data['username'], $this->data['user_colour']),
				'EDIT_TIME'			=> $this->user->format_date($this->data['article_last_edit']),
				'U_VERSION'			=> $this->helper->route('phpbbmodders_wiki_article', array('id' => $this->data['article_id'])),
				'U_DELETE'			=> $this->helper->route('phpbbmodders_wiki_article', array('action' => 'delete', 'id' => $this->data['article_id'])),
				'U_SET_ACTIVE'		=> $this->helper->route('phpbbmodders_wiki_article', array('action' => 'active', 'id' => $this->data['article_id'])),
				'U_RESTORE'			=> $this->helper->route('phpbbmodders_wiki_article', array('action' => 'restore', 'id' => $this->data['article_id'])),
			));
		}
		$this->db->sql_freeresult($result);

		// No active versions and no right to view inactive articles
		if (!$has_active_version && !$this->auth->acl_get('m_wiki_view_inactive'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$pagination_url = $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action'	=> 'versions'));
		$start = $this->pagination->validate_start($start, $this->config['topics_per_page'], $total_count);
		$this->pagination->generate_template_pagination($pagination_url, 'pagination', 'start', $total_count, $this->config['topics_per_page'], $start);

		$this->template->assign_vars(array(
			'TOTAL_ITEMS'			=> $this->user->lang('TOTAL_ITEMS', (int) $total_count),
		));

		return $this->helper->render('article_versions.html', $this->user->lang('VERSIONS_WIKI'));
	}
}
