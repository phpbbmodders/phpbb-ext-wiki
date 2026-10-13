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
 * Registers the Wiki search page in the ACP.
 */
class search_info
{
	public function module()
	{
		return array(
			'filename'	=> '\phpbbmodders\wiki\acp\search_module',
			'title'		=> 'ACP_WIKI_SEARCH',
			'modes'		=> array(
				'settings'	=> array(
					'title'	=> 'ACP_WIKI_SEARCH',
					'auth'	=> 'ext_phpbbmodders/wiki && acl_a_board',
					'cat'	=> array('ACP_WIKI_TITLE'),
				),
			),
		);
	}
}
