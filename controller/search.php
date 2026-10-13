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
	/** Longest stretch of text shown for a result, in characters */
	const SNIPPET_LENGTH = 300;

	/** Most matches counted, to spare the server on a very broad search */
	const MATCH_LIMIT = 1000;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

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

	/** @var string */
	protected $article_table;

	/** @var string */
	protected $search_text_table;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * @param \phpbb\auth\auth											$auth
	 * @param \phpbb\config\config										$config
	 * @param \phpbb\db\driver\driver_interface							$db
	 * @param \phpbb\controller\helper									$helper
	 * @param \phpbb\language\language									$language
	 * @param \phpbb\pagination											$pagination
	 * @param \phpbb\request\request									$request
	 * @param \phpbb\template\template									$template
	 * @param \phpbb\user												$user
	 * @param \phpbbmodders\wiki\search\wiki_search_backend_factory	$factory
	 * @param string													$article_table
	 * @param string													$search_text_table
	 * @param string													$phpbb_root_path
	 * @param string													$php_ext
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\language\language $language, \phpbb\pagination $pagination, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbbmodders\wiki\search\wiki_search_backend_factory $factory, $article_table, $search_text_table, $phpbb_root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->helper = $helper;
		$this->language = $language;
		$this->pagination = $pagination;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->factory = $factory;
		$this->article_table = $article_table;
		$this->search_text_table = $search_text_table;
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
	 * The words to mark in the results, as a regular expression part, from the search words.
	 *
	 * @param string $keywords What was typed
	 * @return array The words, each already safe in a regular expression
	 */
	protected function highlight_words($keywords)
	{
		$hilit = phpbb_clean_search_string(str_replace(array('+', '-', '|', '(', ')', '&quot;'), ' ', $keywords));
		$words = array_filter(explode(' ', $hilit), 'strlen');

		$safe = array();
		foreach ($words as $word)
		{
			$word = phpbb_clean_search_string($word);
			$word = str_replace('\*', '\w*?', preg_quote($word, '#'));
			$word = preg_replace('#(^|\s)\\\\w\*\?(\s|$)#', '$1\w+?$2', $word);
			if ($word !== '')
			{
				$safe[] = $word;
			}
		}

		return $safe;
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

		$correct = $search->split_keywords($keywords, $terms);
		if (!$correct || !$search->get_search_query())
		{
			$common = $search->get_common_words();
			$ignored = count($common) ? $this->language->lang('IGNORED_TERMS_EXPLAIN', implode(' ', $common)) . '<br>' : '';
			$length = $search->get_word_length();
			trigger_error($ignored . ($length ? $this->language->lang('NO_KEYWORDS', $this->language->lang('CHARACTERS', (int) $length['min']), $this->language->lang('CHARACTERS', (int) $length['max'])) : ''));
		}

		$per_page = max(1, (int) $this->config['wiki_per_page_search']);
		$sort_by_sql = array('t' => 'article_date', 's' => 'article_title');
		$ids = array();
		$total = (int) $search->keyword_search('posts', $fields, $terms, $sort_by_sql, $sort_key, $sort_dir, '0', array(), 0, array(), '', $ids, $start, $per_page);

		$words = $this->highlight_words($keywords);
		$pattern = !empty($words) ? '#(?!<.*)(?<!\w)(' . implode('|', $words) . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#isu' : '';

		if (!empty($ids))
		{
			$sql = 'SELECT s.article_id, s.article_title, s.article_description, s.article_body, s.article_date, s.author, a.article_url, a.article_user_id
				FROM ' . $this->search_text_table . ' s
				INNER JOIN ' . $this->article_table . ' a ON (a.article_id = s.article_id)
				WHERE ' . $this->db->sql_in_set('s.article_id', $ids) . '
					AND a.article_approved = 1';
			$result = $this->db->sql_query($sql);
			$rows = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$rows[(int) $row['article_id']] = $row;
			}
			$this->db->sql_freeresult($result);

			// In the order the backend gave
			foreach ($ids as $id)
			{
				if (!isset($rows[(int) $id]))
				{
					continue;
				}

				$row = $rows[(int) $id];
				$title = htmlspecialchars($row['article_title'], ENT_COMPAT, 'UTF-8');
				$description = htmlspecialchars($row['article_description'], ENT_COMPAT, 'UTF-8');
				$snippet = ($fields === 'titleonly') ? '' : get_context(htmlspecialchars($row['article_body'], ENT_COMPAT, 'UTF-8'), $words ? array_map('stripslashes', $words) : array(), self::SNIPPET_LENGTH);
				if ($pattern !== '')
				{
					$mark = '<span class="posthilit">$1</span>';
					$title = preg_replace($pattern, $mark, $title);
					$description = preg_replace($pattern, $mark, $description);
					$snippet = preg_replace($pattern, $mark, $snippet);
				}

				$this->template->assign_block_vars('results', array(
					'TITLE'			=> $title,
					'DESCRIPTION'	=> $description,
					'SNIPPET'		=> $snippet,
					'DATE'			=> $this->user->format_date($row['article_date']),
					'AUTHOR'		=> htmlspecialchars($row['author'], ENT_COMPAT, 'UTF-8'),
					'U_ARTICLE'		=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $row['article_url'])),
				));
			}
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
