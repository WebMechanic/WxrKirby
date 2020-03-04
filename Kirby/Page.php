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

//use Kirby\Cms\File;
//use Kirby\Toolkit\F;

class Page extends Content
{
	protected $id = 0;

	/** Kirby system fields */
	protected $blueprint = '';
	protected $filename = '';

	protected $slug = '';

	protected $order = 0;

	protected $created = '';

	/**
	 * @var array A collection of Wordpress_Meta to do smart things with.
	 */
	protected $meta = [];

	/** @var array the original HTML markup */
	private $html = ['content' => null, 'excerpt' => null];

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
		$this->setFilename($blueprint);
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
	 * useful for a Kirby content file.
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

	/**
	 * Store WP name as Kirby slug.
	 *
	 * @param string $name
	 * @return Page
	 */
	public function setName(string $name): Page
	{
		$this->slug = $name;
		return $this;
	}

	/**
	 * Store WP 'menu_order' as Kirby 'order' content field.
	 *
	 * @param string $order
	 * @return Page
	 * @todo use number in output content path
	 */
	public function setMenuOrder(string $order): Page
	{
		$this->order = sprintf('%02d', (int) $order);
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
		$this->debug = Converter::getOption('debug', false);

		$titleField = Converter::getOption('title', 'title');
		$textField  = Converter::getOption('text', 'text');
		$ignored    = Converter::getOption('ignore_fields', []);

		$props = [
			'title', 'link',
			'creator', /* Author */
			'blueprint' => 'template',
			'filepath',
			'date', 'status',
			'id', 'parent', 'name'
		];
		foreach ($props as $method => $prop) {
			if ($titleField == $prop) {$prop = $titleField;}
			if (in_array($prop, $ignored)) continue;
			if (is_string($method)) {
				$method = 'set' . ucwords($method, '_');
			} else {
				$method = 'set' . ucwords($prop, '_');
				$method = str_replace('_', '', $method);
			}

			if (method_exists($this, $method)) {
				$this->$method($post->{$prop});
			} else {
				$this->set($prop, $post->{$prop});
			}
		}

		/* @todo use 'text' option for 'content' fieldname */
		$props = ['content', 'excerpt', 'description'];
		foreach ($props as $prop) {
			if (in_array($prop, $ignored)) continue;
			$this->setContent($prop, $post->{$prop});
		}

		/** deconstruct fields from arrays */
		$props = ['fields', 'data'];
		foreach ($props as $prop) {
			if (in_array($prop, $ignored)) continue;

			foreach ((array) $post->{$prop} as $key => $value) {
				if ($textField == $key) {$key = $textField;}
				if (in_array($key, $ignored)) continue;
				$method = 'set' . ucwords($key, '_');
				$method = str_replace('_', '', $method);

				if (method_exists($this, $method)) {
					$this->$method($key, $value);
				} else {
					$this->setContent($key, $value);
				}
			}
		}

		/** build value lists from arrays */
		$props = ['tags', 'categories'];
		foreach ($props as $prop) {
			$value = $post->{$prop};
			if (is_array($value)) {
				$this->set($prop, implode(', ', $value));
			} else {
				$this->set($prop, $value);
			}
		}

		$this->meta = $post->meta;

		/** optionally written in writeHtmlOutput() */
		if (Converter::getOption('write_html', false)) {
			$this->html = [
				'content' => $post->content_html,
				'excerpt' => $post->excerpt_html,
			];
		}

		return $this;
	}

	/**
	 * @uses Converter::$options
	 * @uses writeHtmlOutput()
	 * @todo use \Kirby\Cms\File::create() and \Kirby\Toolkit\F
	 */
	public function writeOutput(): Page
	{
		try {
			$contentPath = $this->createContentPath($this->filepath);
		} catch (\RuntimeException $e) {
			return $this;
		}

		echo "Write: ", $this->title, PHP_EOL,"       ", $this->getContentFile(), PHP_EOL;

		if ($this->debug) {
			echo 'P ', $contentPath, PHP_EOL,
				 'F ', $this->filename, PHP_EOL,
			PHP_EOL;
		} else {
			$contentFile = $this->getContentFile();
			$this->fh = @fopen($contentFile, "w+b");
		}
		if (!is_resource($this->fh)) {
			throw new \RuntimeException("Invalid filepath '$contentFile`.");
		}

		/** @var Author $creator */
		$creator = $this->getContent('creator');
		$this->setContent('creator', null);

		foreach ($this->getContent() as $fieldname => $value) {
			$this->write($fieldname, $value);
		}

		$this->write('creator', $creator->getFullName());

		$props = ['creator', 'tags', 'categories', 'link', 'id'];
		foreach ($props as $prop) {
			$this->write($prop, $this->$prop);
		}

		if (!$this->debug) fclose($this->fh);

		return $this;
	}

	/**
	 * @return Page
	 */
	public function writeHtmlOutput(): Page
	{
		$filePath = $this->getContentPath() . $this->filepath;

		$content = $this->html['content'];
		if (strlen($content)) {
			echo "  HTML {$filePath}content.html \n";

			file_put_contents("{$filePath}content.html", $content);
		}

		$excerpt = $this->html['excerpt'];
		if (strlen($excerpt)) {
			echo "  HTML {$filePath}excerpt.html \n";

			file_put_contents("{$filePath}excerpt.html", $excerpt);
		}

		return $this;
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
		$line = sprintf("%s :{$nl}%s\n\n----\n\n", ucfirst($fieldname), $value);

		if ($this->debug) {
			echo $line;
		} else {
			fwrite($this->fh, $line);
		}
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
