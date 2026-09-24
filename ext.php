<?php
/**
*
* @package phpBB Extension - Wiki
* @copyright (c) 2015 tas2580 (https://tas2580.net)
* @copyright (c) Christian Schnegelberger (Crizz0), phpBB.de - 3.2.x fork
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
* See README.md for full acknowledgments and this fork's changes.
*/
namespace tas2580\wiki;

/**
* @ignore
*/
class ext extends \phpbb\extension\base
{
	public function enable_step($old_state)
	{
		switch ($old_state)
		{
			case '': // Empty means nothing has run yet
				return $this->notification_handler('enable', array(
					'tas2580.wiki.notification.type.article_edit',
				));
			break;
			default:
				// Run parent enable step method
				return parent::enable_step($old_state);
			break;
		}
	}
	public function disable_step($old_state)
	{
		switch ($old_state)
		{
			case '': // Empty means nothing has run yet
				return $this->notification_handler('disable', array(
					'tas2580.wiki.notification.type.article_edit',
				));
			break;
			default:
				// Run parent disable step method
				return parent::disable_step($old_state);
			break;
		}
	}
	public function purge_step($old_state)
	{
		switch ($old_state)
		{
			case '': // Empty means nothing has run yet
				return $this->notification_handler('purge', array(
					'tas2580.wiki.notification.type.article_edit',
				));
			break;
			default:
				// Run parent purge step method
				return parent::purge_step($old_state);
			break;
		}
	}
	protected function notification_handler($step, $notification_types)
	{
		$phpbb_notifications = $this->container->get('notification_manager');
		foreach ($notification_types as $notification_type)
		{
			$phpbb_notifications->{$step . '_notifications'}($notification_type);
		}
		return 'notifications';
	}
}
