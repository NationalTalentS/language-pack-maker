<?php

/**
 * GitHub Updater Language Pack Maker
 *
 * @package   Language_Pack_Maker
 * @author    Andy Fragen
 * @license   MIT
 * @link      https://github.com/afragen/github-updater-language-pack-maker
 * @version   1.0
 */

namespace Fragen\GitHub_Updater;


/**
 * Class Language_Pack_Maker
 *
 * @package Fragen\GitHub_Updater
 */
class Language_Pack_Maker {

	/**
	 * List of files in specified directory.
	 *
	 * @var array
	 */
	private $directory_list;

	/**
	 * List of available translations.
	 *
	 * @var array
	 */
	private $translations;

	/**
	 * Array of .mo/.po files for each translation.
	 *
	 * @var array
	 */
	private $packages;

	/**
	 * Shortcut to root directory of languages repo.
	 *
	 * @var string
	 */
	private $root_dir;

	/**
	 * Shortcut to `/languages` directory.
	 *
	 * @var string
	 */
	private $language_files_dir;

	/**
	 * Shortcut to `/packages` directory, where zipfiles will live.
	 *
	 * @var string
	 */
	private $packages_dir;

	/**
	 * Language_Pack_Maker constructor.
	 */
	public function __construct() {
		$this->root_dir           = dirname( dirname( __DIR__ ) );
		$this->language_files_dir = $this->root_dir . '/languages';
		$this->packages_dir       = $this->root_dir . '/packages';
		@mkdir( $this->packages_dir, 0777 );
		$this->run();
	}

	/**
	 * Start making stuff.
	 */
	private function run() {
		$this->directory_list = $this->list_directory( $this->language_files_dir );
		$this->translations   = $this->process_directory( $this->directory_list );
		$this->packages       = $this->create_packages();
		$this->create_language_packs();
		$this->create_json();
	}

	/**
	 * Create an array of the directory contents.
	 *
	 * @param string $dir filepath
	 *
	 * @return array $dir_list Listing of directory contents.
	 */
	public function list_directory( $dir ) {
		$dir_list = array();
		$dir_handle = @opendir( $dir ) or die( "Unable to open $dir" );
		$skip_files = array( '.', '..', '.DS_Store', '.htaccess' );

		while ( false !== ( $file = readdir( $dir_handle ) ) ) {
			if ( ! in_array( $file, $skip_files ) ) {
				if ( false !== stripos( $file, '.pot' ) ) {
					continue;
				}
				$dir_list[] = $file;
			}
		}
		closedir( $dir_handle );

		return $dir_list;
	}

	/**
	 * Returns an array of translations with stripped file extension.
	 *
	 * @param array $dir_list Listing of directory contents.
	 *
	 * @return array $translation_list An array of translations.
	 */
	private function process_directory( $dir_list ) {
		$translation_list = array_map( function( $e ) {
			return pathinfo( $e, PATHINFO_FILENAME );
		}, $dir_list );
		$translation_list = array_unique( $translation_list );

		return $translation_list;
	}

	/**
	 * Creates an associative array of translations from directory listing.
	 *
	 * @return array $packages Associative array of translation files per translation.
	 */
	private function create_packages() {
		$packages = array();
		foreach ( $this->translations as $translation ) {
			$package = array();
			foreach ( $this->directory_list as $file ) {
				if ( false !== stristr( $file, $translation ) ) {
					$package[] = $this->language_files_dir . '/' . $file;
				}
			}
			$packages[ $translation ] = $package;
		}

		return $packages;
	}

	/**
	 * Create language pack zipfiles.
	 */
	private function create_language_packs() {
		foreach ( $this->packages as $translation => $files ) {
			$this->create_zip( $files, $this->packages_dir . '/' . $translation . '.zip', true );
		}
	}

	/**
	 * Create individual zipfile.
	 *
	 * @link https://davidwalsh.name/create-zip-php
	 *
	 * @param array  $files       Array of .mo/.po files for each translation.
	 * @param string $destination Filepath to zipfile.
	 * @param bool   $overwrite   Boolean to set zipfile creation overwrite mode.
	 *
	 * @return bool
	 */
	private function create_zip( $files = array(), $destination = '', $overwrite = true ) {
		//if the zip file already exists and overwrite is false, return false
		if ( file_exists( $destination ) && ! $overwrite ) {
			return false;
		}

		//create the archive
		$zip = new \ZipArchive();
		if ( $zip->open( $destination, \ZIPARCHIVE::OVERWRITE | \ZIPARCHIVE::CREATE ) !== true ) {
			return false;
		}
		//add the files
		foreach ( $files as $file ) {
			$zip->addFile( $file, basename( $file ) );
		}

		//close the zip -- done!
		$zip->close();

		//check to make sure the file exists
		if ( file_exists( $destination ) ) {
			printf( basename( $destination ) . ' created.' . "\n<br>" );
		} else {
			printf( '<span style="color:#f00">' . basename( $destination ) . ' failed.</span>' . "\n<br>" );
		}
	}

	/**
	 * Create JSON file of translations for export and use by GitHub Updater.
	 */
	private function create_json() {
		$packages = $this->list_directory( $this->packages_dir );
		$arr      = array();

		foreach ( $packages as $package ) {
			foreach ( $this->translations as $translation ) {
				if ( false !== stristr( $package, $translation ) ) {
					$arr[ $translation ]['slug']       = stristr( $translation, strrchr( $translation, '-' ), true );
					$arr[ $translation ]['language']   = ltrim( strrchr( $translation, '-' ), '-' );
					$arr[ $translation ]['updated']    = date( 'Y-m-d H:i:s', filemtime( $this->packages[ $translation ][0] ) );
					$arr[ $translation ]['package']    = '/packages/' . $package;
					$arr[ $translation ]['autoupdate'] = '1';
				}
			}
		}

		file_put_contents( $this->root_dir . '/language-pack.json', json_encode( $arr ) );
		printf( "\n<br>" . 'language-pack.json created.' . "\n<br>" );
	}

}

