<?php

function start_group( $name ) {
	echo '::group::', $name, PHP_EOL;
}

function end_group() {
	echo '::endgroup::', PHP_EOL;
}

/**
 * Default environment variables.
 * 
 * @link https://docs.github.com/en/actions/writing-workflows/choosing-what-your-workflow-does/variables#default-environment-variables
 */
$repository = getenv( 'GITHUB_REPOSITORY' );

/**
 * Slug.
 */
$slug = 'pronamic-pay-with-rabo-smart-pay-for-woocommerce';

/**
 * Version.
 */
$version = '1.0.0';

/**
 * Filename.
 * 
 * Notation: “{name}.{version}”.
 * 
 * @link https://developer.wordpress.org/cli/commands/dist-archive/
 */
$filename = "$slug.$version.zip";

/**
 * Files.
 */
$work_dir = tempnam( sys_get_temp_dir(), '' );

unlink( $work_dir );

mkdir( $work_dir );

$archives_dir = $work_dir . '/archives';
$plugins_dir  = $work_dir . '/plugins';

mkdir( $archives_dir );
mkdir( $plugins_dir );

/**
 * Download release.
 *
 * @link https://cli.github.com/manual/gh_release_download
 */
start_group( '📥 Download plugin' );

passthru( "gh release download --pattern '$filename' --dir $archives_dir --repo $repository" );

end_group();

/**
 * Unzip.
 */
start_group( '📦 Unzip plugin' );

passthru(
	sprintf(
		'unzip %s -d %s',
		escapeshellarg( $archives_dir . '/' . $filename ),
		escapeshellarg( $plugins_dir )
	)
);

end_group();
