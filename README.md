**[WIP]** *Check the Tasks section about plans and progress of this beast.*
You need to write a couple of lines PHP to do a basic migration of page and blog contents.  
If you're a PHP geek and know your way around massivly polluted XML files you can enhance this package on a very granualar level to your liking.  
Enjoy and good luck!

# WxrKirby: WordPress eXtended RSS to Kirby
Convert and Transform WordPress WXR export files into a new Kirby 3 Site.

This package attempts to convert Pages and Blogposts contents, Attachment metadata and Author/Creator information into Kirby equivalents by creating various Markdown files that represent the former site structure incl. some global Site information with a great deal of customisation.

It allows to transform individual XML item metadata and element values using easy to extend custom rules (some defaults are provided).

Produces a textfile with a list of Apache `RewriteRules` for your page and image upload URLs to avoid annoying 404s, preserve SE ranking, and keep your visitors happy after migration.

## Stuff not covered
WxrKirby only uses the XML-based export files. Any supplemental information stored in the SQL database such as user account passwords and passwords for protected pages are ignored.

By default WxrKirby does not cover the conversion or transform of plugin data or the navigation menu. The extendable `Transform` and `Meta` classes allow to virtually implement all sorts of data manipulation of any XML element via simple custom functions (Closures).

## Requirements
 - PHP 7.3+
 - one or several WordPress WXR export files
 - [League\HTMLToMarkdown](https://github.com/thephpleague/html-to-markdown/) for HTML to Markdown conversion (this dependency is subject to removal).

To have the scripts also migrate your image and file uploads from WordPress to Kirby you should have the `/wp-content/uploads/` folder ready on the same machine you plan to perform their migration (still WIP).
WxrKirby will not download any files for you.

**Optional:**
A preinstalled, preconfigured working version of Kirby 3.3+ to fetch some configuration. Unless you specify a different output folder, this will then also become the target root directory i.e. for the `./content` and `./site` folders.

## Differences after migration
For the most part the existing content structure of your WordPress site will be preserved and you will end up with a bunch of Markdown files for your Blog Posts and Pages. There are of course differences in how Kirby treats user accounts, content _files_ and their associated image uploads.

Virtual pages like some created by gallery plugins or provided internally for the individual uploads will no longer exists but become part of the static `RewriteRule` output file for your review. A future version might create rule sets for the Kirby router (PRs welcome).

**WIP:** Images aka Attachments will no longer be organised in the common YEAR/MONTH folder structure of the `/wp-content/uploads/`. They'll either "move" to `/assets/images/` folder in your Kirby root or are stored side by side with the Post that contains the image. Meta data or other information available for the image will automatically become part of an image sidecar file (`theimage.jpg.txt`).
 > As of now the image and attachment data is retrieved and technically accessible inside the objects but data or files are currently created or written or moved. This would be subject to various `writeOutput()` methods. Check the "Tasks" section below.

# Composer?
_There won't be an installable Composer package in the foreseeable future_ and I will not accept PRs for this. However you can of course call an existing autoloader or bootstrapper to locate the optional Kirby CMS and `League\HTMLToMarkdown` packages, but you're on your own to eventually set up their class paths and vendor dirs.

# Setup!
For the time being just unzip (or git clone) the files of this repo into a convenient folder where the scripts have _read-write access_ to your XML export files, the target folder for all generated output files, and optional all your beautiful WordPress image uploads.

Here's a thought:
```
/htdocs/wordpress/wp-content/uploads/
/htdocs/wordpress/wp-content/gallery/  (or whatever)

/htdocs/kirby3/
/htdocs/kirby3/vendor/autoload.php  (hint!)

/htdocs/WebMechanic/Converter
/htdocs/WebMechanic/Converter/Kirby
/htdocs/WebMechanic/Converter/Wordpress

/htdocs/tests/Migrate.php
/htdocs/tests/worpress.pages.xml
```

## A Custom Converter
You should not need to make changes to the files provided &hellip; unless there are bugs :)

 - install `League\HTMLToMarkdown` package (for now this is a requirement)
 - make yourself familiar with the various `Constructor::$options` and their defaults!
 - create yourself a subclass of `\WebMechanic\Converter\Converter` (see below)
 - make sure your class can load/find any `Converter\Kirby\*`, `Converter\Wordpress\*` class and `HTMLToMarkdown`: **WxrKirby does not provide an autoloader for you.** Try the one that comes with Kirby (/kirbypath/vendor/autoload.php)
 - in your Class `__constructor`
   - change `static::$options['paths']['kirby'] = '/path/for/migration/'` to point to an existing folder. That's where all converted files will eventually be stored.
   - change `static::$options['paths']['create'] = true;` if you want the Converter to create the content folder structure for you
   - change any `$options` as you see fit and add your own for your Converter or Transform subclasses
 - have a WXR file ready
 - pass the XML filepath to the constructor and run `convert()`
 - if there are no errors loop thru the collections you care about and call their `writeOutput()` to create the files

```php
class Migrator extends Converter
{
  public function __construct($xmlfile)
  {
    static::$options['paths']['create'] = true;
    static::$options['paths']['kirby'] = '/path/for/migration/';

    // kick start XML processing
    parent::__construct($xmlfile);

    // optional: call a couple of custom Transforms for this site conversion
    $this->createTransforms();
  }
  private function createTransforms()
  {
    // change the textContent value of <language> element(s)
    $this->transforms['language'] = new Transform(
      function (DOMNode $node) {
        $node->textContent = str_replace('-', '_', $node->textContent);
        return $this;
      }
    );
    // mo stuff ...
  }
}

$M = new Migrator('export.wordpress.pages.2020-02-20.xml');
$M->convert();

// first transform <channel> and write "site.txt"
$M->getSite()->writeOutput();

// then transform all Pages/Posts <item> and write to /content/
foreach ($M->getPages() as $page) {
  $page->writeOutput();
}
```
Once you called `convert()` on your subclass you can use `getSite()`, and the collections returned from `getPages()`, `getAuthors()`, `getFiles()` (attachments) to migrate each of their contents or data.


# License
[WTFPL](http://www.wtfpl.net/)

# Tasks

- [x] Transform XML to create Kirby-ish objects representing
   - [x] Site
   - [x] Pages/Posts
   - [x] Attachments
   - [x] Authors/Creators
- [ ] Write Site markdown
   - [x] URL mapping and transform
- [x] Write Pages markdown
   - [x] Blueprint name mapping
   - [ ] Fields name mapping and removal
   - [ ] Remove [HTMLToMarkdown](https://github.com/thephpleague/html-to-markdown/) dependency
     - [ ] find use for [PHPHtmlParser](https://github.com/paquettg/php-html-parser) if installed (comes with [Kirby Editor](https://github.com/getkirby/editor))
- [ ] Write image Attachment sidecars with meta data
  - [ ] Add config options to call custom Image processors like [League\ColorExtractor](https://github.com/thephpleague/color-extractor)
- [ ] Write Account 'user.txt'
   - [ ] Remap creator to Accounts
   - [ ] Create JSON files to bulk create new Kirby Accounts
- [ ] Write (simple) Blueprints for encountered WordPress Templates other than `default`
- [ ] Create Apache `RewriteRules`
- [ ] Move/Link/Copy Attachments
- [ ] Use Kirby Toolkit to do stuff

Contributions are welcome!
