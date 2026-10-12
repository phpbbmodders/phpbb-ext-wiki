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
 * The PHP version this extension claims to need is stated in several places (composer.json,
 * ext.php, the install message, the README and the CI workflows); these tests keep them in step.
 * They read the files as text, so they need no phpBB classes.
 */
class declared_support_test extends \PHPUnit\Framework\TestCase
{
	private const ROOT = __DIR__ . '/../..';

	private function read(string $path): string
	{
		$this->assertFileExists(self::ROOT . '/' . $path);

		return file_get_contents(self::ROOT . '/' . $path);
	}

	public function test_composer_requires_php_8_2_or_newer()
	{
		$composer = json_decode($this->read('composer.json'), true, 512, JSON_THROW_ON_ERROR);

		$this->assertSame('>=8.2', $composer['require']['php']);
	}

	public function test_the_extension_refuses_to_enable_below_the_same_version()
	{
		$this->assertMatchesRegularExpression('/return PHP_VERSION_ID >= 80200;/', $this->read('ext.php'));
		$this->assertStringContainsString('PHP 8.2.0', $this->read('language/en/install_wiki.php'));
	}

	public function test_the_readme_states_the_same_requirement()
	{
		$this->assertStringContainsString('- PHP 8.2 or later', $this->read('README.md'));
	}

	/** @dataProvider workflows_with_a_php_matrix */
	public function test_every_ci_matrix_starts_at_8_2_and_includes_8_5(string $file)
	{
		$workflow = $this->read($file);

		$this->assertGreaterThanOrEqual(1, preg_match_all("/(?:PHP_VERSION_MATRIX: '|php: )(\\[[^\\]]+\\])/", $workflow, $matches), $file);
		foreach ($matches[1] as $matrix)
		{
			$versions = json_decode(str_replace("'", '"', $matrix), true);
			usort($versions, 'version_compare');
			$this->assertSame('8.2', $versions[0], $file . ' ' . $matrix);
			$this->assertContains('8.5', $versions, $file . ' ' . $matrix);
		}
	}

	public function workflows_with_a_php_matrix(): array
	{
		return [
			'tests' => ['.github/workflows/tests.yml'],
			'lint'  => ['.github/workflows/lint.yml'],
		];
	}

	public function test_both_test_jobs_name_a_primary_php_inside_their_matrix()
	{
		$workflow = $this->read('.github/workflows/tests.yml');

		$this->assertSame(2, preg_match_all("/PRIMARY_PHP_VERSION: '([0-9.]+)'/", $workflow, $primary));
		$this->assertSame(['8.2', '8.2'], $primary[1]);
		$this->assertSame(2, substr_count($workflow, 'PHP_VERSION_MATRIX'));
	}
}
