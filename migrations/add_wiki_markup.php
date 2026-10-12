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
 * Makes the text formatter learn the headings and table of contents markup, then
 * reparses the saved articles so "== Heading ==" lines in them become headings.
 */
class add_wiki_markup extends \phpbb\db\migration\container_aware_migration
{
	public static function depends_on()
	{
		return array(
			'\phpbbmodders\wiki\migrations\add_recent_changes',
		);
	}

	public function update_data()
	{
		return array(
			array('custom', array(array($this, 'regenerate_formatter'))),
			array('custom', array(array($this, 'reparse'))),
		);
	}

	/**
	 * The text formatter keeps its tag list in a cache; rebuild it so the new tags exist.
	 *
	 * @return void
	 */
	public function regenerate_formatter()
	{
		$this->container->get('text_formatter.s9e.factory')->regenerate();
	}

	/**
	 * Reparse the articles, newest rows first, fifty a step.
	 *
	 * @param int $current A row id to continue from, 0 to start
	 * @return bool|int The next row id, or true when finished
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

		$limit = 50;
		$start = max(1, $current + 1 - $limit);
		$end = max(1, $current);

		$reparser->reparse_range($start, $end);

		$current = $start - 1;

		return ($current === 0) ? true : $current;
	}
}
