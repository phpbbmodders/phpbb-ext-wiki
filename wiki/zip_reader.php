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
 * Reads the starter templates out of a zip of the wiki-templates repository, without
 * extracting anything: the zip's central directory is read, only "templates/<name>.wiki"
 * entries are taken (at the top or one folder down, as in GitHub's "Download ZIP"), and
 * they are decompressed in memory within fixed limits, so a crafted zip can neither write
 * outside a folder nor fill the disk or memory. Needs no PHP zip extension.
 */
class zip_reader
{
	/** Most entries a zip may list */
	const MAX_ENTRIES = 2000;

	/** Most bytes one template may have once decompressed */
	const MAX_TEMPLATE_BYTES = 262144;

	/** Most bytes all templates together may have once decompressed */
	const MAX_TOTAL_BYTES = 10485760;

	/** Where a template sits in the zip: templates/Name.wiki, or one folder down (not "." or "..") */
	const ENTRY_PATTERN = '#^(?:(?!\.\.?/)[^/]+/)?templates/([^/]+)\.wiki$#';

	/**
	 * The templates in a zip file.
	 *
	 * @param string $path The zip file
	 * @return array Template names (from the file names) with their texts
	 * @throws \RuntimeException When the file is not a zip this can read, or breaks a limit
	 */
	public static function templates($path)
	{
		$data = @file_get_contents($path);
		if ($data === false)
		{
			throw new \RuntimeException('WIKI_STARTER_ZIP_UNREADABLE');
		}

		$templates = array();
		$total = 0;
		foreach (self::entries($data) as $entry)
		{
			if (!preg_match(self::ENTRY_PATTERN, $entry['name'], $match))
			{
				continue;
			}
			if ($entry['size'] > self::MAX_TEMPLATE_BYTES || ($total += $entry['size']) > self::MAX_TOTAL_BYTES)
			{
				throw new \RuntimeException('WIKI_STARTER_ZIP_TOO_LARGE');
			}
			$templates[$match[1]] = self::content($data, $entry);
		}

		return $templates;
	}

	/**
	 * The entries the zip's central directory lists.
	 *
	 * @param string $data The whole zip
	 * @return array Each with name, method, compressed and size, and the offset of its local header
	 */
	protected static function entries($data)
	{
		// The end of central directory record is in the last 64 KiB plus its own 22 bytes
		$end = strrpos(substr($data, -65558), "PK\x05\x06");
		if ($end === false)
		{
			throw new \RuntimeException('WIKI_STARTER_ZIP_INVALID');
		}
		$end += max(0, strlen($data) - 65558);
		$record = unpack('vdisk/vcd_disk/vdisk_entries/ventries/Vcd_size/Vcd_offset', substr($data, $end + 4, 16));
		if ($record['entries'] > self::MAX_ENTRIES || $record['cd_offset'] + $record['cd_size'] > $end)
		{
			throw new \RuntimeException('WIKI_STARTER_ZIP_INVALID');
		}

		$entries = array();
		$offset = $record['cd_offset'];
		for ($i = 0; $i < $record['entries']; $i++)
		{
			if (substr($data, $offset, 4) !== "PK\x01\x02")
			{
				throw new \RuntimeException('WIKI_STARTER_ZIP_INVALID');
			}
			$header = unpack('vmethod/Vtime/Vcrc/Vcompressed/Vsize/vname_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Vlocal', substr($data, $offset + 10, 36));
			$entries[] = array(
				'name'			=> substr($data, $offset + 46, $header['name_len']),
				'method'		=> $header['method'],
				'crc'			=> $header['crc'],
				'compressed'	=> $header['compressed'],
				'size'			=> $header['size'],
				'local'			=> $header['local'],
			);
			$offset += 46 + $header['name_len'] + $header['extra_len'] + $header['comment_len'];
		}

		return $entries;
	}

	/**
	 * One entry's content, decompressed and checked against its size and checksum.
	 *
	 * @param string $data The whole zip
	 * @param array $entry From entries()
	 * @return string
	 */
	protected static function content($data, array $entry)
	{
		if (substr($data, $entry['local'], 4) !== "PK\x03\x04")
		{
			throw new \RuntimeException('WIKI_STARTER_ZIP_INVALID');
		}
		$local = unpack('vname_len/vextra_len', substr($data, $entry['local'] + 26, 4));
		$raw = substr($data, $entry['local'] + 30 + $local['name_len'] + $local['extra_len'], $entry['compressed']);

		if ($entry['method'] === 0)
		{
			$text = $raw;
		}
		else if ($entry['method'] === 8)
		{
			// The size from the directory caps the output, so a zip bomb stops here
			$text = @gzinflate($raw, $entry['size'] + 1);
		}
		else
		{
			throw new \RuntimeException('WIKI_STARTER_ZIP_INVALID');
		}

		if ($text === false || strlen($text) !== $entry['size'] || crc32($text) !== $entry['crc'])
		{
			throw new \RuntimeException('WIKI_STARTER_ZIP_INVALID');
		}

		return $text;
	}
}
