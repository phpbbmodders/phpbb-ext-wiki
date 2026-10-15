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
 * Reparses every saved article version, newest first, a batch at a time, after the way the
 * wiki reads its markup has changed (an update, or a new template nesting depth). It runs from
 * a cron task on ordinary page views, where the wiki's own markup is loaded; a migration
 * cannot do it, as the wiki's listeners are not loaded while the extension is being enabled,
 * and a reparse then would turn every heading and template back into plain text. When it is
 * done, the category memberships are worked out again, as calls read differently can change them.
 *
 * The config value wiki_reparse_next holds the highest row id still to reparse, 0 when none.
 */
class article_reparse
{
	/** @var string The config value holding the queue's position */
	const CONFIG = 'wiki_reparse_next';

	/** @var int Rows reparsed per run */
	const BATCH = 50;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbbmodders\wiki\textreparser\plugins\article_text */
	protected $reparser;

	/**
	 * @param \phpbb\config\config									$config		Config object
	 * @param \phpbbmodders\wiki\textreparser\plugins\article_text	$reparser	The articles' text reparser
	 */
	public function __construct(\phpbb\config\config $config, \phpbbmodders\wiki\textreparser\plugins\article_text $reparser)
	{
		$this->config = $config;
		$this->reparser = $reparser;
	}

	/**
	 * Queue every article version, starting again from the newest if a reparse is under way.
	 *
	 * @return void
	 */
	public function queue()
	{
		$this->config->set(self::CONFIG, (int) $this->reparser->get_max_id());
	}

	/**
	 * @return bool Whether some articles are still to be reparsed
	 */
	public function pending()
	{
		return !empty($this->config[self::CONFIG]);
	}

	/**
	 * Reparse the next batch, and move the queue on.
	 *
	 * @return void
	 */
	public function run_batch()
	{
		$end = (int) $this->config[self::CONFIG];
		if ($end < 1)
		{
			return;
		}

		$start = max(1, $end + 1 - self::BATCH);
		$this->reparser->reparse_range($start, $end);

		// Only move on if nothing queued the whole list again meanwhile
		if ($this->config->set_atomic(self::CONFIG, $end, $start - 1) && $start === 1)
		{
			// Done. A call that is read now, or no longer, can add or drop a category, so the
			// category memberships are worked out again by their own cron task (see
			// category_members::build_batch()); until then the category lists read the articles.
			$this->config->set('wiki_category_members_cursor', '');
			$this->config->set('wiki_category_members_built', 0);
		}
	}
}
