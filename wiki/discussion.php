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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The "Start discussion" button: creates the discussion topic for an article
 * in the forum chosen in the ACP and links it to the article.
 */
class discussion
{
	/** Name of the link hash the button's URL carries */
	const LINK_HASH = 'wiki_start_discussion';

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/** @var string article_table */
	protected $article_table;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string php_ext */
	protected $php_ext;

	/**
	 * @param \phpbb\auth\auth						$auth
	 * @param \phpbb\config\config					$config
	 * @param \phpbb\db\driver\driver_interface		$db
	 * @param \phpbb\controller\helper				$helper
	 * @param \phpbb\request\request				$request
	 * @param \phpbb\user							$user
	 * @param string								$article_table
	 * @param string								$phpbb_root_path
	 * @param string								$php_ext
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\request\request $request, \phpbb\user $user, $article_table, $phpbb_root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->helper = $helper;
		$this->request = $request;
		$this->user = $user;
		$this->article_table = $article_table;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Whether to show the button on an article.
	 *
	 * It shows when a discussion forum is set, the article has no topic yet and is
	 * not a redirect, and the viewer may link topics and post in that forum.
	 *
	 * @param array $article Article row as the view loads it
	 * @return bool
	 */
	public function can_start(array $article)
	{
		$forum_id = (int) $this->config['wiki_discussion_forum_id'];

		return $forum_id > 0
			&& empty($article['article_topic_id'])
			&& empty($article['article_redirect'])
			&& !empty($article['article_approved'])
			&& $this->auth->acl_get('u_wiki_edit_topic')
			&& $this->auth->acl_get('f_post', $forum_id);
	}

	/**
	 * The URL the button points to; the link hash stops other sites from
	 * making a signed-in member start discussions.
	 *
	 * @param string $article_url URL name of the article
	 * @return string
	 */
	public function get_url($article_url)
	{
		return $this->helper->route('phpbbmodders_wiki_article', array(
			'article'	=> $article_url,
			'action'	=> 'start_discussion',
			'hash'		=> generate_link_hash(self::LINK_HASH),
		));
	}

	/**
	 * Create the topic, save its ID on the article and send the member to it.
	 *
	 * A member who clicks again after the topic exists is sent to it instead of
	 * getting a second one.
	 *
	 * @param string $article_url URL name of the article
	 */
	public function start($article_url)
	{
		if (!$this->auth->acl_get('u_wiki_edit_topic'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		if (!check_link_hash($this->request->variable('hash', ''), self::LINK_HASH))
		{
			trigger_error('FORM_INVALID');
		}

		$article = $this->get_live_article($article_url);
		if (!$article)
		{
			trigger_error('NO_ARTICLE');
		}

		if (!empty($article['article_topic_id']))
		{
			$this->redirect_to_topic((int) $article['article_topic_id']);
		}

		$forum_id = (int) $this->config['wiki_discussion_forum_id'];
		if ($forum_id <= 0)
		{
			trigger_error('DISCUSSION_NO_FORUM');
		}

		if (!$this->auth->acl_get('f_post', $forum_id) || !empty($article['article_redirect']))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$sql = 'SELECT forum_name
			FROM ' . FORUMS_TABLE . '
			WHERE forum_id = ' . $forum_id . '
				AND forum_type = ' . FORUM_POST;
		$result = $this->db->sql_query($sql);
		$forum_name = $this->db->sql_fetchfield('forum_name');
		$this->db->sql_freeresult($result);

		if ($forum_name === false)
		{
			trigger_error('DISCUSSION_NO_FORUM');
		}

		$topic_id = $this->create_topic($article, $forum_id, $forum_name);

		$sql = 'UPDATE ' . $this->article_table . '
			SET article_topic_id = ' . $topic_id . "
			WHERE article_url = '" . $this->db->sql_escape($article['article_url']) . "'";
		$this->db->sql_query($sql);

		$this->redirect_to_topic($topic_id);
	}

	/**
	 * The live (approved, latest) version of an article.
	 *
	 * @param string $article_url
	 * @return array|false
	 */
	protected function get_live_article($article_url)
	{
		$sql = 'SELECT article_id, article_title, article_url, article_description, article_topic_id, article_redirect, article_approved
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article_url) . "'
				AND article_approved = 1
			ORDER BY article_last_edit DESC";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	/**
	 * Post the topic: the article's description, if it has one, and a link to it.
	 *
	 * @param array  $article    Live article row
	 * @param int    $forum_id
	 * @param string $forum_name
	 * @return int ID of the new topic
	 */
	protected function create_topic(array $article, $forum_id, $forum_name)
	{
		if (!function_exists('submit_post'))
		{
			include($this->phpbb_root_path . 'includes/functions_posting.' . $this->php_ext);
		}

		$link = $this->helper->route('phpbbmodders_wiki_article', array('article' => $article['article_url']), false, false, UrlGeneratorInterface::ABSOLUTE_URL);

		// The description is member-written, so it goes in as a placeholder and is
		// swapped in as plain text after parsing; BBCode typed into it would
		// otherwise be formatted in the topic.
		$text = ($article['article_description'] !== '' ? "{WIKI_DESCRIPTION}\n\n" : '')
			. '[b][url=' . $link . ']' . $this->user->lang('DISCUSSION_READ_ARTICLE') . '[/url][/b]';

		$options = 0;
		generate_text_for_storage($text, $bbcode_uid, $bitfield, $options, true, true, true);
		$text = self::insert_plain_text($text, array('{WIKI_DESCRIPTION}' => $article['article_description']));

		$title = truncate_string($article['article_title'], 255, 255, false);

		$data = array(
			'topic_title'		=> $title,
			'forum_id'			=> $forum_id,
			'forum_name'		=> $forum_name,
			'icon_id'			=> 0,
			'poster_id'			=> (int) $this->user->data['user_id'],
			'enable_bbcode'		=> true,
			'enable_smilies'	=> true,
			'enable_urls'		=> true,
			'enable_sig'		=> true,
			'notify'			=> 0,
			'notify_set'		=> '',
			'enable_indexing'	=> true,
			'message'			=> $text,
			'message_md5'		=> md5($text),
			'bbcode_bitfield'	=> $bitfield,
			'bbcode_uid'		=> $bbcode_uid,
			'post_edit_locked'	=> 0,
		);

		$poll = array();
		submit_post('post', $title, $this->user->data['username'], POST_NORMAL, $poll, $data, false);

		// submit_post() fills in the topic ID by reference
		return (int) $data['topic_id'];
	}

	/**
	 * Swap placeholders in parsed post text for plain text.
	 *
	 * @param string $parsed_post  The post as generate_text_for_storage() left it
	 * @param array  $plain_values Placeholder => text as stored in the database (HTML-escaped)
	 * @return string
	 */
	protected static function insert_plain_text($parsed_post, array $plain_values)
	{
		$replacements = array();
		foreach ($plain_values as $placeholder => $escaped_text)
		{
			$replacements[$placeholder] = htmlspecialchars(htmlspecialchars_decode((string) $escaped_text, ENT_QUOTES), ENT_NOQUOTES | ENT_XML1, 'UTF-8');
		}

		return strtr($parsed_post, $replacements);
	}

	/**
	 * @param int $topic_id
	 */
	protected function redirect_to_topic($topic_id)
	{
		redirect(append_sid($this->phpbb_root_path . 'viewtopic.' . $this->php_ext, 't=' . (int) $topic_id));
	}
}
