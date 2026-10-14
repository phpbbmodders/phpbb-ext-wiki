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
 * The text and date work behind {{#len:}}, {{#sub:}}, {{#time:}}, {{padleft:}} and the
 * other functions that only need their arguments. All of it is character based (UTF-8).
 */
class text_tools
{
	/** Longest text these functions hand back, in characters. */
	const MAX_LENGTH = 10000;

	/** Format letters {{#time:}} passes on to PHP's date(). */
	const TIME_LETTERS = 'dDjlNwzWFmMntLoYyaAgGhHisTPOcrUeI';

	/**
	 * The text {{#pos:}}, {{#rpos:}}, {{#replace:}} and {{#explode:}} look for. Left
	 * empty, it is a single space, as in MediaWiki: arguments are trimmed, so a space
	 * cannot be written there.
	 *
	 * @param string $search The search text as given
	 * @return string
	 */
	protected static function search_text($search)
	{
		return ($search === '') ? ' ' : $search;
	}

	/**
	 * {{#pos:}}: where a text first occurs in another.
	 *
	 * @param string $haystack
	 * @param string $needle Empty for a space
	 * @param int $offset Characters to skip
	 * @return string The position counted from 0, or "" when it is not there
	 */
	public static function pos($haystack, $needle, $offset = 0)
	{
		$needle = self::search_text($needle);
		$position = mb_strpos($haystack, $needle, max(0, min($offset, mb_strlen($haystack))));

		return ($position === false) ? '' : (string) $position;
	}

	/**
	 * {{#rpos:}}: where a text last occurs in another.
	 *
	 * @param string $haystack
	 * @param string $needle Empty for a space
	 * @return string The position counted from 0, or "-1" when it is not there
	 */
	public static function rpos($haystack, $needle)
	{
		$position = mb_strrpos($haystack, self::search_text($needle));

		return ($position === false) ? '-1' : (string) $position;
	}

	/**
	 * {{#sub:}}: part of a text.
	 *
	 * @param string $text
	 * @param int $start From this character, counted from 0; negative counts from the end
	 * @param int|null $length How many characters; negative leaves that many off the end
	 * @return string
	 */
	public static function sub($text, $start, $length = null)
	{
		return mb_substr($text, $start, $length);
	}

	/**
	 * {{#replace:}}: every occurrence of a text swapped for another.
	 *
	 * @param string $text
	 * @param string $search Empty for a space
	 * @param string $replacement
	 * @return string
	 */
	public static function replace($text, $search, $replacement)
	{
		$result = str_replace(self::search_text($search), $replacement, $text);

		return (mb_strlen($result) > self::MAX_LENGTH) ? mb_substr($result, 0, self::MAX_LENGTH) : $result;
	}

	/**
	 * {{#explode:}}: one piece of a text cut at a delimiter.
	 *
	 * @param string $text
	 * @param string $delimiter Empty for a space
	 * @param int $position The piece, counted from 0; negative counts from the end
	 * @param int|null $limit Cut into at most this many pieces first
	 * @return string "" when there is no such piece
	 */
	public static function explode($text, $delimiter, $position = 0, $limit = null)
	{
		$delimiter = self::search_text($delimiter);
		$pieces = ($limit !== null && $limit > 0) ? explode($delimiter, $text, $limit) : explode($delimiter, $text);
		if ($position < 0)
		{
			$position += count($pieces);
		}

		return isset($pieces[$position]) ? $pieces[$position] : '';
	}

	/**
	 * {{padleft:}} and {{padright:}}: fill a text up to a length.
	 *
	 * @param string $text
	 * @param int $length The length to reach
	 * @param string $pad What to fill with, repeated as needed
	 * @param bool $left Fill on the left (otherwise on the right)
	 * @return string
	 */
	public static function pad($text, $length, $pad, $left)
	{
		$length = max(0, min($length, 500));
		$missing = $length - mb_strlen($text);
		if ($missing <= 0 || $pad === '')
		{
			return $text;
		}

		$fill = mb_substr(str_repeat($pad, (int) ceil($missing / mb_strlen($pad))), 0, $missing);

		return $left ? $fill . $text : $text . $fill;
	}

	/**
	 * {{#time:}}: turn a format written for the wiki into one for PHP's date().
	 * Letters work as in PHP (Y year, n month, j day, H hour, ...), "quoted text" and
	 * \c (a backslash and a character) come out as they are, and any other letter does too.
	 *
	 * @param string $format For example 'j F Y, H:i "UTC"'
	 * @return string
	 */
	public static function time_format($format)
	{
		$out = '';
		$quoted = false;
		$length = strlen($format);
		for ($i = 0; $i < $length; $i++)
		{
			$char = $format[$i];
			if ($char === '"')
			{
				$quoted = !$quoted;
			}
			else if ($quoted)
			{
				$out .= '\\' . $char;
			}
			else if ($char === '\\' && $i + 1 < $length)
			{
				$out .= '\\' . $format[++$i];
			}
			else if (strpos(self::TIME_LETTERS, $char) !== false)
			{
				$out .= $char;
			}
			else
			{
				$out .= '\\' . $char;
			}
		}

		return $out;
	}

	/**
	 * {{#time:}}: a date in a format.
	 *
	 * @param string $format As time_format() reads it
	 * @param string $date Anything PHP reads as a date and time ("2026-10-12", "@1791500000",
	 *                     "yesterday"), or "" for the time now
	 * @param int $now The time now as a Unix timestamp
	 * @return string|null Null when the date cannot be read
	 */
	public static function time($format, $date, $now)
	{
		if ($date !== '' && date_parse($date)['error_count'] > 0)
		{
			return null;
		}

		$utc = new \DateTimeZone('UTC');
		try
		{
			$base = (new \DateTimeImmutable('@' . $now))->setTimezone($utc);
			if ($date === '')
			{
				$moment = $base;
			}
			else if (preg_match('/\b(?:now|today|tomorrow|yesterday|midnight|noon|ago|next|last|this)\b|^\s*[+-]\s*\d/i', $date))
			{
				// Words like "yesterday" and "+1 day" count from the time now
				$moment = $base->modify($date);
			}
			else
			{
				$moment = new \DateTimeImmutable($date, $utc);
			}

			if ($moment === false)
			{
				return null;
			}
		}
		catch (\Exception $e)
		{
			return null;
		}

		return $moment->format(self::time_format($format));
	}

	/**
	 * {{CURRENTYEAR}}, {{CURRENTMONTHNAME}} and the rest of the date words, in UTC.
	 *
	 * @param string $word For example CURRENTDAY2
	 * @param int $now The time now as a Unix timestamp
	 * @return string|null Null when it is not a date word
	 */
	public static function date_word($word, $now)
	{
		static $formats = array(
			'CURRENTYEAR'		=> 'Y',
			'CURRENTMONTH'		=> 'm',
			'CURRENTMONTHNAME'	=> 'F',
			'CURRENTMONTHABBREV'	=> 'M',
			'CURRENTDAY'		=> 'j',
			'CURRENTDAY2'		=> 'd',
			'CURRENTDAYNAME'	=> 'l',
			'CURRENTDOW'		=> 'w',
			'CURRENTWEEK'		=> 'W',
			'CURRENTHOUR'		=> 'H',
			'CURRENTTIME'		=> 'H:i',
			'CURRENTTIMESTAMP'	=> 'YmdHis',
		);

		if (!isset($formats[$word]))
		{
			return null;
		}

		return (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone('UTC'))->format($formats[$word]);
	}

	/**
	 * Whether a name is one of the date words.
	 *
	 * @param string $word
	 * @return bool
	 */
	public static function is_date_word($word)
	{
		return self::date_word($word, 0) !== null;
	}
}
