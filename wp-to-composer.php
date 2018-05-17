#!/usr/bin/php
<?php
/**
 *  USAGE:
 *
 * (optional) Download script:
 * wget https://raw.githubusercontent.com/pelmered/wp-to-composer/master/wp-to-composer.php
 *
 * Basic usage:
 * php wp-to-composer.php
 * And then follow the instructions
 *
 * Options
 *
 * All options
 * php wp-to-composer.php --path=public/wp --url=https://www.example.com --repo=https://satis.example.com/satispress/packages.json --version
 *
 * path - specify path to WordPress core files. Same as WP CLI path
 * url - specify url to site(needed for multisite)
 * version - Use latest version in generated composer.json(for example "^2.3.1"). Omitting this sets "*" as version.
 * repo - Specify custom composer repo url
 *
 **/

$options = getopt( 'p::u::v::r::', [ 'path::', 'url::', 'version::', 'repo::' ] );

$extra_params = '';

if ( ! empty( $options['p'] ) ) {
	$extra_params .= ' --path=' . $options['p'];
} else if ( ! empty( $options['path'] ) ) {
	$extra_params .= ' --path=' . $options['path'];
}

if ( ! empty( $options['u'] ) ) {
	$extra_params .= ' --url=' . $options['u'];
} else if ( ! empty( $options['url'] ) ) {
	$extra_params .= ' --url=' . $options['url'];
}

$custom_repo_url = false;

if ( ! empty( $options['r'] ) ) {
	$custom_repo_url = $options['r'];
} else if ( ! empty( $options['repo'] ) ) {
	$custom_repo_url = $options['repo'];
}

$use_version = isset($options['v']) || isset($options['version']);

function get_path( $command ) {
	return ltrim(str_replace(getcwd(), '', trim(@shell_exec($command))), '/');
}

function array_find( $needle, array $haystack ) {
	foreach ( $haystack as $value ) {
		if ( false !== stripos( $value, $needle ) ) {
			return $value;
		}
	}

	return false;
}

function match_version( $plugin_version, $found_plugin, $plugin_name ) {
	global $warnings;

	if ( ! empty( $found_plugin[ $plugin_version ] ) ) {
		return $plugin_version;
	}

	$current_version = $plugin_version;

	$versions = array_keys( $found_plugin );

	// Sort versions so we can find the best match easier,
	sort( $versions, SORT_NATURAL );

	foreach ( $versions as $version ) {
		if ( version_compare( $version, $plugin_version ) >= 0 ) {
			echo "Note: Couldn't find exact version. ";
			echo "The following plugin was upgraded from {$plugin_version} to {$version} \n";

			$warnings[] = "$plugin_name was upgraded from {$plugin_version} to {$version}";

			return $version;
		}

		$current_version = $version;
	}

	return $current_version;
}

function match_custom_repo( $plugin, $custom_repo_data, $custom_repo_plugins, $use_version ) {

	$found = array_find( $plugin['name'], $custom_repo_plugins );

	if ( $found ) {

		//$plugin['version'] = '5.6.3';

		$found_plugin = $custom_repo_data['packages'][ $found ];

		$format = '"%s":"%s",';

		if ( $use_version ) {
			$version = match_version( $plugin['version'], $found_plugin, $found );

			return sprintf( $format, $found, '^' . $version );
		}

		return sprintf( $format, $found, '*' );
	}
}

function display_packagist_package( $package, $return = false ) {

	$output = '';
	foreach ( $package as $key => $value ) {
		$output .= ucfirst( $key ) . ': ' . $value . "\n";
	}

	if( $return ) {
		return $output;
	}
	echo $output;
}

function select_packagist_package_version( string $package_name ) {

	$package_data = json_decode( file_get_contents( 'https://packagist.org/p/' . $package_name . '.json' ), true );

	$versions = array_keys( $package_data['packages'][ $package_name ] );

	foreach ( $versions as $key => $version ) {
		echo "[{$key}] {$version} \n";
	}

	$line = (int) trim( readline( 'Select option, or blank for "*": ' ) );

	if ( $line > 0 ) {
		return $versions[ $line ];
	}

	return '*';
}

function get_gitignore_line($plugin_name, $plugin_type) {
	global $wp_content_path, $plugins_path;

	switch( $plugin_type ) {
		case 'dropin':
			return $wp_content_path . '/' . $plugin_name;
			break;
		case 'must-use':
			return $wp_content_path . '/mu-plugins/' . $plugin_name . '.php';
			break;
		default:
			return $plugins_path . '/' . $plugin_name;
			break;
	}
}

$wp_content_path = get_path( 'wp config get WP_CONTENT_DIR ' . $extra_params );
$plugins_path    = get_path( 'wp plugin path ' . $extra_params );


echo 'Getting plugins list... ';
$plugins_csv = @shell_exec( 'wp plugin list --fields=name,version,status --format=csv ' . $extra_params );

$lines = explode( "\n", $plugins_csv );
$head  = str_getcsv( array_shift( $lines ) );

$composer_plugins = [];

// remove empty items in array
$lines = array_filter( $lines );

if( $custom_repo_url ) {
	$custom_repo_data         = json_decode( file_get_contents( $custom_repo_url ), true );
	$custom_repo_plugins = array_keys( $custom_repo_data['packages'] );
}

echo 'Done!' . "\n\n";

$found_plugins = [];
$gitignores    = [];
$warnings      = [];

echo 'Checking plugins against wordpress.org repo...' . "\n\n";

if ( isset( $lines ) && is_array( $lines ) ) {


	foreach ( $lines as $line ) {
		echo "\n";

		$plugin = array_combine( $head, str_getcsv( $line ) );

		// Skip dropins and must-use
		/*
		if ( in_array( $plugin['status'], [ 'must-use', 'dropin' ] ) ) {
			echo 'Plugin: ' . $plugin['name'] . ' is ' . $plugin['status'] . '. Skipping.' . "\n";
			$gitignores[] = $wp_content_path . '/' . ( $plugin['status'] == 'must-use' ? 'mu-plugins/' : '' ) . $plugin['name'];
			continue;
		}
		*/

		if( $custom_repo_url ) {
			$match = match_custom_repo( $plugin, $custom_repo_data, $custom_repo_plugins, $use_version );

			if ( $match ) {
				$found_plugins[] = $match;
				echo 'Plugin: ' . $plugin['name'] . ' found in custom repo.' . "\n";
				continue;
			}
		}

		$data = file_get_contents( 'https://api.wordpress.org/plugins/info/1.0/' . $plugin['name'] );

		$wp_object = unserialize( $data, [] );

		if ( isset( $wp_object->slug ) && $wp_object->slug == $plugin['name'] ) {
			//$found_plugins[] = $wp_object;

			$found_plugins[] = sprintf( '"wpackagist-plugin/%s":"%s",', $wp_object->slug, ( $use_version ? '^' . $wp_object->version : '*' ) );
			echo 'Plugin: ' . $wp_object->name . ' found on wordpress.org.' . "\n";
			continue;
		}

		$data = json_decode( file_get_contents( 'https://packagist.org/search.json?q=' . $plugin['name'] ), true );


		if ( isset( $data['total'] ) && $data['total'] > 0 ) {

			if ( $data['total'] === 1 && isset( $data['results'][0] ) && is_array( $data['results'][0] ) ) {
				$package_name = $data['results'][0]['name'];

				echo 'Plugin: ' . $package_name . ' found on packagist.' . "\n\n";

				display_packagist_package( $data['results'][0] );
				echo "\n";

				$line = trim( readline( 'Add this package (Y/n) ? ' ) );

				if ( empty( $line ) || strtolower( $line ) === 'y' ) {


					$version = select_packagist_package_version( $package_name );

					$found_plugins[] = '"' . $package_name . '":"' . $version . '",';
					echo 'Plugin: ' . $package_name . ' added from packagist.' . "\n";
					continue;
				}

				echo 'Plugin: ' . $package_name . ' skipped.' . "\n";
				$gitignores[] = get_gitignore_line($plugin['name'], $plugin['status']);

				continue;
			}

			echo 'Plugin: ' . $plugin['name'] . ' found on packagist. But has multiple candidates.' . "\n";

			$option = 1;
			foreach ( $data['results'] as $option => $package ) {
				echo "\n\n";
				echo "Option " . ( $option + 1 ) . ": \n";
				display_packagist_package( $package );
			}

			echo "\n";
			$line = (int) trim( readline( 'Select option, or blank to skip: ' ) );

			if ( $line > 0 ) {

				$package_name = $data['results'][ $line - 1 ]['name'];

				echo "\nYou selected {$package_name}. Select version: \n";

				$version = select_packagist_package_version( $package_name );

				$found_plugins[] = '"' . $data['results'][0]['name'] . '":"' . $version . '",';

				echo 'Plugin: ' . $package_name . ' added from packagist.' . "\n";
				continue;
			}

			echo 'Plugin: ' . $plugin['name'] . ' skipped.' . "\n";
			$gitignores[] = get_gitignore_line($plugin['name'], $plugin['status']);
			continue;
		}

		$gitignores[] = get_gitignore_line($plugin['name'], $plugin['status']);
		echo 'Plugin: ' . $plugin['name'] . ' not found.' . "\n";

	}
} else {
	die( 'No plugins found. Check output of : wp plugin list --fields=name,version,status --format=csv ' . $extra_params );
}

echo "\n\n\n";
echo "#################### \n";
echo "#  SCRIPT RESULTS  # \n";
echo "#################### \n";
echo "\n\n";


if ( isset( $found_plugins ) && is_array( $found_plugins ) ) {
	echo 'PLUGINS FOUND(paste this into the require section of your composers.json):';
	echo "\n\n";

	foreach ( $found_plugins AS $plugin ) {
		//printf('"wpackagist-plugin/%s":"%s",', $plugin->slug, ( $use_version ? '^'.$plugin->version : '*' ) );
		echo $plugin . "\n";
	}
} else {
	echo 'NO PLUGINS FOUND :( ';
	echo "\n\n";
}

echo "\n\n\n";
if ( isset( $gitignores ) && is_array( $gitignores ) ) {
	echo "PLUGINS NOT FOUND ON WORDPRESS.ORG. You need to do one of the following: \n";
	echo " - Find an alternative source / composer repository, or create you own using Satis or similar. \n";
	echo " - Must-use/MU-plugins might be loaded with composer. \n";
	echo " - Add the following to your .gitignore to add the plugins to your repo for local version control:  \n\n";

	foreach ( $gitignores AS $gitignore ) {
		echo '!' . $gitignore . "\n";
	}
} else {
	echo 'ALL PLUGINS FOUND ON WORDPRESS.ORG :D ';
	echo "\n";
}

if( !empty( $warnings) ) {
	echo "\n\nWARNINGS\n\n";

	foreach( $warnings as $warning ) {
		echo $warning."\n";
	}
}

echo "\n\n";

die();
