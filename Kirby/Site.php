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
	protected $contentPath = '/site/';

	/**
	 * This is like Kirby's `site.txt` and will end up in the same folder.
	 * It's using a different name 'cos accidents happen...
	 *
	 * @see setFilename();
	 * @var string Wordpress Site metadata useful for Kirby
	 */
	protected $filename = 'wordpress';

	/** @var string Website Title */
	protected $title;

	/** @var string Website Hostname */
	protected $host = '';

	/** @var array  blueprints per path/URL */
	protected $blueprints = ['/' => 'default'];

	/**
	 * @see rewriteApache()
	 * @var array PCRE patterns to map WP with Kirby URLs
	 */
	protected $rewriteMap = ['\/slides\/.*' => '/gallery/{filepath}/{filename}'];

	/**
	 * @param Channel $channel
	 * @return mixed|void
	 */
	public function assign($channel): Site
	{
		$this->ext = Converter::getOption('extension', '.txt');

		$this->title  = $channel->title;
		$this->url    = $channel->link;
		$this->host   = $channel->host;

		foreach ((array) $channel->fields as $key => $value) {
			$method = 'set' . ucfirst("{$key}");
			if (method_exists($this, $method)) {
				$this->$method($key, $value);
			} else {
				$this->content[$key] = $value;
			}
		}

		return $this;
	}

	/**
	 * @todo use Kirby\Cms\File::create() and Kirby\Toolkit\F
	 */
	public function writeOutput()
	{
		$content = <<<OUT
Title: {$this->title}
---- 
URL: {$this->url}
---- 
Link: {$this->link}
----

OUT;

		foreach ($this->content as $field => $data) {
			if (is_array($data)) {
				$data = $data[0];
			}
			$field   = ucfirst($field);
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
	 * @param string $filepath
	 * @param string $blueprint
	 *
	 * @return Site
	 */
	public function setBlueprint(string $filepath, string $blueprint): Site
	{
		$this->blueprints[$filepath] = $blueprint;
		return $this;
	}

	/**
	 * @param string $filepath
	 *
	 * @return string
	 */
	public function getBlueprint(string $filepath): string
	{
		return $this->blueprints[$filepath];
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
