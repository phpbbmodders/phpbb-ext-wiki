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

use phpbbmodders\wiki\wiki\categories;

/**
 * The page of a category, "Category:Name", and the list of all categories.
 *
 * A category page is an article called "Category:Name", which may hold a description,
 * with the live articles in the category listed below it, A to Z. A category nobody
 * wrote a description for still has a page, with a link to write one.
 */
class category
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

	/** @var categories */
	protected $categories;

	/** @var \phpbbmodders\wiki\controller\main */
	protected $main;

	/** @var string */
	protected $article_table;

	/**
	 * @param \phpbb\auth\auth						$auth
	 * @param \phpbb\config\config					$config
	 * @param \phpbb\db\driver\driver_interface		$db
	 * @param \phpbb\controller\helper				$helper
	 * @param \phpbb\pagination						$pagination
	 * @param \phpbb\request\request				$request
	 * @param \phpbb\template\template				$template
	 * @param \phpbb\user							$user
	 * @param categories	$categories
	 * @param \phpbbmodders\wiki\controller\main	$main
	 * @param string								$article_table
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\pagination $pagination, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, categories $categories, \phpbbmodders\wiki\controller\main $main, $article_table)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->helper = $helper;
		$this->pagination = $pagination;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->categories = $categories;
		$this->main = $main;
		$this->article_table = $article_table;
	}

	/**
	 * Fill the template with a page of a category's members.
	 *
	 * @param string $name The category's normalised name
	 * @return void
	 */
	protected function assign_members($name)
	{
		$members = $this->categories->members($name);
		$total = count($members);
		$per_page = max(1, (int) $this->config['topics_per_page']);
		$start = $this->pagination->validate_start($this->request->variable('start', 0), $per_page, $total);

		foreach (array_slice($members, $start, $per_page) as $row)
		{
			$this->template->assign_block_vars('category_members', array(
				'TITLE'			=> $row['article_title'],
				'DESCRIPTION'	=> $row['article_description'],
				'U_ARTICLE'		=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $row['article_url'])),
			));
		}

		$this->pagination->generate_template_pagination($this->helper->route('phpbbmodders_wiki_category', array('name' => $name)), 'pagination', 'start', $total, $per_page, $start);
		$this->template->assign_vars(array(
			'S_CATEGORY_PAGE'	=> true,
			'CATEGORY_TOTAL'	=> $this->user->lang('WIKI_CATEGORY_ARTICLES_TOTAL', $total),
		));
	}

	/**
	 * Whether a category has a page of its own: an article called "Category:Name" with a live version.
	 *
	 * @param string $name
	 * @return bool
	 */
	protected function has_page($name)
	{
		$sql = 'SELECT article_id
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape(categories::PREFIX . $name) . "'
				AND article_approved = 1";
		$result = $this->db->sql_query_limit($sql, 1);
		$found = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $found;
	}

	/**
	 * A category's page. Anything but a plain view (editing it, its versions) is the
	 * ordinary article page of "Category:Name".
	 *
	 * @param string $name What follows "Category:" in the address
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function page($name)
	{
		$this->user->add_lang_ext('phpbbmodders/wiki', 'common');

		if (!$this->auth->acl_get('u_wiki_view'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$article = categories::PREFIX . $name;
		$normal = categories::normalise($name);
		$plain_view = !$this->request->is_set('action') && !$this->request->is_set('id');

		if ($plain_view && $this->has_page($name))
		{
			// The description is the article; the members go below it
			$this->assign_members($normal);

			return $this->main->article($article);
		}

		if ($plain_view && $this->categories->has_members($normal))
		{
			$this->assign_members($normal);
			$this->template->assign_block_vars('navlinks', array(
				'FORUM_NAME'	=> $this->user->lang('WIKI'),
				'U_VIEW_FORUM'	=> $this->helper->route('phpbbmodders_wiki_index', array()),
			));
			$this->template->assign_vars(array(
				'CATEGORY_TITLE'	=> categories::PREFIX . $normal,
				'U_CREATE'			=> $this->auth->acl_get('u_wiki_edit') ? $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'edit')) : '',
			));

			return $this->helper->render('category.html', categories::PREFIX . $normal);
		}

		// Nothing in it and no page: the ordinary article flow, which offers to create it
		return $this->main->article($article);
	}

	/**
	 * The list of all categories in use, with how many articles each has.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function index()
	{
		$this->user->add_lang_ext('phpbbmodders/wiki', 'common');

		if (!$this->auth->acl_get('u_wiki_view'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$counts = $this->categories->in_use();
		$total = count($counts);
		$per_page = max(1, (int) $this->config['topics_per_page']);
		$start = $this->pagination->validate_start($this->request->variable('start', 0), $per_page, $total);

		foreach (array_slice($counts, $start, $per_page, true) as $name => $count)
		{
			$this->template->assign_block_vars('categories', array(
				'NAME'			=> $name,
				'COUNT'			=> $count,
				'U_CATEGORY'	=> $this->helper->route('phpbbmodders_wiki_article', array('article' => categories::PREFIX . $name)),
			));
		}

		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $this->user->lang('WIKI'),
			'U_VIEW_FORUM'	=> $this->helper->route('phpbbmodders_wiki_index', array()),
		));

		$this->pagination->generate_template_pagination($this->helper->route('phpbbmodders_wiki_categories', array()), 'pagination', 'start', $total, $per_page, $start);
		$this->template->assign_vars(array(
			'TOTAL_ITEMS'	=> $this->user->lang('TOTAL_ITEMS', $total),
		));

		return $this->helper->render('category_list.html', $this->user->lang('WIKI_CATEGORIES'));
	}
}
