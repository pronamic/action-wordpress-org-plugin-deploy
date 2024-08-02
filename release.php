<?php

/**
 * Functions.
 */
function escape_sequence( $code ) {
	return "\e[" . $code . 'm';
}

function format_command( $value ) {
	return escape_sequence( '36' ) . $value . escape_sequence( '0' );
}

function format_error( $value ) {
	return escape_sequence( '31' ) . escape_sequence( '1' ) . 'Error:' . escape_sequence( '0' ) . ' ' . $value;
}

function run_command( $command ) {
	echo format_command( $command ), PHP_EOL;

	passthru( $command, $result_code );

	if ( 0 !== $result_code ) {
		exit( $result_code );
	}
}

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
 * Setup.
 */
$wp_slug = 'pronamic-pay-with-rabo-smart-pay-for-woocommerce';

$version = '1.0.0';

$svn_url = "https://plugins.svn.wordpress.org/$wp_slug";

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
$svn_dir      = $work_dir . '/svn';

mkdir( $archives_dir );
mkdir( $plugins_dir );
mkdir( $svn_dir );

$plugin_dir = $plugins_dir . '/' . $wp_slug;

/**
 * Download release.
 *
 * @link https://cli.github.com/manual/gh_release_download
 */
start_group( '📥 Download plugin' );

run_command( "gh release download --pattern '$filename' --dir $archives_dir --repo $repository" );

end_group();

/**
 * Unzip.
 */
start_group( '📦 Unzip plugin' );

run_command(
	sprintf(
		'unzip %s -d %s',
		escapeshellarg( $archives_dir . '/' . $filename ),
		escapeshellarg( $plugins_dir )
	)
);

end_group();

/**
 * Check tag existence.
 */
start_group( '🔎 Check tag existence' );

run_command( "svn info $svn_url/tags/$version" );

end_group();

/**
 * Subversion.
 * 
 * @link https://stackoverflow.com/a/122291
 */
start_group( '⬇ Subversion checkout WordPress.org' );

run_command( "svn checkout $svn_url $svn_dir --depth=immediates" );

run_command( "cd $svn_dir" );

chdir( $svn_dir );

run_command( 'svn update trunk --depth=infinity' );

end_group();

/**
 * Synchronize.
 * 
 * @link http://stackoverflow.com/a/14789400
 * @link http://askubuntu.com/a/476048
 */
start_group( '🔄 Synchronize plugin' );

run_command(
	sprintf(
		'rsync --archive --delete --verbose %s %s',
		escapeshellarg( $plugin_dir . '/' ),
		escapeshellarg( $svn_dir . '/trunk/' )
	)
);

end_group();


/**
 * Subversion modifications.
 */
start_group( '💾 Subversion modifications' );

run_command( 'svn status' );

$output = shell_exec( 'svn status --xml' );

echo $output;

end_group();
