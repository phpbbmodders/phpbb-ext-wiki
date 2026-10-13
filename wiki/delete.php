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

class delete extends \phpbbmodders\wiki\wiki\functions
{

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \parse_message */
	protected $message_parser;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbbmodders\wiki\wiki\files */
	protected $files;

	/** @var \phpbbmodders\wiki\wiki\watch */
	protected $watch;

	/** @var string article_table */
	protected $article_table;


	/**
	* Constructor
	*
	* @param \phpbb\auth\auth						$auth					Auth object
	* @param \phpbb\db\driver\driver_interface		$db						Database object
	* @param \phpbb\controller\helper				$helper					Controller helper object
	* @param \phpbb\user							$user					User object
	* @param string									$article_table
	* @param \phpbbmodders\wiki\wiki\files				$files					Uploaded files
	* @param \phpbb\request\request				$request				Request object
	* @param \phpbbmodders\wiki\wiki\watch				$watch					Watched articles
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\user $user, $article_table, \phpbbmodders\wiki\wiki\files $files, \phpbb\request\request $request, \phpbbmodders\wiki\wiki\watch $watch, \phpbbmodders\wiki\wiki\search_sync $search_sync)
	{
		$this->search_sync = $search_sync;
		$this->files = $files;
		$this->request = $request;
		$this->watch = $watch;
		$this->auth = $auth;
		$this->db = $db;
		$this->helper = $helper;
		$this->user = $user;
		$this->article_table = $article_table;
	}

	/**
	 * Delete a version of an article
	 *
	 * @param	int		$id	Id of the version to delete
	 * @return	object
	 */
	public function version($id)
	{
		if (!$this->auth->acl_get('m_wiki_delete'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$sql = 'SELECT article_approved, article_url
			FROM ' . $this->article_table . '
				WHERE article_id = ' . (int) $id;
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		if ($row['article_approved'] <> 0)
		{
			trigger_error($this->user->lang('NO_DELETE_ACTIVE_VERSION') . '<br /><br /><a href="' . $this->helper->route('phpbbmodders_wiki_article', array('article' => $row['article_url']))  . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
		}

		if (confirm_box(true))
		{
			$sql = 'DELETE FROM ' . $this->article_table . '
				WHERE article_id = ' . (int) $id;
			$this->db->sql_query($sql);
			//return $helper->message('DELETE_VERSION_SUCCESS', array());
			trigger_error($this->user->lang('DELETE_VERSION_SUCCESS') . '<br /><br /><a href="' . $this->helper->route('phpbbmodders_wiki_article', array('article' => $row['article_url']))  . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
		}
		else
		{
			$s_hidden_fields = build_hidden_fields(array(
				'id'    => $id,
			));
			confirm_box(false, $this->user->lang('CONFIRM_DELETE_VERSION'), $s_hidden_fields);
		}
		redirect($this->helper->route('phpbbmodders_wiki_index', array('id' => $id)));
	}

	/**
	 * Delete an complete article
	 *
	 * @param	string	$article	URL of the article to delete
	 * @return	object
	 */
	public function article($article)
	{
		if (!$this->auth->acl_get('m_wiki_delete_article'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		if (confirm_box(true))
		{
			$article = self::confirmed_article($this->request, $article);

			// Out of the search first, while its versions can still be found
			$this->search_sync->remove($article);

			$sql = 'DELETE FROM ' . $this->article_table . "
				WHERE article_url = '" . $this->db->sql_escape($article) . "'";
			$this->db->sql_query($sql);
			$this->files->delete_all($article);
			$this->watch->delete_for_article($article);
			trigger_error($this->user->lang('DELETE_ARTICLE_SUCCESS') . '<br /><br /><a href="' . $this->helper->route('phpbbmodders_wiki_index', array())  . '">' . $this->user->lang('BACK_TO_WIKI') . '</a>');
		}
		else
		{
			$s_hidden_fields = build_hidden_fields(array(
				'article'    => $article,
			));
			confirm_box(false, $this->user->lang('CONFIRM_DELETE_ARTICLE'), $s_hidden_fields);
		}
		redirect($this->helper->route('phpbbmodders_wiki_index', array('article' => $article)));
	}
}
