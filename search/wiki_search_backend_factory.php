<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

namespace phpbbmodders\wiki\search;

use phpbb\config\config;
use phpbb\di\service_collection;
use phpbbmodders\wiki\search\backend\wiki_search_backend_interface;

class wiki_search_backend_factory
{
	/** @var config */
	protected config $config;

	/** @var service_collection */
	protected service_collection $search_backends;

	/**
	 * Constructor
	 *
	 * @param config             $config
	 * @param service_collection $search_backends
	 */
	public function __construct(config $config, service_collection $search_backends)
	{
		$this->config = $config;
		$this->search_backends = $search_backends;
	}

	/**
	 * Obtains a specified search backend
	 *
	 * @param string $class
	 *
	 * @return wiki_search_backend_interface
	 */
	public function get(string $class): wiki_search_backend_interface
	{
		return $this->search_backends->get_by_class($class);
	}

	/**
	 * Obtains active search backend
	 *
	 * @return wiki_search_backend_interface
	 */
	public function get_active(): wiki_search_backend_interface
	{
		return $this->get($this->config['wiki_search_type']);
	}
}
