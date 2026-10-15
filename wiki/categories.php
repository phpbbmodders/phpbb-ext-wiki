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
 * Categories: [[Category:Name]] in a wiki article puts it in a category. The text
 * formatter stores the mark as a WIKICATEGORY tag, which renders as nothing; the names
 * are collected while an article is shown, to list under it, and the members of a
 * category are the live articles whose stored text holds the tag.
 *
 * The member lists come from category_members once its table is built, which knows the
 * categories templates add, sub-categories and sort keys; until then they are read from
 * what is written in the articles themselves.
 */
class categories
{
	/** A category page is an article called "Category:Name". */
	const PREFIX = 'Category:';

	/** @var array Names found in the text rendered since reset(), in order */
	protected static $found = array();

	/** @var array The sort key each of those was written with, '' for none */
	protected static $keys = array();

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbbmodders\wiki\wiki\templates */
	protected $templates;

	/** @var string */
	protected $article_table;

	/** @var category_members */
	protected $category_members;

	/**
	 * @param \phpbb\db\driver\driver_interface	$db				Database object
	 * @param \phpbbmodders\wiki\wiki\templates	$templates		Wiki templates service, for the live-version query
	 * @param string								$article_table	Wiki article table
	 * @param category_members						$category_members	Stored memberships
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbbmodders\wiki\wiki\templates $templates, $article_table, category_members $category_members)
	{
		$this->category_members = $category_members;
		$this->db = $db;
		$this->templates = $templates;
		$this->article_table = $article_table;
	}

	/**
	 * A category name as it is kept: spaces for underscores, runs of spaces made one, and a
	 * capital first letter, so [[Category:boxes]] and [[Category:Boxes]] are one category.
	 * Also the filter the text formatter runs on the name in the tag.
	 *
	 * @param string $name The name as written
	 * @return string
	 */
	public static function normalise($name)
	{
		$name = trim(preg_replace('/[\s_]+/u', ' ', (string) $name));

		return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
	}

	/**
	 * Forget the names found so far, before an article is rendered.
	 *
	 * @return void
	 */
	public static function reset()
	{
		self::$found = array();
		self::$keys = array();
	}

	/**
	 * Note that rendered text puts the article in a category. The first sort key written for
	 * a category counts, as on MediaWiki.
	 *
	 * @param string $name The category's name
	 * @param string $key The sort key after the bar, '' for none
	 * @return void
	 */
	public static function add($name, $key = '')
	{
		$name = self::normalise($name);
		if ($name !== '' && !in_array($name, self::$found, true))
		{
			self::$found[] = $name;
			self::$keys[$name] = trim((string) $key);
		}
	}

	/**
	 * The sort keys of the categories found since reset(): names with their keys.
	 *
	 * @return array
	 */
	public static function found_keys()
	{
		return self::$keys;
	}

	/**
	 * The categories found in the text rendered since reset(), in the order they were written.
	 *
	 * @return array
	 */
	public static function found()
	{
		return self::$found;
	}

	/**
	 * The category names in the stored text of an article.
	 *
	 * @param string $stored The article text as it is stored (XML)
	 * @return array Unique names, in the order they were written
	 */
	public static function names_in($stored)
	{
		if (!preg_match_all('/<WIKICATEGORY name="([^"]*)"/', $stored, $matches))
		{
			return array();
		}

		$names = array();
		foreach ($matches[1] as $name)
		{
			$name = html_entity_decode($name, ENT_QUOTES | ENT_XML1, 'UTF-8');
			if (!in_array($name, $names, true))
			{
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * SQL for the live articles in a category.
	 *
	 * @param string $category The category's name
	 * @return string
	 */
	protected function members_where($category)
	{
		$needle = '<WIKICATEGORY name="' . htmlspecialchars(self::normalise($category), ENT_COMPAT, 'UTF-8') . '"';

		return $this->templates->live_where('a', '') . '
			AND a.article_text ' . $this->db->sql_like_expression($this->db->get_any_char() . $needle . $this->db->get_any_char());
	}

	/**
	 * The category names a template page puts itself in: those written outside its
	 * <includeonly> parts, since those belong to the articles that include it.
	 *
	 * @param string $raw The template page's text as it is written
	 * @return array Normalised names, unique, in order
	 */
	public static function names_outside_includeonly($raw)
	{
		return array_keys(self::keys_outside_includeonly($raw));
	}

	/**
	 * The categories a template page puts itself in, with their sort keys.
	 *
	 * @param string $raw The template page's text as it is written
	 * @return array Normalised names, in order, with the first sort key written for each ('' for none)
	 */
	public static function keys_outside_includeonly($raw)
	{
		$raw = preg_replace('#<includeonly>.*?</includeonly>#is', '', $raw);
		if (!preg_match_all('/\[\[\s*Category:\s*([^\[\]|\n]+?)\s*(?:\|\s*([^\[\]\n]*?)\s*)?\]\]/i', $raw, $matches, PREG_SET_ORDER))
		{
			return array();
		}

		$names = array();
		foreach ($matches as $match)
		{
			$name = self::normalise($match[1]);
			if ($name !== '' && !isset($names[$name]))
			{
				$names[$name] = isset($match[2]) ? trim($match[2]) : '';
			}
		}

		return $names;
	}

	/**
	 * The categories a live article is in as far as its own text says. For a template page
	 * that leaves out what is inside <includeonly>.
	 *
	 * @param array $row Row with article_url, article_text and bbcode_uid
	 * @return array
	 */
	protected function names_of($row)
	{
		if (strpos($row['article_url'], templates::PREFIX) !== 0)
		{
			return self::names_in($row['article_text']);
		}

		$edit = generate_text_for_edit($row['article_text'], $row['bbcode_uid'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);

		return self::names_outside_includeonly(htmlspecialchars_decode($edit['text'], ENT_COMPAT));
	}

	/**
	 * Whether any live article, or sub-category, is in a category.
	 *
	 * @param string $category
	 * @return bool
	 */
	public function has_members($category)
	{
		return count($this->members($category)) > 0 || count($this->subcategories($category)) > 0;
	}

	/**
	 * Every live article in a category, by sort key or else title (A to Z before the stored
	 * memberships are built), sub-categories left out.
	 *
	 * @param string $category
	 * @return array Rows with article_url, article_title and article_description
	 */
	public function members($category)
	{
		if ($this->category_members->built())
		{
			return $this->category_members->members($category)['articles'];
		}

		$normal = self::normalise($category);
		$sql = 'SELECT a.article_url, a.article_title, a.article_description, a.article_text, a.bbcode_uid
			FROM ' . $this->article_table . ' a
			WHERE ' . $this->members_where($category) . '
			ORDER BY a.article_title ASC, a.article_id ASC';
		$result = $this->db->sql_query($sql);
		$members = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			// A template page counts for what it says outside <includeonly>
			if (strpos($row['article_url'], templates::PREFIX) === 0 && !in_array($normal, $this->names_of($row), true))
			{
				continue;
			}
			unset($row['article_text'], $row['bbcode_uid']);
			$members[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $members;
	}

	/**
	 * The sub-categories of a category: category pages that are themselves in it, by sort
	 * key or else title. Known once the stored memberships are built.
	 *
	 * @param string $category
	 * @return array Rows with article_url, article_title and article_description
	 */
	public function subcategories($category)
	{
		return $this->category_members->built() ? $this->category_members->members($category)['subcategories'] : array();
	}

	/**
	 * The short description of every category that has a page: the description field of
	 * the live version of its "Category:Name" article.
	 *
	 * @return array Normalised category names with their descriptions, as stored
	 */
	public function descriptions()
	{
		$sql = 'SELECT a.article_url, a.article_description
			FROM ' . $this->article_table . ' a
			WHERE ' . $this->templates->live_where('a', self::PREFIX);
		$result = $this->db->sql_query($sql);
		$descriptions = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$descriptions[self::normalise(substr($row['article_url'], strlen(self::PREFIX)))] = $row['article_description'];
		}
		$this->db->sql_freeresult($result);

		return $descriptions;
	}

	/**
	 * Every category with a live article in it, and how many.
	 *
	 * @return array Category names with their member counts, A to Z
	 */
	public function in_use()
	{
		if ($this->category_members->built())
		{
			return $this->category_members->in_use();
		}

		$sql = 'SELECT a.article_url, a.article_text, a.bbcode_uid
			FROM ' . $this->article_table . ' a
			WHERE ' . $this->templates->live_where('a', '') . '
				AND a.article_text ' . $this->db->sql_like_expression($this->db->get_any_char() . '<WIKICATEGORY name="' . $this->db->get_any_char());
		$result = $this->db->sql_query($sql);
		$counts = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			foreach ($this->names_of($row) as $name)
			{
				$counts[$name] = isset($counts[$name]) ? $counts[$name] + 1 : 1;
			}
		}
		$this->db->sql_freeresult($result);

		ksort($counts, SORT_STRING | SORT_FLAG_CASE);

		return $counts;
	}
}
