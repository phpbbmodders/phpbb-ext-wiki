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
 * The Wiki settings page: which forum "Start discussion" creates topics in.
 */
class main_module
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

		$config = $phpbb_container->get('config');
		$db = $phpbb_container->get('dbal.conn');
		$language = $phpbb_container->get('language');
		$request = $phpbb_container->get('request');
		$template = $phpbb_container->get('template');

		if (!function_exists('make_forum_select'))
		{
			include($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
		}

		$language->add_lang('acp_wiki', 'phpbbmodders/wiki');

		$this->tpl_name = 'acp_wiki_settings';
		$this->page_title = $language->lang('ACP_WIKI_SETTINGS');
		add_form_key('phpbbmodders_wiki_settings');

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('phpbbmodders_wiki_settings'))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$forum_id = $request->variable('wiki_discussion_forum_id', 0);

			// Only a forum that exists and takes posts can hold the topics
			if ($forum_id)
			{
				$sql = 'SELECT forum_id
					FROM ' . FORUMS_TABLE . '
					WHERE forum_id = ' . (int) $forum_id . '
						AND forum_type = ' . FORUM_POST;
				$result = $db->sql_query($sql);
				$exists = $db->sql_fetchfield('forum_id') !== false;
				$db->sql_freeresult($result);

				if (!$exists)
				{
					trigger_error($language->lang('WIKI_DISCUSSION_FORUM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
				}
			}

			$config->set('wiki_discussion_forum_id', $forum_id);

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$template->assign_vars(array(
			'U_ACTION'					=> $this->u_action,
			'S_WIKI_FORUM_OPTIONS'		=> '<option value="0"' . (empty($config['wiki_discussion_forum_id']) ? ' selected="selected"' : '') . '>' . $language->lang('WIKI_DISCUSSION_FORUM_NONE') . '</option>'
				. make_forum_select((int) $config['wiki_discussion_forum_id'], false, true, true),
		));
	}
}
