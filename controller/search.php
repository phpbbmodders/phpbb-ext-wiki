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
 * The wiki's search page: a form, and the live articles that match the keywords, found
 * by the search backend that is chosen in the ACP.
 */
class search
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\pagination */
	protected $pagination;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbbmodders\wiki\search\wiki_search_backend_factory */
	protected $factory;

	/** @var \phpbbmodders\wiki\wiki\search_results */
	protected $search_results;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * @param \phpbb\auth\auth											$auth
	 * @param \phpbb\config\config										$config
	 * @param \phpbb\controller\helper									$helper
	 * @param \phpbb\language\language									$language
	 * @param \phpbb\pagination											$pagination
	 * @param \phpbb\request\request									$request
	 * @param \phpbb\template\template									$template
	 * @param \phpbb\user												$user
	 * @param \phpbbmodders\wiki\search\wiki_search_backend_factory	$factory
	 * @param \phpbbmodders\wiki\wiki\search_results					$search_results
	 * @param string													$phpbb_root_path
	 * @param string													$php_ext
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\language\language $language, \phpbb\pagination $pagination, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbbmodders\wiki\search\wiki_search_backend_factory $factory, \phpbbmodders\wiki\wiki\search_results $search_results, $phpbb_root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->pagination = $pagination;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->factory = $factory;
		$this->search_results = $search_results;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Check that the wiki search may be used right now, and stop with a message if not.
	 *
	 * @return void
	 */
	protected function check_allowed()
	{
		if (!$this->auth->acl_get('u_wiki_view'))
		{
			trigger_error('NOT_AUTHORISED');
		}
		if (!$this->config['wiki_search'])
		{
			trigger_error('SEARCH_DISABLED');
		}
		if (!$this->auth->acl_get('u_search') || !$this->config['load_search'])
		{
			$this->template->assign_var('S_NO_SEARCH', true);
			trigger_error('NO_SEARCH');
		}
		if ($this->user->load && $this->config['limit_search_load'] && $this->user->load > (float) $this->config['limit_search_load'])
		{
			$this->template->assign_var('S_NO_SEARCH', true);
			trigger_error('NO_SEARCH_TIME');
		}
	}

	/**
	 * The search page.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function main()
	{
		$this->user->add_lang_ext('phpbbmodders/wiki', 'common');
		$this->language->add_lang('search');
		$this->language->add_lang('search', 'phpbbmodders/wiki');
		$this->check_allowed();

		include_once($this->phpbb_root_path . 'includes/functions_content.' . $this->php_ext);

		$keywords = $this->request->variable('keywords', '', true);
		$terms = ($this->request->variable('terms', 'all') === 'any') ? 'any' : 'all';
		$fields = $this->request->variable('sf', 'all');
		$fields = in_array($fields, array('all', 'titleonly', 'msgonly'), true) ? $fields : 'all';
		$sort_key = $this->request->variable('sk', 't');
		$sort_key = in_array($sort_key, array('t', 's'), true) ? $sort_key : 't';
		$sort_dir = ($this->request->variable('sd', 'd') === 'a') ? 'a' : 'd';
		$start = max(0, $this->request->variable('start', 0));

		$u_search = $this->helper->route('phpbbmodders_wiki_search');
		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $this->user->lang('WIKI'),
			'U_VIEW_FORUM'	=> $this->helper->route('phpbbmodders_wiki_index', array()),
		));
		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'	=> $this->user->lang('WIKI_SEARCH'),
			'U_VIEW_FORUM'	=> $u_search,
		));

		$search = $this->factory->get_active();
		$this->template->assign_vars(array(
			'KEYWORDS'				=> $keywords,
			'S_TERMS_ANY'			=> ($terms === 'any'),
			'S_FIELDS'				=> $fields,
			'S_SORT_KEY'			=> $sort_key,
			'S_SORT_DIR'			=> $sort_dir,
			'U_SEARCH'				=> $u_search,
			'S_SEARCH_NOT_BUILT'	=> !$search->index_created(),
			'U_ACP_SEARCH'			=> $this->auth->acl_get('a_board') ? append_sid($this->phpbb_root_path . 'adm/index.' . $this->php_ext, 'i=-phpbbmodders-wiki-acp-search_module&mode=settings') : '',
		));

		if ($keywords === '')
		{
			return $this->helper->render('search.html', $this->user->lang('WIKI_SEARCH'));
		}

		$per_page = max(1, (int) $this->config['wiki_per_page_search']);
		$found = $this->search_results->find($keywords, $terms, $fields, $sort_key, $sort_dir, $start, $per_page);
		if ($found === false)
		{
			$common = $search->get_common_words();
			$ignored = count($common) ? $this->language->lang('IGNORED_TERMS_EXPLAIN', implode(' ', $common)) . '<br>' : '';
			$length = $search->get_word_length();
			trigger_error($ignored . ($length ? $this->language->lang('NO_KEYWORDS', $this->language->lang('CHARACTERS', (int) $length['min']), $this->language->lang('CHARACTERS', (int) $length['max'])) : ''));
		}

		$total = $found['total'];
		foreach ($found['rows'] as $row)
		{
			$this->template->assign_block_vars('results', $row);
		}

		$u_params = array('keywords' => $keywords);
		foreach (array('terms' => $terms, 'sf' => $fields, 'sk' => $sort_key, 'sd' => $sort_dir) as $name => $value)
		{
			$u_params[$name] = $value;
		}
		$this->pagination->generate_template_pagination($this->helper->route('phpbbmodders_wiki_search', $u_params), 'pagination', 'start', $total, $per_page, $start);

		$this->template->assign_vars(array(
			'S_RESULTS'			=> true,
			'TOTAL_MATCHES'		=> $total,
			'SEARCH_MATCHES'	=> $this->user->lang('WIKI_SEARCH_FOUND', $total),
			'TOTAL_ITEMS'		=> $this->user->lang('TOTAL_ITEMS', $total),
			'PAGE_NUMBER'		=> $this->pagination->on_page($total, $per_page, $start),
		));

		return $this->helper->render('search.html', $this->user->lang('WIKI_SEARCH'));
	}
}
