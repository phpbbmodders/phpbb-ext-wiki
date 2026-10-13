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
 * [[Category:Name]] tag. Boards that already ran add_wiki_template_element would
 * otherwise keep a cached configuration that has no category tag.
 */
class add_wiki_categories extends \phpbb\db\migration\container_aware_migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_wiki_template_element',
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
