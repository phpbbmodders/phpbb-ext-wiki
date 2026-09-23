<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2019
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace tas2580\wiki\textreparser\plugins;
class article_text extends \phpbb\textreparser\row_based_plugin
{
	/**
	 * {@inheritdoc}
	 */
	public function get_columns()
	{
		return array(
			'id'			=> 'article_id',
			'text'			=> 'article_text',
			'bbcode_uid'	=> 'bbcode_uid',
		);
	}
}
