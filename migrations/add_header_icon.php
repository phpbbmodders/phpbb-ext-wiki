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
 * Adds the setting for the icon next to the Wiki link in the board header.
 */
class add_header_icon extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_discussion_forum',
		);
	}

	public function update_data()
	{
		return array(
			// The Wikipedia "W" the link has always had; an empty value means no icon
			array('config.add', array('wiki_icon', 'wikipedia-w')),
		);
	}
}
