<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\cron\task;

/**
 * Reparses the saved articles a batch at a time on ordinary page views, after an update or a
 * change of the template nesting depth (see wiki\article_reparse), then stops until the next one.
 */
class article_reparse extends \phpbb\cron\task\base
{
	/** @var \phpbbmodders\wiki\wiki\article_reparse */
	protected $article_reparse;

	/**
	 * @param \phpbbmodders\wiki\wiki\article_reparse $article_reparse
	 */
	public function __construct(\phpbbmodders\wiki\wiki\article_reparse $article_reparse)
	{
		$this->article_reparse = $article_reparse;
	}

	public function run()
	{
		$this->article_reparse->run_batch();
	}

	public function is_runnable()
	{
		return $this->article_reparse->pending();
	}

	/**
	 * Every request may take the next batch until all are done.
	 *
	 * @return bool
	 */
	public function should_run()
	{
		return true;
	}
}
