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

class rename_article_edit_notification extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_article_description',
		);
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'rename_notification_type'))),
		);
	}

	/**
	 * The notification type class/service was renamed from
	 * "articke_edit" (typo) to "article_edit". Existing installs already
	 * have a row for the old name in the notification types table;
	 * rename it in place so already-stored notifications keep working.
	 */
	public function rename_notification_type()
	{
		$sql = 'UPDATE ' . $this->table_prefix . "notification_types
			SET notification_type_name = 'tas2580.wiki.notification.type.article_edit'
			WHERE notification_type_name = 'tas2580.wiki.notification.type.articke_edit'";
		$this->db->sql_query($sql);

		return true;
	}
}
