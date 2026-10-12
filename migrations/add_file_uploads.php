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
 * Adds the table that records an article's uploaded files, and the permission to upload them.
 */
class add_file_uploads extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_discussion_forum',
		);
	}

	public function update_schema()
	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'wiki_file'	=> array(
					'COLUMNS'	=> array(
						'file_id'			=> array('UINT', null, 'auto_increment'),
						// The article's URL name: files belong to the article, not to one version of it
						'article_url'		=> array('VCHAR:255', ''),
						// The name people see and write in [[File:name]]
						'file_name'			=> array('VCHAR:255', ''),
						// The name on disk, which is random and has no extension
						'physical_name'		=> array('VCHAR:255', ''),
						'mimetype'			=> array('VCHAR:100', ''),
						'file_size'			=> array('UINT', 0),
						'is_image'			=> array('BOOL', 0),
						'user_id'			=> array('UINT', 0),
						'file_time'			=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY'	=> 'file_id',
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'wiki_file',
			),
		);
	}

	public function update_data()
	{
		return array(
			// Nobody has it until an administrator gives it
			array('permission.add', array('u_wiki_upload', true)),
		);
	}
}
