<?php
/**
 * Transforms item elements of type `<wp:post_type>page`
 * Creates `default.txt` or as given by $blueprint property.
 *
 * item > title           : title
 * item > link            : url           - [M] used to build redirect rules
 * item > post_id         : id            - not used
 * item > post_parent     : parent        - used for folder hierarchy
 * item > status          : status        - publish|draft|inherit
 * item > creator         : author        - Login Name [M] opt. combine fields from `author` file
 * item > description     : intro         - article introduction
 * item > content:encoded : text          - article full text
 * item > excerpt:encoded : abstract      - article abstract
 * item > post_date_gmt   : created       - GMT timestamp
 * item > post_password   : [ignored]
 * item > is_sticky       : [ignored]
 * item > category        : category
 * item > postmeta        : delegate to custom `Transform_Meta` instance
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Kirby;

use WebMechanic\Converter\Converter;
use WebMechanic\Converter\Wordpress\Post;

class Page extends Content
{
	protected $id = 0;

	/** Kirby system fields */
	protected $blueprint = 'default';
	protected $filename = 'default.txt';

	protected $slug = '';

	protected $created = '';

	/**
	 * @var array A collection of Wordpress_Meta to do smart things with.
	 */
	protected $meta = [];

	/** @var resource file handle */
	private $fh = null;
	/** @var boolean */
	private $debug = false;

	/**
	 * Sets the $blueprint to be used for this page.
	 * Checks against $options['blueprints'] for file mappings of WP templates.
	 *
	 * @param string $blueprint
	 *
	 * @return Page
	 * @uses Converter::$options
	 */
	public function setBlueprint(string $blueprint): Page
	{
		static $blueprints = null;
		if ($blueprints === null) {
			$blueprints = Converter::getOption('blueprints');
		}
		if (isset($blueprints[$blueprint])) {
			$blueprint = $blueprints[$blueprint];
		}

		$this->blueprint = $blueprint;
		$this->filename  = $blueprint . $this->ext;
		return $this;
	}

	/**
	 * Returns the fully qualified content path for Kirby. Does not check
	 * if the path actually exists.
	 * @param string $folder
	 * @return string
	 */
	public function getContentPath($folder = 'content'): string
	{
		return parent::getContentPath($folder);
	}

	/**
	 * @param $subfolders
	 * @return string
	 * @throws \RuntimeException "Error creating content path"
	 */
	public function createContentPath($subfolders): string
	{
		$contentPath = $this->getContentPath() . $subfolders;
		@mkdir($contentPath, 0750, true);
		$contentPath = realpath($contentPath);
		if (!is_dir($contentPath)) {
			throw new \RuntimeException('Error creating content path ['. $this->getContentPath() . $subfolders .']');
		}

		return $contentPath;
	}

	/**
	 * Returns the fully qualified content filepath for Kirby. Does not check
	 * if the file actually exists.
	 * @return string
	 */
	public function getContentFile(): string
	{
		return $this->getContentPath() . $this->filepath . $this->filename;
	}

	/**
	 * Uses Transform\Meta to convert WP meta information into something
	 * useful for a Kirby page.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return Page
	 */
	public function setMeta(string $key, $value): Page
	{
		$this->meta[$key] = $value;
		return $this;
	}

	public function setName(string $name): Page
	{
		$this->slug = $name;
		return $this;
	}

	/**
	 * Takes a Wordpress <item> of type "post" and reads properties to create
	 * a Kirby Page file.
	 *
	 * @param Post $post
	 * @return Page
	 * @uses Converter::$options
	 * @todo Convert inline LINK in Post::content, excerpt, description
	 * @todo Convert inline IMG in Post::content, excerpt, description
	 */
	public function assign($post): Page
	{
		$this->set('ext', Converter::getOption('extension', '.txt'));

		$props = [
			'id', 'title', 'link', 'parent',
			'name', 'filepath',
			'creator', /* Author */
			'blueprint' => 'template',
		];
		foreach ($props as $method => $prop) {
			if (is_string($method)) {
				$method = 'set' . ucfirst("{$method}");
			}
			if (method_exists($this, $method)) {
				$this->$method($post->{$prop});
			} else {
				$this->set($prop, $post->{$prop});
			}
		}

		$props = ['tags', 'categories'];
		foreach ($props as $prop) {
			$value = $post->{$prop};
			if (is_array($value)) {
				$this->set($prop, implode(',', $value));
			} else {
				$this->set($prop, $value);
			}
		}

		$props = [
			'fields', 'data',
			'date', 'status'
		];
		$this->meta = $post->meta;
		foreach ($props as $prop) {
			$method = 'set' . ucfirst("{$prop}");
			foreach ((array) $post->{$prop} as $key => $value) {
				if (method_exists($this, $method)) {
					$this->$method($key, $value);
				} else {
					$this->setContent($key, $value);
				}
			}
		}

		# hints
		$props = ['content', 'excerpt', 'description'];
		foreach ($props as $prop) {
			$this->setContent($prop, $post->{$prop});
		}

		/* @todo save as .html backup */
//		$props = ['content_html', 'excerpt_html'];

		return $this;
	}

	/**
	 * @uses Converter::$options
	 * @todo use \Kirby\Cms\File::create() and \Kirby\Toolkit\F
	 */
	public function writeOutput()
	{
		$this->debug = Converter::getOption('debug', false);

		try {
			$contentPath = $this->createContentPath($this->filepath);
		} catch (\RuntimeException $e) {
			return;
		}

		echo "Write Page: ", $this->getContentFile(), PHP_EOL;
		if ($this->debug) {
			echo 'P ', $contentPath, PHP_EOL,
				'F ', $this->filename, PHP_EOL,
			PHP_EOL;
			return;
		} else {
			$this->fh = fopen($this->getContentFile(), "w+b");
		}

		/** @var Author $creator */
		$creator = $this->getContent('creator');

		$this->setContent('creator', null);
		$this->setContent('guid', null);

		foreach ($this->getContent() as $fieldname => $value) {
			$this->write($fieldname, $value);
		}

		$this->write('creator', $creator->getFullName());

		$props = ['creator', 'tags', 'categories', 'link', 'id'];
		foreach ($props as $prop) {
			$this->write($prop, $this->$prop);
		}

		if (!$this->debug) fclose($this->fh);
	}

	private function write($fieldname, $value)
	{
		if (empty($value)) {
			return;
		}
		if (is_array($value)) {
			$value = @implode(', ', $value);
		}

		$nl   = strlen($value) > 64 ? "\n" : ' ';
		$line = sprintf("%s :{$nl}%s\n----\n", ucfirst($fieldname), $value);

		if ($this->debug)
			echo $line;
		else
			fwrite($this->fh, $line);
	}

	public function dump()
	{
		/** @var Author $creator */
		$creator = $this->getContent('creator');
		$meta    = @implode(', ', $this->meta);
		$fields  = @implode(', ', array_keys($this->getContent()));

		//	$subtitle = $post->getField('Subtitle');
		echo <<<LOG
Page: {$this->id} ({$this->parent}) {$this->link} {$this->created} 
    | {$this->getContentFile()}
    F {$fields}
    M {$meta}
    C {$creator->getFullName()} <{$creator->email}> ({$creator->username}) 

LOG;
	}

}
