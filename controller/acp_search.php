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

/**
 * The ACP side of the wiki's search: the settings, and building or deleting the index of
 * the search backend that is chosen. Building the index goes on in steps, so a large wiki
 * does not run out of time; the step it has reached is kept in the setting
 * wiki_search_indexing_state until it finishes or is cancelled.
 */
class acp_search
{
	/** Positions in the saved state of an indexing run */
	const STATE_SEARCH_TYPE = 0;
	const STATE_ACTION = 1;
	const STATE_ARTICLE_COUNTER = 2;

	/** @var \phpbb\config\config */
	protected $config;

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

	/** @var \phpbb\di\service_collection */
	protected $backends;

	/** @var \phpbbmodders\wiki\search\wiki_search_backend_factory */
	protected $factory;

	/** @var \phpbbmodders\wiki\wiki\search_sync */
	protected $search_sync;

	/** @var string */
	protected $u_action = '';

	/**
	 * @param \phpbb\config\config											$config
	 * @param \phpbb\language\language										$language
	 * @param \phpbb\log\log_interface										$log
	 * @param \phpbb\request\request										$request
	 * @param \phpbb\template\template										$template
	 * @param \phpbb\user													$user
	 * @param \phpbb\di\service_collection									$backends	The search backends
	 * @param \phpbbmodders\wiki\search\wiki_search_backend_factory	$factory
	 * @param \phpbbmodders\wiki\wiki\search_sync							$search_sync
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\language\language $language, \phpbb\log\log_interface $log, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbb\di\service_collection $backends, \phpbbmodders\wiki\search\wiki_search_backend_factory $factory, \phpbbmodders\wiki\wiki\search_sync $search_sync)
	{
		$this->config = $config;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->backends = $backends;
		$this->factory = $factory;
		$this->search_sync = $search_sync;
	}

	/**
	 * @param string $u_action The address of the page
	 * @return void
	 */
	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}

	/**
	 * The settings: search on or off, results to a page, and the backend.
	 *
	 * @param string $id Module id
	 * @param string $mode Module mode
	 * @return void
	 */
	public function settings($id, $mode)
	{
		$submit = $this->request->is_set_post('submit');
		if ($submit && !check_form_key('phpbbmodders_wiki_search'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$search_options = '';
		foreach ($this->backends as $search)
		{
			// All three are listed; the ones this database cannot run are shown but cannot be chosen
			$type = $search->get_type();
			if ($search->is_available())
			{
				$selected = ($this->config['wiki_search_type'] === $type) ? ' selected="selected"' : '';
				$search_options .= '<option value="' . $type . '"' . $selected . '>' . $search->get_name() . '</option>';
			}
			else
			{
				$search_options .= '<option value="' . $type . '" disabled="disabled">' . $search->get_name() . ' (' . $this->language->lang('WIKI_SEARCH_BACKEND_UNAVAILABLE') . ')</option>';
			}
		}

		$cfg_array = $this->request->is_set('config') ? $this->request->variable('config', array('' => ''), true) : array();
		$updated = $this->request->variable('updated', false);

		$settings = array('wiki_search' => 'bool', 'wiki_per_page_search' => 'integer:1:9999', 'wiki_search_board' => 'bool', 'wiki_search_board_max' => 'integer:1:50');
		foreach ($settings as $config_name => $var_type)
		{
			if (!isset($cfg_array[$config_name]))
			{
				continue;
			}

			$var_type = explode(':', $var_type);
			$config_value = $cfg_array[$config_name];
			settype($config_value, $var_type[0]);
			if (isset($var_type[1]))
			{
				$config_value = max((int) $var_type[1], $config_value);
			}
			if (isset($var_type[2]))
			{
				$config_value = min((int) $var_type[2], $config_value);
			}

			if ($submit && ($this->config[$config_name] != $config_value))
			{
				$this->config->set($config_name, $config_value);
				$updated = true;
			}
		}

		if ($submit)
		{
			$extra_message = '';
			if ($updated)
			{
				$this->log->add('admin', $this->user->data['user_id'], $this->user->data['user_ip'], 'LOG_WIKI_CONFIG_SEARCH', time());
			}

			if (isset($cfg_array['wiki_search_type']) && ($cfg_array['wiki_search_type'] != $this->config['wiki_search_type']))
			{
				$search = $this->factory->get($cfg_array['wiki_search_type']);
				if (confirm_box(true))
				{
					// A backend that is not ready gives a message; false means it is
					if (!($error = $search->init()))
					{
						$this->config->set('wiki_search_type', $cfg_array['wiki_search_type']);
						$extra_message = '<br>' . $this->language->lang('SWITCHED_SEARCH_BACKEND');
					}
					else
					{
						trigger_error($error . adm_back_link($this->u_action), E_USER_WARNING);
					}
				}
				else
				{
					// The confirmed request is checked against the form key above, so the token
					// it was first sent with has to travel through the confirm page too
					confirm_box(false, $this->language->lang('CONFIRM_SEARCH_BACKEND'), build_hidden_fields(array(
						'i'				=> $id,
						'mode'			=> $mode,
						'submit'		=> true,
						'updated'		=> $updated,
						'config'		=> array('wiki_search_type' => $cfg_array['wiki_search_type']),
						'creation_time'	=> $this->request->variable('creation_time', 0),
						'form_token'	=> $this->request->variable('form_token', ''),
					)));
				}
			}

			trigger_error($this->language->lang('CONFIG_UPDATED') . $extra_message . adm_back_link($this->u_action));
		}

		$this->template->assign_vars(array(
			'S_SEARCH_TYPES'		=> $search_options,
			'S_WIKI_SEARCH'			=> (bool) $this->config['wiki_search'],
			'WIKI_PER_PAGE_SEARCH'	=> $this->config['wiki_per_page_search'] ? (int) $this->config['wiki_per_page_search'] : 10,
			'S_WIKI_SEARCH_BOARD'	=> (bool) $this->config['wiki_search_board'],
			'WIKI_SEARCH_BOARD_MAX'	=> $this->config['wiki_search_board_max'] ? (int) $this->config['wiki_search_board_max'] : 5,
			'S_SETTINGS'			=> true,
			'U_ACTION'				=> $this->u_action . '&amp;hash=' . generate_link_hash('phpbbmodders_wiki_search'),
		));
	}

	/**
	 * Run what the index part of the page asks for: show the backends, continue a run, or
	 * start or stop one.
	 *
	 * @param string $id Module id
	 * @param string $mode Module mode
	 * @return void
	 */
	public function index($id, $mode)
	{
		$action = $this->request->variable('action', '');
		$state = !empty($this->config['wiki_search_indexing_state']) ? explode(',', $this->config['wiki_search_indexing_state']) : array();

		if ($action && !$this->request->is_set_post('cancel'))
		{
			switch ($action)
			{
				case 'progress_bar':
					$this->display_progress_bar();
				break;

				case 'create':
				case 'delete':
					if ($this->request->is_set_post('action') || $this->request->is_set_post('continue'))
					{
						$this->require_form_key();
					}
					else if (empty($state) || $state[self::STATE_ACTION] !== $action || !check_link_hash($this->request->variable('hash', ''), 'phpbbmodders_wiki_search_' . $action))
					{
						trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
					}
					$this->index_action($id, $mode, $action, $state);
				break;

				default:
					trigger_error('NO_ACTION', E_USER_ERROR);
			}

			return;
		}

		if ($this->request->is_set_post('cancel'))
		{
			$this->require_form_key();
			$state = array();
			$this->save_state($state);
		}

		if (!empty($state))
		{
			$this->index_inprogress($state[self::STATE_ACTION]);
		}
		else
		{
			$this->index_overview();
		}
	}

	/**
	 * List the backends with their statistics and the button to build or delete the index.
	 *
	 * @return void
	 */
	protected function index_overview()
	{
		foreach ($this->backends as $search)
		{
			// A backend this database cannot use is listed, but has no index to build
			if (!$search->is_available())
			{
				$this->template->assign_block_vars('backend', array(
					'NAME'			=> $search->get_name(),
					'TYPE'			=> $search->get_type(),
					'S_AVAILABLE'	=> false,
				));
				continue;
			}

			$this->template->assign_block_vars('backend', array(
				'NAME'				=> $search->get_name(),
				'TYPE'				=> $search->get_type(),
				'S_AVAILABLE'		=> true,
				'S_ACTIVE'			=> $search->get_type() === $this->config['wiki_search_type'],
				'S_HIDDEN_FIELDS'	=> build_hidden_fields(array('wiki_search_type' => $search->get_type())),
				'S_INDEXED'			=> $search->index_created(),
			));

			$stats = $search->index_stats();
			if (!empty($stats))
			{
				foreach ($stats as $name => $value)
				{
					$this->template->assign_block_vars('backend.stats', array(
						'NAME'	=> $name,
						'VALUE'	=> $value,
					));
				}
			}
		}

		$this->template->assign_vars(array(
			'U_INDEX_ACTION'	=> $this->u_action,
			'UA_PROGRESS_BAR'	=> addslashes($this->u_action . '&amp;action=progress_bar'),
		));
	}

	/**
	 * The form to continue or cancel a run that was left half done.
	 *
	 * @param string $action 'create' or 'delete'
	 * @return void
	 */
	protected function index_inprogress($action)
	{
		$this->template->assign_vars(array(
			'U_CONTINUE_INDEXING'	=> $this->u_action . '&amp;action=' . $action,
			'UA_PROGRESS_BAR'		=> addslashes($this->u_action . '&amp;action=progress_bar'),
			'L_CONTINUE'			=> ($action === 'create') ? $this->language->lang('CONTINUE_INDEXING') : $this->language->lang('CONTINUE_DELETING_INDEX'),
			'L_CONTINUE_EXPLAIN'	=> ($action === 'create') ? $this->language->lang('CONTINUE_INDEXING_EXPLAIN') : $this->language->lang('CONTINUE_DELETING_INDEX_EXPLAIN'),
			'S_ACTION'				=> $action,
			'S_CONTINUE'			=> true,
		));
	}

	/**
	 * Do a step of building or deleting the index, and keep going until it is done.
	 *
	 * @param string $id Module id
	 * @param string $mode Module mode
	 * @param string $action 'create' or 'delete'
	 * @param array $state The saved state, empty on the first step
	 * @return void
	 */
	protected function index_action($id, $mode, $action, array $state)
	{
		ini_set('memory_limit', '128M');

		if (empty($state))
		{
			if (!$this->request->is_set_post('wiki_search_type'))
			{
				trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$state = array(
				self::STATE_SEARCH_TYPE		=> $this->request->variable('wiki_search_type', ''),
				self::STATE_ACTION			=> $action,
				self::STATE_ARTICLE_COUNTER	=> 0,
			);
			$this->save_state($state);
		}

		$type = $state[self::STATE_SEARCH_TYPE];
		$action = $state[self::STATE_ACTION];
		$article_counter = &$state[self::STATE_ARTICLE_COUNTER];

		$search = $this->factory->get($type);

		try
		{
			if ($action === 'create' && (int) $article_counter === 0)
			{
				// The backends index the search's plain-text copy of the articles, so make it current first
				$this->search_sync->resync_all();
			}

			$status = ($action === 'create') ? $search->create_index($article_counter) : $search->delete_index($article_counter);
			if ($status)
			{
				$this->save_state($state);

				$u_action = append_sid($this->u_action, 'i=' . $id . '&mode=' . $mode . '&action=' . $action . '&hash=' . generate_link_hash('phpbbmodders_wiki_search_' . $action));
				meta_refresh(1, $u_action);

				$message_redirect = $this->language->lang(($action === 'create') ? 'SEARCH_INDEX_CREATE_REDIRECT' : 'SEARCH_INDEX_DELETE_REDIRECT', (int) $status['row_count'], $status['post_counter']);
				$message_rate = $this->language->lang(($action === 'create') ? 'SEARCH_INDEX_CREATE_REDIRECT_RATE' : 'SEARCH_INDEX_DELETE_REDIRECT_RATE', $status['rows_per_second']);
				trigger_error($message_redirect . $message_rate);
			}
		}
		catch (\Exception $e)
		{
			$this->save_state(array());
			trigger_error($e->getMessage() . adm_back_link($this->u_action) . $this->close_popup_js(), E_USER_WARNING);
		}

		$search->tidy();
		$this->save_state(array());

		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, ($action === 'create') ? 'LOG_SEARCH_INDEX_CREATED' : 'LOG_SEARCH_INDEX_REMOVED', false, array($search->get_name()));

		trigger_error($this->language->lang(($action === 'create') ? 'SEARCH_INDEX_CREATED' : 'SEARCH_INDEX_REMOVED') . adm_back_link($this->u_action) . $this->close_popup_js());
	}

	/**
	 * The small window shown while an index is being built.
	 *
	 * @return void
	 */
	protected function display_progress_bar()
	{
		$type = $this->request->variable('type', '');
		$l_type = ($type === 'create') ? 'INDEXING_IN_PROGRESS' : 'DELETING_INDEX_IN_PROGRESS';

		adm_page_header($this->language->lang($l_type));
		$this->template->set_filenames(array('body' => 'progress_bar.html'));
		$this->template->assign_vars(array(
			'L_PROGRESS'			=> $this->language->lang($l_type),
			'L_PROGRESS_EXPLAIN'	=> $this->language->lang($l_type . '_EXPLAIN'),
		));
		adm_page_footer();
	}

	/**
	 * JavaScript that closes the waiting window; it is attached to the messages.
	 *
	 * @return string
	 */
	protected function close_popup_js()
	{
		return "<script type=\"text/javascript\">\n// <![CDATA[\n\tclose_waitscreen = 1;\n// ]]>\n</script>\n";
	}

	/**
	 * @param array $state
	 * @return void
	 */
	protected function save_state(array $state)
	{
		ksort($state);
		$this->config->set('wiki_search_indexing_state', implode(',', $state), true);
	}

	/**
	 * Refuse a form that does not carry the page's token.
	 *
	 * @return void
	 */
	protected function require_form_key()
	{
		if (!check_form_key('phpbbmodders_wiki_search'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}
	}
}
