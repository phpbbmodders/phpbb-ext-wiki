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
 * Every few days (one by default, as the admin chooses), when the board has switched it on
 * (off by default), ask GitHub whether the
 * starter templates repository has new or changed templates, and notify the admins. It
 * only checks: downloading and installing are always done by an admin in the ACP.
 */
class starter_templates_check extends \phpbb\cron\task\base
{
	/** @var \phpbbmodders\wiki\wiki\starter_templates */
	protected $starter_templates;

	/**
	 * @param \phpbbmodders\wiki\wiki\starter_templates $starter_templates
	 */
	public function __construct(\phpbbmodders\wiki\wiki\starter_templates $starter_templates)
	{
		$this->starter_templates = $starter_templates;
	}

	/**
	 * @return void
	 */
	public function run()
	{
		$this->starter_templates->check();
	}

	/**
	 * @return bool Whether the board has the check switched on
	 */
	public function is_runnable()
	{
		return $this->starter_templates->check_enabled();
	}

	/**
	 * @return bool Whether the chosen number of days has passed since the last check
	 */
	public function should_run()
	{
		return $this->starter_templates->check_due();
	}
}
