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
 * The icon next to the Wiki link in the board header.
 *
 * The ACP takes a Font Awesome icon name as phpBB 3.3.x spells it (for example
 * "book"). phpBB 4.0 draws icons with Font Awesome 6 and needs a style class as
 * well; brand icons need "fa-brands", every other icon "fas".
 */
class icon
{
	/** Icon used until the ACP says otherwise */
	const DEFAULT_ICON = 'wikipedia-w';

	/**
	 * Brand icons that phpBB 4.0 must draw with fa-brands. Any other name is
	 * drawn as a solid icon there, so a brand not listed here shows blank on 4.0.
	 */
	const BRANDS = array(
		'android', 'apple', 'bitbucket', 'discord', 'facebook', 'git', 'github', 'gitlab',
		'google', 'instagram', 'linkedin', 'linux', 'php', 'pinterest', 'reddit', 'slack',
		'stack-overflow', 'telegram', 'twitter', 'ubuntu', 'whatsapp', 'wikipedia-w',
		'windows', 'wordpress', 'youtube',
	);

	/**
	 * Whether a value can be stored: an icon name (lower-case letters, digits and
	 * dashes) or the empty "no icon" value.
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function is_valid($name)
	{
		return $name === '' || preg_match('/^[a-z0-9-]+$/', $name) === 1;
	}

	/**
	 * CSS classes of the `<i>` element that draws an icon.
	 *
	 * phpBB 3.3 reads "icon" and "fa-<name>"; phpBB 4.0 reads "o-icon o-icon-font",
	 * "fa-<name>" and the style class. Each version ignores the classes meant for
	 * the other.
	 *
	 * @param string $name Icon name, or '' for no icon
	 * @return string Classes, or '' when there is no icon to draw
	 */
	public static function classes($name)
	{
		if ($name === '' || !self::is_valid($name))
		{
			return '';
		}

		return 'icon o-icon o-icon-font ' . (in_array($name, self::BRANDS, true) ? 'fa-brands' : 'fas') . ' fa-' . $name . ' fa-fw';
	}
}
