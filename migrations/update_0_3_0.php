<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2016 tas2580 (https://tas2580.net)
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\migrations;

class update_0_3_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\initial_module',
			'\phpbbmodders\wiki\migrations\update_0_1_2',
			'\phpbbmodders\wiki\migrations\update_0_2_0',
		);
	}

	public function update_data()
	{
		return array(
			array('permission.add', array('u_wiki_set_sticky', true, 'm_')),
		);
	}

	public function update_schema()
	{
		return array(
			'add_columns' => array(
				$this->table_prefix . 'wiki_article' => array(
					'article_sticky'		=> array('BOOL', 0),
					'article_views'			=> array('UINT', 0),
					'article_redirect'		=> array('VCHAR:255', ''),
				),
			),
		);
	}
}
