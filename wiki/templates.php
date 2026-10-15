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

	/** All the text the calls in one article may produce, counted as they are worked out, in characters. */
	const MAX_OUTPUT = 500000;

	/** What an error message starts with, which {{#iferror:}} looks for. */
	const ERROR_OPEN = '[color=red][b]';

	/** Article names of templates start with this. */
	const PREFIX = 'Template:';

	/** The name of a template a new article can start from begins with this, after the prefix. */
	const START_PREFIX = 'Start ';

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

	/** @var int Characters produced by calls since the outermost one began */
	protected $produced = 0;

	/** @var int Templates being included inside the text now being worked out */
	protected $level = 0;

	/** @var int Calls, functions and magic words worked out since the outermost one began */
	protected $operations = 0;

	/** @var array Whether an article exists, by name, for this request */
	protected $exists = array();

	/** @var array Raw template text by article name, for this request */
	protected $sources = array();

	/** @var \phpbb\user|null The reader, for #timel; none in the tests that leave it out */
	protected $user;

	/** @var array The live version of each article read for the REVISION words, this request */
	protected $revisions = array();

	/**
	 * @param \phpbb\config\config				$config			Config object
	 * @param \phpbb\db\driver\driver_interface	$db				Database object
	 * @param \phpbb\language\language			$language		Language object
	 * @param string								$article_table	Wiki article table
	 * @param \phpbb\user|null					$user			The reader, for #timel
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\language\language $language, $article_table, \phpbb\user|null $user = null)
	{
		$this->user = $user;
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
		// The limits are for the whole of one article, not for each call in it
		if ($this->depth === 0)
		{
			$this->expansions = 0;
			$this->operations = 0;
			$this->produced = 0;
		}

		$pattern = '/<template class="wikitemplate" data-wiki-template="([^"]*)" data-wiki-call="([^"]*)"><\/template>/';

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
		if ($this->depth >= self::MAX_DEPTH || ++$this->expansions > self::MAX_EXPANSIONS || $this->operations > self::MAX_OPERATIONS || $this->produced > self::MAX_OUTPUT)
		{
			// Once the limits are used up, the rest of the article's calls are cheap to refuse
			return '<strong class="error">' . htmlspecialchars($this->language->lang('WIKI_TEMPLATE_TOO_DEEP', trim($name)), ENT_QUOTES, 'UTF-8') . '</strong>';
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

		$result = $this->evaluate_call($inner);
		$this->produced += strlen($result);
		if ($this->produced > self::MAX_OUTPUT)
		{
			return $this->error($this->language->lang('WIKI_TEMPLATE_TOO_DEEP', trim(strtok($inner, '|'))));
		}

		return $result;
	}

	/**
	 * Work out one call, whatever it is.
	 *
	 * @param string $inner What stood between the braces
	 * @return string
	 */
	protected function evaluate_call($inner)
	{
		$trimmed = ltrim($inner);
		if (preg_match('/^#([a-z][a-z0-9]*)\s*:(.*)$/is', $trimmed, $match))
		{
			return $this->parser_function(strtolower($match[1]), self::split_pieces($match[2]));
		}
		if (preg_match('/^(lc|uc|lcfirst|ucfirst|urlencode|anchorencode)\s*:(.*)$/is', $trimmed, $match))
		{
			return $this->text_function(strtolower($match[1]), trim($this->expand_text($match[2])));
		}
		if (preg_match('/^(formatnum|plural|gender)\s*:(.*)$/is', $trimmed, $match))
		{
			return $this->colon_function(strtolower($match[1]), self::split_pieces($match[2]));
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

			case 'titleparts':
				return text_tools::titleparts($arg(0), (int) $arg(1), ($arg(2) === '') ? 1 : (int) $arg(2));

			case 'rel2abs':
				return text_tools::rel2abs($arg(0), (isset($args[1]) && $arg(1) !== '') ? $arg(1) : (string) $this->current_article());

			case 'tag':
				return $this->tag_function($arg(0), isset($args[1]) ? $arg(1) : null, array_slice($args, 2));

			case 'timel':
				return $this->local_time($arg(0), $arg(1));
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
	 * {{formatnum:...}}, {{plural:...}} and {{gender:...}}.
	 *
	 * @param string $function
	 * @param array $pieces The parts after the colon, as written
	 * @return string
	 */
	protected function colon_function($function, array $pieces)
	{
		$pieces = array_map(function ($piece) {
			return trim($this->expand_text($piece));
		}, $pieces);

		switch ($function)
		{
			case 'formatnum':
				return text_tools::formatnum($pieces[0], isset($pieces[1]) ? $pieces[1] : '');

			case 'plural':
				return text_tools::plural($pieces[0], array_slice($pieces, 1));

			default:
				// gender: phpBB keeps no gender, so the neutral form (the third), or else the first,
				// as MediaWiki gives for a member who has not said
				return isset($pieces[3]) ? $pieces[3] : (isset($pieces[1]) ? $pieces[1] : '');
		}
	}

	/**
	 * {{#tag: name | content | attribute=value ...}}: an HTML tag written out. It is parsed with
	 * the article afterwards, so only what the wiki allows written by hand works (tables,
	 * <center>, <details>); anything else stays text.
	 *
	 * @param string $name
	 * @param string|null $content Null for an empty tag
	 * @param array $attributes Pieces of the form key=value, as written
	 * @return string
	 */
	protected function tag_function($name, $content, array $attributes)
	{
		$name = strtolower(trim($name));
		if (!preg_match('/^[a-z][a-z0-9]*$/', $name))
		{
			return $this->error($this->language->lang('WIKI_TEMPLATE_UNKNOWN_FUNCTION', '#tag:' . $name));
		}

		$html = '<' . $name;
		foreach ($attributes as $attribute)
		{
			$pair = self::split_equals($this->expand_text($attribute));
			if ($pair !== null && preg_match('/^[a-z][a-z0-9-]*$/i', trim($pair[0])))
			{
				$value = trim(trim($pair[1]), '"\'');
				$html .= ' ' . strtolower(trim($pair[0])) . '="' . str_replace('"', '&quot;', $value) . '"';
			}
		}

		return $html . '>' . (($content === null) ? '' : $content) . '</' . $name . '>';
	}

	/**
	 * {{#timel:...}}: like #time, in the reader's time zone, with month and day names in the
	 * reader's language. Without a reader (as in some tests) it is #time.
	 *
	 * @param string $format
	 * @param string $date
	 * @return string
	 */
	protected function local_time($format, $date)
	{
		$zone = ($this->user !== null && isset($this->user->timezone) && $this->user->timezone instanceof \DateTimeZone) ? $this->user->timezone : new \DateTimeZone('UTC');
		$time = text_tools::time($format, $date, $this->now(), $zone);
		if ($time === null)
		{
			return $this->error($this->language->lang('WIKI_TIME_INVALID'));
		}
		$names = $this->language->lang_raw('datetime');

		return is_array($names) ? strtr($time, $names) : $time;
	}

	/**
	 * The REVISION words: the live version of the article being shown.
	 *
	 * @param string $word
	 * @return string|null Null when it is not one of them
	 */
	protected function revision_word($word)
	{
		static $formats = array(
			'REVISIONDAY'		=> 'j',
			'REVISIONDAY2'		=> 'd',
			'REVISIONMONTH'		=> 'm',
			'REVISIONMONTH1'	=> 'n',
			'REVISIONYEAR'		=> 'Y',
			'REVISIONTIMESTAMP'	=> 'YmdHis',
		);
		if (!isset($formats[$word]) && $word !== 'REVISIONID' && $word !== 'REVISIONUSER')
		{
			return null;
		}

		$row = $this->live_revision((string) $this->current_article());
		if (!$row)
		{
			return '';
		}

		switch ($word)
		{
			case 'REVISIONID':
				return (string) $row['article_id'];
			case 'REVISIONUSER':
				return (string) $row['username'];
		}

		return (new \DateTimeImmutable('@' . (int) $row['article_last_edit']))->setTimezone(new \DateTimeZone('UTC'))->format($formats[$word]);
	}

	/**
	 * The live version of an article, read once per request.
	 *
	 * @param string $article
	 * @return array The row (article_id, article_last_edit, username), empty when there is none
	 */
	protected function live_revision($article)
	{
		if (!isset($this->revisions[$article]))
		{
			$sql = 'SELECT a.article_id, a.article_last_edit, u.username
				FROM ' . $this->article_table . ' a
				LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = a.article_user_id
				WHERE ' . $this->live_where('a', '') . "
					AND a.article_url = '" . $this->db->sql_escape($article) . "'";
			$result = $this->db->sql_query_limit($sql, 1);
			$this->revisions[$article] = $this->db->sql_fetchrow($result) ?: array();
			$this->db->sql_freeresult($result);
		}

		return $this->revisions[$article];
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

		$revision = $this->revision_word($word);
		if ($revision !== null)
		{
			return $revision;
		}
		// LOCAL... is the CURRENT... word in the board's time zone
		if (strpos($word, 'LOCAL') === 0)
		{
			try
			{
				$zone = new \DateTimeZone(!empty($this->config['board_timezone']) ? $this->config['board_timezone'] : 'UTC');
			}
			catch (\Exception $e)
			{
				$zone = new \DateTimeZone('UTC');
			}

			return text_tools::date_word('CURRENT' . substr($word, 5), $this->now(), $zone);
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
		$braces = array();
		$brackets = 0;
		$length = strlen($text);
		for ($i = 0; $i < $length;)
		{
			if ($step = self::brace_step($text, $i, $braces))
			{
				$part .= substr($text, $i, $step);
				$i += $step;
				continue;
			}

			$pair = substr($text, $i, 2);
			if ($pair === '[[' || $pair === ']]')
			{
				$brackets += ($pair === '[[') - ($pair === ']]');
				$part .= $pair;
				$i += 2;
			}
			else
			{
				if ($text[$i] === '|' && empty($braces) && $brackets <= 0)
				{
					$parts[] = $part;
					$part = '';
				}
				else
				{
					$part .= $text[$i];
				}
				$i++;
			}
		}
		$parts[] = $part;

		return $parts;
	}

	/**
	 * Step over the braces at a position, keeping count of the {{calls}} and {{{parameters}}}
	 * open there: {{{ opens a parameter and {{ a call, and each closes only with as many
	 * braces as it opened with, as in call_end(), so a run like }}}}}}}} closes the right ones.
	 *
	 * @param string $text
	 * @param int $i The position
	 * @param array $stack The open braces (3 or 2 each), changed in place
	 * @return int How many characters were braces that opened or closed something, or 0
	 */
	protected static function brace_step($text, $i, array &$stack)
	{
		if (substr($text, $i, 3) === '{{{')
		{
			$stack[] = 3;

			return 3;
		}
		if (substr($text, $i, 2) === '{{')
		{
			$stack[] = 2;

			return 2;
		}
		if ($text[$i] === '}' && !empty($stack) && substr($text, $i, end($stack)) === str_repeat('}', end($stack)))
		{
			return array_pop($stack);
		}

		return 0;
	}

	/**
	 * Cut "key = value" at its first = that is not inside a link or template.
	 *
	 * @param string $text
	 * @return array|null The key and the value, or null when there is no =
	 */
	protected static function split_equals($text)
	{
		$braces = array();
		$brackets = 0;
		$length = strlen($text);
		for ($i = 0; $i < $length;)
		{
			if ($step = self::brace_step($text, $i, $braces))
			{
				$i += $step;
				continue;
			}

			$pair = substr($text, $i, 2);
			if ($pair === '[[' || $pair === ']]')
			{
				$brackets += ($pair === '[[') - ($pair === ']]');
				$i += 2;
			}
			else if ($text[$i] === '=' && empty($braces) && $brackets <= 0)
			{
				return array(substr($text, 0, $i), substr($text, $i + 1));
			}
			else
			{
				$i++;
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
	 * SQL condition for the live version of a template page: approved, and not replaced
	 * by a newer approved version.
	 *
	 * @param string $alias The alias of the article table in the query
	 * @param string $name_prefix What the article's name starts with, "Template:" for all templates
	 * @return string
	 */
	public function live_where($alias, $name_prefix)
	{
		return $alias . '.article_approved = 1
			AND ' . $alias . '.article_url ' . $this->db->sql_like_expression($name_prefix . $this->db->get_any_char()) . '
			AND NOT EXISTS (
				SELECT 1 FROM ' . $this->article_table . ' b
				WHERE b.article_url = ' . $alias . '.article_url
					AND b.article_approved = 1
					AND (b.article_last_edit > ' . $alias . '.article_last_edit
						OR (b.article_last_edit = ' . $alias . '.article_last_edit AND b.article_id > ' . $alias . '.article_id)))';
	}

	/**
	 * The templates a new article can start from: the live ones called "Template:Start ...".
	 *
	 * @return array Rows with the name (for example "Start Extension"), a label (the rest of the name) and the description
	 */
	public function start_templates()
	{
		$sql = 'SELECT a.article_url, a.article_title, a.article_description
			FROM ' . $this->article_table . ' a
			WHERE ' . $this->live_where('a', self::PREFIX . self::START_PREFIX) . '
			ORDER BY a.article_title ASC, a.article_id ASC';
		$result = $this->db->sql_query($sql);
		$templates = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$name = substr($row['article_url'], strlen(self::PREFIX));
			$templates[] = array(
				'name'			=> $name,
				'label'			=> substr($name, strlen(self::START_PREFIX)),
				'description'	=> $row['article_description'],
			);
		}
		$this->db->sql_freeresult($result);

		return $templates;
	}

	/**
	 * The text a new article starts with, from one of the "Template:Start ..." pages: the
	 * text of the page without its <noinclude> parts, as it is written.
	 *
	 * @param string $name The template's name, for example "Start Extension"
	 * @return string|null Null when there is no such start template
	 */
	public function start_text($name)
	{
		$name = self::normalise_name($name);
		if (strpos($name, self::START_PREFIX) !== 0)
		{
			return null;
		}

		$source = $this->source(self::PREFIX . $name);

		return ($source === null) ? null : trim(self::transclusion_text($source));
	}

	/**
	 * What to show on the page of a template: its documentation, which is what stands
	 * inside <noinclude> (worked out like an article, so it can show examples), and
	 * its text as a template, which is what an article gets, shown as it is written.
	 *
	 * @param string $article_text The stored text of the page
	 * @param string $bbcode_uid The page's bbcode_uid
	 * @return array The rendered documentation ("" when there is none) and the template text
	 */
	public function page_view($article_text, $bbcode_uid)
	{
		$edit = generate_text_for_edit($article_text, $bbcode_uid, OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | OPTION_FLAG_LINKS);
		$raw = htmlspecialchars_decode($edit['text'], ENT_COMPAT);

		$documentation = self::documentation($raw);
		$html = '';
		if ($documentation !== '')
		{
			$uid = $bitfield = '';
			$flags = 0;
			$was_active = markup::is_active();
			$was_entities = markup::entities();
			markup::set_active(true);
			// Already decoded once on the way here; entities must not be decoded again
			markup::set_entities(false);
			try
			{
				generate_text_for_storage($documentation, $uid, $bitfield, $flags, true, true, true);
			}
			finally
			{
				markup::set_active($was_active);
				markup::set_entities($was_entities);
			}
			$html = generate_text_for_display($documentation, $uid, $bitfield, $flags);
		}

		return array($html, trim(self::transclusion_text($raw)));
	}

	/**
	 * The parameters a text uses, with the default written for each: for the form that asks
	 * for a Start template's values. A default that holds other calls is not offered.
	 *
	 * @param string $text
	 * @return array Names, in the order first written, with their defaults ('' for none)
	 */
	public static function parameter_defaults($text)
	{
		$defaults = array();
		preg_match_all('#\{\{\{\s*([^{}|]+?)\s*(?:\|([^{}]*))?\}\}\}#', $text, $matches, PREG_SET_ORDER);
		foreach ($matches as $match)
		{
			$name = $match[1];
			if (!isset($defaults[$name]) || ($defaults[$name] === '' && isset($match[2])))
			{
				$defaults[$name] = isset($match[2]) ? trim($match[2]) : '';
			}
		}
		// Parameters whose default holds a call are still asked for, without a default
		foreach (self::parameters($text) as $name)
		{
			if (!isset($defaults[$name]))
			{
				$defaults[$name] = '';
			}
		}

		return $defaults;
	}

	/**
	 * The parameters a template's text uses, in the order they first appear.
	 *
	 * @param string $text The template's text, as an article gets it
	 * @return array Their names: "1", "2" ... for unnamed parameters, the name for named ones
	 */
	public static function parameters($text)
	{
		preg_match_all('#\{\{\{\s*([^{}|]+?)\s*(?:\||\}\}\})#', $text, $matches);

		return array_values(array_unique($matches[1]));
	}

	/**
	 * How to write a call to a template, with a placeholder for each value it takes,
	 * for example "{{Row|…|…}}" or "{{Out of date|reason=…}}".
	 *
	 * @param string $name The template's name, without the prefix
	 * @param array $parameters Its parameters, from parameters()
	 * @return string
	 */
	public static function usage($name, array $parameters)
	{
		$unnamed = array_filter($parameters, 'ctype_digit');
		$call = $name . str_repeat('|…', $unnamed ? max(array_map('intval', $unnamed)) : 0);
		foreach (array_diff($parameters, $unnamed) as $parameter)
		{
			$call .= '|' . $parameter . '=…';
		}

		return '{{' . $call . '}}';
	}

	/**
	 * The text inside the <noinclude> parts of a template page.
	 *
	 * @param string $text The template page's text
	 * @return string The parts one after the other, "" when there are none
	 */
	public static function documentation($text)
	{
		preg_match_all('#<noinclude>(.*?)</noinclude>#is', $text, $matches);

		return trim(implode("\n\n", array_map('trim', $matches[1])));
	}

	/**
	 * The text of the live version of a template page, as an editor would see it. A
	 * template that was moved with a redirect left behind is read from its new name, one
	 * step only, so the articles that use the old name keep working.
	 *
	 * @param string $article The template's article name, with the prefix
	 * @param bool $follow_redirect Whether to follow a redirect to another template
	 * @return string|null Null when there is no approved version
	 */
	protected function source($article, $follow_redirect = true)
	{
		if (array_key_exists($article, $this->sources))
		{
			return $this->sources[$article];
		}

		$sql = 'SELECT article_text, bbcode_uid, article_redirect
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

		$target = htmlspecialchars_decode((string) $row['article_redirect'], ENT_COMPAT);
		if ($follow_redirect && strpos($target, self::PREFIX) === 0 && $target !== $article)
		{
			return $this->sources[$article] = $this->source($target, false);
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
		if (isset($this->exists[$article]))
		{
			return $this->exists[$article];
		}

		$sql = 'SELECT article_id
			FROM ' . $this->article_table . "
			WHERE article_url = '" . $this->db->sql_escape($article) . "'
				AND article_approved = 1";
		$result = $this->db->sql_query_limit($sql, 1);
		$exists = (bool) $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $this->exists[$article] = $exists;
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

		// Table rows and cells only parse inside a table, so a template that gives some
		// is parsed in one, and the table is taken off again
		list($open, $close) = self::table_wrapper($text);
		$text = $open . $text . $close;

		$was_active = markup::is_active();
		$was_entities = markup::entities();
		markup::set_active(true);
		// Template text was decoded once on its way here; entities must not be decoded again
		markup::set_entities(false);
		try
		{
			generate_text_for_storage($text, $uid, $bitfield, $flags, true, true, true);
		}
		finally
		{
			markup::set_active($was_active);
			markup::set_entities($was_entities);
		}

		// While this text is rendered, what it still holds is part of the article being shown
		$this->depth++;
		try
		{
			$html = generate_text_for_display($text, $uid, $bitfield, $flags);
		}
		finally
		{
			$this->depth--;
		}

		return ($open === '') ? $html : self::unwrap_table($html, $open);
	}

	/**
	 * What to put around text that starts with a table row or cell so it parses.
	 *
	 * @param string $text
	 * @return array The opening and closing text, empty when none is needed
	 */
	public static function table_wrapper($text)
	{
		if (preg_match('/^\s*<(tr|tbody|thead|tfoot|caption)[\s>]/i', $text))
		{
			return array('<table>', '</table>');
		}
		if (preg_match('/^\s*<(td|th)[\s>]/i', $text))
		{
			return array('<table><tr>', '</tr></table>');
		}

		return array('', '');
	}

	/**
	 * Take the table put round rows or cells by table_wrapper() off the rendered text.
	 *
	 * @param string $html The rendered text
	 * @param string $open The opening text table_wrapper() gave
	 * @return string
	 */
	public static function unwrap_table($html, $open)
	{
		$row = ($open === '<table><tr>') ? '(?:<tr>\s*)' : '';
		$html = preg_replace('#^\s*<table>\s*' . $row . '#i', '', $html);
		$row = ($open === '<table><tr>') ? '(?:</tr>\s*)' : '';

		return preg_replace('#\s*' . $row . '</table>\s*$#i', '', $html);
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
