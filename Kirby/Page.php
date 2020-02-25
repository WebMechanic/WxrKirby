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
		$this->filename  = $blueprint . $this->ext;
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
	 * @return string  fully qualified content filepath for Kirby
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

	/**
	 * Takes a Wordpress <item> of type "post" and reads properties to create
	 * a Kirby Page file.
	 *
	 * @param Post $post
	 * @return Page
	 * @todo Convert inline LINK in Post::content, excerpt, description
	 * @todo Convert inline IMG in Post::content, excerpt, description
	 */
	public function assign($post): Page
	{
		$this->ext = Converter::getOption('extension', '.txt');

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
					$this->content[$key] = $value;
				}
			}
		}

		# hints
		$props = ['content', 'excerpt', 'description'];
		foreach ($props as $prop) {
			$this->content[$prop] = $post->{$prop};
		}

		/* @todo save as .html backup */
//		$props = ['content_html', 'excerpt_html'];

		return $this;
	}

	/**
	 * @todo use \Kirby\Cms\File::create() and \Kirby\Toolkit\F
	 */
	public function writeOutput()
	{
		/** @var Author */
		$creator = $this->content['creator'];
		$meta    = @implode(', ', $this->meta);
		$felder  = @implode(', ', array_keys($this->content));

		//	$subtitle = $post->getField('Subtitle');
		echo <<<LOG
Page: {$this->id} ({$this->parent}) {$this->link} {$this->date} 
    | {$this->getContentFile()}
    F {$felder}
    M {$meta}
    C {$creator->getFullName()} <{$creator->email}> ({$creator->username}) 

LOG;
	}

}
