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

use WebMechanic\Converter\Converter;

class File extends Content
{
	/**
	 * Takes a Wordpress <item> of type "attachment" and reads properties
	 * to copy files into the Kirby site.
	 *
	 * @param mixed $item some Item
	 * @return File
	 * @uses Converter::$options
	 */
	public function assign($item): File
	{
		$this->set('ext', Converter::getOption('extension', '.txt'));
		// TODO: Implement assign() method.
		return $this;
	}

	/**
	 * @todo use Kirby\Cms\File::create() and Kirby\Toolkit\F
	 */
	public function writeOutput()
	{
		echo 'File::writeOutput() ', $this->filepath, PHP_EOL;
	}
}
