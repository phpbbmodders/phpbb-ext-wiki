<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\acp;

/**
 * The Starter templates page under Extensions > Wiki.
 */
class templates_info
{
	public function module()
	{
		return array(
			'filename'	=> '\phpbbmodders\wiki\acp\templates_module',
			'title'		=> 'ACP_WIKI_STARTER_TEMPLATES',
			'modes'		=> array(
				'settings'	=> array(
					'title'	=> 'ACP_WIKI_STARTER_TEMPLATES',
					'auth'	=> 'ext_phpbbmodders/wiki && acl_a_board',
					'cat'	=> array('ACP_WIKI_TITLE'),
				),
			),
		);
	}
}
