<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace phpbbmodders\wiki\controller;

use phpbbmodders\wiki\wiki\starter_templates;

/**
 * The Starter templates page in the ACP: get the templates of the wiki-templates repository
 * (upload its zip, download it from GitHub, or copy the files by FTP), see what installing
 * each one would do, and install the ones chosen. Each step is the admin's own button press.
 */
class acp_templates
{
	/** Name of the form key */
	const FORM_KEY = 'phpbbmodders_wiki_templates';

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\files\factory */
	protected $files_factory;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var starter_templates */
	protected $starter_templates;

	/**
	 * @param \phpbb\config\config		$config
	 * @param \phpbb\files\factory		$files_factory
	 * @param \phpbb\language\language	$language
	 * @param \phpbb\log\log_interface	$log
	 * @param \phpbb\request\request	$request
	 * @param \phpbb\template\template	$template
	 * @param \phpbb\user				$user
	 * @param starter_templates			$starter_templates
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\files\factory $files_factory, \phpbb\language\language $language, \phpbb\log\log_interface $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, starter_templates $starter_templates)
	{
		$this->config = $config;
		$this->files_factory = $files_factory;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->starter_templates = $starter_templates;
	}

	/**
	 * Run the button pressed, if any, then show the page.
	 *
	 * @param string $u_action The page's address
	 * @return void
	 */
	public function handle($u_action)
	{
		$actions = array('settings', 'upload', 'download', 'check', 'install');
		foreach ($actions as $action)
		{
			if ($this->request->is_set_post('submit_' . $action))
			{
				if (!check_form_key(self::FORM_KEY))
				{
					trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($u_action), E_USER_WARNING);
				}
				$this->{'do_' . $action}($u_action);
			}
		}

		$this->show($u_action);
	}

	/**
	 * Save whether the board checks the repository for new templates, and how often.
	 *
	 * @param string $u_action
	 * @return void
	 */
	protected function do_settings($u_action)
	{
		$days = $this->request->variable('wiki_starter_check_days', 0);
		if ($days < starter_templates::CHECK_DAYS_MIN || $days > starter_templates::CHECK_DAYS_MAX)
		{
			trigger_error($this->language->lang('WIKI_STARTER_CHECK_DAYS_INVALID', starter_templates::CHECK_DAYS_MIN, starter_templates::CHECK_DAYS_MAX) . adm_back_link($u_action), E_USER_WARNING);
		}

		$this->config->set('wiki_starter_check', $this->request->variable('wiki_starter_check', 0) ? 1 : 0);
		$this->config->set('wiki_starter_check_days', $days);
		trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($u_action));
	}

	/**
	 * Take the templates out of an uploaded zip of the repository.
	 *
	 * @param string $u_action
	 * @return void
	 */
	protected function do_upload($u_action)
	{
		// The upload class words its errors with keys from the posting language file
		$this->language->add_lang('posting');
		$upload = $this->files_factory->get('files.upload')
			->set_allowed_extensions(array('zip'))
			->set_max_filesize(starter_templates::MAX_ZIP_BYTES);
		$file = $upload->handle_upload('files.types.form', 'starter_zip');
		if (empty($file) || !$file->get('uploadname'))
		{
			trigger_error($this->language->lang('NO_UPLOAD_FORM_FOUND') . adm_back_link($u_action), E_USER_WARNING);
		}
		if (count($file->error))
		{
			$errors = $file->error;
			$file->remove();
			trigger_error(implode('<br />', $errors) . adm_back_link($u_action), E_USER_WARNING);
		}

		try
		{
			$count = $this->starter_templates->take_zip($file->get('filename'));
		}
		catch (\RuntimeException $e)
		{
			trigger_error($this->language->lang($e->getMessage()) . adm_back_link($u_action), E_USER_WARNING);
		}
		finally
		{
			$file->remove();
		}
		trigger_error($this->language->lang('WIKI_STARTER_TAKEN', $count) . adm_back_link($u_action));
	}

	/**
	 * Download the repository's zip from GitHub and take its templates. Installs nothing.
	 *
	 * @param string $u_action
	 * @return void
	 */
	protected function do_download($u_action)
	{
		try
		{
			$count = $this->starter_templates->download();
		}
		catch (\RuntimeException $e)
		{
			trigger_error($this->language->lang($e->getMessage()) . adm_back_link($u_action), E_USER_WARNING);
		}
		trigger_error($this->language->lang('WIKI_STARTER_TAKEN', $count) . adm_back_link($u_action));
	}

	/**
	 * Ask GitHub now whether the repository has new or changed templates.
	 *
	 * @param string $u_action
	 * @return void
	 */
	protected function do_check($u_action)
	{
		$changed = $this->starter_templates->check();
		if ($changed === null)
		{
			trigger_error($this->language->lang('WIKI_STARTER_CHECK_FAILED') . adm_back_link($u_action), E_USER_WARNING);
		}
		$message = $changed
			? $this->language->lang('WIKI_STARTER_CHECK_CHANGES', count($changed), htmlspecialchars(implode(', ', $changed), ENT_COMPAT, 'UTF-8'))
			: $this->language->lang('WIKI_STARTER_CHECK_NONE');
		trigger_error($message . adm_back_link($u_action));
	}

	/**
	 * Install the templates ticked in the list.
	 *
	 * @param string $u_action
	 * @return void
	 */
	protected function do_install($u_action)
	{
		$names = array_map(function ($name) {
			return htmlspecialchars_decode($name, ENT_COMPAT);
		}, $this->request->variable('install', array(''), true));
		$installed = $this->starter_templates->install(array_filter($names));
		if (!$installed)
		{
			trigger_error($this->language->lang('WIKI_STARTER_NOTHING_CHOSEN') . adm_back_link($u_action), E_USER_WARNING);
		}
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_WIKI_STARTER_INSTALLED', false, array(implode(', ', $installed)));
		trigger_error($this->language->lang('WIKI_STARTER_INSTALLED', count($installed), htmlspecialchars(implode(', ', $installed), ENT_COMPAT, 'UTF-8')) . adm_back_link($u_action));
	}

	/**
	 * The page: where the templates come from, and the list of what installing would do.
	 *
	 * @param string $u_action
	 * @return void
	 */
	protected function show($u_action)
	{
		add_form_key(self::FORM_KEY);

		$counts = array();
		foreach ($this->starter_templates->scan() as $row)
		{
			$counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
			$this->template->assign_block_vars('starter_templates', array(
				'NAME'			=> $row['name'],
				'STATUS'		=> $this->language->lang('WIKI_STARTER_STATUS_' . strtoupper($row['status'])),
				'REASON'		=> ($row['reason'] !== '') ? $this->language->lang($row['reason']) : '',
				'S_CHOOSABLE'	=> !in_array($row['status'], array(starter_templates::REFUSED, starter_templates::UNCHANGED), true),
				// New and updated templates are ticked; an edited page only when the admin ticks it
				'S_TICKED'		=> in_array($row['status'], array(starter_templates::NEW, starter_templates::UPDATE), true),
				'S_EDITED'		=> $row['status'] === starter_templates::EDITED,
				'S_REFUSED'		=> $row['status'] === starter_templates::REFUSED,
			));
		}

		$last_check = (int) $this->config['wiki_starter_last_check'];
		$this->template->assign_vars(array(
			'U_ACTION'					=> $u_action,
			'STARTER_FOLDER'			=> starter_templates::DIRECTORY,
			'S_FOLDER_WRITABLE'			=> $this->starter_templates->directory() !== '',
			'S_CHECK_ENABLED'			=> $this->starter_templates->check_enabled(),
			'STARTER_CHECK_DAYS'		=> $this->starter_templates->check_days(),
			'STARTER_CHECK_DAYS_MIN'	=> starter_templates::CHECK_DAYS_MIN,
			'STARTER_CHECK_DAYS_MAX'	=> starter_templates::CHECK_DAYS_MAX,
			'STARTER_LAST_CHECK'		=> $last_check ? $this->user->format_date($last_check) : '',
			'STARTER_UPDATES'			=> (int) $this->config['wiki_starter_updates'],
			'STARTER_MAX_ZIP'			=> get_formatted_filesize(starter_templates::MAX_ZIP_BYTES),
			'S_HAS_TEMPLATES'			=> !empty($counts),
		));
	}
}
