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
 * Per-article edit protection: a moderator names the phpBB groups whose members may edit an
 * article. It narrows the board-wide "Can edit articles" permission for that one article and
 * never widens it; an article with no groups named is open to everyone with that permission,
 * as before. Members with "Can protect an article" may always edit it.
 *
 * Protection covers saving an edit, restoring an older version, uploading files and moving.
 */
class protection
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\group\helper */
	protected $group_helper;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $protect_table;

	/** @var array Group ids per article, as read in this request */
	protected $cache = array();

	/**
	 * @param \phpbb\auth\auth					$auth
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\group\helper				$group_helper
	 * @param \phpbb\user						$user
	 * @param string							$protect_table
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\group\helper $group_helper, \phpbb\user $user, $protect_table)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->group_helper = $group_helper;
		$this->user = $user;
		$this->protect_table = $protect_table;
	}

	/**
	 * The groups whose members may edit the article.
	 *
	 * @param string $article
	 * @return int[] Empty when the article is not protected
	 */
	public function groups($article)
	{
		if (!isset($this->cache[$article]))
		{
			$sql = 'SELECT group_id
				FROM ' . $this->protect_table . "
				WHERE article_url = '" . $this->db->sql_escape($article) . "'
				ORDER BY group_id";
			$result = $this->db->sql_query($sql);
			$groups = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$groups[] = (int) $row['group_id'];
			}
			$this->db->sql_freeresult($result);
			$this->cache[$article] = $groups;
		}

		return $this->cache[$article];
	}

	/**
	 * Whether the viewer may set protection.
	 *
	 * @return bool
	 */
	public function can_protect()
	{
		return (bool) $this->auth->acl_get('u_wiki_protect');
	}

	/**
	 * Whether the viewer may change the article: they may edit articles at all, and the article
	 * is not protected, or they may protect articles, or they belong to one of its groups.
	 *
	 * @param string $article
	 * @return bool
	 */
	public function may_edit($article)
	{
		if (!$this->auth->acl_get('u_wiki_edit'))
		{
			return false;
		}

		return $this->allows($article, (int) $this->user->data['user_id']);
	}

	/**
	 * The protection part of may_edit(), for the article and a member.
	 *
	 * @param string $article
	 * @param int $user_id
	 * @return bool
	 */
	public function allows($article, $user_id)
	{
		$groups = $this->groups($article);
		if (!$groups || $this->can_protect())
		{
			return true;
		}

		$sql = 'SELECT group_id
			FROM ' . USER_GROUP_TABLE . '
			WHERE user_id = ' . (int) $user_id . '
				AND user_pending = 0
				AND ' . $this->db->sql_in_set('group_id', $groups);
		$result = $this->db->sql_query_limit($sql, 1);
		$member = $this->db->sql_fetchfield('group_id') !== false;
		$this->db->sql_freeresult($result);

		return $member;
	}

	/**
	 * Protect the article for these groups, or lift its protection with none.
	 *
	 * @param string $article
	 * @param int[] $group_ids
	 * @return void
	 */
	public function set($article, array $group_ids)
	{
		$group_ids = array_values(array_unique(array_filter(array_map('intval', $group_ids))));

		$this->db->sql_transaction('begin');
		$this->db->sql_query('DELETE FROM ' . $this->protect_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'");
		foreach ($group_ids as $group_id)
		{
			$this->db->sql_query('INSERT INTO ' . $this->protect_table . ' ' . $this->db->sql_build_array('INSERT', array(
				'article_url'	=> $article,
				'group_id'		=> $group_id,
			)));
		}
		$this->db->sql_transaction('commit');

		$this->cache[$article] = $group_ids;
	}

	/**
	 * The article moved to a new name; its protection goes with it.
	 *
	 * @param string $old
	 * @param string $new
	 * @return void
	 */
	public function move($old, $new)
	{
		$this->db->sql_query('UPDATE ' . $this->protect_table . ' SET ' . $this->db->sql_build_array('UPDATE', array('article_url' => $new)) . "
			WHERE article_url = '" . $this->db->sql_escape($old) . "'");
		unset($this->cache[$old], $this->cache[$new]);
	}

	/**
	 * The article was deleted.
	 *
	 * @param string $article
	 * @return void
	 */
	public function delete_for_article($article)
	{
		$this->set($article, array());
	}

	/**
	 * Every group a moderator can choose, A to Z, the bots and AI crawlers left out.
	 *
	 * @return array Group ids with their names as shown
	 */
	public function choosable_groups()
	{
		$sql = 'SELECT group_id, group_name
			FROM ' . GROUPS_TABLE . '
			WHERE ' . $this->db->sql_in_set('group_name', array('BOTS', 'AI_CRAWLERS'), true);
		$result = $this->db->sql_query($sql);
		$groups = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$groups[(int) $row['group_id']] = $this->group_helper->get_name($row['group_name']);
		}
		$this->db->sql_freeresult($result);
		natcasesort($groups);

		return $groups;
	}

	/**
	 * The names of the groups that protect the article, for the note under it.
	 *
	 * @param string $article
	 * @return string[]
	 */
	public function group_names($article)
	{
		$groups = $this->groups($article);
		if (!$groups)
		{
			return array();
		}

		$sql = 'SELECT group_name
			FROM ' . GROUPS_TABLE . '
			WHERE ' . $this->db->sql_in_set('group_id', $groups);
		$result = $this->db->sql_query($sql);
		$names = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$names[] = $this->group_helper->get_name($row['group_name']);
		}
		$this->db->sql_freeresult($result);
		natcasesort($names);

		return array_values($names);
	}
}
