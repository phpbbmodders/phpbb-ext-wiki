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
 * The Wiki BBCodes page: which of the BBCodes the admin added under Posting, BBCodes work only
 * in wiki articles (see wiki\wiki_only_bbcodes).
 */
class bbcodes_module
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
		global $phpbb_admin_path, $phpbb_container, $phpbb_root_path, $phpEx;

		$language = $phpbb_container->get('language');
		$request = $phpbb_container->get('request');
		$template = $phpbb_container->get('template');
		$wiki_only_bbcodes = $phpbb_container->get('phpbbmodders.wiki.wiki_only_bbcodes');

		if (!function_exists('adm_back_link'))
		{
			include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
		}

		$language->add_lang('acp_wiki', 'phpbbmodders/wiki');

		$this->tpl_name = 'acp_wiki_bbcodes';
		$this->page_title = $language->lang('ACP_WIKI_BBCODES');
		add_form_key('phpbbmodders_wiki_bbcodes');

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('phpbbmodders_wiki_bbcodes'))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$wiki_only_bbcodes->save($request->variable('wiki_only_bbcodes', array(0)));

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$wiki_only = $wiki_only_bbcodes->ids();
		foreach ($wiki_only_bbcodes->custom_bbcodes() as $row)
		{
			$template->assign_block_vars('wiki_only_bbcodes', array(
				'ID'		=> (int) $row['bbcode_id'],
				'TAG'		=> rtrim($row['bbcode_tag'], '='),
				'HELPLINE'	=> $language->is_set($row['bbcode_helpline']) ? $language->lang($row['bbcode_helpline']) : $row['bbcode_helpline'],
				'S_CHECKED'	=> in_array((int) $row['bbcode_id'], $wiki_only, true),
			));
		}

		// The explanations link to phpBB's own BBCodes page
		$u_bbcodes = append_sid("{$phpbb_admin_path}index.$phpEx", 'i=acp_bbcodes&amp;mode=bbcodes');
		$template->assign_vars(array(
			'WIKI_ONLY_BBCODES_EXPLAIN'	=> $language->lang('WIKI_ONLY_BBCODES_EXPLAIN', $u_bbcodes),
			'WIKI_ONLY_BBCODES_NONE'	=> $language->lang('WIKI_ONLY_BBCODES_NONE', $u_bbcodes),
			'U_ACTION'					=> $this->u_action,
		));
	}
}
