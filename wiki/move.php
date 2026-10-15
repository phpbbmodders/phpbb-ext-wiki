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
 * Move an article to a new name (its address), carrying every version, its uploaded
 * files and its watchers with it, and optionally leaving a redirect at the old name.
 * Links in other articles are not rewritten; the redirect covers them.
 */
class move
{
	/** Characters an article name cannot hold, as for [[links]] */
	const INVALID_NAME = '#[\[\]|<>"\#?%/\\\\\n]#';

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

	/** @var links */
	protected $links;

	/** @var search_sync */
	protected $search_sync;

	/** @var string */
	protected $article_table;

	/** @var string */
	protected $file_table;

	/** @var protection */
	protected $protection;

	/** @var string */
	protected $watch_table;

	/**
	 * @param \phpbb\auth\auth					$auth
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\controller\helper			$helper
	 * @param \phpbb\request\request			$request
	 * @param \phpbb\template\template			$template
	 * @param \phpbb\user						$user
	 * @param links								$links
	 * @param search_sync						$search_sync
	 * @param string							$article_table
	 * @param string							$file_table
	 * @param string							$watch_table
	 * @param protection						$protection
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, links $links, search_sync $search_sync, $article_table, $file_table, $watch_table, protection $protection)
	{
		$this->protection = $protection;
		$this->auth = $auth;
		$this->db = $db;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->links = $links;
		$this->search_sync = $search_sync;
		$this->article_table = $article_table;
		$this->file_table = $file_table;
		$this->watch_table = $watch_table;
	}

	/**
	 * Whether a name can be an article's address.
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function valid_name($name)
	{
		return $name !== '' && utf8_strlen($name) <= 255 && !preg_match(self::INVALID_NAME, $name) && trim($name) === $name;
	}

	/**
	 * The move form, and the move once it is sent.
	 *
	 * @param string $article The article's current name, as in its address
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function page($article)
	{
		// Moving changes the article, so its protection applies too
		if (!$this->auth->acl_get('u_wiki_move') || !$this->protection->allows($article, (int) $this->user->data['user_id']))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$sql = 'SELECT article_title
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
			ORDER BY article_last_edit DESC, article_id DESC";
		$result = $this->db->sql_query_limit($sql, 1);
		$current = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$current)
		{
			trigger_error('NO_ARTICLE');
		}

		add_form_key('wiki_move');
		// The request escapes HTML. Names are stored as written, titles escaped, as an edit stores them.
		$new_name = $this->request->is_set_post('new_name') ? trim(htmlspecialchars_decode($this->request->variable('new_name', '', true), ENT_COMPAT)) : $article;
		$new_title = $this->request->is_set_post('new_title') ? trim($this->request->variable('new_title', '', true)) : $current['article_title'];
		$leave_redirect = $this->request->is_set_post('submit') ? $this->request->variable('leave_redirect', false) : true;
		$used_by = (strpos($article, templates::PREFIX) === 0) ? $this->template_users($article) : 0;
		$errors = array();

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key('wiki_move'))
			{
				$errors[] = $this->user->lang('FORM_INVALID');
			}
			if (!self::valid_name($new_name))
			{
				$errors[] = $this->user->lang('WIKI_MOVE_INVALID_NAME');
			}
			else if ($new_name === $article)
			{
				$errors[] = $this->user->lang('WIKI_MOVE_SAME_NAME');
			}
			else if ($this->taken($new_name, $article))
			{
				$errors[] = $this->user->lang('WIKI_MOVE_NAME_TAKEN', htmlspecialchars($new_name, ENT_COMPAT, 'UTF-8'));
			}
			if ($new_title === '' || utf8_strlen(htmlspecialchars_decode($new_title, ENT_COMPAT)) > 255)
			{
				$errors[] = $this->user->lang('WIKI_MOVE_INVALID_TITLE');
			}
			// Moving a template that articles use needs a second, explicit yes
			if ($used_by && !$this->request->variable('confirm_template', false))
			{
				$errors[] = $this->user->lang('WIKI_MOVE_TEMPLATE_CONFIRM_MISSING');
			}

			if (!$errors)
			{
				$this->move_article($article, $new_name, $new_title, $leave_redirect, $current['article_title']);

				$u_new = $this->helper->route('phpbbmodders_wiki_article', array('article' => $new_name));
				trigger_error($this->user->lang($leave_redirect ? 'WIKI_MOVE_SUCCESS_REDIRECT' : 'WIKI_MOVE_SUCCESS') . '<br /><br /><a href="' . $u_new . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
			}
		}

		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $current['article_title'],
			'U_VIEW_FORUM'	=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article)),
		));
		$this->template->assign_vars(array(
			'ERROR'				=> implode('<br />', $errors),
			'ARTICLE_NAME'		=> htmlspecialchars($article, ENT_COMPAT, 'UTF-8'),
			'NEW_NAME'			=> htmlspecialchars($new_name, ENT_COMPAT, 'UTF-8'),
			'NEW_TITLE'			=> $new_title,
			'S_LEAVE_REDIRECT'	=> $leave_redirect,
			'TEMPLATE_USED_BY'	=> $used_by ? $this->user->lang('WIKI_MOVE_TEMPLATE_WARNING', $used_by) : '',
			'U_ACTION'			=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'move')),
			'U_CANCEL'			=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article)),
		));

		return $this->helper->render('article_move.html', $this->user->lang('WIKI_MOVE_ARTICLE'));
	}

	/**
	 * How many articles use a template, by its name or by a template name that redirects
	 * to it (what an earlier move left behind).
	 *
	 * @param string $article The template's name, with the prefix
	 * @return int
	 */
	protected function template_users($article)
	{
		$users = $this->links->what_links_here($article);

		$sql = 'SELECT article_url
			FROM ' . $this->article_table . "
			WHERE article_approved = 1
				AND article_redirect = '" . $this->db->sql_escape(htmlspecialchars($article, ENT_COMPAT, 'UTF-8')) . "'
				AND article_url " . $this->db->sql_like_expression(templates::PREFIX . $this->db->get_any_char());
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users = array_merge($users, $this->links->what_links_here($row['article_url']));
		}
		$this->db->sql_freeresult($result);

		return count(array_unique(array_column($users, 'article_url')));
	}

	/**
	 * Whether a name is taken: any version, live or not, is stored under it, other than
	 * redirects to the article being moved. Those are what an earlier move left behind,
	 * so an article can be moved back over them.
	 *
	 * @param string $name The name to move to
	 * @param string $article The article being moved
	 * @return bool
	 */
	protected function taken($name, $article)
	{
		$sql = 'SELECT article_redirect
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($name) . "'";
		$result = $this->db->sql_query($sql);
		$taken = false;
		while ($row = $this->db->sql_fetchrow($result))
		{
			if (htmlspecialchars_decode((string) $row['article_redirect'], ENT_COMPAT) !== $article)
			{
				$taken = true;
				break;
			}
		}
		$this->db->sql_freeresult($result);

		return $taken;
	}

	/**
	 * Move every version, file and watch to the new name, in one transaction, and leave a
	 * live redirect version at the old name when asked.
	 *
	 * @param string $old The current name
	 * @param string $new The new name
	 * @param string $title The new title, as stored (HTML-escaped)
	 * @param bool $leave_redirect
	 * @param string $old_title The current title, as stored, for the redirect
	 * @return void
	 */
	protected function move_article($old, $new, $title, $leave_redirect, $old_title)
	{
		$where = " WHERE article_url = '" . $this->db->sql_escape($old) . "'";

		// Redirects back to this article at the new name give way to it (see taken())
		$this->search_sync->remove($new);

		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->article_table . " WHERE article_url = '" . $this->db->sql_escape($new) . "'");
		$this->db->sql_query('UPDATE ' . $this->article_table . ' SET ' . $this->db->sql_build_array('UPDATE', array(
			'article_url'	=> $new,
			'article_title'	=> $title,
		)) . $where);
		$this->db->sql_query('UPDATE ' . $this->file_table . ' SET ' . $this->db->sql_build_array('UPDATE', array('article_url' => $new)) . $where);
		$this->protection->move($old, $new);
		// A member who also watched the new name keeps one watch, not two
		$sql = 'SELECT user_id FROM ' . $this->watch_table . " WHERE article_url = '" . $this->db->sql_escape($new) . "'";
		$result = $this->db->sql_query($sql);
		$watching_new = array_map('intval', array_column($this->db->sql_fetchrowset($result), 'user_id'));
		$this->db->sql_freeresult($result);
		if ($watching_new)
		{
			$this->db->sql_query('DELETE FROM ' . $this->watch_table . $where . ' AND ' . $this->db->sql_in_set('user_id', $watching_new));
		}
		$this->db->sql_query('UPDATE ' . $this->watch_table . ' SET ' . $this->db->sql_build_array('UPDATE', array('article_url' => $new)) . $where);

		if ($leave_redirect)
		{
			$now = time();
			$this->db->sql_query('INSERT INTO ' . $this->article_table . ' ' . $this->db->sql_build_array('INSERT', array(
				'article_title'			=> $old_title,
				'article_url'			=> $old,
				'article_text'			=> '',
				'bbcode_uid'			=> '',
				'bbcode_bitfield'		=> '',
				'article_approved'		=> 1,
				'article_user_id'		=> (int) $this->user->data['user_id'],
				'article_last_edit'		=> $now,
				'article_time_created'	=> $now,
				'article_edit_reason'	=> $this->user->lang('WIKI_MOVE_REDIRECT_REASON', $new),
				'article_topic_id'		=> 0,
				'article_sources'		=> '',
				'article_sticky'		=> 0,
				'article_views'			=> 0,
				'article_redirect'		=> htmlspecialchars($new, ENT_COMPAT, 'UTF-8'),
				'article_description'	=> '',
				'article_toc'			=> '',
			)));
		}
		$this->db->sql_transaction('commit');

		// The search index is keyed on versions, which moved with the article; its title
		// and the redirect left behind are brought up to date
		$this->search_sync->sync($new);
		$this->search_sync->sync($old);
	}
}
