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
 * The arithmetic behind {{#expr: ...}} and {{#ifexpr: ...}}, after Wikipedia's:
 * numbers, + - * / div mod ^, round, comparisons (= != <> < > <= >=), and, or, not,
 * parentheses, and the functions abs, ceil, floor, trunc, sqrt, exp, ln, sin, cos, tan
 * and the constants e and pi.
 *
 * It reads the text itself and never runs it as PHP.
 */
class expression
{
	/** Longest expression read, in characters. */
	const MAX_LENGTH = 500;

	/** Deepest nesting of brackets and prefix operators. */
	const MAX_DEPTH = 40;

	/** Words that are prefix functions, with what they do to their number. */
	const FUNCTIONS = array('abs', 'ceil', 'floor', 'trunc', 'sqrt', 'exp', 'ln', 'sin', 'cos', 'tan', 'not');

	/** @var array The tokens of the expression */
	protected $tokens = array();

	/** @var int Where the reader is in the tokens */
	protected $position = 0;

	/** @var int Current nesting */
	protected $depth = 0;

	/**
	 * Work out an expression.
	 *
	 * @param string $text For example "(2 + 3) * 4"
	 * @return string The result as it is written on a page: no trailing zeros, "" for empty text
	 * @throws \InvalidArgumentException The message is a language key and the code is unused;
	 *                                   a second part after a bar is the thing at fault
	 */
	public static function calculate($text)
	{
		$text = trim($text);
		if ($text === '')
		{
			return '';
		}
		if (strlen($text) > self::MAX_LENGTH)
		{
			throw new \InvalidArgumentException('WIKI_EXPR_TOO_LONG');
		}

		$reader = new self();
		$reader->tokens = self::tokenise($text);

		return self::format($reader->read());
	}

	/**
	 * Whether an expression comes out as something other than zero.
	 *
	 * @param string $text
	 * @return bool|null Null for empty text
	 * @throws \InvalidArgumentException As calculate()
	 */
	public static function is_true($text)
	{
		$result = self::calculate($text);

		return $result === '' ? null : (float) $result != 0;
	}

	/**
	 * @param string $text
	 * @return array Tokens as array(type, value): number, word, op, open or close
	 */
	protected static function tokenise($text)
	{
		$tokens = array();
		$length = strlen($text);
		for ($i = 0; $i < $length;)
		{
			$rest = substr($text, $i);
			if (preg_match('/^\s+/', $rest, $match))
			{
				$i += strlen($match[0]);
			}
			else if (preg_match('/^(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?/', $rest, $match))
			{
				$tokens[] = array('number', (float) $match[0]);
				$i += strlen($match[0]);
			}
			else if (preg_match('/^[a-z]+/i', $rest, $match))
			{
				$tokens[] = array('word', strtolower($match[0]));
				$i += strlen($match[0]);
			}
			else if (preg_match('/^(?:<=|>=|!=|<>|[-+*\/^=<>])/', $rest, $match))
			{
				$tokens[] = array('op', $match[0]);
				$i += strlen($match[0]);
			}
			else if ($rest[0] === '(' || $rest[0] === ')')
			{
				$tokens[] = array($rest[0] === '(' ? 'open' : 'close', $rest[0]);
				$i++;
			}
			else
			{
				throw new \InvalidArgumentException('WIKI_EXPR_UNEXPECTED|' . $rest[0]);
			}
		}

		return $tokens;
	}

	/**
	 * @return float
	 */
	protected function read()
	{
		$value = $this->read_or();
		if ($this->position < count($this->tokens))
		{
			throw new \InvalidArgumentException('WIKI_EXPR_UNEXPECTED|' . $this->tokens[$this->position][1]);
		}

		return $value;
	}

	/**
	 * @return float
	 */
	protected function read_or()
	{
		$value = $this->read_and();
		while ($this->take_word('or'))
		{
			$right = $this->read_and();
			$value = (float) (($value != 0) || ($right != 0));
		}

		return $value;
	}

	/**
	 * @return float
	 */
	protected function read_and()
	{
		$value = $this->read_compare();
		while ($this->take_word('and'))
		{
			$right = $this->read_compare();
			$value = (float) (($value != 0) && ($right != 0));
		}

		return $value;
	}

	/**
	 * @return float
	 */
	protected function read_compare()
	{
		$value = $this->read_round();
		while (($op = $this->take_op(array('=', '!=', '<>', '<=', '>=', '<', '>'))) !== null)
		{
			$right = $this->read_round();
			switch ($op)
			{
				case '=':
					$value = (float) $this->same($value, $right);
				break;
				case '!=':
				case '<>':
					$value = (float) !$this->same($value, $right);
				break;
				case '<=':
					$value = (float) ($value <= $right);
				break;
				case '>=':
					$value = (float) ($value >= $right);
				break;
				case '<':
					$value = (float) ($value < $right);
				break;
				default:
					$value = (float) ($value > $right);
				break;
			}
		}

		return $value;
	}

	/**
	 * Equal, allowing for the error in adding up fractions: 0.1 + 0.2 = 0.3.
	 *
	 * @param float $a
	 * @param float $b
	 * @return bool
	 */
	protected function same($a, $b)
	{
		return abs($a - $b) <= 1e-12 * max(1.0, abs($a), abs($b));
	}

	/**
	 * "a round b" rounds a to b decimal places.
	 *
	 * @return float
	 */
	protected function read_round()
	{
		$value = $this->read_sum();
		while ($this->take_word('round'))
		{
			$value = round($value, (int) $this->read_sum());
		}

		return $value;
	}

	/**
	 * @return float
	 */
	protected function read_sum()
	{
		$value = $this->read_product();
		while (($op = $this->take_op(array('+', '-'))) !== null)
		{
			$right = $this->read_product();
			$value = ($op === '+') ? $value + $right : $value - $right;
		}

		return $value;
	}

	/**
	 * @return float
	 */
	protected function read_product()
	{
		$value = $this->read_power();
		while (true)
		{
			if (($op = $this->take_op(array('*', '/'))) !== null)
			{
				$word = $op;
			}
			else if ($this->take_word('div'))
			{
				$word = '/';
			}
			else if ($this->take_word('mod'))
			{
				$word = 'mod';
			}
			else
			{
				return $value;
			}

			$right = $this->read_power();
			if (($word === '/' || $word === 'mod') && $right == 0)
			{
				throw new \InvalidArgumentException('WIKI_EXPR_DIV_ZERO');
			}
			$value = ($word === '*') ? $value * $right : (($word === '/') ? $value / $right : fmod($value, $right));
		}
	}

	/**
	 * @return float
	 */
	protected function read_power()
	{
		$value = $this->read_unary();
		while ($this->take_op(array('^')) !== null)
		{
			$value = $this->check(pow($value, $this->read_unary()));
		}

		return $value;
	}

	/**
	 * @return float
	 */
	protected function read_unary()
	{
		if (++$this->depth > self::MAX_DEPTH)
		{
			throw new \InvalidArgumentException('WIKI_EXPR_TOO_LONG');
		}

		try
		{
			$token = $this->tokens[$this->position] ?? null;
			if ($token === null)
			{
				throw new \InvalidArgumentException('WIKI_EXPR_MISSING');
			}

			if ($token[0] === 'op' && ($token[1] === '-' || $token[1] === '+'))
			{
				$this->position++;
				$value = $this->read_unary();

				return ($token[1] === '-') ? -$value : $value;
			}

			if ($token[0] === 'word' && in_array($token[1], self::FUNCTIONS, true))
			{
				$this->position++;

				return $this->apply($token[1], $this->read_unary());
			}

			return $this->read_primary();
		}
		finally
		{
			$this->depth--;
		}
	}

	/**
	 * @return float
	 */
	protected function read_primary()
	{
		$token = $this->tokens[$this->position++];
		if ($token[0] === 'number')
		{
			return $token[1];
		}
		if ($token[0] === 'word' && $token[1] === 'e')
		{
			return M_E;
		}
		if ($token[0] === 'word' && $token[1] === 'pi')
		{
			return M_PI;
		}
		if ($token[0] === 'open')
		{
			$value = $this->read_or();
			if (!isset($this->tokens[$this->position]) || $this->tokens[$this->position][0] !== 'close')
			{
				throw new \InvalidArgumentException('WIKI_EXPR_MISSING');
			}
			$this->position++;

			return $value;
		}

		throw new \InvalidArgumentException('WIKI_EXPR_UNEXPECTED|' . $token[1]);
	}

	/**
	 * @param string $function One of FUNCTIONS
	 * @param float $value
	 * @return float
	 */
	protected function apply($function, $value)
	{
		switch ($function)
		{
			case 'abs':
				return abs($value);
			case 'ceil':
				return ceil($value);
			case 'floor':
				return floor($value);
			case 'trunc':
				return (float) (int) $value;
			case 'not':
				return (float) ($value == 0);
			case 'sqrt':
				if ($value < 0)
				{
					throw new \InvalidArgumentException('WIKI_EXPR_NAN');
				}

				return sqrt($value);
			case 'ln':
				if ($value <= 0)
				{
					throw new \InvalidArgumentException('WIKI_EXPR_NAN');
				}

				return log($value);
			case 'exp':
				return $this->check(exp($value));
			case 'sin':
				return sin($value);
			case 'cos':
				return cos($value);
			default:
				return $this->check(tan($value));
		}
	}

	/**
	 * @param float $value
	 * @return float
	 * @throws \InvalidArgumentException When the result is not a usable number
	 */
	protected function check($value)
	{
		if (is_nan($value) || is_infinite($value))
		{
			throw new \InvalidArgumentException('WIKI_EXPR_NAN');
		}

		return $value;
	}

	/**
	 * @param string $word
	 * @return bool Whether the next token was that word, and it has been read
	 */
	protected function take_word($word)
	{
		$token = $this->tokens[$this->position] ?? null;
		if ($token !== null && $token[0] === 'word' && $token[1] === $word)
		{
			$this->position++;

			return true;
		}

		return false;
	}

	/**
	 * @param array $ops
	 * @return string|null The operator if the next token is one of them, which has been read
	 */
	protected function take_op(array $ops)
	{
		$token = $this->tokens[$this->position] ?? null;
		if ($token !== null && $token[0] === 'op' && in_array($token[1], $ops, true))
		{
			$this->position++;

			return $token[1];
		}

		return null;
	}

	/**
	 * @param float $value
	 * @return string The number as a page shows it
	 */
	protected static function format($value)
	{
		if ($value == 0)
		{
			return '0';
		}
		if (abs($value) >= 1e15)
		{
			return (string) $value;
		}

		return rtrim(rtrim(sprintf('%.13F', $value), '0'), '.');
	}
}
