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
 * The Wiki search page: whether the search is on, how many results a page shows, which
 * search backend it uses, and building or deleting the backend's index.
 */
class search_module
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
		$language->add_lang('acp/search');
		$language->add_lang('acp_wiki', 'phpbbmodders/wiki');

		if (!function_exists('adm_back_link'))
		{
			include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
		}

		$controller = $phpbb_container->get('phpbbmodders.wiki.acp_search');
		$controller->set_page_url($this->u_action);

		$this->tpl_name = 'acp_wiki_search';
		$this->page_title = $language->lang('ACP_WIKI_SEARCH');
		add_form_key('phpbbmodders_wiki_search');

		$controller->settings($id, $mode);
		$controller->index($id, $mode);
	}
}
