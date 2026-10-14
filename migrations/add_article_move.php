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
 * The permission to move an article to a new name. Like the other wiki moderation
 * permissions, it is given to whoever has moderator permissions (m_).
 */
class add_article_move extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_wiki_board_search',
		);
	}

	public function update_data()
	{
		return array(
			array('permission.add', array('u_wiki_move', true, 'm_')),
		);
	}
}
