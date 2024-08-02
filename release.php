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

function run_command( $command, $expected_result_code = 0 ) {
	echo format_command( $command ), PHP_EOL;

	passthru( $command, $result_code );

	if ( null !== $expected_result_code && $expected_result_code !== $result_code ) {
		exit( $result_code );
	}

	return $result_code;
}

function start_group( $name ) {
	echo '::group::', $name, PHP_EOL;
}

function end_group() {
	echo '::endgroup::', PHP_EOL;
}

/**
 * Get input.
 * 
 * @link https://docs.github.com/en/actions/creating-actions/metadata-syntax-for-github-actions#inputs
 * @link https://docs.github.com/en/actions/using-workflows/workflow-syntax-for-github-actions#jobsjob_idstepswith
 * @link https://github.com/actions/checkout/blob/cd7d8d697e10461458bc61a30d094dc601a8b017/dist/index.js#L2699-L2717
 * @param string $name
 * @return string|array|false
 */
function get_input( $name ) {
	$env_name = 'INPUT_' . strtoupper( $name );

	return getenv( $env_name );
}

function get_required_input( $name ) {
	$value = get_input( $name );

	if ( false === $value || '' === $value ) {
		echo format_error( escape_sequence( '90' ) . 'Input required and not supplied:' . escape_sequence( '0' ) . ' ' . $name );

		exit( 1 );
	}

	return $value;
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
$svn_username = get_required_input( 'svn-username' );
$svn_password = get_required_input( 'svn-password' );

$slug = get_required_input( 'wp-slug' );

$version = '1.0.0';

$svn_url = "https://plugins.svn.wordpress.org/$slug";

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

$plugin_dir = $plugins_dir . '/' . $slug;

$readme_file = $plugin_dir . '/readme.txt';

/**
 * Start.
 */
start_group( 'ℹ️ Release to WordPress.org' );

echo '• ', escape_sequence( '1' ), 'Subversion URL:', escape_sequence( '0' ), ' ', $svn_url, PHP_EOL;
echo '• ', escape_sequence( '1' ), 'Subversion username:', escape_sequence( '0' ), ' ', $svn_username, PHP_EOL;
echo '• ', escape_sequence( '1' ), 'Subversion password:', escape_sequence( '0' ), ' ', $svn_password, PHP_EOL;

end_group();

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
 * Parse stable tag.
 */
$readme_content = file_get_contents( $readme_file );

$pattern = "/Stable tag: (.*)/";

$stable_tag = '';

if ( 1 === preg_match( $pattern, $readme_content, $matches ) ) {
	$stable_tag = $matches[1];
}

if ( $stable_tag !== $version ) {
	echo 'Stable tag in readme.txt is not equal to release version.';

	exit( 1 );
}

/**
 * Check tag existence.
 */
start_group( '🔎 Check tag existence' );

$svn_url_tag = "$svn_url/tags/$version";

$result_code = run_command( "svn info $svn_url_tag", null );

if ( 0 === $result_code ) {
	echo "There is already a tag for version $version:. $svn_url_tag";

	exit( 1 );
}

end_group();

/**
 * Subversion.
 * 
 * @link https://stackoverflow.com/a/122291
 */
start_group( '⬇ Subversion checkout WordPress.org' );

run_command( "svn checkout $svn_url $svn_dir --depth immediates" );

run_command( "cd $svn_dir" );

chdir( $svn_dir );

run_command( 'svn update trunk --set-depth infinity' );

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

$xml = simplexml_load_string( $output );

if ( false === $xml ) {
	echo 'A problem occurred while reading the `svn status --xml`.';

	exit( 1 );
}

foreach ( $xml->target->entry as $entry ) {
	$path = (string) $entry['path'];

	$wc_status = (string) $entry->{'wc-status'}['item'];

	switch ( $wc_status ) {
		case 'missing':
			run_command( "svn rm $path" );

			break;
		case 'modified';
			// Modified entry will be commited.

			break;
		case 'unversioned':
			run_command( "svn add $path" );

			break;
		default:
			echo "Unsupport working copy status: $wc_status - $path.";

			exit( 1 );
	}
}

end_group();

/**
 * Commit.
 */
start_group( '⬆ Subversion commit WordPress.org' );

run_command( "svn commit --message 'Update' --non-interactive --username '$svn_username' --password '$svn_password'" );

end_group();

/**
 * Tag.
 */
start_group( '🏷️ Subversion tag WordPress.org' );

run_command( "svn cp $svn_url/trunk $svn_url/tags/$version --message 'Tagging version $version' --non-interactive --username '$svn_username' --password '$svn_password'" );

end_group();

/**
 * Clean up.
 */
start_group( '🗑️ Clean up' );

run_command( "rm -f -R $work_dir" );

end_group();
