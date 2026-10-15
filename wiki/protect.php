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
 * The Protect article page, under Moderator controls: tick the groups whose members may edit
 * the article, or none to let everyone who may edit articles edit it again.
 */
class protect
{
	/** Name of the form key */
	const FORM_KEY = 'wiki_protect';

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

	/** @var protection */
	protected $protection;

	/** @var string */
	protected $article_table;

	/**
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\controller\helper			$helper
	 * @param \phpbb\request\request			$request
	 * @param \phpbb\template\template			$template
	 * @param \phpbb\user						$user
	 * @param protection						$protection
	 * @param string							$article_table
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, protection $protection, $article_table)
	{
		$this->db = $db;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->protection = $protection;
		$this->article_table = $article_table;
	}

	/**
	 * The form, and the protection once it is sent.
	 *
	 * @param string $article The article's name, as in its address
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function page($article)
	{
		if (!$this->protection->can_protect())
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

		$u_article = $this->helper->route('phpbbmodders_wiki_article', array('article' => $article));
		$choosable = $this->protection->choosable_groups();
		$error = '';

		add_form_key(self::FORM_KEY);
		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				$error = $this->user->lang('FORM_INVALID');
			}
			else
			{
				// Only groups that exist can be chosen
				$chosen = array_intersect(array_map('intval', $this->request->variable('protect_groups', array(0))), array_keys($choosable));
				$this->protection->set($article, $chosen);
				$message = $chosen ? $this->user->lang('WIKI_PROTECT_SAVED', implode(', ', $this->protection->group_names($article))) : $this->user->lang('WIKI_PROTECT_LIFTED');
				trigger_error($message . '<br /><br /><a href="' . $u_article . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
			}
		}

		$current_groups = $this->protection->groups($article);
		foreach ($choosable as $group_id => $name)
		{
			$this->template->assign_block_vars('protect_groups', array(
				'ID'		=> $group_id,
				'NAME'		=> $name,
				'S_CHECKED'	=> in_array($group_id, $current_groups, true),
			));
		}

		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $current['article_title'],
			'U_VIEW_FORUM'	=> $u_article,
		));
		$this->template->assign_vars(array(
			'ERROR'			=> $error,
			'ARTICLE_NAME'	=> htmlspecialchars($article, ENT_COMPAT, 'UTF-8'),
			'U_ACTION'		=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'protect')),
			'U_CANCEL'		=> $u_article,
		));

		return $this->helper->render('article_protect.html', $this->user->lang('WIKI_PROTECT_ARTICLE'));
	}
}
