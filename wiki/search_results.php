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
 * Runs a keyword search over the live articles and turns the matches into rows for a
 * template, with the searched words marked. The wiki's search page and the wiki block
 * on the board's search results both use it.
 */
class search_results
{
	/** Longest stretch of text shown for a result, in characters */
	const SNIPPET_LENGTH = 300;

	/** @var \phpbbmodders\wiki\wiki\protection */
	protected $protection;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\controller\helper */
	protected $helper;

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
	 * @param \phpbb\db\driver\driver_interface							$db
	 * @param \phpbb\controller\helper									$helper
	 * @param \phpbb\user												$user
	 * @param \phpbbmodders\wiki\search\wiki_search_backend_factory	$factory
	 * @param string													$article_table
	 * @param string													$search_text_table
	 * @param string													$phpbb_root_path
	 * @param string													$php_ext
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\user $user, \phpbbmodders\wiki\search\wiki_search_backend_factory $factory, $article_table, $search_text_table, $phpbb_root_path, $php_ext, \phpbbmodders\wiki\wiki\protection $protection)
	{
		$this->protection = $protection;
		$this->db = $db;
		$this->helper = $helper;
		$this->user = $user;
		$this->factory = $factory;
		$this->article_table = $article_table;
		$this->search_text_table = $search_text_table;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * The words to mark in the results, as parts of a regular expression, from the search words.
	 *
	 * @param string $keywords What was typed
	 * @return array The words, each already safe in a regular expression
	 */
	public function highlight_words($keywords)
	{
		include_once($this->phpbb_root_path . 'includes/functions_content.' . $this->php_ext);

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
	 * Search the live articles.
	 *
	 * @param string	$keywords	What was typed
	 * @param string	$terms		'all' or 'any'
	 * @param string	$fields		'all', 'titleonly' or 'msgonly'
	 * @param string	$sort_key	't' for the last edit, 's' for the title
	 * @param string	$sort_dir	'a' or 'd'
	 * @param int		$start		Offset of the first result
	 * @param int		$per_page	Most results to return
	 * @return array|bool False when the words cannot be searched on (all too short or too
	 *					common). Otherwise array('total' => matches, 'rows' => rows) where a row
	 *					has TITLE, DESCRIPTION and SNIPPET (marked, safe to print), DATE, AUTHOR
	 *					and U_ARTICLE.
	 */
	public function find($keywords, $terms, $fields, $sort_key, $sort_dir, $start, $per_page)
	{
		$search = $this->factory->get_active();
		if (!$search->split_keywords($keywords, $terms) || !$search->get_search_query())
		{
			return false;
		}

		$sort_by_sql = array('t' => 'article_date', 's' => 'article_title');
		$ids = array();
		$total = (int) $search->keyword_search('posts', $fields, $terms, $sort_by_sql, $sort_key, $sort_dir, '0', array(), 0, array(), '', $ids, $start, $per_page);

		$rows = array();
		if (!empty($ids))
		{
			$rows = $this->build_rows($ids, $keywords, $fields);
		}

		return array('total' => $total, 'rows' => $rows);
	}

	/**
	 * @param array		$ids		Article ids, in the order the backend gave them
	 * @param string	$keywords
	 * @param string	$fields
	 * @return array The rows, as described for find()
	 */
	protected function build_rows(array $ids, $keywords, $fields)
	{
		$words = $this->highlight_words($keywords);
		$pattern = !empty($words) ? '#(?!<.*)(?<!\w)(' . implode('|', $words) . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#isu' : '';

		$sql = 'SELECT s.article_id, s.article_title, s.article_description, s.article_body, s.article_date, s.author, a.article_url, a.article_user_id
			FROM ' . $this->search_text_table . ' s
			INNER JOIN ' . $this->article_table . ' a ON (a.article_id = s.article_id)
			WHERE ' . $this->db->sql_in_set('s.article_id', $ids) . '
				AND a.article_approved = 1';
		$result = $this->db->sql_query($sql);
		$found = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$found[(int) $row['article_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		$rows = array();
		foreach ($ids as $id)
		{
			if (!isset($found[(int) $id]))
			{
				continue;
			}

			$row = $found[(int) $id];
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

			$rows[] = array(
				'TITLE'			=> $title,
				'DESCRIPTION'	=> $description,
				'SNIPPET'		=> $snippet,
				'DATE'			=> $this->user->format_date($row['article_date']),
				'AUTHOR'		=> htmlspecialchars($row['author'], ENT_COMPAT, 'UTF-8'),
				'U_ARTICLE'		=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $row['article_url'])),
				'PROTECTED'		=> $this->protection->list_label($row['article_url']),
			);
		}

		return $rows;
	}
}
