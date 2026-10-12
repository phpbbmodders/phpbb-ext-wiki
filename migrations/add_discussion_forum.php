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
 * Adds the "Discussion forum" setting and the ACP page that edits it.
 */
class add_discussion_forum extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_article_description',
		);
	}

	public function update_data()
	{
		return array(
			// 0 means no forum is chosen, so the Start discussion button stays hidden
			array('config.add', array('wiki_discussion_forum_id', 0)),

			array('module.add', array('acp', 'ACP_CAT_DOT_MODS', 'ACP_WIKI_TITLE')),
			array('module.add', array('acp', 'ACP_WIKI_TITLE', array(
				'module_basename'	=> '\phpbbmodders\wiki\acp\main_module',
				'modes'				=> array('settings'),
			))),
		);
	}
}
