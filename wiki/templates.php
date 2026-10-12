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

/**
 * Templates: {{Name}}, {{Name|first|second}} and {{Name|key=value}} in a wiki article
 * include the live version of the article "Template:Name" at that place. Inside the
 * template, {{{1}}}, {{{key}}} and {{{key|default}}} stand for the parameters.
 *
 * The call is stored in the article as a WIKITEMPLATE tag (see wikilink_listener) and
 * expanded when the article is shown, so editing a template changes every article that
 * uses it. The template's text is parsed and rendered like any article text, so it can
 * bring in nothing an article could not.
 */
class templates
{
	/** Templates inside templates stop here. */
	const MAX_DEPTH = 10;

	/** Most templates expanded for one article, so a template calling itself twice cannot run away. */
	const MAX_EXPANSIONS = 500;

	/** Calls, parser functions and magic words worked out for one article, at most. */
	const MAX_OPERATIONS = 2000;

	/** Longest text a template call may come to, in characters. */
	const MAX_LENGTH = 200000;

	/** What an error message starts with, which {{#iferror:}} looks for. */
	const ERROR_OPEN = '[color=red][b]';

	/** Article names of templates start with this. */
	const PREFIX = 'Template:';

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var string */
	protected $article_table;

	/** @var int Template expansions in progress, from the article down */
	protected $depth = 0;

	/** @var int Templates expanded since the outermost one began */
	protected $expansions = 0;

	/** @var array Names of the templates being expanded, outermost first */
	protected $stack = array();

	/** @var int Templates being included inside the text now being worked out */
	protected $level = 0;

	/** @var int Calls, functions and magic words worked out since the outermost one began */
	protected $operations = 0;

	/** @var array Raw template text by article name, for this request */
	protected $sources = array();

	/**
	 * @param \phpbb\config\config				$config			Config object
	 * @param \phpbb\db\driver\driver_interface	$db				Database object
	 * @param \phpbb\language\language			$language		Language object
	 * @param string								$article_table	Wiki article table
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\language\language $language, $article_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->language = $language;
		$this->article_table = $article_table;
	}

	/**
	 * Whether a template is being expanded, that is, whether the text now being
	 * rendered is a template's and not the article's.
	 *
	 * @return bool
	 */
	public function is_expanding()
	{
		return $this->depth > 0;
	}

	/**
	 * Replace every template placeholder in rendered text with the template's output.
	 *
	 * @param string $html Rendered text that holds <span class="wikitemplate"> placeholders
	 * @return string The text with the templates in place
	 */
	public function expand_all($html)
	{
		$pattern = '/<span class="wikitemplate" data-wiki-template="([^"]*)" data-wiki-call="([^"]*)"><\/span>/';

		return preg_replace_callback($pattern, function ($match) {
			return $this->expand(
				htmlspecialchars_decode($match[1], ENT_QUOTES),
				htmlspecialchars_decode($match[2], ENT_QUOTES)
			);
		}, $html);
	}

	/**
	 * Expand one template call, parser function or magic word as it stands in an article.
	 *
	 * @param string $name The name as written (kept for the tag; the call has it too)
	 * @param string $call What stood between the braces: the name and the parameters
	 * @return string Rendered text
	 */
	public function expand($name, $call)
	{
		if ($this->depth === 0)
		{
			$this->expansions = 0;
			$this->operations = 0;
		}

		if ($this->depth >= self::MAX_DEPTH || ++$this->expansions > self::MAX_EXPANSIONS)
		{
			return $this->render($this->error($this->language->lang('WIKI_TEMPLATE_TOO_DEEP', trim($name))));
		}

		$text = trim($this->expand_text('{{' . $call . '}}'));
		if ($text === '')
		{
			return '';
		}

		return $this->render($text);
	}

	/**
	 * Work out every call in a piece of text: templates are replaced by their text with
	 * the parameters filled in, parser functions and magic words by their result. What
	 * comes back is text for an article, not yet parsed.
	 *
	 * @param string $text Text that may hold {{calls}}
	 * @return string
	 */
	public function expand_text($text)
	{
		$out = '';
		$position = 0;
		while (($start = self::find_call($text, $position)) !== false)
		{
			$end = self::call_end($text, $start);
			if ($end === false)
			{
				break;
			}

			$out .= substr($text, $position, $start - $position) . $this->evaluate(substr($text, $start + 2, $end - $start - 4));
			$position = $end;

			if (strlen($out) > self::MAX_LENGTH)
			{
				return substr($out, 0, self::MAX_LENGTH);
			}
		}

		return $out . substr($text, $position);
	}

	/**
	 * Where the next {{call}} starts, passing {{{parameters}}}.
	 *
	 * @param string $text
	 * @param int $from Where to start looking
	 * @return int|bool The position of its first brace, or false
	 */
	protected static function find_call($text, $from)
	{
		$length = strlen($text);
		for ($i = $from; $i < $length - 1; $i++)
		{
			if ($text[$i] === '{' && $text[$i + 1] === '{')
			{
				if (isset($text[$i + 2]) && $text[$i + 2] === '{')
				{
					$i += 2;
					continue;
				}

				return $i;
			}
		}

		return false;
	}

	/**
	 * Where the call that starts at a position ends, counting the braces that open
	 * and close inside it.
	 *
	 * @param string $text
	 * @param int $start The position of the call's {{
	 * @return int|bool The position just after its }}, or false when it is never closed
	 */
	protected static function call_end($text, $start)
	{
		$stack = array();
		$length = strlen($text);
		for ($i = $start; $i < $length;)
		{
			if (substr($text, $i, 3) === '{{{')
			{
				$stack[] = 3;
				$i += 3;
			}
			else if (substr($text, $i, 2) === '{{')
			{
				$stack[] = 2;
				$i += 2;
			}
			else if ($text[$i] === '}' && !empty($stack) && substr($text, $i, end($stack)) === str_repeat('}', end($stack)))
			{
				$i += array_pop($stack);
				if (empty($stack))
				{
					return $i;
				}
			}
			else
			{
				$i++;
			}
		}

		return false;
	}

	/**
	 * Work out one call.
	 *
	 * @param string $inner What stood between the braces
	 * @return string
	 */
	protected function evaluate($inner)
	{
		if (++$this->operations > self::MAX_OPERATIONS)
		{
			return $this->error($this->language->lang('WIKI_TEMPLATE_TOO_DEEP', trim(strtok($inner, '|'))));
		}

		$trimmed = ltrim($inner);
		if (preg_match('/^#([a-z]+)\s*:(.*)$/is', $trimmed, $match))
		{
			return $this->parser_function(strtolower($match[1]), self::split_pieces($match[2]));
		}
		if (preg_match('/^(lc|uc|lcfirst|ucfirst|urlencode|anchorencode)\s*:(.*)$/is', $trimmed, $match))
		{
			return $this->text_function(strtolower($match[1]), trim($this->expand_text($match[2])));
		}
		if (preg_match('/^(padleft|padright)\s*:(.*)$/is', $trimmed, $match))
		{
			return $this->pad_function(strtolower($match[1]) === 'padleft', self::split_pieces($match[2]));
		}
		if (preg_match('/^([A-Z][A-Z0-9]*)\s*$/', $trimmed, $match) && ($word = $this->magic_word($match[1])) !== null)
		{
			return $word;
		}

		return $this->include_template($inner);
	}

	/**
	 * Include a template: its text with the parameters filled in, and its own calls worked out.
	 *
	 * @param string $call The name and the parameters
	 * @return string
	 */
	protected function include_template($call)
	{
		list($name, $positional, $named) = self::split_call($call);
		$name = self::normalise_name($name);
		$article = self::PREFIX . $name;

		if ($this->level >= self::MAX_DEPTH)
		{
			return $this->error($this->language->lang('WIKI_TEMPLATE_TOO_DEEP', $name));
		}
		if (in_array($article, $this->stack, true))
		{
			return $this->error($this->language->lang('WIKI_TEMPLATE_LOOP', $name));
		}

		$source = $this->source($article);
		if ($source === null)
		{
			// A red link to the missing template
			return '[[' . $article . ']]';
		}

		// The parameters are worked out first, then put in, then the template's own calls
		$positional = array_map(array($this, 'expand_text'), $positional);
		$named = array_map(array($this, 'expand_text'), $named);
		$text = self::substitute(self::transclusion_text($source), $positional, $named);

		$this->stack[] = $article;
		$this->level++;
		try
		{
			return trim($this->expand_text($text));
		}
		finally
		{
			$this->level--;
			array_pop($this->stack);
		}
	}

	/**
	 * {{#if:}}, {{#ifeq:}}, {{#switch:}}, {{#ifexist:}}, {{#expr:}} and {{#ifexpr:}}. Only
	 * the part that is chosen is worked out, so a template can stop its own recursion with
	 * {{#if:}}.
	 *
	 * @param string $function The name without the # (lower case)
	 * @param array $args The arguments as written
	 * @return string
	 */
	protected function parser_function($function, array $args)
	{
		// Each argument is worked out when it is first wanted, and once
		$worked = array();
		$arg = function ($index) use ($args, &$worked) {
			if (!isset($worked[$index]))
			{
				$worked[$index] = isset($args[$index]) ? trim($this->expand_text($args[$index])) : '';
			}

			return $worked[$index];
		};

		switch ($function)
		{
			case 'if':
				return ($arg(0) !== '') ? $arg(1) : $arg(2);

			case 'ifeq':
				return self::same_value($arg(0), $arg(1)) ? $arg(2) : $arg(3);

			case 'ifexist':
				return $this->page_exists($arg(0)) ? $arg(1) : $arg(2);

			case 'expr':
				return $this->calculate($arg(0));

			case 'ifexpr':
				try
				{
					$result = expression::is_true($arg(0));
				}
				catch (\InvalidArgumentException $e)
				{
					return $this->expression_error($e);
				}

				return $result ? $arg(1) : $arg(2);

			case 'switch':
				return $this->switch_value($arg(0), array_slice($args, 1));

			case 'iferror':
				if (strpos($arg(0), self::ERROR_OPEN) !== false)
				{
					return $arg(1);
				}

				return isset($args[2]) ? $arg(2) : $arg(0);

			case 'time':
				$time = text_tools::time($arg(0), $arg(1), $this->now());

				return ($time === null) ? $this->error($this->language->lang('WIKI_TIME_INVALID')) : $time;

			case 'len':
				return (string) mb_strlen($arg(0));

			case 'pos':
				return text_tools::pos($arg(0), $arg(1), (int) $arg(2));

			case 'rpos':
				return text_tools::rpos($arg(0), $arg(1));

			case 'sub':
				return text_tools::sub($arg(0), (int) $arg(1), ($arg(2) === '') ? null : (int) $arg(2));

			case 'replace':
				return text_tools::replace($arg(0), $arg(1), $arg(2));

			case 'explode':
				return text_tools::explode($arg(0), $arg(1), (int) $arg(2), ($arg(3) === '') ? null : (int) $arg(3));
		}

		return $this->error($this->language->lang('WIKI_TEMPLATE_UNKNOWN_FUNCTION', '#' . $function));
	}

	/**
	 * {{#switch:}}: the result for the case that matches. A case without a result falls
	 * through to the next one that has one; a last argument without = is the default.
	 *
	 * @param string $value The value looked up
	 * @param array $cases The cases as written, "case = result" or "case"
	 * @return string
	 */
	protected function switch_value($value, array $cases)
	{
		$matched = false;
		$default = null;
		$last = count($cases) - 1;
		foreach ($cases as $index => $case)
		{
			$pair = self::split_equals($case);
			if ($pair === null)
			{
				if ($index === $last)
				{
					return $matched ? trim($this->expand_text($case)) : ($default !== null ? trim($this->expand_text($default)) : trim($this->expand_text($case)));
				}
				$matched = $matched || self::same_value($value, trim($this->expand_text($case)));
				continue;
			}

			$key = trim($this->expand_text($pair[0]));
			if ($matched || self::same_value($value, $key))
			{
				return trim($this->expand_text($pair[1]));
			}
			if ($key === '#default')
			{
				$default = $pair[1];
			}
		}

		return ($default !== null) ? trim($this->expand_text($default)) : '';
	}

	/**
	 * {{lc:}}, {{uc:}}, {{lcfirst:}} and {{ucfirst:}}.
	 *
	 * @param string $function
	 * @param string $text
	 * @return string
	 */
	protected function text_function($function, $text)
	{
		switch ($function)
		{
			case 'lc':
				return mb_strtolower($text);
			case 'uc':
				return mb_strtoupper($text);
			case 'lcfirst':
				return mb_strtolower(mb_substr($text, 0, 1)) . mb_substr($text, 1);
			case 'urlencode':
				return urlencode($text);
			case 'anchorencode':
				return markup::anchor($text);
			default:
				return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
		}
	}

	/**
	 * {{padleft:text|length|fill}} and {{padright:text|length|fill}}.
	 *
	 * @param bool $left
	 * @param array $args The arguments as written
	 * @return string
	 */
	protected function pad_function($left, array $args)
	{
		$text = isset($args[0]) ? trim($this->expand_text($args[0])) : '';
		$length = isset($args[1]) ? (int) trim($this->expand_text($args[1])) : 0;
		$fill = isset($args[2]) ? trim($this->expand_text($args[2])) : '0';

		return text_tools::pad($text, $length, ($fill === '') ? '0' : $fill, $left);
	}

	/**
	 * @return int The time now, as a Unix timestamp
	 */
	protected function now()
	{
		return time();
	}

	/**
	 * {{PAGENAME}}, {{FULLPAGENAME}}, {{NAMESPACE}}, {{SITENAME}} and the date words such as
	 * {{CURRENTYEAR}}.
	 *
	 * @param string $word
	 * @return string|null Null when the word is none of these, so it may be a template
	 */
	protected function magic_word($word)
	{
		$full = (string) $this->current_article();
		$in_namespace = stripos($full, self::PREFIX) === 0;

		switch ($word)
		{
			case 'FULLPAGENAME':
				return $full;
			case 'NAMESPACE':
				return $in_namespace ? rtrim(self::PREFIX, ':') : '';
			case 'PAGENAME':
				return $in_namespace ? ltrim(substr($full, strlen(self::PREFIX))) : $full;
			case 'SITENAME':
				return (string) $this->config['sitename'];
		}

		return text_tools::date_word($word, $this->now());
	}

	/**
	 * @return string|null The article being shown, as in its address
	 */
	protected function current_article()
	{
		return files::get_context();
	}

	/**
	 * @param string $text An expression
	 * @return string The result, or a message when it cannot be worked out
	 */
	protected function calculate($text)
	{
		try
		{
			return expression::calculate($text);
		}
		catch (\InvalidArgumentException $e)
		{
			return $this->expression_error($e);
		}
	}

	/**
	 * @param \InvalidArgumentException $e From the expression reader; its message is a language key,
	 *                                      with the thing at fault after a bar
	 * @return string
	 */
	protected function expression_error(\InvalidArgumentException $e)
	{
		$parts = explode('|', $e->getMessage(), 2);

		$reason = isset($parts[1]) ? $this->language->lang($parts[0], $parts[1]) : $this->language->lang($parts[0]);

		return $this->error($this->language->lang('WIKI_EXPR_ERROR', $reason));
	}

	/**
	 * Whether two values count as equal for {{#ifeq:}} and {{#switch:}}: as numbers when
	 * both are numbers, otherwise as text.
	 *
	 * @param string $a
	 * @param string $b
	 * @return bool
	 */
	protected static function same_value($a, $b)
	{
		if (is_numeric($a) && is_numeric($b))
		{
			return (float) $a == (float) $b;
		}

		return $a === $b;
	}

	/**
	 * Cut text at its bars, leaving alone those inside [[links]], {{templates}} and {{{parameters}}}.
	 *
	 * @param string $text For example "a|[[b|c]]|{{d|e}}"
	 * @return array The pieces as written, "a", "[[b|c]]" and "{{d|e}}" here
	 */
	public static function split_pieces($text)
	{
		$parts = array();
		$part = '';
		$braces = $brackets = 0;
		$length = strlen($text);
		for ($i = 0; $i < $length; $i++)
		{
			$pair = substr($text, $i, 2);
			if ($pair === '{{' || $pair === '}}' || $pair === '[[' || $pair === ']]')
			{
				$braces += ($pair === '{{') - ($pair === '}}');
				$brackets += ($pair === '[[') - ($pair === ']]');
				$part .= $pair;
				$i++;
			}
			else if ($text[$i] === '|' && $braces <= 0 && $brackets <= 0)
			{
				$parts[] = $part;
				$part = '';
			}
			else
			{
				$part .= $text[$i];
			}
		}
		$parts[] = $part;

		return $parts;
	}

	/**
	 * Cut "key = value" at its first = that is not inside a link or template.
	 *
	 * @param string $text
	 * @return array|null The key and the value, or null when there is no =
	 */
	protected static function split_equals($text)
	{
		$braces = $brackets = 0;
		$length = strlen($text);
		for ($i = 0; $i < $length; $i++)
		{
			$pair = substr($text, $i, 2);
			if ($pair === '{{' || $pair === '}}' || $pair === '[[' || $pair === ']]')
			{
				$braces += ($pair === '{{') - ($pair === '}}');
				$brackets += ($pair === '[[') - ($pair === ']]');
				$i++;
			}
			else if ($text[$i] === '=' && $braces <= 0 && $brackets <= 0)
			{
				return array(substr($text, 0, $i), substr($text, $i + 1));
			}
		}

		return null;
	}

	/**
	 * Name of a template as it is stored: trimmed, without a leading "Template:", and
	 * with a capital first letter, as on Wikipedia, so {{out of date}} finds "Out of date".
	 *
	 * @param string $name The name as written
	 * @return string
	 */
	public static function normalise_name($name)
	{
		$name = trim($name);
		if (stripos($name, self::PREFIX) === 0)
		{
			$name = ltrim(substr($name, strlen(self::PREFIX)));
		}

		return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
	}

	/**
	 * Split what stood between {{ and }} into the template's name and its parameters.
	 * Bars inside [[links]], {{templates}} and {{{parameters}}} belong to those.
	 *
	 * @param string $call For example "Infobox|name=Wiki|1.1.0"
	 * @return array The name, the positional parameters (numbered from 1) and the named ones
	 */
	public static function split_call($call)
	{
		$parts = self::split_pieces($call);

		$name = trim(array_shift($parts));
		$positional = $named = array();
		$next = 1;
		foreach ($parts as $part)
		{
			// key=value, as long as the = comes before any link or template in the part
			if (preg_match('/^\s*([^=\[\]{}<>\n]+?)\s*=(.*)$/s', $part, $match))
			{
				$key = $match[1];
				$value = trim($match[2]);
				if (ctype_digit($key))
				{
					$positional[(int) $key] = $value;
				}
				else
				{
					$named[$key] = $value;
				}
			}
			else
			{
				$positional[$next++] = $part;
			}
		}

		return array($name, $positional, $named);
	}

	/**
	 * Fill {{{1}}}, {{{key}}} and {{{key|default}}} with the parameters. A parameter that
	 * was not given and has no default stays as it was written.
	 *
	 * @param string $text The template's text
	 * @param array $positional Parameters by position, from 1
	 * @param array $named Parameters by name
	 * @return string
	 */
	public static function substitute($text, array $positional, array $named)
	{
		// The innermost {{{ }}} first, so a default can hold another parameter
		$pattern = '/\{\{\{\s*([^{}|]*?)\s*(?:\|((?:[^{}]|\{\{(?!\{)[^{}]*\}\})*))?\}\}\}/';
		$replace = function ($match) use ($positional, $named) {
			$key = $match[1];
			if (ctype_digit($key) && isset($positional[(int) $key]))
			{
				return $positional[(int) $key];
			}
			if (isset($named[$key]))
			{
				return $named[$key];
			}

			return isset($match[2]) ? $match[2] : $match[0];
		};

		for ($pass = 0; $pass < self::MAX_DEPTH; $pass++)
		{
			$changed = preg_replace_callback($pattern, $replace, $text);
			if ($changed === $text)
			{
				break;
			}
			$text = $changed;
		}

		return $text;
	}

	/**
	 * The part of a template's text that is included: not what is inside
	 * <noinclude>, and what is inside <includeonly> without the tags.
	 *
	 * @param string $text The template page's text
	 * @return string
	 */
	public static function transclusion_text($text)
	{
		$text = preg_replace('#<noinclude>.*?</noinclude>#is', '', $text);

		return preg_replace('#</?includeonly>#i', '', $text);
	}

	/**
	 * The text of the live version of a template page, as an editor would see it.
	 *
	 * @param string $article The template's article name, with the prefix
	 * @return string|null Null when there is no approved version
	 */
	protected function source($article)
	{
		if (array_key_exists($article, $this->sources))
		{
			return $this->sources[$article];
		}

		$sql = 'SELECT article_text, bbcode_uid
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
				AND article_approved = 1
			ORDER BY article_last_edit DESC, article_id DESC";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return $this->sources[$article] = null;
		}

		$edit = generate_text_for_edit($row['article_text'], $row['bbcode_uid'], OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);

		return $this->sources[$article] = htmlspecialchars_decode($edit['text'], ENT_COMPAT);
	}

	/**
	 * Whether an article has an approved version.
	 *
	 * @param string $article The article's name as in its address
	 * @return bool
	 */
	protected function page_exists($article)
	{
		if ($article === '')
		{
			return false;
		}
		if (array_key_exists($article, $this->sources))
		{
			return $this->sources[$article] !== null;
		}

		$sql = 'SELECT article_id
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
				AND article_approved = 1";
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $exists;
	}

	/**
	 * Parse and render text the way an article's is, with the wiki's own markup on.
	 *
	 * @param string $text Template text with its parameters filled in
	 * @return string
	 */
	protected function render($text)
	{
		$uid = $bitfield = '';
		$flags = 0;

		$was_active = markup::is_active();
		markup::set_active(true);
		try
		{
			generate_text_for_storage($text, $uid, $bitfield, $flags, true, true, true);
		}
		finally
		{
			markup::set_active($was_active);
		}

		// While this text is rendered, what it still holds is part of the article being shown
		$this->depth++;
		try
		{
			return generate_text_for_display($text, $uid, $bitfield, $flags);
		}
		finally
		{
			$this->depth--;
		}
	}

	/**
	 * @param string $message A message to show where a template could not be worked out
	 * @return string Text for an article: the message in bold red
	 */
	protected function error($message)
	{
		return self::ERROR_OPEN . str_replace(array('[', ']'), '', $message) . '[/b][/color]';
	}
}
