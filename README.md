**[WIP]** *A fully working `master` branch expected to be available by the end of March 2020.* 

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

To have the scripts also migrate your image and file uploads from WordPress to Kirby you should have the `/wp-content/uploads/` folder ready on the same machine you plan to perform the migration.  
WxrKirby will not download any files for you.

**Optional:**  
A preinstalled, preconfigured working version of Kirby 3.3+ to fetch some configuration. Unless you specify a different output folder, this will also become the target root directory i.e. for the `./content` and `./site` folders.

## Differences after migration
For the most part the existing content structure of your WordPress site will be preserved and you will end up with a bunch of Markdown files for your Blog Posts and Pages. There are of course differences in how Kirby treats user accounts, content _files_ and their associated image uploads. 

Virtual pages like some created by gallery plugins or provided internally for the individual uploads will no longer exists but become part of the static `RewriteRule` output file for your review. A future version might create rule sets for the Kirby router (PRs welcome).

Images aka Attachments will no longer be organised in the common YEAR/MONTH folder structure of the `/wp-content/uploads/`. They'll either "move" to `/assets/images/` folder in your Kirby root or are stored side by side with the Post that contains the image. Meta data or other information available for the image will automatically become part of an image sidecar file (`theimage.jpg.txt`).

# Composer?
_There won't be a Composer package in the foreseeable future_ and I will not accept PRs for this. However you can of course call an existing autoloader or bootstrapper to locate the optional Kirby CMS and `League\HTMLToMarkdown` packages, but you're on your own to eventually set up their class paths and vendor dirs.

# Setup!
For the time being just unzip (or git clone) the files of this repo into a convenient folder where the scripts have _read-write access_ to your XML export files, the target folder for all generated Kirby files, and optional all your beautiful WordPress image uploads.

## A Custom Converter
You should not need to make changes to the files provided &hellip; unless there are bugs :)

 - install `League\HTMLToMarkdown` package (for now this is a requirement)
 - make yourself familiar with the various `Constructor::$options` and their defaults!
 - create yourself a subclass of `\WebMechanic\Converter\Converter`
 - make sure your class can load/find any `Converter\Kirby\*`, `Converter\Wordpress\*` class and `HTMLToMarkdown`: **WxrKirby does not provide an autoloader for you.**
 - in your Class `__constructor` 
 - change 
   - `static::$options['paths']['kirby'] = '/path/for/migration/'` to point to an existing folder. That's where all converted files will eventually be stored.
   - `static::$options['paths']['create'] = true;` if you want the Converter to create the content folder structure for you
   - other `$options` as you see fit and add your own for your Converter or Transforms
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

    // create a couple of custom Transforms for this site conversion
    $this->createTransforms();
  }
  private function createTransforms()
  {
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

// transform <channel> and write "site.txt"
$M->getSite()->writeOutput();

// transform all Pages/Posts <item> and write to /content/
foreach ($M->getPages() as $page) {
  $page->writeOutput();
}
```

# Tasks

- [x] Transform XML to create Kirby-ish
   - [x] Site
   - [x] Pages/Posts
   - [x] Attachments
   - [x] Authors/Creators
- [ ] Write Site markdown
   - [x] URL mapping and transform
- [x] Write Pages markdown
   - [x] Blueprint name mapping
   - [ ] Fields name mapping and omission
   - [ ] Remove HTMLToMarkdown dependency
- [ ] Write image Attachment sidecars with meta data
- [ ] Write Account 'user.txt'
   - [ ] Remap creator to Accounts
   - [ ] Create JSON files to bulk create new Kirby Accounts
- [ ] Write (simple) Blueprints for Pages
- [ ] Create Apache RewriteRules
- [ ] Move/Link/Copy Attachments
- [ ] Use Kirby Toolkit to do the file stuff

Contributions are welcome!
