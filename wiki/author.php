<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace phpbbmodders\wiki\wiki;

/**
 * An author's name as shown on articles and in the version history: it links to
 * that member's contributions page.
 */
class author
{
	/**
	 * @param \phpbb\controller\helper $helper
	 * @param int                      $user_id
	 * @param string                   $username
	 * @param string                   $colour   Six hex digits, or empty
	 * @return string HTML; the plain name for a guest or a deleted member, who have no page
	 */
	public static function link(\phpbb\controller\helper $helper, $user_id, $username, $colour)
	{
		if ((int) $user_id <= ANONYMOUS || $username === null || $username === '')
		{
			return get_username_string('no_profile', $user_id, $username, $colour);
		}

		$url = $helper->route('phpbbmodders_wiki_contributions', array('username' => htmlspecialchars_decode($username, ENT_QUOTES)));

		return '<a href="' . $url . '" class="username' . ($colour ? '-coloured' : '') . '"' . ($colour ? ' style="color: #' . $colour . ';"' : '') . '>' . $username . '</a>';
	}
}
