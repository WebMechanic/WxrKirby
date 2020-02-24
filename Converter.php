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

use DOMElement;
use Kirby\Cms\App;

use League\HTMLToMarkdown\HtmlConverter as HtmlConverter;
use WebMechanic\Converter\Kirby\Author;
use WebMechanic\Converter\Kirby\Page;
use WebMechanic\Converter\Kirby\Site;
use WebMechanic\Converter\Wordpress\Attachment;
use WebMechanic\Converter\Wordpress\Channel;
use WebMechanic\Converter\Wordpress\Post;
use WebMechanic\Converter\Wordpress\WXR;

/**
 * Manages a Wordpress XML to Kirby (v3) content conversion.
 * Delegates tasks to other 'Wordpres' and 'Kirby' classes doing the actual work.
 */
class Converter
{
	/** @var WXR $WXR access to WXR date */
	protected $WXR = null;

	/** @var Converter */
	public static $converter;

	/** @var Site $site the Kirby\Site `site.txt` with possible useful WordPress settings */
	protected $site = null;

	/** @var array  list of WebMechanic\Kirby\Pages from Wordpress\Post's */
	protected $pages = [];

	/** @var array  list of WebMechanic\Kirby\Author from Wordpress users/creators */
	protected $authors = [];

	/** @var array  list of WebMechanic\Kirby\Files (assets) from Wordpress\Attachment's */
	protected $files = [];

	/** @var array optional Transform objects */
	protected $transforms = null;

	/** @var App optional instance of a Kirby App from an active installation to gather some useful information */
	public static $kirby = null;

	/** @var HtmlConverter optional instance of `League\HTMLToMarkdown` form HTML to Markdown conversion */
	public static $HTML;

	/**
	 * Arbitrary plugin stuff or WP internals we don't care about.
	 *
	 * title: Kirby Field used to store the Post title
	 * text: Kirby Field used to store the Post content
	 * paths: an array with folders to use for output
	 *  - kirby: root folder (contains 'content' and 'site ' folders)
	 *  - content: alternative path for content output files
	 *  - assets: alternative path for images (WP uploads)
	 *  - site: alternative path for blueprint and account output files
	 *
	 * DELEGATE: XML Element names handled by specific classes.
	 * 'nav_menu_item': The WP Mainmenu.
	 *        Only useful to recreate the same structure in Kirby.
	 *        Due to its complexity, this should be handled in a separate class,
	 *        i.e. Wordpress\Menu (not provided), rebuilding the node tree based
	 *        on the `wp:postmeta` information in all <item>s.
	 *
	 * DISCARD: XML Element names ignored during conversion.
	 * 'display_type': unknown
	 * 'ngg_pictures', 'ngg_gallery', 'gal_display_source',
	 * 'slide', 'lightbox_library': gallery stuff
	 * 'wooframework': a fat staple in WP installations
	 *
	 * @see getOption()
	 */
	protected static $options = [
		'title' => 'Title',
		'text' => 'Text',

		// that's where the output goes. Kirby App config may override
		'paths' => [
			'create' => false,
			'kirby' => __DIR__ . '/migration/',
			'content' => null,
			'assets' => null,
			'site' => null,
		],

		// elements ignored but "sub-classable"
		'delegate' => ['nav_menu_item' => null],

		// discarded plugin data 'post_type'
		'discard' => ['display_type','wooframework','ngg_pictures','ngg_gallery','gal_display_source','slide','lightbox_library'],

		// League\HTMLToMarkdown config options
		'html2md' => [
			'header_style' => 'atx',
			'hard_break' => false,
			],
	];

	protected $debug = false;

	/**
	 * Converter constructor.
	 * To get going call `$this->convert()` in your derived class.
	 *
	 * @param string $xml_path
	 */
	protected function __construct(string $xml_path)
	{
		$this->WXR       = new WXR($xml_path);
		self::$converter = $this;

		foreach (array_keys(static::$options['resolveUrls']) as $key) {
			if (is_string(static::$options['resolveUrls'][$key])) {
				static::$options['resolveUrls'][$key] = json_decode(static::$options['resolveUrls'][$key]);
			}
		}

		$this->checkFolders();
	}

	/**
	 * Check and build output paths.
	 * Will use $options['paths']['kirby'] as the root for 'content', 'assets',
	 * and 'site'. Individual paths for each will be used if they exist.
	 */
	private function checkFolders()
	{
		settype(static::$options['paths'], 'array');
		if (is_dir(static::$options['paths']['kirby'])) {
			/* will also throw on Windows where "Kirby" and "kirby" are treated the same */
			if (is_file(static::$options['paths']['kirby'] .'/Content.php')) {
				throw new \InvalidArgumentException(
					'Cannot use program folder '. static::$options['paths']['kirby'] .' as output directory for Kirby files.');
			}
			if (!is_writable(static::$options['paths']['kirby'])) {
				throw new \RuntimeException(
					'Folder '. static::$options['paths']['kirby'] .' is not writeable.');
			}
		} else {
			if (true === static::$options['paths']['create']) {
				mkdir(static::$options['paths']['kirby'], 0750, true);
			} else {
				throw new \InvalidArgumentException(
					'Please provide a valid, existing and writeable Kirby output directory: ' .
					PHP_EOL . static::$options['paths']['kirby'] . ' is not.'
				);
			}
		}

		foreach (['content', 'assets', 'site', 'site/accounts', 'site/blueprints', 'site/templates'] as $folder) {
			if ( empty(static::$options['paths'][$folder]) || !is_dir(static::$options['paths'][$folder]) )
			{
				static::$options['paths'][$folder] = static::$options['paths']['kirby'] . '/' . $folder;
			}

			if ( true === static::$options['paths']['create'] && !is_dir(static::$options['paths'][$folder]) )
			{
				mkdir(static::$options['paths'][$folder], 0750, true);
			}

			static::$options['paths'][$folder] = realpath(static::$options['paths'][$folder]);
		}
	}

	/**
	 * @param string $key
	 * @param null   $default
	 * @return mixed|null
	 */
	public static function getOption(string $key, $default = null)
	{
		if ($key === null) {
			return static::$options;
		}
		if (!isset(static::$options[$key])) {
			static::$options[$key] = $default;
		}
		return static::$options[$key];
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
		// <br>, two spaces at the line end in output Markdown
		$options =  static::getOption('html2md', ['hard_break' => false]);

		return static::$HTML = static::$HTML ?? new HtmlConverter($options);
	}

	/**
	 * @param string $elementName an element name incl. its namespace / or a suitable key
	 *
	 * @return Transform
	 */
	public function getTransform(string $elementName): Transform
	{
		if (isset($this->transforms[$elementName])) {
			return $this->transforms[$elementName];
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
	 * @return Site
	 */
	public function getSite(): Site
	{
		return $this->site;
	}

	/**
	 * Assign a Site object to write information into `site.txt`.
	 *
	 * @param Channel $site
	 *
	 * @return Converter
	 */
	public function setSite(Channel $site): Converter
	{
		$this->site = new Site();
		$this->site->set('ext',  static::getOption('extension', 'txt'));
		$this->site->assign($site);

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
		$this->pages[$post->id] = (new Page())->assign($post);
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
	 * The <dc:creator> of an <item> refers to the username, so this is used as the key.
	 *
	 * @param DOMElement $wp_author
	 * @return Converter
	 */
	public function setAuthor(DOMElement $wp_author): Converter
	{
		$author = new Author();
		$author->parseNode($wp_author);
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
		$this->files[$file->id] = $file;
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
