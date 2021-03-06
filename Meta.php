<?php
/**
 * Transform rules for Wordpress Meta Elements into Kirby content file fields.
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter;

use DOMNode;
use WebMechanic\Converter\Wordpress\Item;

/**
 * Transform mappings of wp:postmeta fields into specific content fields
 * of various output files. Defaults to writing to the generated page.
 *
 * Allow for customisation of WP plugin meta fields (ie galleries) into
 * something a Kirby instance might make use of.
 *
 * @see Transform
 */
class Meta extends Transform
{
	/**
	 * @see apply()
	 * @var null a list of Closures to call during apply()
	 */
	private $handler = [];

	/**
	 * Called on `<wp:postmeta>` and it's childNodes <wp:meta_key>, <wp:meta_value>
	 * Override because Meta does not use a Closure for the transform
	 * but some more complex rules.
	 *
	 * Known meta_key:
	 * - _wp_page_template          may become a blueprint filename in Kirby
	 * - _wp_attached_file          relative  file path
	 * - _wp_attachment_metadata    serialised array with image meta data
	 * - _edit_last                 edit history count?
	 * - seo_follow                 false|true ~ rel follow|nofollow
	 * - seo_noindex                false|true ~ rel index|noindex
	 * - "Custom Fieldname"
	 *
	 * @param DOMNode   $node
	 * @param Item|null $post
	 * @see addHandler()
	 * @see pageTemplate(), seoFollow(), seoNoindex()
	 */
	function apply(DOMNode $node, Item $post = null): void
	{
		if ($node->nodeName !== 'wp:postmeta') {
			return;
		}

		$key = $node->firstChild->textContent;
		if (isset($this->handler[$key])) {
			if (is_callable($this->handler[$key])) {
				$callback = $this->handler[$key];
				$callback($node, $post);
				return;
			}
		}

		/* is there a public setter? */
		$method = 'set' . ucwords($key, '_');
		$method = str_replace(array('_wp_', '_'), '', $method);
		if (method_exists($post, $method)) {
			$post->$method($node);
		}
	}

	public function addHandler($key, \Closure $handler)
	{
		$this->handler[$key] = $handler;
	}
}

