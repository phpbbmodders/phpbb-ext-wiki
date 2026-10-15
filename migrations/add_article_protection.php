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
 * Per-article edit protection: the groups whose members may edit an article (see
 * wiki\protection), and the permission to set them, which moderators (m_) get, like the
 * other wiki moderation permissions. No article is protected after the update.
 */
class add_article_protection extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_edit_lock',
		);
	}

	public function update_schema()
	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'wiki_article_protect'	=> array(
					'COLUMNS'		=> array(
						'article_url'	=> array('VCHAR_UNI:255', ''),
						'group_id'		=> array('UINT', 0),
					),
					'PRIMARY_KEY'	=> array('article_url', 'group_id'),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'wiki_article_protect',
			),
		);
	}

	public function update_data()
	{
		return array(
			array('permission.add', array('u_wiki_protect', true, 'm_')),
		);
	}
}
