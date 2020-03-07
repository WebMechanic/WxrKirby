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
	protected $filename = '';

	/** @var string Website Title */
	protected $title;

	/** @var string Website Hostname */
	protected $host = '';

	/** @var string previous WP Blog URL */
	protected $blog = '';

	/** @var string original WP URL of the item */
	protected $url = '';

	/** @var array  blueprints per path/URL */
	protected $blueprints = ['/' => 'default'];

	/**
	 * @see rewriteApache()
	 * @var array PCRE patterns to map WP with Kirby URLs
	 */
	protected $rewriteMap = ['\/slides\/.*' => '/gallery/{filepath}/{filename}'];

	/** @var boolean */
	protected $debug = false;

	/**
	 * @param Channel $channel
	 * @return mixed|void
	 * @uses Converter::$options
	 */
	public function assign($channel): Site
	{
		$this->debug = (bool) Converter::getOption('debug', false);

		$titleField = Converter::getOption('title', 'title');
		$ignored    = Converter::getOption('ignore_fields', []);

		$this->set('ext', Converter::getOption('extension', '.txt'));
		$this->set('url', $channel->link);
		$this->set('description', $channel->description);

		/** don't overwrite `site.txt` */
		$this->setFilename('wordpress');

		$this->{$titleField}  = $channel->title;
		$this->host   = $channel->host;
		$this->blog   = $channel->blogUrl;

		foreach ($channel->fields as $key => $value) {
			if (in_array($key, $ignored)) continue;
			$method = 'set' . ucwords($key, '_');
			$method = str_replace('_', '', $method);

			if (method_exists($this, $method)) {
				$this->$method($key, $value);
			} else {
				$this->setContent($key, $value);
			}
		}

		return $this;
	}

	/**
	 * @todo use Kirby\Cms\File::create() and Kirby\Toolkit\F
	 */
	public function writeOutput(): Site
	{
		try {
			$contentPath = $this->createContentPath($this->filepath);
		} catch (\RuntimeException $e) {
			return $this;
		}

		$contentFile = $this->getContentFile();
		echo "Site Info: ", $this->title, PHP_EOL,"       ", $contentFile, PHP_EOL;

		if ($this->debug) {
			echo 'P ', $contentPath, PHP_EOL,
				 'F ', $this->filename, PHP_EOL,
			PHP_EOL;
		}

		$props = ['title', 'description', 'url', 'blog'];
		foreach ($props as $prop) {
			$this->write($prop, $this->$prop);
		}

		return $this;
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
