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

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Files uploaded to an article.
 *
 * Files belong to the article, not to one version of it. They are stored under
 * random names with no extension in store/phpbbmodders_wiki/, which the web
 * server must not serve; they are only read through serve(), which checks that
 * the reader may see the article first. The board's allowed file types and size
 * limit apply.
 */
class files
{
	/** Folder under the board's root that holds the files */
	const DIRECTORY = 'store/phpbbmodders_wiki/';

	/** Name of the form key the upload form carries */
	const FORM_KEY = 'wiki_upload';

	/** Types shown inside the browser. Anything else is only ever sent as a download. */
	const INLINE_IMAGE_TYPES = array('image/gif', 'image/jpeg', 'image/png', 'image/webp');

	/** @var string|null URL name of the article being rendered, so [[File:name]] knows whose files to use */
	protected static $context = null;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\files\factory */
	protected $files_factory;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $file_table;

	/** @var string */
	protected $article_table;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * @param \phpbb\auth\auth					$auth
	 * @param \phpbb\cache\service				$cache
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\files\factory				$files_factory
	 * @param \phpbb\controller\helper			$helper
	 * @param \phpbb\request\request			$request
	 * @param \phpbb\template\template			$template
	 * @param \phpbb\user						$user
	 * @param string							$file_table
	 * @param string							$article_table
	 * @param string							$phpbb_root_path
	 * @param string							$php_ext
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\cache\service $cache, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\files\factory $files_factory, \phpbb\controller\helper $helper, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, $file_table, $article_table, $phpbb_root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->files_factory = $files_factory;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->file_table = $file_table;
		$this->article_table = $article_table;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * Name the article whose text is about to be rendered; null when done.
	 *
	 * @param string|null $article_url
	 */
	public static function set_context($article_url)
	{
		self::$context = $article_url;
	}

	/**
	 * @return string|null
	 */
	public static function get_context()
	{
		return self::$context;
	}

	/**
	 * A display name that can be written inside [[File:...]]: no path, no
	 * characters the link syntax or a URL would trip over.
	 *
	 * @param string $name Name the browser sent
	 * @return string
	 */
	public static function clean_name($name)
	{
		$name = str_replace('\\', '/', (string) $name);
		$name = basename($name);
		$name = preg_replace('/[\[\]|<>"#?%\/\\\\\x00-\x1f]+/u', '_', $name);
		$name = trim($name, " .\t");

		return function_exists('mb_substr') ? mb_substr($name, 0, 255) : substr($name, 0, 255);
	}

	/**
	 * Whether a stored type is shown inside the browser.
	 *
	 * @param string $mimetype
	 * @return bool
	 */
	public static function is_inline_image($mimetype)
	{
		return in_array($mimetype, self::INLINE_IMAGE_TYPES, true);
	}

	/**
	 * Whether the viewer may upload.
	 *
	 * @return bool
	 */
	public function can_upload()
	{
		return (bool) $this->auth->acl_get('u_wiki_upload');
	}

	/**
	 * Whether the viewer may remove a file: the member who uploaded it, if they
	 * still may upload, or a moderator who may delete versions.
	 *
	 * @param array $file A wiki_file row
	 * @return bool
	 */
	public function can_delete(array $file)
	{
		return $this->auth->acl_get('m_wiki_delete')
			|| ($this->auth->acl_get('u_wiki_upload') && (int) $file['user_id'] === (int) $this->user->data['user_id'] && (int) $file['user_id'] > ANONYMOUS);
	}

	/**
	 * The file types the board allows in every forum.
	 *
	 * @return string[] Lower-case extensions
	 */
	public function allowed_extensions()
	{
		$extensions = $this->cache->obtain_attach_extensions(0);

		return array_map('strval', array_keys($extensions['_allowed_']));
	}

	/**
	 * Full path of the folder, created (with files in it kept private) when it is missing.
	 *
	 * @return string With a trailing slash
	 */
	protected function directory()
	{
		$dir = $this->phpbb_root_path . self::DIRECTORY;

		if (!is_dir($dir))
		{
			@mkdir($dir, 0755, true);
			@file_put_contents($dir . '.htaccess', "# Files are only read through the wiki.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n");
			@file_put_contents($dir . 'index.htm', '');
		}

		return $dir;
	}

	/**
	 * An article's files, oldest first.
	 *
	 * @param string $article_url
	 * @return array wiki_file rows with the uploader's name
	 */
	public function get_files($article_url)
	{
		$sql = 'SELECT f.*, u.username, u.user_colour
			FROM ' . $this->file_table . ' f
			LEFT JOIN ' . USERS_TABLE . " u ON (u.user_id = f.user_id)
			WHERE f.article_url = '" . $this->db->sql_escape($article_url) . "'
			ORDER BY f.file_id ASC";
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Files of an article by display name.
	 *
	 * @param string   $article_url
	 * @param string[] $names
	 * @return array Name => wiki_file row
	 */
	public function get_files_by_name($article_url, array $names)
	{
		$names = array_values(array_unique(array_filter($names, 'strlen')));
		if (!$names)
		{
			return array();
		}

		$sql = 'SELECT file_id, file_name, is_image
			FROM ' . $this->file_table . "
			WHERE article_url = '" . $this->db->sql_escape($article_url) . "'
				AND " . $this->db->sql_in_set('file_name', $names);
		$result = $this->db->sql_query($sql);
		$found = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$found[$row['file_name']] = $row;
		}
		$this->db->sql_freeresult($result);

		return $found;
	}

	/**
	 * URL a file is read from.
	 *
	 * @param string $article_url
	 * @param int    $file_id
	 * @return string
	 */
	public function get_url($article_url, $file_id)
	{
		return $this->helper->route('phpbbmodders_wiki_article', array('article' => $article_url, 'action' => 'file', 'id' => (int) $file_id));
	}

	/**
	 * Put the file list and the upload form on an article page.
	 *
	 * @param string $article_url
	 */
	public function assign_to_template($article_url)
	{
		foreach ($this->get_files($article_url) as $file)
		{
			$this->template->assign_block_vars('files', array(
				'NAME'			=> $file['file_name'],
				'SIZE'			=> get_formatted_filesize($file['file_size']),
				'UPLOADER'		=> get_username_string('no_profile', $file['user_id'], $file['username'], $file['user_colour']),
				'TIME'			=> $this->user->format_date($file['file_time']),
				'S_IMAGE'		=> (bool) $file['is_image'],
				'S_CAN_DELETE'	=> $this->can_delete($file),
				'U_FILE'		=> $this->get_url($article_url, $file['file_id']),
				'U_DELETE'		=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article_url, 'action' => 'delete_file', 'id' => (int) $file['file_id'])),
			));
		}

		$can_upload = $this->can_upload();
		if ($can_upload)
		{
			add_form_key(self::FORM_KEY);
		}

		$this->template->assign_vars(array(
			'S_WIKI_CAN_UPLOAD'		=> $can_upload,
			'U_WIKI_UPLOAD'			=> $this->helper->route('phpbbmodders_wiki_article', array('article' => $article_url, 'action' => 'upload')),
		));
	}

	/**
	 * The live version of an article, or false when it has none.
	 *
	 * @param string $article_url
	 * @return array|false
	 */
	protected function get_live_article($article_url)
	{
		$sql = 'SELECT article_id, article_url
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article_url) . "'
				AND article_approved = 1";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	/**
	 * Take the uploaded file, check it and store it.
	 *
	 * @param string $article_url
	 */
	public function upload($article_url)
	{
		if (!$this->can_upload())
		{
			trigger_error('NOT_AUTHORISED');
		}

		if (!$this->get_live_article($article_url))
		{
			trigger_error('NO_ARTICLE');
		}

		if (!check_form_key(self::FORM_KEY))
		{
			trigger_error('FORM_INVALID');
		}

		$back = $this->helper->route('phpbbmodders_wiki_article', array('article' => $article_url));
		$back_link = '<br /><br /><a href="' . $back . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>';

		$upload = $this->files_factory->get('files.upload')
			->set_allowed_extensions($this->allowed_extensions())
			->set_max_filesize((int) $this->config['max_filesize']);

		$file = $upload->handle_upload('files.types.form', 'wiki_file');

		if (empty($file) || !$file->get('uploadname'))
		{
			trigger_error($this->user->lang('NO_UPLOAD_FORM_FOUND') . $back_link, E_USER_WARNING);
		}

		if (count($file->error))
		{
			$errors = $file->error;
			$file->remove();
			trigger_error(implode('<br />', $errors) . $back_link, E_USER_WARNING);
		}

		$name = self::clean_name($file->get('uploadname'));
		if ($name === '')
		{
			$file->remove();
			trigger_error($this->user->lang('NO_UPLOAD_FORM_FOUND') . $back_link, E_USER_WARNING);
		}

		if ($this->get_files_by_name($article_url, array($name)))
		{
			$file->remove();
			trigger_error($this->user->lang('WIKI_FILE_EXISTS', $name) . $back_link, E_USER_WARNING);
		}

		$is_image = self::is_inline_image($file->get('mimetype')) && $file->is_image();

		$file->clean_filename('unique', (int) $this->user->data['user_id'] . '_');
		$file->move_file($this->directory(), false, !$is_image);

		if (count($file->error))
		{
			$errors = $file->error;
			$file->remove();
			trigger_error(implode('<br />', $errors) . $back_link, E_USER_WARNING);
		}

		// Store without the extension: nothing on disk can be run or sniffed by name
		$stored = pathinfo($file->get('realname'), PATHINFO_FILENAME);
		$dir = $this->directory();
		if ($stored !== $file->get('realname') && @rename($dir . $file->get('realname'), $dir . $stored))
		{
			$physical = $stored;
		}
		else
		{
			$physical = $file->get('realname');
		}

		$sql_ary = array(
			'article_url'		=> $article_url,
			'file_name'			=> $name,
			'physical_name'		=> $physical,
			'mimetype'			=> $is_image ? $file->get('mimetype') : 'application/octet-stream',
			'file_size'			=> (int) $file->get('filesize'),
			'is_image'			=> $is_image ? 1 : 0,
			'user_id'			=> (int) $this->user->data['user_id'],
			'file_time'			=> time(),
		);
		$this->db->sql_query('INSERT INTO ' . $this->file_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		meta_refresh(3, $back);
		trigger_error($this->user->lang('WIKI_FILE_UPLOADED', $name) . $back_link);
	}

	/**
	 * A file row that belongs to an article.
	 *
	 * @param string $article_url
	 * @param int    $file_id
	 * @return array|false
	 */
	protected function get_file($article_url, $file_id)
	{
		$sql = 'SELECT *
			FROM ' . $this->file_table . '
			WHERE file_id = ' . (int) $file_id . "
				AND article_url = '" . $this->db->sql_escape($article_url) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row;
	}

	/**
	 * Send a file to the reader, if the reader may see the article.
	 *
	 * @param string $article_url
	 * @param int    $file_id
	 * @return BinaryFileResponse
	 */
	public function serve($article_url, $file_id)
	{
		if (!$this->auth->acl_get('u_wiki_view'))
		{
			trigger_error('NOT_AUTHORISED');
		}

		// A switched-off article's files are as hidden as the article
		if (!$this->get_live_article($article_url) && !$this->auth->acl_get('m_wiki_view_inactive'))
		{
			trigger_error('NO_ARTICLE');
		}

		$file = $this->get_file($article_url, $file_id);
		$path = $file ? $this->directory() . basename($file['physical_name']) : '';

		if (!$file || !is_file($path))
		{
			trigger_error('WIKI_FILE_NOT_FOUND');
		}

		$inline = $file['is_image'] && self::is_inline_image($file['mimetype']);

		$response = new BinaryFileResponse($path);
		$response->headers->set('Content-Type', $inline ? $file['mimetype'] : 'application/octet-stream');
		$response->headers->set('X-Content-Type-Options', 'nosniff');
		$response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
		$response->headers->set('Cache-Control', 'private, max-age=3600');
		$response->setContentDisposition(
			$inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
			$file['file_name'],
			preg_replace('/[^A-Za-z0-9._-]+/', '_', $file['file_name'])
		);

		return $response;
	}

	/**
	 * Ask, then remove one file.
	 *
	 * @param string $article_url
	 * @param int    $file_id
	 */
	public function delete($article_url, $file_id)
	{
		$file = $this->get_file($article_url, $file_id);
		if (!$file)
		{
			trigger_error('WIKI_FILE_NOT_FOUND');
		}

		if (!$this->can_delete($file))
		{
			trigger_error('NOT_AUTHORISED');
		}

		$back = $this->helper->route('phpbbmodders_wiki_article', array('article' => $article_url));

		if (confirm_box(true))
		{
			$this->remove_files(array($file));
			trigger_error($this->user->lang('WIKI_FILE_DELETED', $file['file_name']) . '<br /><br /><a href="' . $back . '">' . $this->user->lang('BACK_TO_ARTICLE') . '</a>');
		}

		confirm_box(false, $this->user->lang('WIKI_CONFIRM_DELETE_FILE', $file['file_name']), build_hidden_fields(array('article' => $article_url, 'id' => (int) $file_id, 'action' => 'delete_file')));
		redirect($back);
	}

	/**
	 * Remove every file of an article, as its deletion does.
	 *
	 * @param string $article_url
	 */
	public function delete_all($article_url)
	{
		$this->remove_files($this->get_files($article_url));
	}

	/**
	 * Delete files from disk and from the table.
	 *
	 * @param array $files wiki_file rows
	 */
	protected function remove_files(array $files)
	{
		if (!$files)
		{
			return;
		}

		$dir = $this->directory();
		foreach ($files as $file)
		{
			$path = $dir . basename($file['physical_name']);
			if (is_file($path))
			{
				@unlink($path);
			}
		}

		$this->db->sql_query('DELETE FROM ' . $this->file_table . ' WHERE ' . $this->db->sql_in_set('file_id', array_map('intval', array_column($files, 'file_id'))));
	}
}
