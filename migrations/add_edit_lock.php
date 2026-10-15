<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\migrations;

/**
 * Who has an article's edit form open, for the notice on the edit form and the optional lock
 * (see wiki\edit_lock). A board starts with the notice; the lock is the admin's choice.
 */
class add_edit_lock extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_starter_templates',
		);
	}

	public function update_schema()
	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'wiki_edit_lock'	=> array(
					'COLUMNS'		=> array(
						'article_url'		=> array('VCHAR_UNI:255', ''),
						'user_id'			=> array('UINT', 0),
						'lock_time'			=> array('TIMESTAMP', 0),
						'lock_expires'		=> array('TIMESTAMP', 0),
						'blocked_notified'	=> array('BOOL', 0),
					),
					'PRIMARY_KEY'	=> array('article_url', 'user_id'),
					'KEYS'			=> array(
						'lock_expires'	=> array('INDEX', 'lock_expires'),
					),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'wiki_edit_lock',
			),
		);
	}

	public function update_data()
	{
		return array(
			array('config.add', array('wiki_edit_conflict', 'notice')),
			array('config.add', array('wiki_edit_lock_minutes', 30)),
		);
	}
}
