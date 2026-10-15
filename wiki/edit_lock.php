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
 * Who has an article's edit form open, so two people do not edit it at once without knowing.
 *
 * Opening the form (and Preview, and a ping from the open form) records the editor until a
 * time that keeps moving while they work. What the board does with that is the admin's
 * choice: nothing, a notice on the edit form naming the others, or a lock that keeps the
 * others out until the editor saves or the time runs out. Moderators can break a lock.
 */
class edit_lock
{
	/** No notice and no lock */
	const MODE_OFF = 'off';

	/** The edit form names who else has it open */
	const MODE_NOTICE = 'notice';

	/** Only the first editor may edit until they save or their time runs out */
	const MODE_LOCK = 'lock';

	/** Minutes an editor is recorded for after their last sign of work */
	const MINUTES_DEFAULT = 30;
	const MINUTES_MIN = 5;
	const MINUTES_MAX = 240;

	/** Seconds between the pings an open edit form sends */
	const PING_SECONDS = 240;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $lock_table;

	/**
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param string							$lock_table
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $lock_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->lock_table = $lock_table;
	}

	/**
	 * What the board does when two people edit an article.
	 *
	 * @return string One of the MODE_ constants
	 */
	public function mode()
	{
		$mode = isset($this->config['wiki_edit_conflict']) ? (string) $this->config['wiki_edit_conflict'] : self::MODE_NOTICE;

		return self::valid_mode($mode) ? $mode : self::MODE_NOTICE;
	}

	/**
	 * @param string $mode
	 * @return bool Whether it is one of the modes
	 */
	public static function valid_mode($mode)
	{
		return in_array($mode, array(self::MODE_OFF, self::MODE_NOTICE, self::MODE_LOCK), true);
	}

	/**
	 * Minutes an editor stays recorded after their last sign of work, as the admin set it.
	 *
	 * @return int
	 */
	public function minutes()
	{
		$minutes = isset($this->config['wiki_edit_lock_minutes']) ? (int) $this->config['wiki_edit_lock_minutes'] : self::MINUTES_DEFAULT;

		return min(self::MINUTES_MAX, max(self::MINUTES_MIN, $minutes));
	}

	/**
	 * The others who have the article's edit form open, first to open it first.
	 *
	 * @param string $article
	 * @param int $user_id The one asking, who is left out
	 * @return array Rows with user_id, username, user_colour, lock_time, lock_expires and blocked_notified
	 */
	public function others($article, $user_id)
	{
		$sql = 'SELECT l.user_id, l.lock_time, l.lock_expires, l.blocked_notified, u.username, u.user_colour
			FROM ' . $this->lock_table . ' l
			LEFT JOIN ' . USERS_TABLE . " u ON u.user_id = l.user_id
			WHERE l.article_url = '" . $this->db->sql_escape($article) . "'
				AND l.user_id <> " . (int) $user_id . '
				AND l.lock_expires > ' . time() . '
			ORDER BY l.lock_time ASC, l.user_id ASC';
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Who keeps this user out of the article, when the board locks articles: the editor who
	 * started first. Normally only one editor is recorded, but after the board switches from
	 * the notice to the lock there can be several, and the first of them keeps the article.
	 *
	 * @param string $article
	 * @param int $user_id
	 * @return array|null The holder's row from others(), or null when the user may edit
	 */
	public function holder($article, $user_id)
	{
		if ($this->mode() !== self::MODE_LOCK)
		{
			return null;
		}
		$others = $this->others($article, $user_id);
		if (!$others)
		{
			return null;
		}

		$sql = 'SELECT lock_time
			FROM ' . $this->lock_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
				AND user_id = " . (int) $user_id . '
				AND lock_expires > ' . time();
		$result = $this->db->sql_query($sql);
		$own_start = $this->db->sql_fetchfield('lock_time');
		$this->db->sql_freeresult($result);

		$first = $others[0];
		if ($own_start !== false && ((int) $own_start < (int) $first['lock_time'] || ((int) $own_start === (int) $first['lock_time'] && (int) $user_id < (int) $first['user_id'])))
		{
			return null;
		}

		return $first;
	}

	/**
	 * Record that the user is editing the article, or extend the time they are recorded for.
	 * The time they started is kept, so the notice says when they began.
	 *
	 * @param string $article
	 * @param int $user_id
	 * @return int When the record runs out
	 */
	public function take($article, $user_id)
	{
		$now = time();
		$expires = $now + $this->minutes() * 60;

		// Old records go whenever anyone edits, so the table stays small
		$this->db->sql_query('DELETE FROM ' . $this->lock_table . ' WHERE lock_expires <= ' . $now);

		$this->db->sql_query('UPDATE ' . $this->lock_table . '
			SET lock_expires = ' . $expires . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
				AND user_id = " . (int) $user_id);
		if (!$this->db->sql_affectedrows())
		{
			$this->db->sql_query('INSERT INTO ' . $this->lock_table . ' ' . $this->db->sql_build_array('INSERT', array(
				'article_url'		=> $article,
				'user_id'			=> (int) $user_id,
				'lock_time'			=> $now,
				'lock_expires'		=> $expires,
				'blocked_notified'	=> 0,
			)));
		}

		return $expires;
	}

	/**
	 * The ping from an open edit form: extend the user's time, unless the board locks
	 * articles and someone else holds this one (their own time ran out, and another
	 * editor came in).
	 *
	 * @param string $article
	 * @param int $user_id
	 * @return int|null When the record now runs out, or null when someone else holds the lock
	 */
	public function renew($article, $user_id)
	{
		if ($this->holder($article, $user_id) !== null)
		{
			return null;
		}

		return $this->take($article, $user_id);
	}

	/**
	 * The user saved, so they no longer hold the article.
	 *
	 * @param string $article
	 * @param int $user_id
	 * @return void
	 */
	public function release($article, $user_id)
	{
		$this->db->sql_query('DELETE FROM ' . $this->lock_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
				AND user_id = " . (int) $user_id);
	}

	/**
	 * A moderator breaks the lock: every record for the article goes.
	 *
	 * @param string $article
	 * @return int How many records there were
	 */
	public function break_lock($article)
	{
		$this->db->sql_query('DELETE FROM ' . $this->lock_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'");

		return (int) $this->db->sql_affectedrows();
	}

	/**
	 * Note that the moderators were told this lock kept someone out, so they are told once
	 * per lock, not each time someone tries.
	 *
	 * @param string $article
	 * @param int $holder_id
	 * @return bool Whether this call made the note (false when it was already made)
	 */
	public function mark_blocked_notified($article, $holder_id)
	{
		$this->db->sql_query('UPDATE ' . $this->lock_table . "
			SET blocked_notified = 1
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
				AND user_id = " . (int) $holder_id . '
				AND blocked_notified = 0');

		return (bool) $this->db->sql_affectedrows();
	}
}
