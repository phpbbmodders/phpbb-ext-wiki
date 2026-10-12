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

use phpbbmodders\wiki\wiki\feed;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Recent changes: every version saved to the wiki that is or was live, newest
 * first, as a page and as an RSS or Atom feed.
 *
 * Versions waiting for approval, and versions of articles with no live version,
 * never appear: the page and the feed are public, and those are not.
 */
class recent_changes
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

	/** @var string */
	protected $article_table;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
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
	 * The feed formats the ACP offers.
	 *
	 * @return string The feed class's RSS, ATOM or BOTH
	 */
	protected function setting()
	{
		return isset($this->config['wiki_feed_format']) ? $this->config['wiki_feed_format'] : feed::BOTH;
	}

	/**
	 * SQL condition for the versions anyone may see: the live one, and the ones a
	 * newer live version replaced.
	 *
	 * @return string
	 */
	protected function visible_where()
	{
		return '(a.article_approved = 1 OR EXISTS (
			SELECT 1 FROM ' . $this->article_table . ' b
			WHERE b.article_url = a.article_url
				AND b.article_approved = 1
				AND b.article_last_edit > a.article_last_edit))';
	}

	/**
	 * Versions, newest first.
	 *
	 * @param int $limit
	 * @param int $start
	 * @return array Rows with the editor's name
	 */
	protected function get_changes($limit, $start = 0)
	{
		$sql = 'SELECT a.article_id, a.article_title, a.article_url, a.article_last_edit, a.article_approved, a.article_edit_reason,
				u.user_id, u.username, u.user_colour
			FROM ' . $this->article_table . ' a
			LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = a.article_user_id)
			WHERE ' . $this->visible_where() . '
			ORDER BY a.article_last_edit DESC, a.article_id DESC';
		$result = $this->db->sql_query_limit($sql, $limit, $start);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * The recent changes page.
	 *
	 * @return Response
	 */
	public function page()
	{
		$this->user->add_lang_ext('phpbbmodders/wiki', 'common');

		if (!$this->auth->acl_get('u_wiki_view'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		include_once($this->phpbb_root_path . 'includes/functions_content.' . $this->php_ext);

		$sql = 'SELECT COUNT(a.article_id) AS total
			FROM ' . $this->article_table . ' a
			WHERE ' . $this->visible_where();
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$per_page = max(1, (int) $this->config['topics_per_page']);
		$start = $this->pagination->validate_start($this->request->variable('start', 0), $per_page, $total);

		$rows = $this->get_changes($per_page, $start);
		$live = $this->get_live_times(array_unique(array_column($rows, 'article_url')));

		foreach ($rows as $row)
		{
			$status = contributions::status($row['article_approved'], $row['article_last_edit'], isset($live[$row['article_url']]) ? $live[$row['article_url']] : 0);

			$this->template->assign_block_vars('changes', array(
				'ARTICLE_TITLE'		=> $row['article_title'],
				'EDIT_REASON'		=> $row['article_edit_reason'],
				'EDIT_TIME'			=> $this->user->format_date($row['article_last_edit']),
				'USER'				=> \phpbbmodders\wiki\wiki\author::link($this->helper, $row['user_id'], $row['username'], $row['user_colour']),
				'STATUS'			=> $this->user->lang('CONTRIBUTION_' . strtoupper($status)),
				'U_VERSION'			=> $this->helper->route('phpbbmodders_wiki_article', array('id' => $row['article_id'])),
				'U_ARTICLE'			=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $row['article_url'])),
				'S_LIVE'			=> $status === contributions::STATUS_LIVE,
			));
		}

		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $this->user->lang('WIKI'),
			'U_VIEW_FORUM'	=> $this->helper->route('phpbbmodders_wiki_index', array()),
		));

		$this->pagination->generate_template_pagination($this->helper->route('phpbbmodders_wiki_recent_changes', array()), 'pagination', 'start', $total, $per_page, $start);

		$this->template->assign_vars(array(
			'TOTAL_ITEMS'	=> $this->user->lang('TOTAL_ITEMS', $total),
		));

		return $this->helper->render('recent_changes.html', $this->user->lang('WIKI_RECENT_CHANGES'));
	}

	/**
	 * The feed, when its format is offered.
	 *
	 * @param string $_format feed::RSS or feed::ATOM
	 * @return Response
	 */
	public function feed($_format)
	{
		$this->user->add_lang_ext('phpbbmodders/wiki', 'common');

		// A feed reader is a guest, so the guest's right to read the wiki decides
		if (!$this->auth->acl_get('u_wiki_view'))
		{
			return new Response($this->user->lang('NOT_AUTHORISED'), 403, array('Content-Type' => 'text/plain; charset=UTF-8'));
		}

		if (!feed::offers($this->setting(), $_format))
		{
			return new Response($this->user->lang('PAGE_NOT_FOUND'), 404, array('Content-Type' => 'text/plain; charset=UTF-8'));
		}

		$rows = $this->get_changes(feed::LIMIT);
		$entries = array();
		foreach ($rows as $row)
		{
			$link = $this->helper->route('phpbbmodders_wiki_article', array('id' => $row['article_id']), false, false, UrlGeneratorInterface::ABSOLUTE_URL);
			$summary = $this->user->lang('WIKI_FEED_SAVED_BY', $row['username'] === null ? '' : $row['username']);
			if ($row['article_edit_reason'] !== '')
			{
				$summary .= ' ' . $this->user->lang('WIKI_FEED_REASON', $row['article_edit_reason']);
			}

			$entries[] = array(
				'id'		=> $link,
				'title'		=> $row['article_title'],
				'link'		=> $link,
				'summary'	=> $summary,
				'author'	=> $row['username'] === null ? '' : $row['username'],
				'time'		=> (int) $row['article_last_edit'],
			);
		}

		$meta = array(
			'title'			=> $this->user->lang('WIKI_FEED_TITLE', $this->config['sitename']),
			'description'	=> $this->user->lang('WIKI_RECENT_CHANGES_EXPLAIN'),
			'link'			=> $this->helper->route('phpbbmodders_wiki_recent_changes', array(), false, false, UrlGeneratorInterface::ABSOLUTE_URL),
			'self'			=> $this->helper->route('phpbbmodders_wiki_recent_changes_feed', array('_format' => $_format), false, false, UrlGeneratorInterface::ABSOLUTE_URL),
			'updated'		=> $rows ? (int) $rows[0]['article_last_edit'] : time(),
		);

		return new Response(feed::build($_format, $meta, $entries), 200, array(
			'Content-Type'	=> ($_format === feed::ATOM ? 'application/atom+xml' : 'application/rss+xml') . '; charset=UTF-8',
			'Cache-Control'	=> 'public, max-age=300',
		));
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
