# wp-to-composer
Script for helping you to convert a normal WordPress site to use composer for plugins.

![Demo](https://raw.githubusercontent.com/pelmered/wp-to-composer/master/demo.gif)

## Requirements:
* [WP-CLI](https://wp-cli.org/)
* PHP CLI
* Linux/Unix terminal

## USAGE:

### Download script:
`wget https://raw.githubusercontent.com/pelmered/wp-to-composer/master/wp-to-composer.php`

### Basic usage:
`php wp-to-composer.php`
Wait until the script finishes, and then follow the instructions.

### Options
Example with all options: 
`php wp-to-composer.php --path=public/wp --url=https://www.example.com --repo=https://satis.example.com/satispress/packages.json --version`

* **path** - specify path to WordPress core files. Same as WP CLI path. For example, for bedrock you should use `--path=app/wp`.
* **url** - specify url to site(needed for multisite with subdomains or domain mapping). 
* **version** - Use latest version in generated composer.json(for example "^2.3.1"). Omitting this sets "*" as version.
* **repo** - Specify custom composer repo url

## TODO / Roadmap

* Global config file with default options(so you don't have to pass repo and other things as an argument every time)
* What do you want? Send me feedback!
* Rewite/refactor of the current spaghetti code
