<?php
/**
 * Provides mappings of XML elements to Markdown fields.
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter;

use DOMNode;
use WebMechanic\Converter\Wordpress\Item;

class Transform
{
	/** @var null replacement or callable */
	private $handler;

	/**
	 * Transform constructor applies $handler
	 * The Closure must accept a `DOMNode` or `DOMElement` as its 1st argument
	 * and an optional `Item` (or derivative) to apply() the transformation.
	 *
	 * @param \Closure|null $handler
	 */
	function __construct(\Closure $handler = null)
	{
		$this->handler  = $handler;
	}

	/**
	 * Apply the transformation handler.
	 *
	 * @param DOMNode $node the XML node
	 * @param Item    $post usually a Wordpress\Page|Attachment object (so far)
	 */
	function apply(DOMNode $node, Item $post = null): void
	{
		if (is_callable($this->handler)) {
			call_user_func($this->handler, $node, $post);
		}
	}

}