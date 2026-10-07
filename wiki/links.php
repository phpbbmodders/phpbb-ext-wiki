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
 * Links between wiki articles, written as [[Article name]] or
 * [[Article name|link text]]: which linked articles exist, and which
 * articles link to a given one.
 */
class links
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $article_table;

	/**
	 * @param \phpbb\db\driver\driver_interface	$db				Database object
	 * @param string								$article_table	Wiki article table
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, $article_table)
	{
		$this->db = $db;
		$this->article_table = $article_table;
	}

	/**
	 * Which of the given article names have an approved version.
	 *
	 * @param array $names Article names (their URL part), as written in [[ ]]
	 * @return array The names that exist, as keys
	 */
	public function existing_articles(array $names)
	{
		$names = array_values(array_unique(array_filter($names, 'strlen')));
		if (empty($names))
		{
			return array();
		}

		$sql = 'SELECT DISTINCT article_url
			FROM ' . $this->article_table . '
			WHERE article_approved = 1
				AND ' . $this->db->sql_in_set('article_url', $names);
		$result = $this->db->sql_query($sql);
		$existing = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$existing[$row['article_url']] = true;
		}
		$this->db->sql_freeresult($result);

		return $existing;
	}

	/**
	 * Articles whose live version (the newest approved one) links to an article.
	 *
	 * @param string $name The linked article's name (its URL part)
	 * @return array Rows of article_url and article_title, sorted by title
	 */
	public function what_links_here($name)
	{
		// Links are stored in the parsed text as <WIKILINK name="...">.
		$needle = '<WIKILINK name="' . htmlspecialchars($name, ENT_COMPAT, 'UTF-8') . '"';
		$sql = 'SELECT article_id, article_url, article_title, article_last_edit
			FROM ' . $this->article_table . '
			WHERE article_approved = 1
				AND article_url <> \'' . $this->db->sql_escape($name) . '\'
				AND article_text ' . $this->db->sql_like_expression($this->db->get_any_char() . $needle . $this->db->get_any_char());
		$result = $this->db->sql_query($sql);
		$candidates = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$candidates[(int) $row['article_id']] = $row;
		}
		$this->db->sql_freeresult($result);

		if (empty($candidates))
		{
			return array();
		}

		// Keep only matches that are their article's live version.
		$urls = array_values(array_unique(array_column($candidates, 'article_url')));
		$sql = 'SELECT article_id, article_url
			FROM ' . $this->article_table . '
			WHERE article_approved = 1
				AND ' . $this->db->sql_in_set('article_url', $urls) . '
			ORDER BY article_last_edit DESC, article_id DESC';
		$result = $this->db->sql_query($sql);
		$live = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			if (!isset($live[$row['article_url']]))
			{
				$live[$row['article_url']] = (int) $row['article_id'];
			}
		}
		$this->db->sql_freeresult($result);

		$linking = array();
		foreach ($live as $article_id)
		{
			if (isset($candidates[$article_id]))
			{
				$linking[] = array(
					'article_url'	=> $candidates[$article_id]['article_url'],
					'article_title'	=> $candidates[$article_id]['article_title'],
				);
			}
		}
		usort($linking, function ($a, $b) {
			return strcasecmp($a['article_title'], $b['article_title']);
		});

		return $linking;
	}
}
