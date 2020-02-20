<?php
/**
 * Transforms item elements of type `<wp:post_type>attachment`
 * like images or file links found in a Wordpress\Post content.
 *
 * @version 0.1.0 2020-01-21
 * @license WTFPL 2.0
 * @author  Rene Serradeil <serradeil@webmechanic.biz>
 */

namespace WebMechanic\Converter\Kirby;

use WebMechanic\Converter\Wordpress\Item;

class File extends Content
{
	/**
	 * Takes a Wordpress <item> of type "attachment" and reads properties
	 * to copy files into the Kirby site.
	 *
	 * @inheritDoc
	 */
	public function assign(Item $item)
	{
		// TODO: Implement assign() method.
	}
}

class Image extends File
{
}

