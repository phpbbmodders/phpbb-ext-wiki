<?php
/**
 *
 * Wiki extension for the phpBB Forum Software package
 *
 * @copyright (c) 2026, phpBB Modders, https://www.phpbbmodders.com/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace phpbbmodders\wiki\tests\unit;

use phpbbmodders\wiki\wiki\zip_reader;

/**
 * Reading the starter templates out of a zip of the repository: which entries are taken,
 * and that broken, crafted or oversized zips are refused rather than read.
 */
class zip_reader_test extends \PHPUnit\Framework\TestCase
{
	/** @var string[] Zips made by the tests, removed afterwards */
	protected $files = array();

	protected function tearDown(): void
	{
		foreach ($this->files as $file)
		{
			@unlink($file);
		}
		parent::tearDown();
	}

	/**
	 * Write a zip of the given entries, built here so the test needs no zip extension.
	 *
	 * @param array $entries Entry names with their contents
	 * @param bool $deflate Compress the entries (method 8) or store them (method 0)
	 * @param array $sizes Entry names with a size to claim instead of the real one
	 * @return string The zip's path
	 */
	protected function zip(array $entries, $deflate = true, array $sizes = array())
	{
		$local = '';
		$directory = '';
		foreach ($entries as $name => $content)
		{
			$data = $deflate ? gzdeflate($content) : $content;
			$size = isset($sizes[$name]) ? $sizes[$name] : strlen($content);
			$fields = pack('vvvvvVVVvv', 20, 0, $deflate ? 8 : 0, 0, 0, crc32($content), strlen($data), $size, strlen($name), 0);
			$directory .= "PK\x01\x02" . pack('v', 20) . $fields . pack('vvvVV', 0, 0, 0, 0, strlen($local)) . $name;
			$local .= "PK\x03\x04" . $fields . $name . $data;
		}
		$zip = $local . $directory . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($entries), count($entries), strlen($directory), strlen($local), 0);

		$path = tempnam(sys_get_temp_dir(), 'wikizip');
		file_put_contents($path, $zip);
		$this->files[] = $path;

		return $path;
	}

	public function test_templates_are_read_from_the_top_or_one_folder_down()
	{
		foreach (array(true, false) as $deflate)
		{
			$templates = zip_reader::templates($this->zip(array(
				'wiki-templates-main/README.md'						=> 'Read me',
				'wiki-templates-main/templates/Small.wiki'			=> '[size=85]{{{1}}}[/size]',
				'wiki-templates-main/templates/See also.wiki'		=> 'See also',
				'wiki-templates-main/tools/fetch_template.py'		=> 'print()',
			), $deflate));
			$this->assertSame(array('Small' => '[size=85]{{{1}}}[/size]', 'See also' => 'See also'), $templates);
		}

		$this->assertSame(array('Main' => 'Main'), zip_reader::templates($this->zip(array('templates/Main.wiki' => 'Main'))));
	}

	public function test_entries_elsewhere_are_ignored()
	{
		$templates = zip_reader::templates($this->zip(array(
			'a/b/templates/Deep.wiki'		=> 'too deep',
			'templates/sub/Nested.wiki'		=> 'nested',
			'templates/Notes.txt'			=> 'not a template',
			'../templates/Escape.wiki'		=> 'outside',
		)));
		$this->assertSame(array(), $templates);
	}

	public function test_a_file_that_is_not_a_zip_is_refused()
	{
		$path = tempnam(sys_get_temp_dir(), 'wikizip');
		file_put_contents($path, 'This is not a zip.');
		$this->files[] = $path;

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('WIKI_STARTER_ZIP_INVALID');
		zip_reader::templates($path);
	}

	public function test_a_missing_file_is_unreadable()
	{
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('WIKI_STARTER_ZIP_UNREADABLE');
		zip_reader::templates(sys_get_temp_dir() . '/no-such-wiki-zip.zip');
	}

	public function test_a_size_that_does_not_match_the_content_is_refused()
	{
		// A zip bomb claims a small size; decompression stops there and the check fails
		$path = $this->zip(array('templates/Bomb.wiki' => str_repeat('a', 5000)), true, array('templates/Bomb.wiki' => 10));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('WIKI_STARTER_ZIP_INVALID');
		zip_reader::templates($path);
	}

	public function test_a_template_over_the_limit_is_refused()
	{
		$path = $this->zip(array('templates/Huge.wiki' => str_repeat('a', zip_reader::MAX_TEMPLATE_BYTES + 1)));

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('WIKI_STARTER_ZIP_TOO_LARGE');
		zip_reader::templates($path);
	}
}
