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
 * Which articles are in which category, kept in a table so a category also lists the articles
 * a template puts in it, and can list its sub-categories and sort its members by key.
 *
 * An article's categories are those found when its live version is rendered (its own
 * [[Category:...]] marks and those its templates add), or for a template page, those written
 * outside its <includeonly> parts. They are worked out again whenever the live version
 * changes, and for every article that uses a template when the template's live version
 * changes. A cron task fills the table for the articles a board already has (see built()).
 */
class category_members
{
	/** How many articles one cron run works out */
	const BATCH = 50;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var links */
	protected $links;

	/** @var templates */
	protected $templates;

	/** @var string */
	protected $article_table;

	/** @var string */
	protected $member_table;

	/**
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param links								$links
	 * @param templates							$templates
	 * @param string							$article_table
	 * @param string							$member_table
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, links $links, templates $templates, $article_table, $member_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->links = $links;
		$this->templates = $templates;
		$this->article_table = $article_table;
		$this->member_table = $member_table;
	}

	/**
	 * Whether the table holds every article yet. Until it does, the category lists read the
	 * articles' text as before.
	 *
	 * @return bool
	 */
	public function built()
	{
		return !empty($this->config['wiki_category_members_built']);
	}

	/**
	 * The categories an article's live version puts it in, with their sort keys.
	 *
	 * @param string $article
	 * @return array Normalised category names with their sort keys ('' for none)
	 */
	public function compute($article)
	{
		$sql = 'SELECT a.article_url, a.article_text, a.bbcode_uid, a.bbcode_bitfield
			FROM ' . $this->article_table . ' a
			WHERE ' . $this->templates->live_where('a', '') . "
				AND a.article_url = '" . $this->db->sql_escape($article) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$row)
		{
			return array();
		}

		if (strpos($article, templates::PREFIX) === 0)
		{
			$edit = generate_text_for_edit($row['article_text'], $row['bbcode_uid'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);

			return categories::keys_outside_includeonly(htmlspecialchars_decode($edit['text'], ENT_COMPAT));
		}

		// Rendering notes every category mark, the templates' too (see the wikilink listener)
		$was_found = categories::found_keys();
		categories::reset();
		files::set_context($article);
		try
		{
			generate_text_for_display($row['article_text'], $row['bbcode_uid'], $row['bbcode_bitfield'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);
			$keys = categories::found_keys();
		}
		finally
		{
			files::set_context(null);
			categories::reset();
			foreach ($was_found as $name => $key)
			{
				categories::add($name, $key);
			}
		}

		return $keys;
	}

	/**
	 * Work out the article's categories again, and when it is a template, those of every
	 * article that uses it (through other templates too).
	 *
	 * @param string $article
	 * @param array $done Articles already worked out in this round, so template loops end
	 * @return void
	 */
	public function update($article, array &$done = array())
	{
		if (isset($done[$article]))
		{
			return;
		}
		$done[$article] = true;

		$this->store($article, $this->compute($article));

		if (strpos($article, templates::PREFIX) === 0)
		{
			foreach ($this->links->what_links_here($article) as $user)
			{
				$this->update($user['article_url'], $done);
			}
		}
	}

	/**
	 * The article is gone: it is in no category any more, and the articles that used it, if
	 * it was a template, lose what it gave them.
	 *
	 * @param string $article
	 * @param array $users For a template, the articles that used it, read before it went
	 * @return void
	 */
	public function remove($article, array $users = array())
	{
		$this->store($article, array());
		$done = array($article => true);
		foreach ($users as $user)
		{
			$this->update($user, $done);
		}
	}

	/**
	 * Replace the stored categories of an article.
	 *
	 * @param string $article
	 * @param array $keys Category names with their sort keys
	 * @return void
	 */
	protected function store($article, array $keys)
	{
		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->member_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'");
		foreach ($keys as $category => $key)
		{
			$this->db->sql_query('INSERT INTO ' . $this->member_table . ' ' . $this->db->sql_build_array('INSERT', array(
				'article_url'	=> $article,
				'category'		=> utf8_substr((string) $category, 0, 255),
				'sort_key'		=> utf8_substr((string) $key, 0, 255),
			)));
		}
		$this->db->sql_transaction('commit');
	}

	/**
	 * One cron run of filling the table for the articles the board already had: the next
	 * articles by name after where the last run stopped. When none are left, the table is
	 * marked built and the category lists start reading it.
	 *
	 * @param int $limit
	 * @return int How many articles this run worked out
	 */
	public function build_batch($limit = self::BATCH)
	{
		$after = (string) $this->config['wiki_category_members_cursor'];
		$sql = 'SELECT DISTINCT article_url
			FROM ' . $this->article_table . "
			WHERE article_url > '" . $this->db->sql_escape($after) . "'
			ORDER BY article_url ASC";
		$result = $this->db->sql_query_limit($sql, (int) $limit);
		$articles = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$articles[] = $row['article_url'];
		}
		$this->db->sql_freeresult($result);

		foreach ($articles as $article)
		{
			$this->store($article, $this->compute($article));
		}

		if (count($articles) < $limit)
		{
			$this->config->set('wiki_category_members_built', 1);
			$this->config->set('wiki_category_members_cursor', '');
		}
		else
		{
			$this->config->set('wiki_category_members_cursor', end($articles));
		}

		return count($articles);
	}

	/**
	 * The live articles in a category, with their sort keys: sub-categories (category pages)
	 * and the other articles apart, each sorted by key, or by title where there is none.
	 *
	 * @param string $category
	 * @return array Two lists, 'subcategories' and 'articles', of rows with article_url,
	 *               article_title, article_description and sort_key
	 */
	public function members($category)
	{
		$sql = 'SELECT a.article_url, a.article_title, a.article_description, m.sort_key
			FROM ' . $this->member_table . ' m
			JOIN ' . $this->article_table . ' a ON a.article_url = m.article_url
			WHERE m.category = \'' . $this->db->sql_escape(categories::normalise($category)) . '\'
				AND ' . $this->templates->live_where('a', '');
		$result = $this->db->sql_query($sql);
		$lists = array('subcategories' => array(), 'articles' => array());
		while ($row = $this->db->sql_fetchrow($result))
		{
			$lists[strpos($row['article_url'], categories::PREFIX) === 0 ? 'subcategories' : 'articles'][] = $row;
		}
		$this->db->sql_freeresult($result);

		foreach ($lists as &$list)
		{
			usort($list, array(self::class, 'compare'));
		}

		return $lists;
	}

	/**
	 * Order two members: by sort key, or title where there is none, ignoring case.
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	public static function compare(array $a, array $b)
	{
		$key_a = ($a['sort_key'] !== '') ? $a['sort_key'] : htmlspecialchars_decode($a['article_title'], ENT_COMPAT);
		$key_b = ($b['sort_key'] !== '') ? $b['sort_key'] : htmlspecialchars_decode($b['article_title'], ENT_COMPAT);

		return strnatcasecmp($key_a, $key_b) ?: strcmp($a['article_url'], $b['article_url']);
	}

	/**
	 * How many live articles and sub-categories each of these categories holds, read in one
	 * query, for the counts beside sub-categories.
	 *
	 * @param array $categories Category names
	 * @return array Normalised names with 'articles' and 'subcategories' counts
	 */
	public function counts(array $categories)
	{
		$names = array_values(array_unique(array_map(array(categories::class, 'normalise'), $categories)));
		$counts = array_fill_keys($names, array('articles' => 0, 'subcategories' => 0));
		if (!$names)
		{
			return $counts;
		}

		$sql = 'SELECT m.category, a.article_url
			FROM ' . $this->member_table . ' m
			JOIN ' . $this->article_table . ' a ON a.article_url = m.article_url
			WHERE ' . $this->db->sql_in_set('m.category', $names) . '
				AND ' . $this->templates->live_where('a', '');
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$counts[$row['category']][strpos($row['article_url'], categories::PREFIX) === 0 ? 'subcategories' : 'articles']++;
		}
		$this->db->sql_freeresult($result);

		return $counts;
	}

	/**
	 * Every category with a live article in it, and how many (sub-categories included).
	 *
	 * @return array Category names with their member counts, A to Z
	 */
	public function in_use()
	{
		$sql = 'SELECT m.category, COUNT(m.article_url) AS total
			FROM ' . $this->member_table . ' m
			JOIN ' . $this->article_table . ' a ON a.article_url = m.article_url
			WHERE ' . $this->templates->live_where('a', '') . '
			GROUP BY m.category';
		$result = $this->db->sql_query($sql);
		$counts = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$counts[$row['category']] = (int) $row['total'];
		}
		$this->db->sql_freeresult($result);
		ksort($counts, SORT_STRING | SORT_FLAG_CASE);

		return $counts;
	}
}
