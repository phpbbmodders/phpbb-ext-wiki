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
 * Throws away the cached text formatter configuration.
 *
 * add_wiki_markup rebuilds that cache while the extension is being enabled, before
 * its listeners exist, so on a fresh install the cached configuration had no
 * wiki tags (no [[links]], [[File:]] or headings) until something purged the cache.
 * This runs after it and clears the cache; phpBB rebuilds it, with the wiki's tags,
 * on the next page.
 */
class purge_text_formatter_cache extends \phpbb\db\migration\container_aware_migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_wiki_markup',
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
