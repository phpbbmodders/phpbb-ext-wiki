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

/**
 * SQL string values must be single-quoted. A double-quoted value works on MySQL and SQLite
 * but names a column on PostgreSQL (and on MySQL in ANSI mode), so the page that used it
 * failed there. This reads the source as text, so it needs no database.
 */
class sql_quoting_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider source_files */
	public function test_an_escaped_value_is_never_wrapped_in_double_quotes_in_sql(string $file)
	{
		$source = file_get_contents($file);

		// "...= \"' . $this->db->sql_escape(" opens a double-quoted SQL value inside a PHP string
		$this->assertDoesNotMatchRegularExpression('/=\s*"\'\s*\.\s*\$this->db->sql_escape/', $source, $file);
	}

	public static function source_files(): array
	{
		$root = realpath(__DIR__ . '/../..');
		$files = array();
		foreach (array('wiki', 'controller', 'event', 'notification', 'textreparser') as $dir)
		{
			foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)) as $file)
			{
				if ($file->getExtension() === 'php')
				{
					$files[substr($file->getPathname(), strlen($root) + 1)] = array($file->getPathname());
				}
			}
		}

		return $files;
	}
}
