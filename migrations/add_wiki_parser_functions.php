<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\migrations;

/**
 * Throws away the cached text formatter configuration so it is rebuilt with the
 * {{#function:}}, {{lc:}} and {{PAGENAME}} forms of the template tag. Boards that
 * already ran add_wiki_templates would otherwise keep the older cached configuration.
 */
class add_wiki_parser_functions extends \phpbb\db\migration\container_aware_migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_wiki_templates',
		);
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'purge_cache'))),
		);
	}

	/**
	 * @return void
	 */
	public function purge_cache()
	{
		$this->container->get('cache')->purge();
	}
}
