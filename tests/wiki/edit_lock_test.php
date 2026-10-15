<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\tests\wiki;

use phpbbmodders\wiki\wiki\edit_lock;

/**
 * Who has an article's edit form open: the notice lists the others, the lock lets only the
 * first one in, records run out, saving releases them and a moderator can break them.
 */
class edit_lock_test extends \phpbb_database_test_case
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	public static function setup_extensions()
	{
		return array('phpbbmodders/wiki');
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(__DIR__ . '/fixtures/edit_lock.xml');
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->db = $this->new_dbal();
	}

	/**
	 * @param array $settings
	 * @return edit_lock
	 */
	protected function lock(array $settings = array())
	{
		global $table_prefix;

		return new edit_lock(new \phpbb\config\config($settings), $this->db, $table_prefix . 'wiki_edit_lock');
	}

	public function test_a_board_starts_with_the_notice_and_thirty_minutes()
	{
		$lock = $this->lock();
		$this->assertSame(edit_lock::MODE_NOTICE, $lock->mode());
		$this->assertSame(30, $lock->minutes());

		$this->assertSame(edit_lock::MODE_NOTICE, $this->lock(array('wiki_edit_conflict' => 'nonsense'))->mode());
		$this->assertSame(edit_lock::MINUTES_MIN, $this->lock(array('wiki_edit_lock_minutes' => 1))->minutes());
		$this->assertSame(edit_lock::MINUTES_MAX, $this->lock(array('wiki_edit_lock_minutes' => 9999))->minutes());
	}

	public function test_the_notice_lists_the_others_and_never_locks()
	{
		$lock = $this->lock(array('wiki_edit_conflict' => edit_lock::MODE_NOTICE, 'wiki_edit_lock_minutes' => 30));
		$expires = $lock->take('Main Page', 2);
		$this->assertGreaterThan(time() + 29 * 60, $expires);
		$lock->take('Main Page', 3);

		$others = $lock->others('Main Page', 3);
		$this->assertCount(1, $others);
		$this->assertSame(2, (int) $others[0]['user_id']);
		$this->assertSame(array(), $lock->others('Other Page', 3));
		$this->assertNull($lock->holder('Main Page', 3), 'the notice keeps nobody out');
	}

	public function test_the_lock_lets_only_the_first_editor_in()
	{
		$lock = $this->lock(array('wiki_edit_conflict' => edit_lock::MODE_LOCK));
		$lock->take('Main Page', 2);

		$holder = $lock->holder('Main Page', 3);
		$this->assertNotNull($holder);
		$this->assertSame(2, (int) $holder['user_id']);
		$this->assertNull($lock->holder('Main Page', 2), 'the holder may go on editing');
		$this->assertNull($lock->renew('Main Page', 3), 'someone else cannot take it over');
		$this->assertNotNull($lock->renew('Main Page', 2));
	}

	public function test_after_switching_to_the_lock_the_first_editor_keeps_the_article()
	{
		// Two editors recorded while the board showed the notice
		foreach (array(2 => time() - 300, 3 => time() - 100) as $user_id => $start)
		{
			$this->db->sql_query('INSERT INTO phpbb_wiki_edit_lock ' . $this->db->sql_build_array('INSERT', array(
				'article_url' => 'Main Page', 'user_id' => $user_id, 'lock_time' => $start, 'lock_expires' => time() + 600, 'blocked_notified' => 0,
			)));
		}
		$lock = $this->lock(array('wiki_edit_conflict' => edit_lock::MODE_LOCK));

		$this->assertNull($lock->holder('Main Page', 2), 'the first to start keeps it');
		$this->assertSame(2, (int) $lock->holder('Main Page', 3)['user_id']);
	}

	public function test_taking_again_keeps_when_the_editor_started()
	{
		$lock = $this->lock();
		$this->db->sql_query('INSERT INTO phpbb_wiki_edit_lock ' . $this->db->sql_build_array('INSERT', array(
			'article_url' => 'Main Page', 'user_id' => 2, 'lock_time' => time() - 600, 'lock_expires' => time() + 60, 'blocked_notified' => 0,
		)));
		$lock->take('Main Page', 2);

		$others = $lock->others('Main Page', 3);
		$this->assertSame(time() - 600, (int) $others[0]['lock_time'], 'the start time is kept', 2);
		$this->assertGreaterThan(time() + 60, (int) $others[0]['lock_expires']);
	}

	public function test_a_record_that_ran_out_keeps_nobody_out_and_is_cleared()
	{
		$lock = $this->lock(array('wiki_edit_conflict' => edit_lock::MODE_LOCK));
		$this->db->sql_query('INSERT INTO phpbb_wiki_edit_lock ' . $this->db->sql_build_array('INSERT', array(
			'article_url' => 'Main Page', 'user_id' => 2, 'lock_time' => time() - 3600, 'lock_expires' => time() - 1, 'blocked_notified' => 0,
		)));

		$this->assertNull($lock->holder('Main Page', 3));
		$lock->take('Other Page', 3);
		$result = $this->db->sql_query("SELECT COUNT(*) AS n FROM phpbb_wiki_edit_lock WHERE article_url = 'Main Page'");
		$this->assertSame(0, (int) $this->db->sql_fetchfield('n'), 'taking any article clears old records');
		$this->db->sql_freeresult($result);
	}

	public function test_saving_releases_and_a_moderator_breaks_every_record()
	{
		$lock = $this->lock(array('wiki_edit_conflict' => edit_lock::MODE_LOCK));
		$lock->take('Main Page', 2);
		$lock->release('Main Page', 2);
		$this->assertNull($lock->holder('Main Page', 3));

		$lock->take('Main Page', 2);
		$lock->take('Main Page', 4);
		$this->assertSame(2, $lock->break_lock('Main Page'));
		$this->assertNull($lock->holder('Main Page', 3));
	}

	public function test_the_moderators_are_told_once_per_lock()
	{
		$lock = $this->lock(array('wiki_edit_conflict' => edit_lock::MODE_LOCK));
		$lock->take('Main Page', 2);

		$this->assertTrue($lock->mark_blocked_notified('Main Page', 2));
		$this->assertFalse($lock->mark_blocked_notified('Main Page', 2));
		$this->assertFalse($lock->mark_blocked_notified('Main Page', 9), 'no record, nothing to note');
	}
}
