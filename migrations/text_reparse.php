<?php
/**
 *
 * @package phpBB Extension - Wiki
 * @copyright (c) 2016 tas2580 (https://tas2580.net)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace tas2580\wiki\migrations;

class text_reparse extends \phpbb\db\migration\container_aware_migration
{
	public static function depends_on()
	{
		return array(
			'\tas2580\wiki\migrations\update_0_3_3',
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
		$reparser = new \tas2580\wiki\textreparser\plugins\article_text(
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
