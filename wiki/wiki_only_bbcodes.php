<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\wiki;

/**
 * The custom BBCodes an admin has made wiki-only: they work in wiki articles and nowhere else.
 * The choice is kept in the config value wiki_only_bbcodes as a comma-separated list of BBCode ids,
 * so renaming a BBCode in the ACP keeps it.
 */
class wiki_only_bbcodes
{
	/** @var string The config value holding the ids */
	const CONFIG = 'wiki_only_bbcodes';

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $bbcodes_table;

	/** @var array|null The parser tag names, worked out once per request */
	protected $tags;

	/**
	 * @param \phpbb\config\config					$config			Config object
	 * @param \phpbb\db\driver\driver_interface	$db				Database object
	 * @param string								$bbcodes_table	phpBB's BBCodes table
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, $bbcodes_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->bbcodes_table = $bbcodes_table;
	}

	/**
	 * @return int[] The ids of the wiki-only BBCodes
	 */
	public function ids()
	{
		$value = isset($this->config[self::CONFIG]) ? (string) $this->config[self::CONFIG] : '';

		return self::parse_ids($value);
	}

	/**
	 * @param string $value A comma-separated list
	 * @return int[] The positive ids in it, each once
	 */
	public static function parse_ids($value)
	{
		$ids = array_filter(array_map('intval', explode(',', $value)), function ($id) {
			return $id > 0;
		});

		return array_values(array_unique($ids));
	}

	/**
	 * Keep a new choice, leaving out ids that are not custom BBCodes.
	 *
	 * @param int[] $ids
	 * @return void
	 */
	public function save(array $ids)
	{
		$ids = self::parse_ids(implode(',', $ids));
		$known = array_column($this->custom_bbcodes(), 'bbcode_id');
		$ids = array_values(array_intersect($ids, array_map('intval', $known)));
		sort($ids);

		$this->config->set(self::CONFIG, implode(',', $ids));
		$this->tags = null;
	}

	/**
	 * @return array The board's custom BBCodes (bbcode_id, bbcode_tag, bbcode_helpline), by tag
	 */
	public function custom_bbcodes()
	{
		$sql = 'SELECT bbcode_id, bbcode_tag, bbcode_helpline
			FROM ' . $this->bbcodes_table . '
			ORDER BY bbcode_tag';
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * The parser's names for the wiki-only BBCodes. phpBB clears this query's cache whenever
	 * a BBCode is added, changed or deleted in the ACP.
	 *
	 * @return string[]
	 */
	public function tag_names()
	{
		if ($this->tags !== null)
		{
			return $this->tags;
		}

		$this->tags = array();
		$ids = $this->ids();
		if (!$ids)
		{
			return $this->tags;
		}

		$sql = 'SELECT bbcode_tag
			FROM ' . $this->bbcodes_table . '
			WHERE ' . $this->db->sql_in_set('bbcode_id', $ids);
		$result = $this->db->sql_query($sql, 3600);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->tags[] = self::tag_name($row['bbcode_tag']);
		}
		$this->db->sql_freeresult($result);

		return $this->tags;
	}

	/**
	 * @param string $bbcode_tag As phpBB stores it, "color=" for [color=...]
	 * @return string The tag's name in the parser
	 */
	public static function tag_name($bbcode_tag)
	{
		return strtoupper(rtrim($bbcode_tag, '='));
	}
}
