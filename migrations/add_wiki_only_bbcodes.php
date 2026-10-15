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
 * The custom BBCodes an admin has made wiki-only (see wiki\wiki_only_bbcodes), and the ACP page
 * choosing them. None to start with, so every BBCode keeps working where it did.
 */
class add_wiki_only_bbcodes extends \phpbb\db\migration\migration
{
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

			array('module.add', array(
				'acp',
				'ACP_WIKI_TITLE',
				array(
					'module_basename'	=> '\phpbbmodders\wiki\acp\bbcodes_module',
					'modes'				=> array('settings'),
				),
			)),
		);
	}
}
