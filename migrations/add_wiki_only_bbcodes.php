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
 * The custom BBCodes an admin has made wiki-only (see wiki\wiki_only_bbcodes). None to start
 * with, so every BBCode keeps working where it did.
 */
class add_wiki_only_bbcodes extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['wiki_only_bbcodes']);
	}

	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_category_members',
		);
	}

	public function update_data()
	{
		return array(
			array('config.add', array('wiki_only_bbcodes', '')),
		);
	}
}
