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
 * The starter templates from the phpbbmodders/wiki-templates repository: kept as .wiki files
 * in store/phpbbmodders_wiki/templates/ (from an uploaded zip, or copied there by FTP),
 * compared with the board's Template: pages, and installed as new, live versions of them.
 *
 * What each install wrote is recorded as a checksum per template, so a later install can
 * tell a page still exactly as installed (safe to update) from one edited on the board
 * (only replaced when the admin asks).
 *
 * When the board allows it (off by default), a check every few days (one by default, as
 * the admin chooses) asks GitHub which templates in
 * the repository differ from the files here, comparing git's fingerprint of each file, so
 * nothing is downloaded, and notifies the admins once per new set of changes. Downloading
 * and installing are always the admin's own button presses.
 */
class starter_templates
{
	/** Folder of the template files, inside the extension's protected folder in store/ */
	const DIRECTORY = 'store/phpbbmodders_wiki/templates/';

	/** A template is new on the board */
	const NEW = 'new';

	/** The board's page is the same as the file */
	const UNCHANGED = 'unchanged';

	/** The board's page is exactly what the last install wrote, and the file is newer */
	const UPDATE = 'update';

	/** The board's page was edited since it was installed, or was written on the board */
	const EDITED = 'edited';

	/** The file cannot be installed */
	const REFUSED = 'refused';

	/** The repository's files, as GitHub's API lists them */
	const TREE_HOST = 'api.github.com';
	const TREE_DIRECTORY = '/repos/phpbbmodders/wiki-templates/git/trees';
	const TREE_FILE = 'main?recursive=1';

	/** The repository as a zip */
	const ZIP_HOST = 'codeload.github.com';
	const ZIP_DIRECTORY = '/phpbbmodders/wiki-templates/zip/refs/heads';
	const ZIP_FILE = 'main';

	/** Seconds to wait for GitHub */
	const TIMEOUT = 15;

	/** Largest zip downloaded */
	const MAX_ZIP_BYTES = 10485760;

	/** Days between two checks: the default, and the range an admin can choose from */
	const CHECK_DAYS_DEFAULT = 1;
	const CHECK_DAYS_MIN = 1;
	const CHECK_DAYS_MAX = 365;

	/** The notification's item: there is only ever one, replaced with each new set of changes */
	const NOTIFICATION_ITEM = 1;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\file_downloader */
	protected $downloader;

	/** @var \phpbb\filesystem\filesystem_interface */
	protected $filesystem;

	/** @var \phpbb\notification\manager */
	protected $notification_manager;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var edit */
	protected $edit;

	/** @var string */
	protected $article_table;

	/** @var string */
	protected $install_table;

	/** @var string */
	protected $phpbb_root_path;

	/**
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\file_downloader				$downloader
	 * @param \phpbb\filesystem\filesystem_interface	$filesystem
	 * @param \phpbb\language\language			$language
	 * @param \phpbb\log\log_interface			$log
	 * @param \phpbb\notification\manager		$notification_manager
	 * @param edit								$edit
	 * @param string							$article_table
	 * @param string							$install_table
	 * @param string							$phpbb_root_path
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\file_downloader $downloader, \phpbb\filesystem\filesystem_interface $filesystem, \phpbb\language\language $language, \phpbb\log\log_interface $log, \phpbb\notification\manager $notification_manager, edit $edit, $article_table, $install_table, $phpbb_root_path)
	{
		$this->config = $config;
		$this->downloader = $downloader;
		$this->filesystem = $filesystem;
		$this->log = $log;
		$this->notification_manager = $notification_manager;
		$this->db = $db;
		$this->language = $language;
		$this->edit = $edit;
		$this->article_table = $article_table;
		$this->install_table = $install_table;
		$this->phpbb_root_path = $phpbb_root_path;
	}

	/**
	 * The folder of template files, made when it is missing, with the files in it kept
	 * private as in the rest of the extension's folder.
	 *
	 * @return string Its full path, or "" when it cannot be made
	 */
	public function directory()
	{
		$dir = $this->phpbb_root_path . self::DIRECTORY;
		if (!is_dir($dir))
		{
			@mkdir($dir, 0755, true);
			// The same protection as the extension's folder for uploaded files
			@file_put_contents($dir . '.htaccess', "# Files are only read through the wiki.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n");
			@file_put_contents($dir . 'index.htm', '');
			$this->share($dir, true);
		}

		return (is_dir($dir) && is_writable($dir)) ? $dir : '';
	}

	/**
	 * Let whoever else looks after the board's files (an FTP account, say) replace what the
	 * web server wrote here, the way phpBB does for its own uploads: same owner and group as
	 * the board's files where it can, otherwise the least access that still allows it.
	 *
	 * @param string $path
	 * @param bool $folder
	 * @return void
	 */
	protected function share($path, $folder)
	{
		$perms = \phpbb\filesystem\filesystem_interface::CHMOD_READ | \phpbb\filesystem\filesystem_interface::CHMOD_WRITE;
		try
		{
			$this->filesystem->phpbb_chmod($path, $folder ? $perms | \phpbb\filesystem\filesystem_interface::CHMOD_EXECUTE : $perms);
		}
		catch (\phpbb\filesystem\exception\filesystem_exception $e)
		{
			// The file is still usable by the board; only FTP may not replace it
		}
	}

	/**
	 * Replace the template files with those in an uploaded zip of the repository.
	 *
	 * @param string $zip_path The uploaded zip
	 * @return int How many templates it held
	 * @throws \RuntimeException With a language key, when the zip or the folder cannot be used
	 */
	public function take_zip($zip_path)
	{
		$templates = zip_reader::templates($zip_path);
		if (!$templates)
		{
			throw new \RuntimeException('WIKI_STARTER_ZIP_EMPTY');
		}

		$dir = $this->directory();
		if ($dir === '')
		{
			throw new \RuntimeException('WIKI_STARTER_FOLDER_NOT_WRITABLE');
		}

		$this->share($dir, true);

		// The zip is the whole set: templates it no longer has are not kept
		foreach (glob($dir . '*.wiki') ?: array() as $old)
		{
			@unlink($old);
		}
		foreach ($templates as $name => $text)
		{
			// Only names a page can have are written, so no path can come from the zip
			if (self::valid_name($name))
			{
				if (@file_put_contents($dir . $name . '.wiki', $text) !== false)
				{
					$this->share($dir . $name . '.wiki', false);
				}
			}
		}

		return count($templates);
	}

	/**
	 * Every template file in the folder, with what installing it would do.
	 *
	 * @return array Rows with name, status, reason (for refused ones) and text, A to Z
	 */
	public function scan()
	{
		$dir = $this->phpbb_root_path . self::DIRECTORY;
		$files = glob($dir . '*.wiki') ?: array();
		$rows = array();
		foreach ($files as $file)
		{
			$name = basename($file, '.wiki');
			$text = self::normalise((string) @file_get_contents($file));
			$rows[$name] = array('name' => $name, 'text' => $text) + $this->status($name, $text);
		}
		uksort($rows, 'strcasecmp');

		return array_values($rows);
	}

	/**
	 * What installing one template would do.
	 *
	 * @param string $name
	 * @param string $text The file's text, normalised
	 * @return array status, and reason for a refused one
	 */
	protected function status($name, $text)
	{
		$problem = self::problem($name, $text);
		if ($problem !== '')
		{
			return array('status' => self::REFUSED, 'reason' => $problem);
		}

		$live = $this->live_text(templates::PREFIX . $name);
		if ($live === null)
		{
			return array('status' => self::NEW, 'reason' => '');
		}
		if (self::normalise($live) === $text)
		{
			return array('status' => self::UNCHANGED, 'reason' => '');
		}

		$installed = $this->installed_checksum($name);

		return array(
			'status'	=> ($installed !== null && $installed === sha1(self::normalise($live))) ? self::UPDATE : self::EDITED,
			'reason'	=> '',
		);
	}

	/**
	 * Why a template file cannot be installed: a name a page cannot have, or a header
	 * without the attribution its licence asks for.
	 *
	 * @param string $name
	 * @param string $text
	 * @return string A language key, or "" when it can be installed
	 */
	public static function problem($name, $text)
	{
		if (!self::valid_name($name))
		{
			return 'WIKI_STARTER_BAD_NAME';
		}
		if (!preg_match('#\A<noinclude>(.*?)</noinclude>#s', $text, $header)
			|| !preg_match('#^Source: https://\S+/Template:\S+$#m', $header[1])
			|| !preg_match('#^Licence: CC BY-SA 4\.0, https://creativecommons\.org/licenses/by-sa/4\.0/$#m', $header[1]))
		{
			return 'WIKI_STARTER_NO_ATTRIBUTION';
		}
		if (preg_match('#^Changes: TODO\b|^Needs adapting:#m', $header[1]))
		{
			return 'WIKI_STARTER_NOT_ADAPTED';
		}

		return '';
	}

	/**
	 * Whether a template name can be a page and a file name.
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function valid_name($name)
	{
		return move::valid_name(templates::PREFIX . $name) && $name !== '' && $name[0] !== '.' && trim($name) === $name;
	}

	/**
	 * Text as compared: Unix line breaks, without trailing white space.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function normalise($text)
	{
		return rtrim(str_replace(array("\r\n", "\r"), "\n", $text));
	}

	/**
	 * Install templates from the folder as new, live versions of their pages.
	 *
	 * @param array $names The templates to install
	 * @return array Names installed
	 */
	public function install(array $names)
	{
		$by_name = array();
		foreach ($this->scan() as $row)
		{
			$by_name[$row['name']] = $row;
		}

		$installed = array();
		$reason = $this->language->lang('WIKI_STARTER_EDIT_REASON');
		foreach ($names as $name)
		{
			// Only what the list offered: never a refused file, nor one already the same
			if (!isset($by_name[$name]) || in_array($by_name[$name]['status'], array(self::REFUSED, self::UNCHANGED), true))
			{
				continue;
			}
			$text = $by_name[$name]['text'];
			$article_id = $this->edit->save_starter_template(templates::PREFIX . $name, $text, $reason);

			// The checksum is of the page as the board now has it, as later compared
			$live = $this->live_text(templates::PREFIX . $name);
			$this->db->sql_transaction('begin');
			$this->db->sql_query('DELETE FROM ' . $this->install_table . " WHERE template_name = '" . $this->db->sql_escape($name) . "'");
			$this->db->sql_query('INSERT INTO ' . $this->install_table . ' ' . $this->db->sql_build_array('INSERT', array(
				'template_name'	=> $name,
				'checksum'		=> sha1(self::normalise((string) $live)),
				'article_id'	=> $article_id,
				'install_time'	=> time(),
			)));
			$this->db->sql_transaction('commit');
			$installed[] = $name;
		}

		return $installed;
	}

	/**
	 * The live text of a page, as an editor would see it.
	 *
	 * @param string $article
	 * @return string|null Null when it has no live version
	 */
	protected function live_text($article)
	{
		$sql = 'SELECT article_text, bbcode_uid
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
				AND article_approved = 1
			ORDER BY article_last_edit DESC, article_id DESC";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$row)
		{
			return null;
		}
		$edit = generate_text_for_edit($row['article_text'], $row['bbcode_uid'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);

		return htmlspecialchars_decode($edit['text'], ENT_COMPAT);
	}

	/**
	 * The checksum of what the last install of a template wrote.
	 *
	 * @param string $name
	 * @return string|null Null when it was never installed
	 */
	protected function installed_checksum($name)
	{
		$sql = 'SELECT checksum
			FROM ' . $this->install_table . "
			WHERE template_name = '" . $this->db->sql_escape($name) . "'";
		$result = $this->db->sql_query($sql);
		$checksum = $this->db->sql_fetchfield('checksum');
		$this->db->sql_freeresult($result);

		return ($checksum === false) ? null : $checksum;
	}

	/**
	 * git's fingerprint of a file's content: the blob hash GitHub's API lists for it.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function git_hash($content)
	{
		return sha1('blob ' . strlen($content) . "\0" . $content);
	}

	/**
	 * The templates in a GitHub tree listing that are new or differ from the local files.
	 *
	 * @param array $tree The API's "tree" list
	 * @param array $local Template names with their git fingerprints
	 * @return array Names, A to Z
	 */
	public static function changed_upstream(array $tree, array $local)
	{
		$changed = array();
		foreach ($tree as $item)
		{
			if (isset($item['type'], $item['path'], $item['sha']) && $item['type'] === 'blob'
				&& preg_match('#^templates/([^/]+)\.wiki$#', $item['path'], $match)
				&& self::valid_name($match[1])
				&& (!isset($local[$match[1]]) || $local[$match[1]] !== $item['sha']))
			{
				$changed[] = $match[1];
			}
		}
		sort($changed, SORT_STRING | SORT_FLAG_CASE);

		return $changed;
	}

	/**
	 * The git fingerprint of each template file in the folder.
	 *
	 * @return array Template names with their fingerprints
	 */
	protected function local_hashes()
	{
		$hashes = array();
		foreach (glob($this->phpbb_root_path . self::DIRECTORY . '*.wiki') ?: array() as $file)
		{
			$hashes[basename($file, '.wiki')] = self::git_hash((string) @file_get_contents($file));
		}

		return $hashes;
	}

	/**
	 * Ask GitHub which templates in the repository are new or differ from the files here.
	 * Downloads only the list of files and their fingerprints, never a template.
	 *
	 * @return array Names, A to Z
	 * @throws \RuntimeException When GitHub cannot be reached or answers something else
	 */
	public function remote_changes()
	{
		try
		{
			$json = $this->downloader->get(self::TREE_HOST, self::TREE_DIRECTORY, self::TREE_FILE, 443, self::TIMEOUT);
		}
		catch (\phpbb\exception\runtime_exception $e)
		{
			$json = false;
		}
		$listing = ($json === false) ? null : json_decode($json, true);
		if (!is_array($listing) || !isset($listing['tree']) || !is_array($listing['tree']))
		{
			throw new \RuntimeException('WIKI_STARTER_CHECK_FAILED');
		}

		return self::changed_upstream($listing['tree'], $this->local_hashes());
	}

	/**
	 * The check: record what changed upstream, and notify the admins once per new set of
	 * changes. A failure is logged and waits for the next check; nothing is retried here.
	 *
	 * @return array|null The changed templates, or null when GitHub could not be asked
	 */
	public function check()
	{
		$this->config->set('wiki_starter_last_check', time());
		try
		{
			$changed = $this->remote_changes();
		}
		catch (\RuntimeException $e)
		{
			$this->log->add('critical', ANONYMOUS, '', 'LOG_WIKI_STARTER_CHECK_FAILED');

			return null;
		}

		$this->config->set('wiki_starter_updates', count($changed));
		$fingerprint = $changed ? sha1(implode("\n", $changed) . "\n" . json_encode($this->local_hashes())) : '';
		if ($fingerprint !== $this->config['wiki_starter_notified'])
		{
			// One notification per set of changes: the old one goes, a new set brings a new one
			$this->notification_manager->delete_notifications('phpbbmodders.wiki.notification.type.starter_templates', self::NOTIFICATION_ITEM);
			if ($changed)
			{
				$this->notification_manager->add_notifications('phpbbmodders.wiki.notification.type.starter_templates', array(
					'item_id'	=> self::NOTIFICATION_ITEM,
					'count'		=> count($changed),
				));
			}
			$this->config->set('wiki_starter_notified', $fingerprint);
		}

		return $changed;
	}

	/**
	 * Whether the board has the check switched on.
	 *
	 * @return bool
	 */
	public function check_enabled()
	{
		return !empty($this->config['wiki_starter_check']);
	}

	/**
	 * Days between two checks, as the admin set it, kept within the allowed range.
	 *
	 * @return int
	 */
	public function check_days()
	{
		$days = isset($this->config['wiki_starter_check_days']) ? (int) $this->config['wiki_starter_check_days'] : self::CHECK_DAYS_DEFAULT;

		return min(self::CHECK_DAYS_MAX, max(self::CHECK_DAYS_MIN, $days));
	}

	/**
	 * Whether a check is due: switched on, and the chosen number of days since the last.
	 * A notice can come no more often than that, since only a check makes one.
	 *
	 * @return bool
	 */
	public function check_due()
	{
		return $this->check_enabled()
			&& (int) $this->config['wiki_starter_last_check'] < time() - $this->check_days() * 86400;
	}

	/**
	 * Download the repository's zip from GitHub and take its templates into the folder.
	 * Installs nothing.
	 *
	 * @return int How many templates it held
	 * @throws \RuntimeException With a language key
	 */
	public function download()
	{
		try
		{
			$zip = $this->downloader->get(self::ZIP_HOST, self::ZIP_DIRECTORY, self::ZIP_FILE, 443, self::TIMEOUT);
		}
		catch (\phpbb\exception\runtime_exception $e)
		{
			$zip = false;
		}
		if ($zip === false || $zip === '' || strlen($zip) > self::MAX_ZIP_BYTES)
		{
			throw new \RuntimeException('WIKI_STARTER_DOWNLOAD_FAILED');
		}

		$dir = $this->directory();
		if ($dir === '')
		{
			throw new \RuntimeException('WIKI_STARTER_FOLDER_NOT_WRITABLE');
		}
		$path = $dir . 'download.zip';
		if (@file_put_contents($path, $zip) === false)
		{
			throw new \RuntimeException('WIKI_STARTER_FOLDER_NOT_WRITABLE');
		}
		try
		{
			$count = $this->take_zip($path);
		}
		finally
		{
			@unlink($path);
		}

		// The folder now holds the repository as it is, so nothing is waiting to be fetched
		$this->clear_updates();

		return $count;
	}

	/**
	 * Forget the changes the last check found, and take back its notification.
	 *
	 * @return void
	 */
	protected function clear_updates()
	{
		$this->config->set('wiki_starter_updates', 0);
		$this->config->set('wiki_starter_notified', '');
		$this->notification_manager->delete_notifications('phpbbmodders.wiki.notification.type.starter_templates', self::NOTIFICATION_ITEM);
	}
}
