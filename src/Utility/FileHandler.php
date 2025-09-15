<?php

namespace App\AdvancedEntryManager\Utility;

// We need to import the FileSystem class
use App\AdvancedEntryManager\Utility\FileSystem;

class FileHandler {

	private $private_dir;
	private $logger;
	private $file_system; // The new property for our FileSystem instance

	public function __construct( $logger ) {
		$upload_dir        = wp_upload_dir();
		$this->private_dir = trailingslashit( $upload_dir['basedir'] ) . 'fem-cf7-uploads';
		$this->logger      = $logger;
		$this->file_system = new FileSystem(); // Initialize the FileSystem instance

		if ( ! $this->file_system->exists( $this->private_dir ) ) {
			if ( ! wp_mkdir_p( $this->private_dir ) ) {
				$this->logger->log( 'Failed to create file upload directory.', 'error' );
			}
		}
	}

	/**
	 * Processes and stores a list of uploaded files.
	 *
	 * @param array $file_paths An array of file paths to process.
	 * @return array An array of new filenames on success, or an empty array on failure.
	 */
	public function process_files( $file_paths ) {
		$file_list  = array();
		$file_paths = (array) $file_paths;

		foreach ( $file_paths as $file_path ) {
			if ( ! $this->file_system->exists( $file_path ) ) {
				$this->logger->log( "File not found at path: {$file_path}", 'warning' );
				continue;
			}

			$new_path     = $this->generate_unique_filepath( basename( $file_path ) );
			$file_content = $this->file_system->read( $file_path );

			if ( $file_content !== false && $this->file_system->write( $new_path, $file_content ) ) {
				$file_list[] = basename( $new_path );
			} else {
				$this->logger->log( "Failed to move file from {$file_path} to {$new_path}", 'error' );
			}
		}

		return $file_list;
	}

	/**
	 * Generates a unique filepath to prevent overwriting existing files.
	 *
	 * @param string $filename The original filename.
	 * @return string The unique filepath.
	 */
	private function generate_unique_filepath( $filename ) {
		$path_info    = pathinfo( $filename );
		$new_filename = sanitize_file_name( $filename );
		$new_filepath = trailingslashit( $this->private_dir ) . $new_filename;
		$i            = 1;

		while ( file_exists( $new_filepath ) ) {
			$new_filename = sprintf( '%s-%d.%s', $path_info['filename'], $i++, $path_info['extension'] );
			$new_filename = sanitize_file_name( $new_filename );
			$new_filepath = trailingslashit( $this->private_dir ) . $new_filename;
		}

		return $new_filepath;
	}
}
