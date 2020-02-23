<?php
/**
 * Collected data from the Wordpress XML export useful for `site.txt`.
 * channel > title            : title
 * channel > base_site_url    : url
 * channel > language        : locale  "xx-YY" (de-DE, en-GB, fr-CN)
 * channel > language        : language  "xx"  (de, en, fr)
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Kirby;

use WebMechanic\Converter\Converter;
use WebMechanic\Converter\Wordpress\Channel;

class Site extends Content
{
	/**
	 * This is like Kirby's `site.txt` and will end up in the same folder.
	 * It's using a different name 'cos accidents happen...
	 *
	 * @see setFilename();
	 * @var string Wordpress Site metadata useful for Kirby
	 */
	protected $filename = 'wordpress.txt';

	/** @var string Website Title */
	protected $title;

	/** @var array  blueprints per path/URL */
	protected $blueprints = ['/' => 'default'];

	/**
	 * @param Channel $channel
	 * @return mixed|void
	 */
	public function assign($channel)
	{
		$this->title  = $channel->title;
		$this->url    = $channel->link;
		$this->fields = $channel->fields;
	}

	public function writeOutput()
	{
		$content = <<<OUT
Title: {$this->title}
---- 
URL: {$this->url}
---- 
Link: {$this->sourceUrl}
----

OUT;

		foreach ($this->fields as $field => $data)
		{
			if (is_array($data)) {
				$data = $data[0];
			}
			$field = ucfirst($field);
			$content .= <<<OUT
{$field}: {$data}
----

OUT;

		}
		echo $this->filepath, PHP_EOL,
		$content, PHP_EOL
		, PHP_EOL;
	}

	/**
	 * Add the name of a $blueprint file to be used for content of the given $path
	 *
	 * @param string $path
	 * @param string $blueprint
	 *
	 * @return Site
	 */
	public function setBlueprint(string $path, string $blueprint): Site
	{
		$this->blueprints[$path] = $blueprint;
		return $this;
	}

	/**
	 * @param string $path
	 *
	 * @return string
	 */
	public function getBlueprint(string $path): string
	{
		return $this->blueprints[$path];
	}

	/**
	 * @param string $folder
	 * @return string
	 */
	public function getContentPath($folder = 'site'): string
	{
		return parent::getContentPath($folder);
	}

	/**
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}
}
