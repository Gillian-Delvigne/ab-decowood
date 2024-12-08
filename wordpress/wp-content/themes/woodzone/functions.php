<?php
/**
 * Theme Functions
 *
 * @author Jegstudio
 * @package woodzone
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

defined( 'WOODZONE_VERSION' ) || define( 'WOODZONE_VERSION', '1.0.0' );
defined( 'WOODZONE_DIR' ) || define( 'WOODZONE_DIR', trailingslashit( get_template_directory() ) );
defined( 'WOODZONE_URI' ) || define( 'WOODZONE_URI', trailingslashit( get_template_directory_uri() ) );

require get_parent_theme_file_path( 'inc/autoload.php' );

Woodzone\Init::instance();
