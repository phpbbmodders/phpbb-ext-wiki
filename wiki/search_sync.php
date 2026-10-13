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
 * Keeps the search's copy of the wiki current. The search looks at one plain-text row
 * for each article, made from its live version (wiki_search_text), and hands each row to
 * the active search backend to index. Only the live version is searched: older versions
 * and edits waiting for approval are not.
 *
 * Call sync() with an article's address after anything that changes which version is
 * live, and remove() before an article is deleted.
 */
class search_sync
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbbmodders\wiki\search\wiki_search_backend_factory */
	protected $factory;

	/** @var string */
	protected $article_table;

	/** @var string */
	protected $search_text_table;

	/**
	 * @param \phpbb\config\config											$config
	 * @param \phpbb\db\driver\driver_interface								$db
	 * @param \phpbbmodders\wiki\search\wiki_search_backend_factory	$factory	Gives the active search backend
	 * @param string														$article_table
	 * @param string														$search_text_table
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbbmodders\wiki\search\wiki_search_backend_factory $factory, $article_table, $search_text_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->factory = $factory;
		$this->article_table = $article_table;
		$this->search_text_table = $search_text_table;
	}

	/**
	 * Plain text for the search from an article's text as it is written: BBCode, links,
	 * templates, tables, headings and categories reduced to the words they hold.
	 *
	 * @param string $raw The article text as an editor sees it
	 * @return string
	 */
	public static function plain_text($raw)
	{
		$text = htmlspecialchars_decode((string) $raw, ENT_QUOTES);

		$text = preg_replace('/\[\[\s*Category:[^\]]*\]\]/i', ' ', $text);			// [[Category:Name]]
		$text = preg_replace('/\[\[\s*File:[^\]|]*(?:\|([^\]]*))?\]\]/i', ' $1 ', $text);	// [[File:name|alt]] keeps the alt text
		$text = preg_replace('/\[\[[^\]|]*\|([^\]]*)\]\]/', ' $1 ', $text);			// [[Article|text]] keeps the text
		$text = preg_replace('/\[\[([^\]]*)\]\]/', ' $1 ', $text);					// [[Article]]
		$text = preg_replace('/\[\/?[a-z*][^\]\n]*\]/i', ' ', $text);				// [b], [/b], [url=...], [*]
		$text = preg_replace('/<[^>\n]*>/', ' ', $text);							// <table>, <noinclude>, ...
		$text = preg_replace('/\{\{\{?|\}\}\}?|\||^={2,6}|={2,6}[ \t]*$/m', ' ', $text);	// {{templates}}, bars, == headings ==
		$text = preg_replace('/\s+/u', ' ', $text);

		return trim((string) $text);
	}

	/**
	 * The ids, in the search's copy, of the versions of an article.
	 *
	 * @param string $article_url
	 * @return array Rows with article_id and author_id
	 */
	protected function indexed_versions($article_url)
	{
		$sql = 'SELECT s.article_id, s.author_id
			FROM ' . $this->search_text_table . ' s
			INNER JOIN ' . $this->article_table . " a ON (a.article_id = s.article_id)
			WHERE a.article_url = '" . $this->db->sql_escape($article_url) . "'";
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Take versions out of the search's copy and out of the backend's index.
	 *
	 * @param array $rows Rows with article_id and author_id
	 * @return void
	 */
	protected function drop(array $rows)
	{
		if (empty($rows))
		{
			return;
		}

		$ids = array_map('intval', array_column($rows, 'article_id'));
		$authors = array_map('intval', array_column($rows, 'author_id'));

		$this->factory->get_active()->index_remove($ids, $authors);
		$this->db->sql_query('DELETE FROM ' . $this->search_text_table . ' WHERE ' . $this->db->sql_in_set('article_id', $ids));
	}

	/**
	 * Make the search's copy of an article match its live version: add it or update it, and
	 * drop the versions that are no longer live.
	 *
	 * @param string $article_url The article's name as in its address
	 * @return void
	 */
	public function sync($article_url)
	{
		$sql = 'SELECT a.article_id, a.article_title, a.article_description, a.article_text, a.bbcode_uid,
				a.article_last_edit, a.article_user_id, u.username
			FROM ' . $this->article_table . ' a
			LEFT JOIN ' . USERS_TABLE . " u ON (u.user_id = a.article_user_id)
			WHERE a.article_url = '" . $this->db->sql_escape($article_url) . "'
				AND a.article_approved = 1
			ORDER BY a.article_last_edit DESC, a.article_id DESC";
		$result = $this->db->sql_query_limit($sql, 1);
		$live = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$known = $this->indexed_versions($article_url);
		$stale = array_filter($known, function ($row) use ($live) {
			return !$live || (int) $row['article_id'] !== (int) $live['article_id'];
		});
		$this->drop($stale);

		if ($live)
		{
			$this->store($live, count($known) > count($stale));
		}

		$this->count_articles();
	}

	/**
	 * Take an article out of the search before it is deleted, while the versions can still be found.
	 *
	 * @param string $article_url
	 * @return void
	 */
	public function remove($article_url)
	{
		$this->drop($this->indexed_versions($article_url));
		$this->count_articles();
	}

	/**
	 * Put one live version in the search's copy and index it.
	 *
	 * @param array $live Row with the version's fields and the author's name
	 * @param bool $update Whether the search already holds this article
	 * @return void
	 */
	protected function store(array $live, $update)
	{
		$edit = generate_text_for_edit($live['article_text'], $live['bbcode_uid'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);
		$body = self::plain_text($edit['text']);
		$title = (string) $live['article_title'];
		$description = (string) $live['article_description'];
		$author_id = (int) $live['article_user_id'];

		$this->db->sql_query('DELETE FROM ' . $this->search_text_table . ' WHERE article_id = ' . (int) $live['article_id']);
		$this->db->sql_query('INSERT INTO ' . $this->search_text_table . ' ' . $this->db->sql_build_array('INSERT', array(
			'article_id'			=> (int) $live['article_id'],
			'article_category_id'	=> 0,
			'approved'				=> 1,
			'article_title'			=> $title,
			'article_description'	=> $description,
			'article_date'			=> (int) $live['article_last_edit'],
			'author_id'				=> $author_id,
			'author'				=> (string) $live['username'],
			'article_body'			=> $body,
		)));

		$this->factory->get_active()->index($update ? 'edit' : 'post', (int) $live['article_id'], $body, $title, $description, $author_id);
	}

	/**
	 * Rebuild the search's copy from every live article, and drop what no longer has an article.
	 * The backend's own index is built from it in the ACP.
	 *
	 * @return int How many articles the copy holds
	 */
	public function resync_all()
	{
		$this->db->sql_query('DELETE FROM ' . $this->search_text_table);

		$sql = 'SELECT a.article_id, a.article_title, a.article_description, a.article_text, a.bbcode_uid,
				a.article_last_edit, a.article_user_id, u.username
			FROM ' . $this->article_table . ' a
			LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = a.article_user_id)
			WHERE a.article_approved = 1
				AND NOT EXISTS (
					SELECT 1 FROM ' . $this->article_table . ' b
					WHERE b.article_url = a.article_url
						AND b.article_approved = 1
						AND (b.article_last_edit > a.article_last_edit
							OR (b.article_last_edit = a.article_last_edit AND b.article_id > a.article_id)))
			ORDER BY a.article_id';
		$result = $this->db->sql_query($sql);
		while ($live = $this->db->sql_fetchrow($result))
		{
			$edit = generate_text_for_edit($live['article_text'], $live['bbcode_uid'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);
			$this->db->sql_query('INSERT INTO ' . $this->search_text_table . ' ' . $this->db->sql_build_array('INSERT', array(
				'article_id'			=> (int) $live['article_id'],
				'article_category_id'	=> 0,
				'approved'				=> 1,
				'article_title'			=> (string) $live['article_title'],
				'article_description'	=> (string) $live['article_description'],
				'article_date'			=> (int) $live['article_last_edit'],
				'author_id'				=> (int) $live['article_user_id'],
				'author'				=> (string) $live['username'],
				'article_body'			=> self::plain_text($edit['text']),
			)));
		}
		$this->db->sql_freeresult($result);

		return $this->count_articles();
	}

	/**
	 * Keep the setting the backends read for "how many articles" up to date.
	 *
	 * @return int
	 */
	protected function count_articles()
	{
		$result = $this->db->sql_query('SELECT COUNT(article_id) AS total FROM ' . $this->search_text_table);
		$total = (int) $this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);
		$this->config->set('wiki_num_articles', $total, true);

		return $total;
	}
}
