<?php
/**
 *
 * @package phpBB Extension - Wiki
 * @copyright (c) 2026 tas2580 (https://tas2580.net)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace tas2580\wiki\migrations;

class rename_article_edit_notification extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\tas2580\wiki\migrations\add_article_description',
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
