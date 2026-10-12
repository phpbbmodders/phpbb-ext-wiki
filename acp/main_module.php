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
 * The Wiki settings page: the header link's icon, and which forum "Start discussion" creates topics in.
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
			$icon = trim($request->variable('wiki_icon', ''));
			$feed_format = $request->variable('wiki_feed_format', '');
			$auto_watch = $request->variable('wiki_auto_watch', 0) ? 1 : 0;

			if (!\phpbbmodders\wiki\wiki\feed::is_valid_setting($feed_format))
			{
				trigger_error($language->lang('WIKI_FEED_FORMAT_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if (!\phpbbmodders\wiki\wiki\icon::is_valid($icon))
			{
				trigger_error($language->lang('WIKI_ICON_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

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
			$config->set('wiki_icon', $icon);
			$config->set('wiki_feed_format', $feed_format);
			$config->set('wiki_auto_watch', $auto_watch);

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$feed_format = isset($config['wiki_feed_format']) ? $config['wiki_feed_format'] : \phpbbmodders\wiki\wiki\feed::BOTH;
		$feed_options = '';
		foreach (array(\phpbbmodders\wiki\wiki\feed::BOTH => 'WIKI_FEED_FORMAT_BOTH', \phpbbmodders\wiki\wiki\feed::RSS => 'WIKI_FEED_FORMAT_RSS', \phpbbmodders\wiki\wiki\feed::ATOM => 'WIKI_FEED_FORMAT_ATOM') as $value => $label)
		{
			$feed_options .= '<option value="' . $value . '"' . ($feed_format === $value ? ' selected="selected"' : '') . '>' . $language->lang($label) . '</option>';
		}

		$template->assign_vars(array(
			'S_WIKI_FEED_OPTIONS'		=> $feed_options,
			'S_WIKI_AUTO_WATCH'			=> !isset($config['wiki_auto_watch']) || !empty($config['wiki_auto_watch']),
			'WIKI_ICON'					=> isset($config['wiki_icon']) ? $config['wiki_icon'] : \phpbbmodders\wiki\wiki\icon::DEFAULT_ICON,
			'U_ACTION'					=> $this->u_action,
			'S_WIKI_FORUM_OPTIONS'		=> '<option value="0"' . (empty($config['wiki_discussion_forum_id']) ? ' selected="selected"' : '') . '>' . $language->lang('WIKI_DISCUSSION_FORUM_NONE') . '</option>'
				. make_forum_select((int) $config['wiki_discussion_forum_id'], false, true, true),
		));
	}
}
