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
	protected $id = null;

	/** Kirby system fields */
	private $blueprint = 'default';
	protected $filename = 'default.txt';

	/**
	 * @var array A collection of Kirby_File based on post_type:attachment
	 */
	protected $files = [];

	/**
	 * @var array A collection of Wordpress_Meta to do smart things with.
	 */
	protected $meta = [];

	/**
	 * Sets the $blueprint to be used for this page.
	 *
	 * @param string $blueprint
	 *
	 * @return Page
	 */
	public function setBlueprint(string $blueprint): Page
	{
		$this->blueprint = $blueprint;
		$this->filename = $blueprint . $this->site()->ext;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getFiles(): array
	{
		return $this->files;
	}

	/**
	 * @param File $file
	 *
	 * @return Page
	 */
	public function setFile(File $file): Page
	{
		$hash = crc32($file->filepath . $file->filename);
		$this->files[$hash] = $file;
		return $this;
	}

	/**
	 * @param string $folder
	 * @return string
	 */
	public function getContentPath($folder = 'content'): string
	{
		return parent::getContentPath($folder);
	}

	/**
	 * Transformiert die Meta-Informationen aus Wordpress is etwas das Kirby
	 * versteht und gebrauchen kann.
	 *
	 * @return array
	 */
	public function getMeta(): array
	{
		return $this->meta;
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

	/**
	 * Takes a Wordpress <item> of type "post" and reads properties to create
	 * a Kirby Page file.
	 *
	 * @param Post $post
	 */
	public function assign($post)
	{
		// TODO: Implement assign() method.
	}

	/**
	 * @todo use Kirby\Cms\File::create() and Kirby\Toolkit\F
	 */
	public function writeOutput()
	{
		// TODO: Implement writeOutput() method.
	}

}
