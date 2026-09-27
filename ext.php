<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2015 tas2580 (https://tas2580.net)
 * @copyright (c) Christian Schnegelberger (Crizz0), phpBB.de - 3.2.x fork
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 * See README.md for full acknowledgments and this fork's changes.
 *
 */

namespace phpbbmodders\wiki;

/**
 * Wiki extension base
 *
 * Handles the switch from the old "tas2580/wiki" package name: an existing
 * install's data is moved over to the new name when this extension is
 * enabled, so nothing is installed twice and no settings are lost.
 */
class ext extends \phpbb\extension\base
{
	/** Package name this extension used before the rename */
	const OLD_EXT_NAME = 'tas2580/wiki';

	/** Package name this extension uses now */
	const NEW_EXT_NAME = 'phpbbmodders/wiki';

	/** PHP namespace prefix used before the rename */
	const OLD_NAMESPACE = '\\tas2580\\wiki\\';

	/** PHP namespace prefix used now */
	const NEW_NAMESPACE = '\\phpbbmodders\\wiki\\';

	/**
	 * Notification type service name before and after the rename. Boards
	 * that never ran the rename_article_edit_notification migration still
	 * have the misspelled "articke_edit" name.
	 */
	const OLD_NOTIFICATION_TYPES = [
		'tas2580.wiki.notification.type.article_edit',
		'tas2580.wiki.notification.type.articke_edit',
	];
	const NEW_NOTIFICATION_TYPE = 'phpbbmodders.wiki.notification.type.article_edit';

	/** Text reparser service name (the key of its CLI resume data) before and after the rename */
	const OLD_REPARSER_NAME = 'tas2580.wiki.text_reparser.article_text';
	const NEW_REPARSER_NAME = 'phpbbmodders.wiki.text_reparser.article_text';

	/**
	 * Refuse to enable while the old copy is still enabled; both would run
	 * at once and the old one's data can't be moved while it's in use.
	 *
	 * @return bool|array True if enableable, otherwise an array of reasons
	 */
	public function is_enableable()
	{
		if ($this->container->get('ext.manager')->is_enabled(self::OLD_EXT_NAME))
		{
			return ['Disable the old "' . self::OLD_EXT_NAME . '" extension first (keep its data, do not delete it).'];
		}

		return true;
	}

	/**
	 * Move an old install's data to the new name, then enable as usual.
	 *
	 * @param mixed $old_state State returned by previous call of this method
	 * @return bool|string
	 */
	public function enable_step($old_state)
	{
		if ($old_state === false)
		{
			$this->move_old_install();

			// The migrator loaded its state before this ran; reload it so the
			// moved migration history counts as already installed.
			$this->migrator->load_migration_state();
		}

		switch ($old_state)
		{
			case false:
			case '':
				return $this->notification_handler('enable', [self::NEW_NOTIFICATION_TYPE]);

			default:
				return parent::enable_step($old_state);
		}
	}

	/**
	 * Disable the notification type, then disable as usual.
	 *
	 * @param mixed $old_state State returned by previous call of this method
	 * @return bool|string
	 */
	public function disable_step($old_state)
	{
		switch ($old_state)
		{
			case false:
			case '':
				return $this->notification_handler('disable', [self::NEW_NOTIFICATION_TYPE]);

			default:
				return parent::disable_step($old_state);
		}
	}

	/**
	 * Purge the notification type, then revert migrations as usual.
	 *
	 * @param mixed $old_state State returned by previous call of this method
	 * @return bool|string
	 */
	public function purge_step($old_state)
	{
		switch ($old_state)
		{
			case false:
			case '':
				return $this->notification_handler('purge', [self::NEW_NOTIFICATION_TYPE]);

			default:
				return parent::purge_step($old_state);
		}
	}

	/**
	 * Run a notification manager step for each notification type.
	 *
	 * @param string $step               enable, disable or purge
	 * @param array  $notification_types Notification type service names
	 * @return string
	 */
	protected function notification_handler($step, $notification_types)
	{
		$phpbb_notifications = $this->container->get('notification_manager');

		foreach ($notification_types as $notification_type)
		{
			$phpbb_notifications->{$step . '_notifications'}($notification_type);
		}

		return 'notifications';
	}

	/**
	 * Rewrite everything the database stores under the old name.
	 *
	 * Rows are filtered in PHP rather than with LIKE, because namespace
	 * backslashes and underscores are special characters in LIKE patterns
	 * on some databases. The tables involved are small.
	 */
	protected function move_old_install()
	{
		$db = $this->container->get('dbal.conn');
		$prefix = $this->container->getParameter('core.table_prefix');

		$db->sql_transaction('begin');

		// Migration history, including each migration's dependency list
		$result = $db->sql_query('SELECT migration_name, migration_depends_on FROM ' . $prefix . 'migrations');
		$rows = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		foreach ($rows as $row)
		{
			if ($this->new_class_name($row['migration_name']) === $row['migration_name'])
			{
				continue;
			}

			$depends_on = unserialize($row['migration_depends_on'], ['allowed_classes' => false]);
			$depends_on = is_array($depends_on) ? array_map([$this, 'new_class_name'], $depends_on) : [];

			$db->sql_query('UPDATE ' . $prefix . 'migrations SET ' . $db->sql_build_array('UPDATE', [
				'migration_name'		=> $this->new_class_name($row['migration_name']),
				'migration_depends_on'	=> serialize($depends_on),
			]) . " WHERE migration_name = '" . $db->sql_escape($row['migration_name']) . "'");
		}

		// Module classes and their "ext_vendor/name" auth checks
		$result = $db->sql_query('SELECT module_id, module_basename, module_auth FROM ' . $prefix . 'modules');
		$rows = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		foreach ($rows as $row)
		{
			$basename = $this->new_class_name($row['module_basename']);
			$auth = str_replace('ext_' . self::OLD_EXT_NAME, 'ext_' . self::NEW_EXT_NAME, $row['module_auth']);

			if ($basename !== $row['module_basename'] || $auth !== $row['module_auth'])
			{
				$db->sql_query('UPDATE ' . $prefix . 'modules SET ' . $db->sql_build_array('UPDATE', [
					'module_basename'	=> $basename,
					'module_auth'		=> $auth,
				]) . ' WHERE module_id = ' . (int) $row['module_id']);
			}
		}

		// Notification type, and every user's subscription to it. Rows are
		// moved one at a time: both spellings of the old name can exist, and
		// both tables have unique indexes on the name.
		$types_table = $prefix . 'notification_types';
		$subs_table = $prefix . 'user_notifications';

		foreach (self::OLD_NOTIFICATION_TYPES as $old_type)
		{
			$result = $db->sql_query('SELECT notification_type_id FROM ' . $types_table . "
				WHERE notification_type_name = '" . $db->sql_escape(self::NEW_NOTIFICATION_TYPE) . "'");
			$new_type_exists = $db->sql_fetchfield('notification_type_id') !== false;
			$db->sql_freeresult($result);

			if (!$new_type_exists)
			{
				$db->sql_query('UPDATE ' . $types_table . "
					SET notification_type_name = '" . $db->sql_escape(self::NEW_NOTIFICATION_TYPE) . "'
					WHERE notification_type_name = '" . $db->sql_escape($old_type) . "'");
			}

			$result = $db->sql_query('SELECT item_id, user_id, method FROM ' . $subs_table . "
				WHERE item_type = '" . $db->sql_escape($old_type) . "'");
			$subscriptions = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			foreach ($subscriptions as $row)
			{
				// phpBB 3.3 has no id column here; the unique key identifies a row
				$key = ' AND item_id = ' . (int) $row['item_id'] . '
					AND user_id = ' . (int) $row['user_id'] . "
					AND method = '" . $db->sql_escape($row['method']) . "'";

				$result = $db->sql_query('SELECT user_id FROM ' . $subs_table . "
					WHERE item_type = '" . $db->sql_escape(self::NEW_NOTIFICATION_TYPE) . "'" . $key);
				$duplicate = $db->sql_fetchfield('user_id') !== false;
				$db->sql_freeresult($result);

				$db->sql_query($duplicate
					? 'DELETE FROM ' . $subs_table . "
						WHERE item_type = '" . $db->sql_escape($old_type) . "'" . $key
					: 'UPDATE ' . $subs_table . "
						SET item_type = '" . $db->sql_escape(self::NEW_NOTIFICATION_TYPE) . "'
						WHERE item_type = '" . $db->sql_escape($old_type) . "'" . $key);
			}
		}

		// Resume point of an interrupted "reparser:reparse" CLI run
		$config_text = $this->container->get('config_text');
		$resume = $config_text->get('reparser_resume');
		$resume = !empty($resume) ? unserialize($resume, ['allowed_classes' => false]) : [];
		if (is_array($resume) && isset($resume[self::OLD_REPARSER_NAME]))
		{
			$resume[self::NEW_REPARSER_NAME] = $resume[self::OLD_REPARSER_NAME];
			unset($resume[self::OLD_REPARSER_NAME]);
			$config_text->set('reparser_resume', serialize($resume));
		}

		// The old extension's own record (only once it's disabled)
		$db->sql_query('DELETE FROM ' . $prefix . "ext
			WHERE ext_name = '" . $db->sql_escape(self::OLD_EXT_NAME) . "'
				AND ext_active = 0");

		$db->sql_transaction('commit');
	}

	/**
	 * Map an old fully qualified class name to the new namespace.
	 *
	 * @param string $class_name Class name, possibly under the old namespace
	 * @return string
	 */
	protected function new_class_name($class_name)
	{
		if (!is_string($class_name))
		{
			return $class_name;
		}

		// Class names may be stored with or without the leading backslash
		foreach ([self::OLD_NAMESPACE, ltrim(self::OLD_NAMESPACE, '\\')] as $old)
		{
			if (strpos($class_name, $old) === 0)
			{
				$new = ($old === self::OLD_NAMESPACE) ? self::NEW_NAMESPACE : ltrim(self::NEW_NAMESPACE, '\\');
				return $new . substr($class_name, strlen($old));
			}
		}

		return $class_name;
	}
}
