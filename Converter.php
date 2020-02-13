<?php
/**
 * Wordpress XML to Kirby Markdown Content Converter CLI application.
 *
 * Reads the eXtended RSS file generated by WordPress export and transforms
 * page, post and attachment items into separate Kirby Markdown files.
 * Kirby content folders are based on WP page and post URLs.
 *
 * Does not handle:
 * - menubar structure (uses pages and posts URLs only)
 * - comments (xmlns:wfw)
 * - custom fields
 * - plugin data (galleries, forms,...)
 *
 * Works with XML export wxr_version 1.2
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter;

use Kirby\Cms\App;

use League\HTMLToMarkdown\HtmlConverter as HtmlConverter;
use WebMechanic\Converter\Kirby\Author;
use WebMechanic\Converter\Wordpress\Attachment;
use WebMechanic\Converter\Wordpress\Channel;
use WebMechanic\Converter\Wordpress\Item;
use WebMechanic\Converter\Wordpress\Post;
use WebMechanic\Converter\Wordpress\WXR;

/**
 * Manages a Wordpress XML to Kirby (v3) content conversion.
 * Delegates tasks to other 'Wordpres' and 'Kirby' classes doing the actual work.
 */
class Converter
{
	/** @var WXR $WXR access to WXR date */
	protected $WXR;

	/** @var Channel $site possible Wordpress settings useful for Kirby's `site.md` */
	protected $site = null;

	/** @var array  list of WebMechanic\Kirby\Pages from Wordpress\Post's */
	protected $pages = [];

	/** @var array  list of WebMechanic\Kirby\Author / Wordpress usernames */
	protected $authors = [];

	/** @var array  list of WebMechanic\Kirby\Files (assets) / Wordpress\Attachment's */
	protected $files = [];

	/** @var array optional Transform objects */
	protected $transforms = null;

	/** @var App optional instance of a Kirby App from an active installation to gather some useful information */
	static $kirby = null;

	/** @var HtmlConverter */
	static $HTML;

	/**
	 * Arbitrary plugin stuff or WP internals we don't care about.
	 *
	 * TITLE: Kirby Field used to store the Post title
	 * TEXT: Kirby Field used to store the Post content
	 *
	 * DELEGATE:
	 * 'nav_menu_item': The WP Mainmenu.
	 * 		Only useful to recreate the same structure in Kirby.
	 * 		Due to its complexity, this should be handled in a separate class,
	 * 		Class Nav_Menu_Item if present, rebuilding the node tree based on
	 * 		the `wp:postmeta` information in all <item>s.
	 *
	 * DISCARD:
	 * 'display_type': unknown
	 * 'ngg_pictures', 'ngg_gallery', 'gal_display_source',
	 * 'slide', 'lightbox_library': gallery stuff
	 * 'wooframework': a fat staple in WP installations
	 * @see getOption()
	 */
	protected static $options = [
		'title' => 'Title',
		'text' => 'Text',
		'delegate' => ['nav_menu_item'],
		'discard' => ['wooframework', 'ngg_pictures', 'ngg_gallery', 'gal_display_source', 'slide', 'lightbox_library'],
	];

	protected $debug = false;

	/**
	 * Converter constructor.
	 * To get going call `$this->XMW->parse($this)` in your derived class.
	 *
	 * @param string $xml_path
	 */
	protected function __construct(string $xml_path)
	{
		$this->WXR = new WXR($xml_path);
	}

	/**
	 * @return array
	 */
	public function getOptions($group): array
	{
		return static::$options[$group] = static::$options[$group] ?? [];
	}

	public function __destruct()
	{
		// will close XMLReader
		unset($this->WXR);
	}

	/**
	 * Extend this method in your subclass to customize Kirby settings
	 * like target paths and Transform filters for some RSS values PRIOR
	 * calling this one with `parent::convert();`
	 *
	 * @return Converter
	 * @see Transform
	 */
	public function convert(): Converter
	{
		$this->WXR->parse($this);
		return $this;
	}

	public function getHtml(): HtmlConverter
	{
		return static::$HTML = static::$HTML ?? new HtmlConverter();
	}

	/**
	 * @param string $name an element name incl. its namespace / or a suitable key
	 *
	 * @return Transform
	 */
	public function getTransform(string $name): Transform
	{
		if (isset($this->transforms[$name])) {
			return $this->transforms[$name];
		}
		// empty to allow chaining w/o causing any harm.
		return new Transform();
	}

	/**
	 * @return App the Kirby App instance used for configuration
	 */
	public function getKirby(): App
	{
		return static::$kirby = static::$kirby ?? App::instance();
	}

	/**
	 * If you assign a valid Kirby App instance, settings will be pulled from
	 * your Kirby installation and output files will be written to its content
	 * and media folders (if possible).
	 *
	 * @param App $kirby a properly configured Kirby (CLI) instance
	 */
	public function setKirby(App $kirby): void
	{
		static::$kirby = $kirby;
	}

	/**
	 * @return Item
	 */
	public function getSite(): Item
	{
		return $this->site;
	}

	/**
	 * Assign a Site object to write information into `site.md`.
	 *
	 * @param Item $site
	 *
	 * @return Converter
	 */
	public function setSite(Channel $site): Converter
	{
		$this->site = $site;
		return $this;
	}

	/**
	 * An array of Wordpress\Post's to be transformed into Kirby\Page's.
	 *
	 * @return array
	 */
	public function getPages(): array
	{
		return $this->pages;
	}

	/**
	 * Store a Wordpress\Post by its ID to be transformed into a Kirby\Page.
	 *
	 * @param Post $post
	 *
	 * @return Converter
	 */
	public function setPage(Post $post): Converter
	{
		$this->pages[$post->getId()] = $post;
		return $this;
	}

	/**
	 * An array of Kirby\Author objects for all converted Wordpress users
	 * ready to be converted (excl. passwords).
	 *
	 * @return array of Kirby\Author
	 */
	public function getAuthors(): array
	{
		return $this->authors;
	}

	/**
	 * The Author object of the Wordpress $username.
	 *
	 * @param string $username
	 * @return Author
	 */
	public function getAuthor(string $username): Author
	{
		return isset($this->authors[$username]) ? $this->authors[$username] : null;
	}

	/**
	 * Store a new Kirby\Author derived from a Wordpress user.
	 * The <dc:creator> of an item refers to the username, so this is used as the key.
	 *
	 * @param Author $author
	 *
	 * @return Converter
	 */
	public function setAuthor(Author $author): Converter
	{
		$this->authors[$author->getUsername()] = $author;
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
	 * Store a Wordpress file Attachment/Upload to be transformed into
	 * a new Kirby\File or Kirby\Image.
	 *
	 * @param Attachment $file
	 *
	 * @return Converter
	 */
	public function setFile(Attachment $file): Converter
	{
		$this->files[$file->getId()] = $file;
		return $this;
	}

	/**
	 * Proxy to `setFile()`
	 *
	 * @param Attachment $image
	 * @return Converter
	 * @see setFile()
	 */
	public function setImage(Attachment $image): Converter
	{
		return $this->setFile($image);
	}

	public function __toString()
	{
		return print_r([
			'pages' => array_keys($this->pages),
			'files' => array_keys($this->files),
			'authors' => array_keys($this->authors)
		], true);
	}
}
