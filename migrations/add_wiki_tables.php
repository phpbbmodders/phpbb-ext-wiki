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
 * HTML tables wiki articles can now use. Boards that already ran
 * purge_text_formatter_cache would otherwise keep a cached configuration without them.
 */
class add_wiki_tables extends \phpbb\db\migration\container_aware_migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\purge_text_formatter_cache',
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
