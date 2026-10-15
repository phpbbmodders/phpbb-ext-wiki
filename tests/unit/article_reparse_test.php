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

use phpbbmodders\wiki\wiki\article_reparse;

/**
 * The background reparse: queued from the newest row, worked through a batch at a time.
 */
class article_reparse_test extends \PHPUnit\Framework\TestCase
{
	public function test_batches_run_from_the_newest_row_down_and_then_stop()
	{
		$config = new \phpbb\config\config(array('wiki_reparse_next' => 0, 'wiki_category_members_built' => 1, 'wiki_category_members_cursor' => ''));
		$reparser = $this->createMock(\phpbbmodders\wiki\textreparser\plugins\article_text::class);
		$reparser->method('get_max_id')->willReturn(120);
		$ranges = array();
		$reparser->method('reparse_range')->willReturnCallback(function ($min, $max) use (&$ranges) {
			$ranges[] = array($min, $max);
		});
		$reparse = new article_reparse($config, $reparser);

		$this->assertFalse($reparse->pending());
		$reparse->queue();
		$this->assertTrue($reparse->pending());

		$reparse->run_batch();
		$this->assertSame(1, (int) $config['wiki_category_members_built'], 'categories are left alone until the end');
		while ($reparse->pending())
		{
			$reparse->run_batch();
		}

		$this->assertSame(array(array(71, 120), array(21, 70), array(1, 20)), $ranges);
		$this->assertSame(0, (int) $config['wiki_reparse_next']);
		$this->assertSame(0, (int) $config['wiki_category_members_built'], 'then worked out again');
	}

	public function test_an_empty_wiki_has_nothing_to_do()
	{
		$config = new \phpbb\config\config(array('wiki_reparse_next' => 0));
		$reparser = $this->createMock(\phpbbmodders\wiki\textreparser\plugins\article_text::class);
		$reparser->method('get_max_id')->willReturn(0);
		$reparser->expects($this->never())->method('reparse_range');
		$reparse = new article_reparse($config, $reparser);

		$reparse->queue();
		$this->assertFalse($reparse->pending());
		$reparse->run_batch();
	}
}
