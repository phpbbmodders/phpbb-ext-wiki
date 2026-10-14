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
 * The Starter templates page: installs templates from the phpbbmodders/wiki-templates
 * repository. A table remembers what each installed template's page held right after the
 * install, so a later install can tell an untouched page from one the board has edited.
 * The check for new templates in the repository starts switched off.
 */
class add_starter_templates extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_article_move',
		);
	}

	public function update_schema()
	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'wiki_template_install'	=> array(
					'COLUMNS'		=> array(
						'template_name'	=> array('VCHAR_UNI:255', ''),
						'checksum'		=> array('CHAR:40', ''),
						'article_id'	=> array('UINT', 0),
						'install_time'	=> array('TIMESTAMP', 0),
					),
					'PRIMARY_KEY'	=> 'template_name',
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'wiki_template_install',
			),
		);
	}

	public function update_data()
	{
		return array(
			array('config.add', array('wiki_starter_check', 0)),
			array('config.add', array('wiki_starter_check_days', 1)),
			array('config.add', array('wiki_starter_last_check', 0, true)),
			array('config.add', array('wiki_starter_updates', 0, true)),
			array('config.add', array('wiki_starter_notified', '', true)),

			array('module.add', array(
				'acp',
				'ACP_WIKI_TITLE',
				array(
					'module_basename'	=> '\phpbbmodders\wiki\acp\templates_module',
					'modes'				=> array('settings'),
				),
			)),
		);
	}
}
