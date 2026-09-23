<?php
/**
 *
 * @package phpBB Extension - Wiki
 * @copyright (c) 2026 tas2580 (https://tas2580.net)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace tas2580\wiki\migrations;

class add_article_description extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return array(
			'\tas2580\wiki\migrations\text_reparse',
		);
	}

	public function update_schema()
	{
		return array(
			'add_columns'	=> array(
				$this->table_prefix . 'wiki_article'	=> array(
					'article_description'	=> array('VCHAR:255', ''),
				),
			),
		);
	}

	public function revert_schema()
	{
		return array(
			'drop_columns'	=> array(
				$this->table_prefix . 'wiki_article'	=> array(
					'article_description',
				),
			),
		);
	}
}
