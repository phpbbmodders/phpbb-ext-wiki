<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2015 tas2580 (https://tas2580.net)
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace phpbbmodders\wiki\wiki;

class edit extends \phpbbmodders\wiki\wiki\functions
{

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \parse_message */
	protected $message_parser;

	/** @var \phpbb\notification\manager */
	protected $notification_manager;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbbmodders\wiki\wiki\watch */
	protected $watch;

	/** @var \phpbbmodders\wiki\wiki\templates */
	protected $templates;

	/** @var string article_table */
	protected $article_table;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string php_ext */
	protected $php_ext;

	/** @var array data */
	protected $data;

	/** @var array option */
	protected $option;

	/** @var \phpbbmodders\wiki\wiki\edit_lock */
	protected $edit_lock;

	/** @var \phpbbmodders\wiki\wiki\protection */
	protected $protection;


	/**
	* Constructor
	*
	* @param \phpbb\auth\auth						$auth					Auth object
	* @param \phpbb\config\config					$config
	* @param \phpbb\db\driver\driver_interface		$db						Database object
	* @param \phpbb\controller\helper				$helper					Controller helper object
	* @param \phpbb\notification\manager			$notification_manager
	* @param \phpbb\request\request					$request				Request object
	* @param \phpbb\template\template				$template				Template object
	* @param \phpbb\user							$user
	* @param string									$article_table
	* @param string									$phpbb_root_path
	* @param string									$php_ext
	* @param \phpbbmodders\wiki\wiki\watch				$watch					Watched articles object
	* @param \phpbbmodders\wiki\wiki\templates			$templates				Wiki templates object
	* @param \phpbbmodders\wiki\wiki\search_sync			$search_sync			Keeps the search current
	* @param \phpbbmodders\wiki\wiki\edit_lock			$edit_lock				Who has an article's edit form open
	* @param \phpbbmodders\wiki\wiki\protection			$protection				Which groups may edit an article
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\notification\manager $notification_manager, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, $article_table, $phpbb_root_path, $php_ext, \phpbbmodders\wiki\wiki\watch $watch, \phpbbmodders\wiki\wiki\templates $templates, \phpbbmodders\wiki\wiki\search_sync $search_sync, \phpbbmodders\wiki\wiki\edit_lock $edit_lock, \phpbbmodders\wiki\wiki\protection $protection)
	{
		$this->protection = $protection;
		$this->edit_lock = $edit_lock;
		$this->search_sync = $search_sync;
		$this->templates = $templates;
		$this->watch = $watch;
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->helper = $helper;
		$this->notification_manager = $notification_manager;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->article_table = $article_table;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;

	}

	/**
	 * Set a version of an article as active
	 *
	 * @param	string	$id	Id of the version to delete
	 * @return	object
	 */
	public function active($id)
	{
		if (!$this->auth->acl_get('u_wiki_set_active'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		if (confirm_box(true))
		{
			$article = $this->set_active_version($id);
			$back_url = empty($article) ? $this->helper->route('phpbbmodders_wiki_index', array()) : $this->helper->route('phpbbmodders_wiki_article', array('article'	=> $article));
			trigger_error($this->user->lang('ACTIVATE_VERSION_SUCCESS') . '<br /><br /><a href="' . $back_url . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
		}
		else
		{
			$s_hidden_fields = build_hidden_fields(array(
				'id'    => $id,
			));
			confirm_box(false, $this->user->lang('CONFIRM_ACTIVATE_VERSION'), $s_hidden_fields);
		}
		redirect($this->helper->route('phpbbmodders_wiki_article', array('article' => $article)));
	}

	/**
	 * Restore an old version: save its text, title, description, sources and redirect as a
	 * new version, so the history stays as it was. The new version goes live for members
	 * who may set versions active, and waits for approval for everyone else, as an edit does.
	 *
	 * @param	int		$id	Id of the version to restore
	 * @return	object
	 */
	public function restore($id)
	{
		if (!$this->auth->acl_get('u_wiki_edit'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$sql = 'SELECT *
			FROM ' . $this->article_table . '
			WHERE article_id = ' . (int) $id;
		$result = $this->db->sql_query($sql);
		$old = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if (!$old)
		{
			trigger_error('NO_ARTICLE');
		}
		$this->protected_out($old['article_url']);
		$article = $old['article_url'];

		if (!confirm_box(true))
		{
			confirm_box(false, $this->user->lang('CONFIRM_RESTORE_VERSION', $this->user->format_date($old['article_last_edit'])), build_hidden_fields(array(
				'id'	=> (int) $id,
			)));
			redirect($this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'versions')));
		}

		// Views, sticky and the discussion topic belong to the article, not to a version,
		// so they come from its newest version
		$sql = 'SELECT article_views, article_sticky, article_topic_id, article_time_created
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
			ORDER BY article_last_edit DESC, article_id DESC";
		$result = $this->db->sql_query_limit($sql, 1);
		$newest = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$sql_data = array(
			'article_title'			=> $old['article_title'],
			'article_url'			=> $article,
			'article_text'			=> $old['article_text'],
			'bbcode_uid'			=> $old['bbcode_uid'],
			'bbcode_bitfield'		=> $old['bbcode_bitfield'],
			'article_approved'		=> 0,
			'article_user_id'		=> (int) $this->user->data['user_id'],
			'article_last_edit'		=> time(),
			'article_time_created'	=> (int) $newest['article_time_created'],
			'article_edit_reason'	=> $this->user->lang('RESTORED_VERSION_REASON', $this->user->format_date($old['article_last_edit'])),
			'article_topic_id'		=> (int) $newest['article_topic_id'],
			'article_sources'		=> $old['article_sources'],
			'article_sticky'		=> (int) $newest['article_sticky'],
			'article_views'			=> (int) $newest['article_views'],
			'article_redirect'		=> $old['article_redirect'],
			'article_description'	=> $old['article_description'],
			'article_toc'			=> '',
		);
		$sql = 'INSERT INTO ' . $this->article_table . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
		$this->db->sql_query($sql);
		$article_id = (int) $this->db->sql_nextid();

		if (!empty($this->config['wiki_auto_watch']))
		{
			$this->watch->add($this->user->data['user_id'], $article);
		}

		if ($this->auth->acl_get('u_wiki_set_active'))
		{
			$this->set_active_version($article_id);
			$msg = $this->user->lang('RESTORE_VERSION_SUCCESS');
		}
		else
		{
			$this->notification_manager->add_notifications('phpbbmodders.wiki.notification.type.article_edit', array(
				'article_id'		=> $article_id,
				'article_title'		=> $old['article_title'],
				'article_url'		=> $article,
				'user_id'			=> $this->user->data['user_id'],
			));
			$msg = $this->user->lang('RESTORE_VERSION_SUCCESS_INACTIVE');
		}

		$back_url = $this->helper->route('phpbbmodders_wiki_article', array('article' => $article));
		trigger_error($msg . '<br /><br /><a href="' . $back_url . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
	}

	/**
	 * Save a starter template as a new, live version of its Template: page, parsed as text
	 * typed in the edit form is: escaped as phpBB's request escapes it, then parsed with the
	 * wiki's markup on. Watchers are notified and the search index updated, as for an edit
	 * that goes live. Views, sticky, the discussion topic and the description of an existing
	 * page are kept.
	 *
	 * @param string $article The page's name, "Template:Name"
	 * @param string $text The template's text, as written in its file
	 * @param string $reason The edit reason
	 * @return int The new version's id
	 */
	public function save_starter_template($article, $text, $reason)
	{
		$sql = 'SELECT article_views, article_sticky, article_topic_id, article_time_created, article_description
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
			ORDER BY article_last_edit DESC, article_id DESC";
		$result = $this->db->sql_query_limit($sql, 1);
		$newest = $this->db->sql_fetchrow($result) ?: array();
		$this->db->sql_freeresult($result);

		$typed = $text;
		(new \phpbb\request\type_cast_helper())->set_var($text, $typed, 'string', true);
		$uid = $bitfield = '';
		$flags = 0;
		\phpbbmodders\wiki\wiki\markup::set_active(true);
		try
		{
			generate_text_for_storage($text, $uid, $bitfield, $flags, true, true, true);
		}
		finally
		{
			\phpbbmodders\wiki\wiki\markup::set_active(false);
		}

		$now = time();
		$sql = 'INSERT INTO ' . $this->article_table . ' ' . $this->db->sql_build_array('INSERT', array(
			'article_title'			=> $article,
			'article_url'			=> $article,
			'article_text'			=> $text,
			'bbcode_uid'			=> $uid,
			'bbcode_bitfield'		=> $bitfield,
			'article_approved'		=> 0,
			'article_user_id'		=> (int) $this->user->data['user_id'],
			'article_last_edit'		=> $now,
			'article_time_created'	=> isset($newest['article_time_created']) ? (int) $newest['article_time_created'] : $now,
			'article_edit_reason'	=> $reason,
			'article_topic_id'		=> isset($newest['article_topic_id']) ? (int) $newest['article_topic_id'] : 0,
			'article_sources'		=> '',
			'article_sticky'		=> isset($newest['article_sticky']) ? (int) $newest['article_sticky'] : 0,
			'article_views'			=> isset($newest['article_views']) ? (int) $newest['article_views'] : 0,
			'article_redirect'		=> '',
			'article_description'	=> isset($newest['article_description']) ? $newest['article_description'] : '',
			'article_toc'			=> '',
		));
		$this->db->sql_query($sql);
		$article_id = (int) $this->db->sql_nextid();

		$this->set_active_version($article_id);

		return $article_id;
	}

	/**
	 * Set a version of an article as active
	 *
	 * @param	string	$id	Id of the version to delete
	 * @return	object
	 */
	public function deactivate($article)
	{
		if (!$this->auth->acl_get('u_wiki_set_active'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		if (confirm_box(true))
		{
			$article = self::confirmed_article($this->request, $article);

			// Set all versions to not approved
			$sql = 'UPDATE ' . $this->article_table . "
				SET article_approved = 0
				WHERE article_url = '" . $this->db->sql_escape($article) . "'";
			$this->db->sql_query($sql);
			$this->sync_search($article);

			trigger_error($this->user->lang('DEACTIVATE_ARTICLE_SUCCESS') . '<br /><br /><a href="' . $this->helper->route('phpbbmodders_wiki_index', array()) . '">' . $this->user->lang('BACK_TO_WIKI') . '</a>');
		}
		else
		{
			$s_hidden_fields = build_hidden_fields(array(
				'article'    => $article,
			));
			confirm_box(false, $this->user->lang('CONFIRM_DEACTIVATE_ARTICLE'), $s_hidden_fields);
		}
		redirect($this->helper->route('phpbbmodders_wiki_article', array('article' => $article)));
	}


	/**
	 * Edit an article
	 *
	 * @param	string	$article	URL of the article
	 * @return	object
	 */
	public function edit_article($article)
	{
		// @TODO
		$this->option['bbcode'] = $this->option['url'] = $this->option['img'] = $this->option['flash'] = $this->option['quote'] = $this->option['smilies'] = true;

		// If no auth to edit, display error message
		if (!$this->auth->acl_get('u_wiki_edit'))
		{
			trigger_error('NO_ARTICLE');
		}
		$this->protected_out($article);

		// Setup message parser
		$this->message_parser = $this->setup_parser();

		// Get data for article
		$this->data = $this->get_article_data($article);

		// Article is a redirect and no auth to edit redirect
		if (!empty($this->data['article_redirect']) && !$this->auth->acl_get('u_wiki_set_redirect'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$this->user->add_lang('posting');

		$preview = $this->request->is_set_post('preview');
		$submit = $this->request->is_set_post('submit');
		$error = array();

		// When the board locks articles, someone else may hold this one
		$user_id = (int) $this->user->data['user_id'];
		$holder = $this->edit_lock->holder($article, $user_id);
		if ($holder !== null && !$preview && !$submit)
		{
			$this->locked_out($article, $holder);
		}
		if ($preview || $submit)
		{
			$this->data['article_title']		= $this->request->variable('title', '', true);
			$this->data['article_text']			= $this->request->variable('message', '', true);
			$this->data['article_description']	= $this->request->variable('article_description', '', true);
			$this->data['article_edit_reason']	= $this->request->variable('edit_reason', '', true);
			$this->data['article_sources']		= $this->request->variable('sources', '', true);
			$this->data['article_topic_id']		= $this->auth->acl_get('u_wiki_edit_topic') ? $this->request->variable('topic_id', '', true) : $this->data['article_topic_id'];
			$this->data['article_approved']		= $this->auth->acl_get('u_wiki_set_active') ? $this->request->variable('set_active', 0) : 0;
			$this->data['article_sticky']		= $this->auth->acl_get('u_wiki_set_sticky') ? $this->request->variable('set_sticky', 0) : $this->data['article_sticky'];
			$this->data['article_redirect']		= $this->auth->acl_get('u_wiki_set_redirect') ? $this->request->variable('article_redirect', '', true) : $this->data['article_redirect'];
			$this->data['article_time_created'] = empty($this->data['article_time_created']) ? time() : $this->data['article_time_created'];

			// Validate user input
			$validate_array = array(
				'article_title'				=> array('string', false, 1, 255),
				'article_text'				=> array('string', false, $this->config['min_post_chars'], $this->config['max_post_chars']),
				'article_edit_reason'		=> array('string', true, 0, 255),
				'article_redirect'			=> array('string', true, 0, 255),
				'article_description'		=> array('string', true, 0, 255),
				'article_sources'			=> array('string', true, 0, 255),
			);

			if (!function_exists('validate_data'))
			{
				include($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
			}
			$error = validate_data($this->data, $validate_array);

			// Validate sources URL
			$sources_array = explode("\n", $this->data['article_sources']);
			foreach ($sources_array as $source)
			{
				if (!empty($source) && !filter_var($source, FILTER_VALIDATE_URL))
				{
					$error[] = $this->user->lang('INVALID_SOURCE_URL');
				}
			}

			// check form
			if (($submit || $preview) && !check_form_key('article'))
			{
				$error[] = $this->user->lang('FORM_INVALID');
			}

			$this->message_parser->message = $this->data['article_text'];

			// The lock was taken while this editor had the form open: keep their text, do not save
			if ($holder !== null)
			{
				$error[] = $this->user->lang('WIKI_EDIT_LOCKED_SAVE', $this->holder_name($holder), $this->user->format_date($holder['lock_expires']));
				$this->notify_locked_out($article, $holder);
			}
		}

		if ($holder === null)
		{
			$this->track_editing($article, $user_id);
		}

		if (sizeof($error))
		{
			$this->template->assign_vars(array(
				'ERROR'			=> implode('<br />', $error),
			));
			$this->display_edit_form(false);
		}
		else if ($preview) // Display the preview
		{
			\phpbbmodders\wiki\wiki\markup::set_active(true);
			$this->message_parser->parse($this->option['bbcode'], $this->option['url'], $this->option['smilies'], $this->option['img'], $this->option['flash'], $this->option['quote']);
			\phpbbmodders\wiki\wiki\markup::set_active(false);
			\phpbbmodders\wiki\wiki\files::set_context($article);
			$this->message_parser->format_display($this->option['bbcode'], $this->option['url'], $this->option['smilies']);
			\phpbbmodders\wiki\wiki\files::set_context(null);

			foreach ($sources_array as $source)
			{
				if (!empty($source))
				{
					$this->template->assign_block_vars('article_sources', array(
						'SOURCE'		=> $source,
					));
				}
			}

			$this->display_edit_form(true);
		}
		else if ($submit) // Submit the article to database
		{
			// A "#REDIRECT [[Article]]" first line is a shortcut for the redirect field
			$redirect_target = \phpbbmodders\wiki\wiki\markup::redirect_target(htmlspecialchars_decode($this->data['article_text'], ENT_COMPAT));
			if ($redirect_target !== '' && $this->auth->acl_get('u_wiki_set_redirect') && empty($this->data['article_redirect']))
			{
				$this->data['article_redirect'] = htmlspecialchars($redirect_target, ENT_COMPAT, 'UTF-8');
			}

			\phpbbmodders\wiki\wiki\markup::set_active(true);
			$this->message_parser->parse($this->option['bbcode'], $this->option['url'], $this->option['smilies'], $this->option['img'], $this->option['flash'], $this->option['quote']);
			\phpbbmodders\wiki\wiki\markup::set_active(false);
			$sql_data = array(
				'article_title'			=> $this->data['article_title'],
				'article_url'			=> $article,
				'article_text'			=> $this->message_parser->message,
				'bbcode_uid'			=> $this->message_parser->bbcode_uid,
				'bbcode_bitfield'		=> $this->message_parser->bbcode_bitfield,
				'article_approved'		=> (int) $this->data['article_approved'],
				'article_user_id'		=> (int) $this->user->data['user_id'],
				'article_last_edit'		=> time(),
				'article_time_created'	=> $this->data['article_time_created'],
				'article_edit_reason'	=> $this->data['article_edit_reason'],
				'article_topic_id'		=> (int) $this->data['article_topic_id'],
				'article_sources'		=> $this->data['article_sources'],
				'article_sticky'		=> (int) $this->data['article_sticky'],
				'article_views'			=> (int) $this->data['article_views'],
				'article_redirect'		=> $this->data['article_redirect'],
				'article_description'	=> $this->data['article_description'],
				'article_toc'			=> '',
			);
			$sql = 'INSERT INTO ' . $this->article_table . '
				' . $this->db->sql_build_array('INSERT', $sql_data);
			$this->db->sql_query($sql);
			$article_id = $this->db->sql_nextid();
			$this->edit_lock->release($article, $user_id);

			// Saving an edit also watches the article, when the board does that
			if (!empty($this->config['wiki_auto_watch']))
			{
				$this->watch->add($this->user->data['user_id'], $article);
			}

			if ($this->auth->acl_get('u_wiki_set_active') && ($this->data['article_approved'] <> 0))
			{
				$this->set_active_version($article_id);
			}
			else
			{
				$notify_data = array(
					'article_id'		=> $article_id,
					'article_title'		=> $this->data['article_title'],
					'article_url'		=> $article,
					'user_id'			=> $this->user->data['user_id'],
				);
				$this->notification_manager->add_notifications('phpbbmodders.wiki.notification.type.article_edit', $notify_data);
			}
			$msg = ($this->data['article_approved'] <> 0) ? $this->user->lang('EDIT_ARTICLE_SUCCESS') : $this->user->lang('EDIT_ARTICLE_SUCCESS_INACTIVE');
			$back_url = empty($article) ? $this->helper->route('phpbbmodders_wiki_index', array()) : $this->helper->route('phpbbmodders_wiki_article', array('article'	=> $article));
			trigger_error($msg . '<br /><br /><a href="' . $back_url . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
		}
		// Get the last version of the article to edit
		else
		{
			$this->message_parser->message = $this->data['article_text'];
			$this->message_parser->decode_message($this->data['bbcode_uid']);

			if (empty($this->data['article_id']))
			{
				$this->offer_start_templates($article);
			}

			$this->display_edit_form(false);
		}
		return $this->helper->render('article_edit.html', $this->user->lang('EDIT_WIKI'));
	}
	/**
	 * Stop here when the article is protected and the viewer is not in one of its groups.
	 *
	 * @param string $article
	 * @return void
	 */
	protected function protected_out($article)
	{
		if (!$this->protection->may_edit($article))
		{
			trigger_error($this->user->lang('WIKI_PROTECTED_NO_EDIT', implode(', ', $this->protection->group_names($article))) . '<br /><br /><a href="' . $this->helper->route('phpbbmodders_wiki_article', array('article' => $article)) . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
		}
	}

	/**
	 * Record that this user is editing the article (or extend their time), and show the edit
	 * form what it needs: the others editing it in notice mode, and the ping that keeps the
	 * record current while the form is open.
	 *
	 * @param string $article
	 * @param int $user_id
	 * @return void
	 */
	protected function track_editing($article, $user_id)
	{
		$mode = $this->edit_lock->mode();
		// Guests share one user id, so they are not recorded
		if ($mode === edit_lock::MODE_OFF || $user_id === ANONYMOUS)
		{
			return;
		}

		$expires = $this->edit_lock->take($article, $user_id);
		if ($mode === edit_lock::MODE_NOTICE)
		{
			foreach ($this->edit_lock->others($article, $user_id) as $other)
			{
				$this->template->assign_block_vars('wiki_other_editors', array(
					'NAME'	=> $this->holder_name($other),
					'SINCE'	=> $this->user->format_date($other['lock_time']),
				));
			}
		}

		$this->template->assign_vars(array(
			'S_WIKI_EDIT_LOCK'			=> $mode === edit_lock::MODE_LOCK,
			'WIKI_EDIT_LOCK_HELD'		=> $this->user->lang('WIKI_EDIT_LOCK_HELD', '<span id="wiki-edit-lock-until">' . $this->user->format_date($expires) . '</span>'),
			'U_WIKI_EDIT_LOCK_PING'		=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'edit_lock', 'hash' => generate_link_hash('wiki_edit_lock'))),
			'WIKI_EDIT_LOCK_PING_MS'	=> edit_lock::PING_SECONDS * 1000,
		));
	}

	/**
	 * Someone else holds the article: say who and until when instead of showing the form, with
	 * the button to break the lock for moderators, and tell the moderators once per lock.
	 *
	 * @param string $article
	 * @param array $holder A row from edit_lock::others()
	 * @return void
	 */
	protected function locked_out($article, array $holder)
	{
		$this->notify_locked_out($article, $holder);

		$message = $this->user->lang('WIKI_EDIT_LOCKED', $this->holder_name($holder), $this->user->format_date($holder['lock_expires']));
		if ($this->auth->acl_get('u_wiki_set_active'))
		{
			$message .= '<br /><br /><a href="' . $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'break_lock')) . '">' . $this->user->lang('WIKI_EDIT_LOCK_BREAK') . '</a>';
		}
		$message .= '<br /><br /><a href="' . $this->helper->route('phpbbmodders_wiki_article', array('article' => $article)) . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>';
		trigger_error($message);
	}

	/**
	 * Tell the moderators that a lock kept this user out, the first time it does.
	 *
	 * @param string $article
	 * @param array $holder
	 * @return void
	 */
	protected function notify_locked_out($article, array $holder)
	{
		if (!$this->edit_lock->mark_blocked_notified($article, (int) $holder['user_id']))
		{
			return;
		}
		$this->notification_manager->add_notifications('phpbbmodders.wiki.notification.type.edit_lock_blocked', array(
			'article_url'	=> $article,
			'article_title'	=> $this->data['article_title'] !== '' ? $this->data['article_title'] : $article,
			'user_id'		=> (int) $this->user->data['user_id'],
			'holder_id'		=> (int) $holder['user_id'],
			'lock_time'		=> (int) $holder['lock_time'],
		));
	}

	/**
	 * The name of an editor from an edit_lock row, coloured and linked as phpBB shows names.
	 *
	 * @param array $row
	 * @return string
	 */
	protected function holder_name(array $row)
	{
		return get_username_string('full', (int) $row['user_id'], (string) $row['username'], (string) $row['user_colour']);
	}

	/**
	 * The ping from an open edit form: extend this user's time. Answers in JSON.
	 *
	 * @param string $article
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 */
	public function ping_lock($article)
	{
		if (!$this->protection->may_edit($article) || !check_link_hash($this->request->variable('hash', ''), 'wiki_edit_lock') || $this->edit_lock->mode() === edit_lock::MODE_OFF)
		{
			return new \Symfony\Component\HttpFoundation\JsonResponse(array('ok' => false), 403);
		}

		$expires = $this->edit_lock->renew($article, (int) $this->user->data['user_id']);
		if ($expires === null)
		{
			return new \Symfony\Component\HttpFoundation\JsonResponse(array('ok' => false, 'locked' => true));
		}

		return new \Symfony\Component\HttpFoundation\JsonResponse(array('ok' => true, 'until' => $this->user->format_date($expires)));
	}

	/**
	 * A moderator breaks the lock on an article, after confirming.
	 *
	 * @param string $article
	 * @return void
	 */
	public function break_lock($article)
	{
		if (!$this->auth->acl_get('u_wiki_set_active'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		if (confirm_box(true))
		{
			$article = self::confirmed_article($this->request, $article);
			$this->edit_lock->break_lock($article);
			$edit_url = $this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'edit'));
			trigger_error($this->user->lang('WIKI_EDIT_LOCK_BROKEN') . '<br /><br /><a href="' . $edit_url . '">' . $this->user->lang('WIKI_EDIT_LOCK_EDIT_NOW') . '</a>');
		}
		else
		{
			confirm_box(false, $this->user->lang('WIKI_EDIT_LOCK_BREAK_CONFIRM'), build_hidden_fields(array('article' => $article)));
		}
		redirect($this->helper->route('phpbbmodders_wiki_article', array('article' => $article, 'action' => 'edit')));
	}

	/**
	 * For a new article: offer the "Template:Start ..." pages to start from, and fill the
	 * text box with the one that was chosen.
	 *
	 * @param string $article The new article's name, as in its address
	 * @return void
	 */
	protected function offer_start_templates($article)
	{
		$options = $this->templates->start_templates();
		if (empty($options))
		{
			return;
		}

		$chosen = $this->request->variable('start_from', '', true);
		$text = ($chosen === '') ? null : $this->templates->start_text($chosen);
		if ($text !== null)
		{
			// The text box takes text escaped as decode_message() leaves it
			$this->message_parser->message = htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
		}

		foreach ($options as $option)
		{
			$this->template->assign_block_vars('start_options', array(
				'NAME'			=> $option['name'],
				'LABEL'			=> $option['label'],
				'S_SELECTED'	=> ($text !== null && $option['name'] === \phpbbmodders\wiki\wiki\templates::normalise_name($chosen)),
			));
		}

		$this->template->assign_vars(array(
			'S_START_PICKER'	=> true,
			'U_START_ACTION'	=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article)),
		));
	}

	/**
	 * Make a version live, then tell the members watching the article.
	 *
	 * @param	int		$id		Version ID
	 * @return	string			URL of the article
	 */
	protected function set_active_version($id)
	{
		$article = parent::set_active_version($id);
		$this->notify_watchers((int) $id);

		return $article;
	}

	/**
	 * Tell the article's watchers that a version went live. The member who saved
	 * that version is left out by the notification type.
	 *
	 * @param int $id Version ID
	 * @return void
	 */
	protected function notify_watchers($id)
	{
		$sql = 'SELECT article_id, article_title, article_url, article_user_id
			FROM ' . $this->article_table . '
			WHERE article_id = ' . (int) $id;
		$result = $this->db->sql_query($sql);
		$version = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$version)
		{
			return;
		}

		$this->notification_manager->add_notifications('phpbbmodders.wiki.notification.type.article_watch', array(
			'article_id'	=> (int) $version['article_id'],
			'article_title'	=> $version['article_title'],
			'article_url'	=> $version['article_url'],
			'user_id'		=> (int) $version['article_user_id'],
		));
	}
}
