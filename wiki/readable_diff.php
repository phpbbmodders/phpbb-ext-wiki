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
 * A readable compare of two rendered versions of an article.
 *
 * Both versions are split into blocks: each line of text, heading, quote, code box,
 * list item and table row. The blocks are compared with phpBB's diff engine, so a block
 * that did not change keeps its formatting, a block that was added or removed is marked
 * as a whole, and a block that changed shows which words changed. Lists and tables that
 * changed are compared item by item and row by row.
 *
 * phpBB's includes/diff/diff.php and engine.php must be loaded first.
 */
class readable_diff
{
	/** Elements whose children are compared one by one when the element changed */
	const CONTAINERS = array('ul', 'ol', 'table', 'thead', 'tbody', 'tfoot');

	/** Elements that start a new block in running text */
	const BLOCKS = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'blockquote', 'pre', 'ul', 'ol', 'dl', 'table', 'hr');

	/**
	 * Compare two rendered versions.
	 *
	 * @param string $from_html The older version as HTML
	 * @param string $to_html The newer version as HTML
	 * @return string HTML of the newer version with the changes marked
	 */
	public static function render($from_html, $to_html)
	{
		$diff = new self();

		return $diff->compare(self::blocks(self::load($from_html)), self::blocks(self::load($to_html)));
	}

	/**
	 * Parse an HTML fragment.
	 *
	 * @param string $html
	 * @return \DOMElement The element holding the fragment
	 */
	protected static function load($html)
	{
		$doc = new \DOMDocument();
		$errors = libxml_use_internal_errors(true);
		$doc->loadHTML('<?xml encoding="utf-8"?><div id="wiki-diff-root">' . $html . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		return $doc->getElementById('wiki-diff-root') ?: $doc->documentElement;
	}

	/**
	 * Split a fragment into blocks: block elements stay whole, and running text is cut
	 * at its line breaks, each line becoming a block of its own. Empty lines are dropped.
	 *
	 * @param \DOMNode $root
	 * @return array DOMNode blocks; a line of running text is an array of its nodes
	 */
	protected static function blocks(\DOMNode $root)
	{
		$blocks = array();
		$line = array();
		$flush = function () use (&$blocks, &$line) {
			if (trim(self::text_of($line)) !== '' || self::has_media($line))
			{
				$blocks[] = $line;
			}
			$line = array();
		};

		foreach (iterator_to_array($root->childNodes) as $node)
		{
			$name = strtolower($node->nodeName);
			if ($name === 'br')
			{
				$flush();
			}
			else if ($node instanceof \DOMElement && in_array($name, self::BLOCKS, true))
			{
				$flush();
				$blocks[] = $node;
			}
			else if (!($node instanceof \DOMComment))
			{
				$line[] = $node;
			}
		}
		$flush();

		return $blocks;
	}

	/**
	 * Compare two lists of blocks.
	 *
	 * @param array $from
	 * @param array $to
	 * @return string
	 */
	protected function compare(array $from, array $to)
	{
		$html = '';
		$i = $j = 0;
		foreach ($this->edits($from, $to) as $edit)
		{
			$orig = array_slice($from, $i, $edit->norig());
			$final = array_slice($to, $j, $edit->nfinal());
			$i += $edit->norig();
			$j += $edit->nfinal();

			if ($edit instanceof \diff_op_copy)
			{
				foreach ($final as $block)
				{
					$html .= $this->block_html($block, '');
				}
			}
			else
			{
				$html .= $this->changed($orig, $final);
			}
		}

		return $html;
	}

	/**
	 * The edits that turn one list of blocks into the other, worked out on a key per block.
	 *
	 * @param array $from
	 * @param array $to
	 * @return array diff_op objects
	 */
	protected function edits(array $from, array $to)
	{
		$from_keys = array_map(array(__CLASS__, 'key'), $from);
		$to_keys = array_map(array(__CLASS__, 'key'), $to);
		if (!$from_keys && !$to_keys)
		{
			return array();
		}
		if (!$from_keys)
		{
			return array(new \diff_op_add($to_keys));
		}
		if (!$to_keys)
		{
			return array(new \diff_op_delete($from_keys));
		}

		$engine = new \diff_engine();

		return $engine->diff($from_keys, $to_keys);
	}

	/**
	 * Blocks that were replaced: pairs of the same kind are compared inside, anything
	 * left over is shown as removed or added.
	 *
	 * @param array $orig
	 * @param array $final
	 * @return string
	 */
	protected function changed(array $orig, array $final)
	{
		$html = '';
		$pairs = min(count($orig), count($final));
		for ($k = 0; $k < $pairs; $k++)
		{
			$html .= $this->changed_pair($orig[$k], $final[$k]);
		}
		for ($k = $pairs; $k < count($orig); $k++)
		{
			$html .= $this->block_html($orig[$k], 'del');
		}
		for ($k = $pairs; $k < count($final); $k++)
		{
			$html .= $this->block_html($final[$k], 'ins');
		}

		return $html;
	}

	/**
	 * One block that became another.
	 *
	 * @param \DOMNode|array $old
	 * @param \DOMNode|array $new
	 * @return string
	 */
	protected function changed_pair($old, $new)
	{
		$old_name = self::name($old);
		$new_name = self::name($new);

		// A list or table that changed: compare its items or rows
		if ($old_name === $new_name && in_array($new_name, self::CONTAINERS, true))
		{
			return $this->open_tag($new) . $this->compare(self::children($old), self::children($new)) . '</' . $new_name . '>';
		}

		// Only the formatting changed: show the new block, marked as changed
		if ($old_name === $new_name && self::text_of($old) === self::text_of($new))
		{
			return $this->block_html($new, 'changed');
		}

		// A line, heading, list item or table row whose words changed. A word diff shows
		// text only, so a part with an image in it is shown whole instead.
		$has_media = self::has_media(is_array($old) ? $old : array($old)) || self::has_media(is_array($new) ? $new : array($new));
		if (!$has_media && $old_name === $new_name)
		{
			$words = self::word_diff(self::text_of($old), self::text_of($new));
			if (is_array($new))
			{
				return '<div class="wiki-diff-line">' . $words . '</div>';
			}
			if ($new_name === 'tr')
			{
				return $this->changed_row($old, $new, $words);
			}

			return $this->open_tag($new) . $words . '</' . $new_name . '>';
		}

		return $this->block_html($old, 'del') . $this->block_html($new, 'ins');
	}

	/**
	 * A table row whose text changed: compared cell by cell when it kept its cells,
	 * otherwise shown as one cell across the row.
	 *
	 * @param \DOMElement $old
	 * @param \DOMElement $new
	 * @param string $words The word diff of the whole row
	 * @return string
	 */
	protected function changed_row(\DOMElement $old, \DOMElement $new, $words)
	{
		$old_cells = self::children($old);
		$new_cells = self::children($new);
		if (count($old_cells) !== count($new_cells))
		{
			return $this->open_tag($new) . '<td colspan="' . max(1, self::cell_count($new)) . '">' . $words . '</td></tr>';
		}

		$html = $this->open_tag($new);
		foreach ($new_cells as $k => $cell)
		{
			$html .= (self::key($old_cells[$k]) === self::key($cell))
				? $this->block_html($cell, '')
				: $this->open_tag($cell) . self::word_diff(self::text_of($old_cells[$k]), self::text_of($cell)) . '</' . strtolower($cell->nodeName) . '>';
		}

		return $html . '</tr>';
	}

	/**
	 * The words of two texts with removed ones in <del> and added ones in <ins>.
	 *
	 * @param string $old
	 * @param string $new
	 * @return string
	 */
	protected static function word_diff($old, $new)
	{
		$old_words = preg_split('/\s+/u', trim($old), -1, PREG_SPLIT_NO_EMPTY);
		$new_words = preg_split('/\s+/u', trim($new), -1, PREG_SPLIT_NO_EMPTY);
		if (!$old_words || !$new_words)
		{
			$edits = $old_words ? array(new \diff_op_delete($old_words)) : array(new \diff_op_add($new_words));
		}
		else
		{
			$engine = new \diff_engine();
			$edits = $engine->diff($old_words, $new_words);
		}

		$parts = array();
		$escape = function ($words) {
			return utf8_htmlspecialchars(implode(' ', $words));
		};
		foreach ($edits as $edit)
		{
			if ($edit instanceof \diff_op_copy)
			{
				$parts[] = $escape($edit->final);
				continue;
			}
			if ($edit->orig)
			{
				$parts[] = '<del class="wiki-diff-del">' . $escape($edit->orig) . '</del>';
			}
			if ($edit->final)
			{
				$parts[] = '<ins class="wiki-diff-ins">' . $escape($edit->final) . '</ins>';
			}
		}

		return implode(' ', $parts);
	}

	/**
	 * HTML of a block, marked as removed ("del"), added ("ins"), changed, or not at all ("").
	 *
	 * @param \DOMNode|array $block
	 * @param string $mark
	 * @return string
	 */
	protected function block_html($block, $mark)
	{
		$class = ($mark === '') ? '' : 'wiki-diff-' . $mark;

		if (is_array($block))
		{
			$inner = '';
			foreach ($block as $node)
			{
				$inner .= $node->ownerDocument->saveHTML($node);
			}

			return '<div class="wiki-diff-line' . ($class ? ' ' . $class : '') . '">' . $inner . '</div>';
		}

		if ($class && $block instanceof \DOMElement)
		{
			$block = $block->cloneNode(true);
			$block->setAttribute('class', trim($block->getAttribute('class') . ' ' . $class));
		}

		return $block->ownerDocument->saveHTML($block);
	}

	/**
	 * The opening tag of an element, with its attributes.
	 *
	 * @param \DOMElement $element
	 * @return string
	 */
	protected function open_tag(\DOMElement $element)
	{
		$tag = '<' . strtolower($element->nodeName);
		foreach ($element->attributes as $attribute)
		{
			$tag .= ' ' . $attribute->nodeName . '="' . utf8_htmlspecialchars($attribute->nodeValue) . '"';
		}

		return $tag . '>';
	}

	/**
	 * What two blocks are compared by: their kind and their text.
	 *
	 * @param \DOMNode|array $block
	 * @return string
	 */
	protected static function key($block)
	{
		$html = '';
		foreach (is_array($block) ? $block : array($block) as $node)
		{
			$html .= $node->ownerDocument->saveHTML($node);
		}

		// The markup counts too, so a change of formatting alone is not taken for no change
		return self::name($block) . ':' . self::text_of($block) . ':' . md5($html);
	}

	/**
	 * @param \DOMNode|array $block
	 * @return string The element name, "line" for a line of running text
	 */
	protected static function name($block)
	{
		return is_array($block) ? 'line' : strtolower($block->nodeName);
	}

	/**
	 * The text of a block on one line, with runs of white space made one space.
	 *
	 * @param \DOMNode|array $block
	 * @return string
	 */
	protected static function text_of($block)
	{
		$text = '';
		foreach (is_array($block) ? $block : array($block) as $node)
		{
			$text .= $node->textContent;
		}

		return trim(preg_replace('/\s+/u', ' ', $text));
	}

	/**
	 * @param array $nodes
	 * @return bool Whether the nodes hold an image or another embedded item
	 */
	protected static function has_media(array $nodes)
	{
		foreach ($nodes as $node)
		{
			if ($node instanceof \DOMElement && (in_array(strtolower($node->nodeName), array('img', 'iframe', 'video', 'audio'), true) || $node->getElementsByTagName('img')->length))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * The items of a list or the rows of a table.
	 *
	 * @param \DOMElement $element
	 * @return array
	 */
	protected static function children(\DOMElement $element)
	{
		$children = array();
		foreach ($element->childNodes as $child)
		{
			if ($child instanceof \DOMElement)
			{
				$children[] = $child;
			}
		}

		return $children;
	}

	/**
	 * @param \DOMElement $row
	 * @return int The number of columns a table row spans
	 */
	protected static function cell_count(\DOMElement $row)
	{
		$count = 0;
		foreach (self::children($row) as $cell)
		{
			$count += max(1, (int) $cell->getAttribute('colspan'));
		}

		return $count;
	}
}
