# wp-to-composer
Script for helping you to convert a normal WordPress site to use composer for plugins.

![Demo](https://raw.githubusercontent.com/pelmered/wp-to-composer/master/demo.gif)

## USAGE:

### Download script:
`wget https://raw.githubusercontent.com/pelmered/wp-to-composer/master/wp-to-composer.php`

### Basic usage:
`php convert-to-composer.php`
Wait until the script finishes, and then follow the instructions.

### Options
Example: `php convert-to-composer.php --path=public/wp --url=https://www.example.com --version`

* **path** - specify path to WordPress core files. Same as WP CLI path
* **url** - specify url to site(needed for multisite)
* **version** - Use latest version in generated composer.json(for example "^2.3.1"). Omitting this sets "*" as version.
* 

## TODO / Roadmap

* Global config file
* Support for reading custom composer repositories 