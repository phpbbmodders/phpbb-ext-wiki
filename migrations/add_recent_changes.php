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
 * Adds the setting that says which recent changes feed formats are offered.
 */
class add_recent_changes extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_header_icon',
			'\phpbbmodders\wiki\migrations\add_file_uploads',
		);
	}

	public function update_data()
	{
		return array(
			// rss, atom or both
			array('config.add', array('wiki_feed_format', 'both')),
		);
	}
}
