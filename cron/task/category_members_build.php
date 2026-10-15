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
 * Fills the table of category memberships for the articles a board had before it existed,
 * a batch at a time on ordinary page views, then stops for good. Until it is done the
 * category lists read the articles' text as before.
 */
class category_members_build extends \phpbb\cron\task\base
{
	/** @var \phpbbmodders\wiki\wiki\category_members */
	protected $category_members;

	/**
	 * @param \phpbbmodders\wiki\wiki\category_members $category_members
	 */
	public function __construct(\phpbbmodders\wiki\wiki\category_members $category_members)
	{
		$this->category_members = $category_members;
	}

	public function run()
	{
		$this->category_members->build_batch();
	}

	public function is_runnable()
	{
		return !$this->category_members->built();
	}

	/**
	 * Every request may take the next batch until the table is built.
	 *
	 * @return bool
	 */
	public function should_run()
	{
		return true;
	}
}
