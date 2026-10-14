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
 * The Starter templates page: see controller\acp_templates.
 */
class templates_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	/**
	 * @param int    $id   Module id
	 * @param string $mode Module mode
	 */
	public function main($id, $mode)
	{
		global $phpbb_container, $phpbb_root_path, $phpEx;

		$language = $phpbb_container->get('language');
		$language->add_lang('acp_wiki', 'phpbbmodders/wiki');

		if (!function_exists('adm_back_link'))
		{
			include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
		}

		$this->tpl_name = 'acp_wiki_templates';
		$this->page_title = $language->lang('ACP_WIKI_STARTER_TEMPLATES');
		$phpbb_container->get('phpbbmodders.wiki.acp_templates')->handle($this->u_action);
	}
}
