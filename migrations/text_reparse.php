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

class text_reparse extends \phpbb\db\migration\container_aware_migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\update_0_3_3',
		);
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'reparse'))),
		);
	}

	/**
	 * Run the wiki article text reparser
	 *
	 * @param int $current A rule identifier
	 * @return bool|int A rule identifier or true if finished
	 */
	public function reparse($current = 0)
	{
		$reparser = new \phpbbmodders\wiki\textreparser\plugins\article_text(
			$this->db,
			$this->container->getParameter('core.table_prefix') . 'wiki_article'
		);

		if (empty($current))
		{
			$current = $reparser->get_max_id();
		}

		$limit 	= 50;
		$start 	= max(1, $current + 1 - $limit);
		$end 	= max(1, $current);

		$reparser->reparse_range($start, $end);

		$current = $start - 1;

		return ($current === 0) ? true : $current;
	}
}
