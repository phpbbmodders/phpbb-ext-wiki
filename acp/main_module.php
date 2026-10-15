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
 * The Wiki settings page: the header link's icon, edit conflicts, the feed, which forum "Start discussion"
 * creates topics in, and which of the admin's own BBCodes work only in the wiki.
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
		$wiki_only_bbcodes = $phpbb_container->get('phpbbmodders.wiki.wiki_only_bbcodes');

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
			$edit_conflict = $request->variable('wiki_edit_conflict', '');
			$lock_minutes = $request->variable('wiki_edit_lock_minutes', 0);
			$wiki_only = $request->variable('wiki_only_bbcodes', array(0));

			if (!\phpbbmodders\wiki\wiki\edit_lock::valid_mode($edit_conflict))
			{
				trigger_error($language->lang('WIKI_EDIT_CONFLICT_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
			if ($lock_minutes < \phpbbmodders\wiki\wiki\edit_lock::MINUTES_MIN || $lock_minutes > \phpbbmodders\wiki\wiki\edit_lock::MINUTES_MAX)
			{
				trigger_error($language->lang('WIKI_EDIT_LOCK_MINUTES_INVALID', \phpbbmodders\wiki\wiki\edit_lock::MINUTES_MIN, \phpbbmodders\wiki\wiki\edit_lock::MINUTES_MAX) . adm_back_link($this->u_action), E_USER_WARNING);
			}

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
			$config->set('wiki_edit_conflict', $edit_conflict);
			$config->set('wiki_edit_lock_minutes', $lock_minutes);
			$wiki_only_bbcodes->save($wiki_only);

			trigger_error($language->lang('CONFIG_UPDATED') . adm_back_link($this->u_action));
		}

		$feed_format = isset($config['wiki_feed_format']) ? $config['wiki_feed_format'] : \phpbbmodders\wiki\wiki\feed::BOTH;
		$feed_options = '';
		foreach (array(\phpbbmodders\wiki\wiki\feed::BOTH => 'WIKI_FEED_FORMAT_BOTH', \phpbbmodders\wiki\wiki\feed::RSS => 'WIKI_FEED_FORMAT_RSS', \phpbbmodders\wiki\wiki\feed::ATOM => 'WIKI_FEED_FORMAT_ATOM') as $value => $label)
		{
			$feed_options .= '<option value="' . $value . '"' . ($feed_format === $value ? ' selected="selected"' : '') . '>' . $language->lang($label) . '</option>';
		}

		$edit_lock = $phpbb_container->get('phpbbmodders.wiki.edit_lock');
		$edit_conflict_options = '';
		foreach (array(\phpbbmodders\wiki\wiki\edit_lock::MODE_OFF => 'WIKI_EDIT_CONFLICT_OFF', \phpbbmodders\wiki\wiki\edit_lock::MODE_NOTICE => 'WIKI_EDIT_CONFLICT_NOTICE', \phpbbmodders\wiki\wiki\edit_lock::MODE_LOCK => 'WIKI_EDIT_CONFLICT_LOCK') as $value => $label)
		{
			$edit_conflict_options .= '<option value="' . $value . '"' . ($edit_lock->mode() === $value ? ' selected="selected"' : '') . '>' . $language->lang($label) . '</option>';
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

		$template->assign_vars(array(
			'S_WIKI_EDIT_CONFLICT_OPTIONS'	=> $edit_conflict_options,
			'WIKI_EDIT_LOCK_MINUTES'		=> $edit_lock->minutes(),
			'WIKI_EDIT_LOCK_MINUTES_MIN'	=> \phpbbmodders\wiki\wiki\edit_lock::MINUTES_MIN,
			'WIKI_EDIT_LOCK_MINUTES_MAX'	=> \phpbbmodders\wiki\wiki\edit_lock::MINUTES_MAX,
			'S_WIKI_FEED_OPTIONS'		=> $feed_options,
			'S_WIKI_AUTO_WATCH'			=> !isset($config['wiki_auto_watch']) || !empty($config['wiki_auto_watch']),
			'WIKI_ICON'					=> isset($config['wiki_icon']) ? $config['wiki_icon'] : \phpbbmodders\wiki\wiki\icon::DEFAULT_ICON,
			'U_ACTION'					=> $this->u_action,
			'S_WIKI_FORUM_OPTIONS'		=> '<option value="0"' . (empty($config['wiki_discussion_forum_id']) ? ' selected="selected"' : '') . '>' . $language->lang('WIKI_DISCUSSION_FORUM_NONE') . '</option>'
				. make_forum_select((int) $config['wiki_discussion_forum_id'], false, true, true),
		));
	}
}
