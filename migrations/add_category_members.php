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
 * Which articles are in which category (see wiki\category_members), with each membership's
 * sort key. The table starts empty: working memberships out means rendering the articles,
 * which a migration cannot do, so a cron task fills it in batches after the update, and the
 * category lists read the articles' text as before until it is done.
 */
class add_category_members extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_article_protection',
		);
	}

	public function update_schema()
	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'wiki_category_member'	=> array(
					'COLUMNS'		=> array(
						'member_id'		=> array('UINT', null, 'auto_increment'),
						'article_url'	=> array('VCHAR_UNI:255', ''),
						'category'		=> array('VCHAR_UNI:255', ''),
						'sort_key'		=> array('VCHAR_UNI:255', ''),
					),
					// A number for the key: two names would be too long a key for SQL Server
					'PRIMARY_KEY'	=> 'member_id',
					'KEYS'			=> array(
						'article_url'	=> array('INDEX', 'article_url'),
						'category'		=> array('INDEX', 'category'),
					),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'wiki_category_member',
			),
		);
	}

	public function update_data()
	{
		return array(
			array('config.add', array('wiki_category_members_built', 0)),
			array('config.add', array('wiki_category_members_cursor', '', true)),
		);
	}
}
