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
 * Adds the table of watched articles and the setting for watching an article by editing it.
 */
class add_article_watch extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_recent_changes',
		);
	}

	public function update_schema()
	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'wiki_watch'	=> array(
					'COLUMNS'	=> array(
						'watch_id'		=> array('UINT', null, 'auto_increment'),
						'user_id'		=> array('UINT', 0),
						// The article's URL name: a watch follows the article, not one version of it
						'article_url'	=> array('VCHAR:255', ''),
						'watch_time'	=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY'	=> 'watch_id',
					'KEYS'			=> array(
						'user_id'		=> array('INDEX', 'user_id'),
					),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'wiki_watch',
			),
		);
	}

	public function update_data()
	{
		return array(
			// 1: saving an edit also watches the article; an administrator can turn it off
			array('config.add', array('wiki_auto_watch', 1)),
		);
	}
}
