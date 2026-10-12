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

use phpbbmodders\wiki\wiki\expression;

/**
 * The arithmetic behind {{#expr:}}.
 */
class expression_test extends \PHPUnit\Framework\TestCase
{
	/** @dataProvider results */
	public function test_an_expression_comes_out_as_written_on_a_page(string $text, string $expected)
	{
		$this->assertSame($expected, expression::calculate($text), $text);
	}

	public static function results(): array
	{
		return array(
			'sum'					=> array('2+3', '5'),
			'precedence'			=> array('2 + 3 * 4', '14'),
			'brackets'				=> array('(2 + 3) * 4', '20'),
			'division'				=> array('10 / 4', '2.5'),
			'div word'				=> array('7 div 2', '3.5'),
			'mod'					=> array('7 mod 3', '1'),
			'power'					=> array('2 ^ 10', '1024'),
			'negative'				=> array('-3 + 5', '2'),
			'double negative'		=> array('3 - -2', '5'),
			'decimals'				=> array('0.5 + .25', '0.75'),
			'round'					=> array('3.14159 round 2', '3.14'),
			'round half up'			=> array('1.5 round 0', '2'),
			'less than'				=> array('1 < 2', '1'),
			'not equal'				=> array('1 != 1', '0'),
			'equal as numbers'		=> array('1 = 1.0', '1'),
			'fractions add up'		=> array('0.1 + 0.2 = 0.3', '1'),
			'and or'				=> array('(1 and 0) or 1', '1'),
			'not'					=> array('not 0', '1'),
			'abs'					=> array('abs -5', '5'),
			'floor'					=> array('floor 2.7', '2'),
			'ceil'					=> array('ceil 2.1', '3'),
			'trunc'					=> array('trunc -2.7', '-2'),
			'sqrt'					=> array('sqrt 16', '4'),
			'pi'					=> array('pi round 3', '3.142'),
			'e'						=> array('e round 2', '2.72'),
			'zero'					=> array('5 - 5', '0'),
			'empty'					=> array('   ', ''),
			'big'					=> array('10 ^ 20', '1.0E+20'),
		);
	}

	/** @dataProvider failures */
	public function test_a_bad_expression_says_what_is_wrong(string $text, string $key)
	{
		try
		{
			expression::calculate($text);
			$this->fail('no error for ' . $text);
		}
		catch (\InvalidArgumentException $e)
		{
			$this->assertSame($key, explode('|', $e->getMessage())[0], $text);
		}
	}

	public static function failures(): array
	{
		return array(
			'divide by zero'		=> array('1 / 0', 'WIKI_EXPR_DIV_ZERO'),
			'mod zero'				=> array('5 mod 0', 'WIKI_EXPR_DIV_ZERO'),
			'ends early'			=> array('2 +', 'WIKI_EXPR_MISSING'),
			'unclosed bracket'		=> array('(2 + 3', 'WIKI_EXPR_MISSING'),
			'stray character'		=> array('2 $ 3', 'WIKI_EXPR_UNEXPECTED'),
			'unknown word'			=> array('2 + foo', 'WIKI_EXPR_UNEXPECTED'),
			'two numbers'			=> array('2 3', 'WIKI_EXPR_UNEXPECTED'),
			'square root of minus'	=> array('sqrt -1', 'WIKI_EXPR_NAN'),
			'log of zero'			=> array('ln 0', 'WIKI_EXPR_NAN'),
			'too long'				=> array(str_repeat('1+', 400) . '1', 'WIKI_EXPR_TOO_LONG'),
			'too deep'				=> array(str_repeat('-', 100) . '1', 'WIKI_EXPR_TOO_LONG'),
		);
	}

	public function test_true_and_false()
	{
		$this->assertTrue(expression::is_true('2 > 1'));
		$this->assertFalse(expression::is_true('2 < 1'));
		$this->assertNull(expression::is_true(''));
	}
}
