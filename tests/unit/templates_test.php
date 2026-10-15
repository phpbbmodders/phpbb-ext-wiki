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

use phpbbmodders\wiki\wiki\templates;

/**
 * The text work behind {{Template|parameters}}: reading a call, filling in a template.
 */
class templates_test extends \PHPUnit\Framework\TestCase
{
	public function test_a_call_without_parameters_is_just_a_name()
	{
		$this->assertSame(array('Out of date', array(), array()), templates::split_call('Out of date'));
		$this->assertSame(array('Spaced', array(), array()), templates::split_call('  Spaced  '));
	}

	public function test_positional_and_named_parameters_are_told_apart()
	{
		list($name, $positional, $named) = templates::split_call('Infobox|first|name = Wiki |second|2=replaced');

		$this->assertSame('Infobox', $name);
		// Named parameters do not use up a position, and a later 2= wins over the second bare one
		$this->assertSame(array(1 => 'first', 2 => 'replaced'), $positional);
		$this->assertSame(array('name' => 'Wiki'), $named);
	}

	public function test_bars_inside_links_and_templates_belong_to_them()
	{
		list(, $positional, $named) = templates::split_call('T|[[A|b]]|{{Ver|1|2}}|x={{Y|z}}|{{{p|d}}}');

		$this->assertSame(array(1 => '[[A|b]]', 2 => '{{Ver|1|2}}', 3 => '{{{p|d}}}'), $positional);
		$this->assertSame(array('x' => '{{Y|z}}'), $named);
	}

	public function test_parameter_defaults_list_each_parameter_once_with_its_default()
	{
		$text = "Version {{{version|1.0}}} of {{{1}}}, by {{{author}}}. Again {{{version}}}. {{{note|see {{Main|x}}}}}";
		$this->assertSame(array('version' => '1.0', '1' => '', 'author' => '', 'note' => ''), templates::parameter_defaults($text));
		$this->assertSame(array(), templates::parameter_defaults('No parameters here.'));
		$this->assertSame(array('a' => 'later'), templates::parameter_defaults('{{{a}}} {{{a|later}}}'), 'a default written later still counts');
	}

	public function test_runs_of_closing_braces_do_not_end_a_parameter_early()
	{
		// }}}}}}}} closes {{{1}}}, {{{w| and {{#if:, so the bar after the next call belongs to it
		list(, $positional, $named) = templates::split_call('Show|issue={{#if:{{{1|}}}|{{{w|{{{1}}}}}}}}{{#if:{{{p|}}}||.}}|2');

		$this->assertSame(array('issue' => '{{#if:{{{1|}}}|{{{w|{{{1}}}}}}}}{{#if:{{{p|}}}||.}}'), $named);
		$this->assertSame(array(1 => '2'), $positional);
		$this->assertSame(array('a', '{{{b|{{{c}}}}}}', 'd'), templates::split_pieces('a|{{{b|{{{c}}}}}}|d'));
		$this->assertSame(array('a', '}} b', 'c'), templates::split_pieces('a|}} b|c'), 'a stray }} closes nothing');
	}

	public function test_an_equals_sign_after_a_link_does_not_name_a_parameter()
	{
		list(, $positional, $named) = templates::split_call('T|[[A]] = B|a=b');

		$this->assertSame(array(1 => '[[A]] = B'), $positional);
		$this->assertSame(array('a' => 'b'), $named);
	}

	public function test_parameters_are_filled_in()
	{
		$text = 'Version {{{1}}} by {{{author}}}, {{{missing|none}}}, {{{empty|}}}.';

		$this->assertSame(
			'Version 2 by me, none, .',
			templates::substitute($text, array(1 => '2'), array('author' => 'me'))
		);
	}

	public function test_a_parameter_that_was_not_given_stays_as_written()
	{
		$this->assertSame('{{{1}}} and {{{name}}}', templates::substitute('{{{1}}} and {{{name}}}', array(), array()));
	}

	public function test_a_default_can_be_another_parameter_or_a_template()
	{
		$this->assertSame('b', templates::substitute('{{{a|{{{b}}}}}}', array(), array('b' => 'b')));
		$this->assertSame('{{Other|x}}', templates::substitute('{{{a|{{Other|x}}}}}', array(), array()));
	}

	public function test_a_default_can_hold_calls_eight_levels_deep()
	{
		$deep = '{{A|{{B|{{C|{{D|{{E|{{F|{{G|{{H|x}}}}}}}}}}}}}}}}';
		$this->assertSame($deep, templates::substitute('{{{a|' . $deep . '}}}', array(), array()));
		$this->assertSame('{{#if:  | {{X|{{Y|1}}}} }}', templates::substitute('{{{a|{{#if: {{{b|}}} | {{X|{{Y|1}}}} }}}}}', array(), array()), 'a parameter inside the default is filled in first');
		$this->assertSame('given', templates::substitute('{{{a|' . $deep . '}}}', array(), array('a' => 'given')));

		$too_deep = '{{{a|{{A|' . $deep . '}}}}}';
		$this->assertSame($too_deep, templates::substitute($too_deep, array(), array()), 'nine levels stay as written');

		// The board's setting moves the limit either way
		$this->assertSame('{{A|' . $deep . '}}', templates::substitute($too_deep, array(), array(), 9));
		$this->assertSame('{{{a|' . $deep . '}}}', templates::substitute('{{{a|' . $deep . '}}}', array(), array(), 3));
	}

	public function test_the_nesting_setting_is_kept_in_range()
	{
		$this->assertSame(8, templates::nesting_in_range(null), 'no setting yet: the default');
		$this->assertSame(8, templates::nesting_in_range('0'));
		$this->assertSame(3, templates::nesting_in_range(2));
		$this->assertSame(12, templates::nesting_in_range('12'));
		$this->assertSame(20, templates::nesting_in_range(99));
	}

	public function test_parameter_values_are_not_read_as_parameters_again()
	{
		$this->assertSame('{{{two}}}', templates::substitute('{{{one}}}', array(), array('one' => '{{{two}}}')));
	}

	public function test_names_take_off_the_template_prefix()
	{
		$this->assertSame('Out of date', templates::normalise_name('  Template:Out of date '));
		$this->assertSame('Out of date', templates::normalise_name('template: Out of date'));
		$this->assertSame('Out of date', templates::normalise_name('Out of date'));
		$this->assertSame('Out of date', templates::normalise_name('out of date'));
		$this->assertSame('Écrit', templates::normalise_name('écrit'));
	}

	public function test_noinclude_and_includeonly_decide_what_is_included()
	{
		$text = "<noinclude>Documentation</noinclude>Body<includeonly> only here</includeonly>";

		$this->assertSame('Body only here', templates::transclusion_text($text));
	}

	public function test_the_documentation_of_a_template_is_what_stands_in_noinclude()
	{
		$text = "<noinclude>First note.</noinclude>Body{{{1}}}<includeonly> hidden </includeonly><noinclude>\nSecond note.\n</noinclude>";

		$this->assertSame("First note.\n\nSecond note.", templates::documentation($text));
		$this->assertSame('', templates::documentation('Body only'));
		$this->assertSame('Body{{{1}}} hidden ', templates::transclusion_text($text));
	}

	public function test_the_parameters_a_template_takes_are_found_once_in_order()
	{
		$text = '<tr><th>{{{1}}}</th><td>{{{2|}}}</td></tr>{{{ name }}} {{{1}}} {{{version|{{{fallback}}}}}}';

		$this->assertSame(array('1', '2', 'name', 'version', 'fallback'), templates::parameters($text));
		$this->assertSame(array(), templates::parameters('No values here, just {{Other template}}.'));
	}

	public function test_the_usage_shows_a_placeholder_for_each_value()
	{
		$this->assertSame('{{Out of date}}', templates::usage('Out of date', array()));
		$this->assertSame('{{Row|…|…}}', templates::usage('Row', array('1', '2')));
		// Unnamed values are counted up to the highest number used, and named ones follow
		$this->assertSame('{{Box|…|…|…|title=…}}', templates::usage('Box', array('title', '3')));
		$this->assertSame('{{Extension infobox|name=…|version=…}}', templates::usage('Extension infobox', array('name', 'version')));
	}
}
