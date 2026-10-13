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
 * The settings for showing wiki matches next to the results of the board's own search.
 */
class add_wiki_board_search extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_wiki_search',
		);
	}

	public function update_data()
	{
		return array(
			array('config.add', array('wiki_search_board', 1)),
			array('config.add', array('wiki_search_board_max', 5)),
		);
	}
}
