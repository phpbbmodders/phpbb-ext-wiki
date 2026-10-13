<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace phpbbmodders\wiki\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shows the wiki articles that match a board search in a block of their own above the
 * forum results, and adds the "Also search the Wiki" option to the advanced search form.
 * Nothing is copied into the forum tables and the forum results are not touched.
 */
class search_listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbbmodders\wiki\search\wiki_search_backend_factory */
	protected $factory;

	/** @var \phpbbmodders\wiki\wiki\search_results */
	protected $search_results;

	/**
	 * @param \phpbb\auth\auth											$auth
	 * @param \phpbb\config\config										$config
	 * @param \phpbb\controller\helper									$helper
	 * @param \phpbb\language\language									$language
	 * @param \phpbb\request\request									$request
	 * @param \phpbb\template\template									$template
	 * @param \phpbbmodders\wiki\search\wiki_search_backend_factory	$factory
	 * @param \phpbbmodders\wiki\wiki\search_results					$search_results
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\controller\helper $helper, \phpbb\language\language $language, \phpbb\request\request $request, \phpbb\template\template $template, \phpbbmodders\wiki\search\wiki_search_backend_factory $factory, \phpbbmodders\wiki\wiki\search_results $search_results)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->factory = $factory;
		$this->search_results = $search_results;
	}

	public static function getSubscribedEvents()
	{
		return array(
			'core.search_modify_forum_select_list'		=> 'add_option',
			'core.search_modify_url_parameters'			=> 'keep_option',
			'core.search_results_modify_search_title'	=> 'add_block',
		);
	}

	/**
	 * Whether the board search may bring in the wiki at all: both switches are on and the
	 * member may read the wiki.
	 *
	 * @return bool
	 */
	protected function enabled()
	{
		return $this->config['wiki_search'] && $this->config['wiki_search_board'] && $this->auth->acl_get('u_wiki_view');
	}

	/**
	 * Whether this search asked for the wiki. A search that does not come from the advanced
	 * form, such as the search box in the header, includes it; the form sends "wiki_form" so
	 * that an unticked box can be told from a form that does not have one.
	 *
	 * @return bool
	 */
	protected function wanted()
	{
		return $this->request->variable('wiki_form', 0) ? (bool) $this->request->variable('wiki_also', 0) : true;
	}

	/**
	 * Show the "Also search the Wiki" option on the advanced search form.
	 *
	 * @return void
	 */
	public function add_option()
	{
		if (!$this->enabled())
		{
			return;
		}

		$this->language->add_lang('search', 'phpbbmodders/wiki');
		$this->template->assign_var('S_WIKI_BOARD_SEARCH', true);
	}

	/**
	 * Carry an unticked option through the links of the results (pages, search in results).
	 *
	 * @param \phpbb\event\data $event
	 * @return void
	 */
	public function keep_option($event)
	{
		if ($this->enabled() && !$this->wanted())
		{
			$event['u_search'] = $event['u_search'] . '&amp;wiki_form=1';
		}
	}

	/**
	 * Show the wiki block on the first page of the results of a search for words.
	 *
	 * @param \phpbb\event\data $event
	 * @return void
	 */
	public function add_block($event)
	{
		$keywords = (string) $event['keywords'];
		if ($keywords === '' || $event['start'] > 0 || !$this->enabled() || !$this->wanted())
		{
			return;
		}

		// Searches that are not for words in the forums: the pre-made ones, one author's
		// posts, and a search inside one topic
		if ($this->request->variable('search_id', '') !== '' || $this->request->variable('author', '', true) !== '' || $this->request->variable('author_id', 0) || $this->request->variable('t', 0))
		{
			return;
		}

		if (!$this->factory->get_active()->index_created())
		{
			return;
		}

		$terms = ($this->request->variable('terms', 'all') === 'any' && $this->request->variable('add_keywords', '', true) === '') ? 'any' : 'all';
		$fields = $this->request->variable('sf', 'all');
		$fields = in_array($fields, array('titleonly', 'msgonly'), true) ? $fields : 'all';

		$max = max(1, (int) $this->config['wiki_search_board_max']);
		$found = $this->search_results->find($keywords, $terms, $fields, 't', 'd', 0, $max);
		if ($found === false || $found['total'] < 1 || empty($found['rows']))
		{
			return;
		}

		$this->language->add_lang('search', 'phpbbmodders/wiki');
		foreach ($found['rows'] as $row)
		{
			$this->template->assign_block_vars('wiki_matches', $row);
		}
		$this->template->assign_vars(array(
			'S_WIKI_BOARD_BLOCK'	=> true,
			'WIKI_BOARD_TOTAL'		=> $this->language->lang('WIKI_BOARD_BLOCK_TITLE', (int) $found['total']),
			'WIKI_BOARD_VIEW_ALL'	=> $this->language->lang('WIKI_BOARD_VIEW_ALL', (int) $found['total']),
			'S_WIKI_BOARD_MORE'		=> $found['total'] > count($found['rows']),
			'U_WIKI_BOARD_VIEW_ALL'	=> $this->helper->route('phpbbmodders_wiki_search', array(
				'keywords'	=> html_entity_decode($keywords, ENT_COMPAT),
				'terms'		=> $terms,
				'sf'		=> $fields,
			)),
		));
	}
}
