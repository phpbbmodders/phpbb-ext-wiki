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
 * The list of templates: the live version of every article called "Template:Name",
 * A to Z, with its short description, how many articles use it, how to write it and a
 * preview, under a short guide to using templates.
 */
class template_list
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

	/** @var \phpbbmodders\wiki\wiki\links */
	protected $links;

	/** @var \phpbbmodders\wiki\wiki\templates */
	protected $templates;

	/** @var string */
	protected $article_table;

	/**
	 * @param \phpbb\auth\auth					$auth
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\controller\helper			$helper
	 * @param \phpbb\pagination					$pagination
	 * @param \phpbb\request\request			$request
	 * @param \phpbb\template\template			$template
	 * @param \phpbb\user						$user
	 * @param \phpbbmodders\wiki\wiki\links		$links
	 * @param \phpbbmodders\wiki\wiki\templates	$templates
	 * @param string							$article_table
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\pagination $pagination, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbbmodders\wiki\wiki\links $links, \phpbbmodders\wiki\wiki\templates $templates, $article_table)
	{
		$this->templates = $templates;
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->helper = $helper;
		$this->pagination = $pagination;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->links = $links;
		$this->article_table = $article_table;
	}

	/**
	 * The templates page.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function page()
	{
		$this->user->add_lang_ext('phpbbmodders/wiki', 'common');

		if (!$this->auth->acl_get('u_wiki_view'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$sql = 'SELECT COUNT(a.article_id) AS total
			FROM ' . $this->article_table . ' a
			WHERE ' . $this->templates->live_where('a', \phpbbmodders\wiki\wiki\templates::PREFIX);
		$result = $this->db->sql_query($sql);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);

		$per_page = max(1, (int) $this->config['topics_per_page']);
		$start = $this->pagination->validate_start($this->request->variable('start', 0), $per_page, $total);

		$sql = 'SELECT a.article_id, a.article_title, a.article_url, a.article_description, a.article_last_edit, a.article_text, a.bbcode_uid
			FROM ' . $this->article_table . ' a
			WHERE ' . $this->templates->live_where('a', \phpbbmodders\wiki\wiki\templates::PREFIX) . '
			ORDER BY a.article_title ASC, a.article_id ASC';
		$result = $this->db->sql_query_limit($sql, $per_page, $start);
		$rows = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		foreach ($rows as $row)
		{
			$name = substr($row['article_url'], strlen(\phpbbmodders\wiki\wiki\templates::PREFIX));

			// The preview is the template's documentation, rendered as on its own page,
			// where authors show it in use
			\phpbbmodders\wiki\wiki\files::set_context($row['article_url']);
			\phpbbmodders\wiki\wiki\categories::reset();
			list($preview, $source) = $this->templates->page_view($row['article_text'], $row['bbcode_uid']);
			\phpbbmodders\wiki\wiki\files::set_context(null);
			\phpbbmodders\wiki\wiki\categories::reset();

			$this->template->assign_block_vars('templates', array(
				'NAME'			=> $name,
				'DESCRIPTION'	=> $row['article_description'],
				'USED_ON'		=> count($this->links->what_links_here($row['article_url'])),
				'EDIT_TIME'		=> $this->user->format_date($row['article_last_edit']),
				'U_TEMPLATE'	=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $row['article_url'])),
				'USAGE'			=> htmlspecialchars(\phpbbmodders\wiki\wiki\templates::usage($name, \phpbbmodders\wiki\wiki\templates::parameters($source)), ENT_COMPAT, 'UTF-8'),
				'PREVIEW'		=> $preview,
				'S_START'		=> strpos($name, \phpbbmodders\wiki\wiki\templates::START_PREFIX) === 0,
			));
		}

		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $this->user->lang('WIKI'),
			'U_VIEW_FORUM'	=> $this->helper->route('phpbbmodders_wiki_index', array()),
		));

		$this->pagination->generate_template_pagination($this->helper->route('phpbbmodders_wiki_templates', array()), 'pagination', 'start', $total, $per_page, $start);

		$this->template->assign_vars(array(
			'TOTAL_ITEMS'	=> $this->user->lang('TOTAL_ITEMS', $total),
			'S_AUTH_EDIT'	=> $this->auth->acl_get('u_wiki_edit'),
		));

		return $this->helper->render('template_list.html', $this->user->lang('WIKI_TEMPLATES'));
	}
}
