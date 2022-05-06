<?php
// If on Pantheon
if( isset( $_ENV['PANTHEON_ENVIRONMENT'] ) ){
	// Disable WordPress auto updates
	if( ! defined('WP_AUTO_UPDATE_CORE')) {
		define( 'WP_AUTO_UPDATE_CORE', false );
	}

	remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
	// Remove the default WordPress core update nag
    add_action('admin_menu','_pantheon_hide_update_nag');
}

function _pantheon_hide_update_nag() {
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
}

// Get the latest WordPress version
function _pantheon_get_latest_wordpress_version() {
	$core_updates = get_core_updates( array('dismissed' => false) );

	if( ! is_array($core_updates) || empty($core_updates) || ! property_exists($core_updates[0], 'current' ) ){
		return null;
	}

	return $core_updates[0]->current;
}

// Check if WordPress core is at the latest version.
function _pantheon_is_wordpress_core_latest() {
	$latest_wp_version = _pantheon_get_latest_wordpress_version();

	if( null === $latest_wp_version ){
		return true;
	}

	// include an unmodified $wp_version
	include( ABSPATH . WPINC . '/version.php' );

	// Return true if our version is the latest
	return version_compare( str_replace( '-src', '', $latest_wp_version ), str_replace( '-src', '', $wp_version ), '=' );

}

// Replace WordPress core update nag EVERYWHERE with our own notice (use git upstream)
function _pantheon_upstream_update_notice() {
    ?>
    <div class="update-nag notice notice-warning">
		<p style="font-size: 14px; font-weight: bold; margin: 0 0 0.5em 0;">
			Check for updates on <a href="https://dashboard.pantheon.io/sites/<?php echo $_ENV['PANTHEON_SITE']; ?>">your Pantheon dashboard</a>.
		</p>
		For details on applying updates, see the <a href="https://pantheon.io/docs/upstream-updates/" target="_blank">Applying Upstream Updates</a> documentation. <br />
		If you need help, contact an administrator for your Pantheon organization.
	</div>
    <?php
}

// Register Pantheon specific WordPress update admin notice
add_action( 'admin_init', '_pantheon_register_upstream_update_notice' );
function _pantheon_register_upstream_update_notice(){
	// but only if we are on Pantheon
	// and this is not a WordPress Ajax request
	// and WordPress is not up to date
	if( isset( $_ENV['PANTHEON_ENVIRONMENT'] ) && ! wp_doing_ajax() && ! _pantheon_is_wordpress_core_latest() ){
		add_action( 'admin_notices', '_pantheon_upstream_update_notice' );
	}
}

// Return zero updates and current time as last checked time
function _pantheon_disable_wp_updates() {
	include ABSPATH . WPINC . '/version.php';
	return (object) array(
		'updates' => array(),
		'version_checked' => $wp_version,
		'last_checked' => time(),
	);
}

// In the Test and Live environments, clear plugin/theme update notifications.
// Users must check a dev or multidev environment for updates.
if ( in_array( $_ENV['PANTHEON_ENVIRONMENT'], array('test', 'live') ) && (php_sapi_name() !== 'cli') ) {

	// Disable Plugin Updates
	remove_action( 'load-update-core.php', 'wp_update_plugins' );
	add_filter( 'pre_site_transient_update_plugins', '_pantheon_disable_wp_updates' );

	// Disable Theme Updates
	remove_action( 'load-update-core.php', 'wp_update_themes' );
	add_filter( 'pre_site_transient_update_themes', '_pantheon_disable_wp_updates' );
}
