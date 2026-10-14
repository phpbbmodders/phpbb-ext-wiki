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
namespace phpbbmodders\wiki\controller;

class main
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbbmodders\wiki\wiki\delete */
	protected $delete;

	/** @var \phpbbmodders\wiki\wiki\edit */
	protected $edit;

	/** @var \phpbbmodders\wiki\wiki\compare */
	protected $compare;

	/** @var \phpbbmodders\wiki\wiki\view */
	protected $view;

	/** @var \phpbbmodders\wiki\wiki\discussion */
	protected $discussion;

	/** @var \phpbbmodders\wiki\wiki\files */
	protected $files;

	/** @var \phpbbmodders\wiki\wiki\watch */
	protected $watch;

	/** @var \phpbbmodders\wiki\wiki\move */
	protected $move;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string php_ext */
	protected $php_ext;

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth				$auth					Auth object
	* @param \phpbb\controller\helper		$helper					Controller helper object
	* @param \phpbb\request\request			$request				Request object
	* @param \phpbb\template\template		$template				Template object
	* @param \phpbb\user					$user					User object
	* @param \phpbbmodders\wiki\wiki\delete		$delete					Delete Wiki object
	* @param \phpbbmodders\wiki\wiki\edit		$edit					Edit Wiki object
	* @param \phpbbmodders\wiki\wiki\compare		$compare				Diff Wiki object
	* @param \phpbbmodders\wiki\wiki\view		$view					View Wiki object
	* @param \phpbbmodders\wiki\wiki\discussion	$discussion				Start discussion object
	* @param \phpbbmodders\wiki\wiki\files		$files					Uploaded files object
	* @param \phpbbmodders\wiki\wiki\watch		$watch					Watched articles object
	* @param \phpbbmodders\wiki\wiki\move		$move					Move article object
	* @param string							$phpbb_root_path
	* @param string							$php_ext
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\controller\helper $helper, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbbmodders\wiki\wiki\delete $delete, \phpbbmodders\wiki\wiki\edit $edit, \phpbbmodders\wiki\wiki\compare $compare, \phpbbmodders\wiki\wiki\view $view, \phpbbmodders\wiki\wiki\discussion $discussion, \phpbbmodders\wiki\wiki\files $files, \phpbbmodders\wiki\wiki\watch $watch, \phpbbmodders\wiki\wiki\move $move, $phpbb_root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->delete = $delete;
		$this->edit = $edit;
		$this->compare = $compare;
		$this->view = $view;
		$this->discussion = $discussion;
		$this->files = $files;
		$this->watch = $watch;
		$this->move = $move;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Display the wiki index page
	 *
	 * @return object
	 */
	public function index()
	{
		$article = $this->request->variable('article', '', true);
		return $this->article($article);
	}

	/**
	 * Display an article
	 *
	 * @param	string	$article	URL of the article
	 * @return	object
	 */
	public function article($article)
	{
		$this->user->add_lang_ext('phpbbmodders/wiki', 'common');

		if (!$this->auth->acl_get('u_wiki_view'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$this->template->assign_block_vars('navlinks', array(
			'FORUM_NAME'		=> $this->user->lang('WIKI'),
			'U_VIEW_FORUM'		=> $this->helper->route('phpbbmodders_wiki_index', array()),
		));

		$this->template->assign_vars(array(
			'WIKI_FOOTER'		=> $this->user->lang('WIKI_FOOTER', base64_decode('aHR0cHM6Ly90YXMyNTgwLm5ldA=='), base64_decode('dGFzMjU4MA==')),
		));

		include($this->phpbb_root_path . 'includes/functions_display.' . $this->php_ext);
		include($this->phpbb_root_path . 'includes/functions_posting.' . $this->php_ext);

		$action = $this->request->variable('action', '');
		$id = $this->request->variable('id', 0);

		switch ($action)
		{
			case 'edit':
				return $this->edit->edit_article($article);

			case 'versions':
				return $this->compare->view_versions($article);

			case 'compare':
				$from = $this->request->variable('from', 0);
				$to = $this->request->variable('to', 0);
				return $this->compare->compare_versions($article, $from, $to);

			case 'delete':
				return $this->delete->version($id);

			case 'detele_article':
				return $this->delete->article($article);

			case 'active':
				return $this->edit->active($id);

			case 'deactivate':
				return $this->edit->deactivate($article);

			case 'move':
				return $this->move->page($article);

			case 'restore':
				return $this->edit->restore($id);

			case 'start_discussion':
				return $this->discussion->start($article);

			case 'watch':
				return $this->watch->handle($article, true);

			case 'unwatch':
				return $this->watch->handle($article, false);

			case 'upload':
				return $this->files->upload($article);

			case 'file':
				return $this->files->serve($article, $id);

			case 'delete_file':
				return $this->files->delete($article, $id);

			default:
				return $this->view->view_article($article, $id);
		}
	}
}
