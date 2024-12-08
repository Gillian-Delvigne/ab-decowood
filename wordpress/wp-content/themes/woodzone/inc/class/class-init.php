<?php
/**
 * Init Configuration
 *
 * @author Jegstudio
 * @package woodzone
 */

namespace Woodzone;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Init Class
 *
 * @package woodzone
 */
class Init {

	/**
	 * Instance variable
	 *
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Class instance.
	 *
	 * @return Init
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		$this->init_instance();
		$this->load_hooks();
	}

	/**
	 * Load initial hooks.
	 */
	private function load_hooks() {
		add_action( 'init', array( $this, 'register_block_patterns' ), 9 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'dashboard_scripts' ) );

		add_action( 'wp_ajax_woodzone_set_admin_notice_viewed', array( $this, 'notice_closed' ) );

		add_action( 'after_switch_theme', array( $this, 'update_global_styles_after_theme_switch' ) );
		add_filter( 'gutenverse_block_config', array( $this, 'default_font' ), 10 );
		add_filter( 'gutenverse_font_header', array( $this, 'default_header_font' ) );
		add_filter( 'gutenverse_global_css', array( $this, 'global_header_style' ) );

		add_filter( 'gutenverse_themes_template', array( $this, 'add_template' ), 10, 2 );
		add_filter( 'gutenverse_themes_override_mechanism', '__return_true' );

		add_filter( 'gutenverse_show_theme_list', '__return_false' );
	}

	/**
	 * Add Template to Editor.
	 *
	 * @param array $template_files Path to Template File.
	 * @param array $template_type Template Type.
	 *
	 * @return array
	 */
	public function add_template( $template_files, $template_type ) {
		if ( 'wp_template' === $template_type ) {
			$new_templates = array(
				'blank-canvas',
			);

			foreach ( $new_templates as $template ) {
				$template_files[] = array(
					'slug'  => $template,
					'path'  => WOODZONE_DIR . "templates/{$template}.html",
					'theme' => get_template(),
					'type'  => 'wp_template',
				);
			}
		}

		return $template_files;
	}

	/**
	 * Initialize Instance.
	 */
	public function init_instance() {
		new Asset_Enqueue();
		new Themeforest_Data();
	}

	/**
	 * Update Global Styles After Theme Switch
	 */
	public function update_global_styles_after_theme_switch() {
		// Get the path to the current theme's theme.json file
		$theme_json_path = get_template_directory() . '/theme.json';
		$theme_slug      = get_option( 'stylesheet' ); // Get the current theme's slug
		$args            = array(
			'post_type'      => 'wp_global_styles',
			'post_status'    => 'publish',
			'name'           => 'wp-global-styles-' . $theme_slug,
			'posts_per_page' => 1,
		);

		$global_styles_query = new WP_Query( $args );
		// Check if the theme.json file exists
		if ( file_exists( $theme_json_path ) && $global_styles_query->have_posts() ) {
			$global_styles_query->the_post();
			$global_styles_post_id = get_the_ID();
			// Step 2: Get the existing global styles (color palette)
			$global_styles_content = json_decode( get_post_field( 'post_content', $global_styles_post_id ), true );
			if ( isset( $global_styles_content['settings']['color']['palette']['theme'] ) ) {
				$existing_colors = $global_styles_content['settings']['color']['palette']['theme'];
			} else {
				$existing_colors = array();
			}

			// Step 3: Extract slugs from the existing colors
			$existing_slugs = array_column( $existing_colors, 'slug' );
			// Step 4:Read the contents of the theme.json file

			$theme_json_content = file_get_contents( $theme_json_path );
			$theme_json_data    = json_decode( $theme_json_content, true );

			// Access the color palette from the theme.json file
			if ( isset( $theme_json_data['settings']['color']['palette'] ) ) {

				$theme_colors = $theme_json_data['settings']['color']['palette'];

				// Step 5: Loop through theme.json colors and add them if they don't exist
				foreach ( $theme_colors as $theme_color ) {
					if ( ! in_array( $theme_color['slug'], $existing_slugs ) ) {
						$existing_colors[] = $theme_color; // Add new color to the existing palette
					}
				}
				foreach ( $theme_colors as $theme_color ) {
					$theme_slug = $theme_color['slug'];

					// Step 6: Use in_array to check if the slug already exists in the global palette
					if ( ! in_array( $theme_slug, $existing_slugs ) ) {
						// If the slug does not exist, add the theme color to the global palette
						$global_colors[] = $theme_color;
					}
				}
				// Step 6: Update the global styles content with the new colors
				$global_styles_content['settings']['color']['palette']['theme'] = $existing_colors;

				// Step 7: Save the updated global styles back to the post
				wp_update_post(
					array(
						'ID'           => $global_styles_post_id,
						'post_content' => wp_json_encode( $global_styles_content ),
					)
				);

			}
			wp_reset_postdata(); // Reset the query
		}
	}

	/**
	 * Notice Closed
	 */
	public function notice_closed() {
		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'woodzone_admin_notice' ) ) {
			update_user_meta( get_current_user_id(), 'gutenverse_install_notice', 'true' );
		}
		die;
	}

	/**
	 * Generate Global Font
	 *
	 * @param string $value  Value of the option.
	 *
	 * @return string
	 */
	public function global_header_style( $value ) {
		$theme_name      = get_stylesheet();
		$global_variable = get_option( 'gutenverse-global-variable-font-' . $theme_name );

		if ( empty( $global_variable ) && function_exists( 'gutenverse_global_font_style_generator' ) ) {
			$font_variable = $this->default_font_variable();
			$value        .= \gutenverse_global_font_style_generator( $font_variable );
		}

		return $value;
	}

	/**
	 * Header Font.
	 *
	 * @param mixed $value  Value of the option.
	 *
	 * @return mixed Value of the option.
	 */
	public function default_header_font( $value ) {
		if ( ! $value ) {
			$value = array(
				array(
					'value'  => 'Alfa Slab One',
					'type'   => 'google',
					'weight' => 'bold',
				),
			);
		}

		return $value;
	}

	/**
	 * Alter Default Font.
	 *
	 * @param array $config Array of Config.
	 *
	 * @return array
	 */
	public function default_font( $config ) {
		if ( empty( $config['globalVariable']['fonts'] ) ) {
			$config['globalVariable']['fonts'] = $this->default_font_variable();

			return $config;
		}

		if ( ! empty( $config['globalVariable']['fonts'] ) ) {
			// Handle existing fonts.
			$theme_name   = get_stylesheet();
			$initial_font = get_option( 'gutenverse-font-init-' . $theme_name );

			if ( ! $initial_font ) {
				$result = array();
				$array1 = $config['globalVariable']['fonts'];
				$array2 = $this->default_font_variable();
				foreach ( $array1 as $item ) {
					$result[ $item['id'] ] = $item;
				}
				foreach ( $array2 as $item ) {
					$result[ $item['id'] ] = $item;
				}
				$fonts = array();
				foreach ( $result as $key => $font ) {
					$fonts[] = $font;
				}
				$config['globalVariable']['fonts'] = $fonts;

				update_option( 'gutenverse-font-init-' . $theme_name, true );
			}
		}

		return $config;
	}

	/**
	 * Default Font Variable.
	 *
	 * @return array
	 */
	public function default_font_variable() {
		return array(
            array (
  'id' => 'H2lmRO',
  'name' => 'H1',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '52',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '30',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '800',
  ),
),array (
  'id' => 'nMGsuT',
  'name' => 'H2',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '36',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '24',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
  ),
),array (
  'id' => 'nDicGx',
  'name' => 'H3',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '24',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => 'BL8phF',
  'name' => 'H4',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.3',
      ),
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '20',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => '3KyvQK',
  'name' => 'H5',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '',
      ),
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
    'transform' => 'uppercase',
    'spacing' => 
    array (
      'Desktop' => '0.1',
    ),
  ),
),array (
  'id' => '6GMv5b',
  'name' => 'H6',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => 'gVRepE',
  'name' => 'H3 Alt',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '28',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '24',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => '1uv6Xg',
  'name' => 'H4 Alt',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '22',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => 'k2MSOO',
  'name' => 'Text',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Heebo',
      'value' => 'Heebo',
      'type' => 'google',
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.5',
      ),
    ),
    'transform' => 'normal',
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'weight' => '400',
  ),
),array (
  'id' => 'uYt9pX',
  'name' => 'Accent',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
    'spacing' => 
    array (
      'Desktop' => '0.1',
    ),
  ),
),array (
  'id' => 'o9uAxX',
  'name' => 'Nav',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.3',
      ),
    ),
  ),
),array (
  'id' => 'AKa8BT',
  'name' => 'Button',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '12',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
    'transform' => 'normal',
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1',
      ),
    ),
  ),
),array (
  'id' => 'PFoznL',
  'name' => 'Button 2',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
    'transform' => 'normal',
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1',
      ),
    ),
  ),
),array (
  'id' => 'K6rUfH',
  'name' => 'Text 2',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Heebo',
      'value' => 'Heebo',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '14',
        'unit' => 'px',
      ),
    ),
    'weight' => '400',
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.5',
      ),
    ),
  ),
),array (
  'id' => 'ZsDZDF',
  'name' => 'Desc Testimonial',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Heebo',
      'value' => 'Heebo',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '24',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '16',
        'unit' => 'px',
      ),
    ),
    'weight' => '300',
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.5',
      ),
    ),
    'style' => 'italic',
  ),
),array (
  'id' => 'pJd1Uz',
  'name' => 'Number',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '64',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '48',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.5',
      ),
    ),
  ),
),array (
  'id' => 'X13Y8s',
  'name' => 'Funfact',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '48',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '32',
        'unit' => 'px',
      ),
    ),
    'weight' => '700',
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1.2',
      ),
    ),
  ),
),array (
  'id' => 'XcIu3n',
  'name' => 'Title Meta Projects',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '18',
        'unit' => 'px',
      ),
    ),
    'weight' => '600',
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '0.1',
      ),
    ),
  ),
),array (
  'id' => 'Wp71Dq',
  'name' => '404',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Lato',
      'value' => 'Lato',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '156',
        'unit' => 'px',
      ),
      'Mobile' => 
      array (
        'point' => '96',
        'unit' => 'px',
      ),
    ),
    'lineHeight' => 
    array (
      'Desktop' => 
      array (
        'unit' => 'em',
        'point' => '1',
      ),
    ),
    'weight' => '600',
  ),
),array (
  'id' => 'FAeYKR',
  'name' => 'Super',
  'font' => 
  array (
    'font' => 
    array (
      'label' => 'Inter',
      'value' => 'Inter',
      'type' => 'google',
    ),
    'size' => 
    array (
      'Desktop' => 
      array (
        'point' => '32',
        'unit' => 'px',
      ),
    ),
  ),
),
		);
	}

	/**
	 * Register Block Pattern.
	 */
	public function register_block_patterns() {
		new Block_Patterns();
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function dashboard_scripts() {
		$screen = get_current_screen();
		wp_enqueue_script('wp-api-fetch');

		if ( is_admin() ) {
			// enqueue css.
			wp_enqueue_style(
				'woodzone-dashboard',
				WOODZONE_URI . '/assets/css/theme-dashboard.css',
				array(),
				WOODZONE_VERSION
			);

			// enqueue js.
			wp_enqueue_script(
				'woodzone-dashboard',
				WOODZONE_URI . '/assets/js/theme-dashboard.js',
				array( 'wp-api-fetch' ),
				WOODZONE_VERSION,
				true
			);

			wp_localize_script( 'woodzone-dashboard', 'GutenThemeConfig', $this->theme_config() );
		}
	}

	/**
	 * Check if plugin is installed.
	 *
	 * @param string $plugin_slug plugin slug.
	 * 
	 * @return boolean
	 */
	public function is_installed( $plugin_slug ) {
		$all_plugins = get_plugins();
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$plugin_dir = dirname($plugin_file);

			if ($plugin_dir === $plugin_slug) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register static data to be used in theme's js file
	 */
	public function theme_config() {
		$active_plugins = get_option( 'active_plugins' );
		$plugins = array();
		foreach( $active_plugins as $active ) {
			$plugins[] = explode( '/', $active)[0];
		}

		$config = array(
			'home_url'      => home_url(),
			'version'       => WOODZONE_VERSION,
			'images'        => WOODZONE_URI . '/assets/img/',
			'title'         => esc_html__( 'Woodzone', 'woodzone' ),
			'description'   => esc_html__( 'Woodzone is a modern and clean WordPress theme designed for carpenters, craftsmen, woodworking projects, furniture repair and services, construction, handymen, and all types of carpentry businesses. Fully compatible with the WordPress Block Editor, this theme offers a professional design with a 100% responsive layout and is easy to customize, no coding skills needed.', 'woodzone' ),
			'pluginTitle'   => esc_html__( 'Plugin Requirement', 'woodzone' ),
			'pluginDesc'    => esc_html__( 'This theme require some plugins. Please make sure all the plugin below are installed and activated.', 'woodzone' ),
			'note'          => esc_html__( '', 'woodzone' ),
			'note2'         => esc_html__( '', 'woodzone' ),
			'demo'          => esc_html__( '', 'woodzone' ),
			'demoUrl'       => esc_url( 'https://gutenverse.com/demo?name=woodzone' ),
			'install'       => '',
			'installText'   => esc_html__( 'Install Gutenverse Plugin', 'woodzone' ),
			'activateText'  => esc_html__( 'Activate Gutenverse Plugin', 'woodzone' ),
			'doneText'      => esc_html__( 'Gutenverse Plugin Installed', 'woodzone' ),
			'dashboardPage' => admin_url( 'themes.php?page=woodzone-dashboard' ),
			'logo'          => WOODZONE_URI . 'assets/img/logo-black.png',
			'slug'          => 'woodzone',
			'upgradePro'    => 'https://gutenverse.com/pro',
			'supportLink'   => 'https://support.jegtheme.com/forums/forum/fse-themes/',
			'libraryApi'    => 'https://gutenverse.com//wp-json/gutenverse-server/v1',
			'docsLink'      => 'https://support.jegtheme.com/theme/fse-themes/',
			'pages'         => array(
				
			),
			'plugins'      => array(
				array(
					'slug'       		=> 'gutenverse',
					'title'      		=> 'Gutenverse',
					'short_desc' 		=> 'GUTENVERSE – GUTENBERG BLOCKS AND WEBSITE BUILDER FOR SITE EDITOR, TEMPLATE LIBRARY, POPUP BUILDER, ADVANCED ANIMATION EFFECTS, 45+ FREE USER-FRIENDLY BLOCKS',
					'active'    		=> in_array( 'gutenverse', $plugins, true ),
					'installed'  		=> $this->is_installed( 'gutenverse' ),
					'icons'      		=> array (
  '1x' => 'https://ps.w.org/gutenverse/assets/icon-128x128.gif?rev=3132408',
  '2x' => 'https://ps.w.org/gutenverse/assets/icon-256x256.gif?rev=3132408',
),
					'download_url'      => '',
				),
				array(
					'slug'       		=> 'gutenverse-form',
					'title'      		=> 'Gutenverse Form',
					'short_desc' 		=> 'GUTENVERSE FORM – FORM BUILDER FOR GUTENBERG BLOCK EDITOR, MULTI-STEP FORMS, CONDITIONAL LOGIC, PAYMENT, CALCULATION, 15+ FREE USER-FRIENDLY FORM BLOCKS',
					'active'    		=> in_array( 'gutenverse-form', $plugins, true ),
					'installed'  		=> $this->is_installed( 'gutenverse-form' ),
					'icons'      		=> array (
  '1x' => 'https://ps.w.org/gutenverse-form/assets/icon-128x128.png?rev=3135966',
),
					'download_url'      => '',
				)
			),
			'assign'       => array(
				array(
						'title' => 'Home',
						'page'  => 'Home',
						'demo'  => 'https://fse.jegtheme.com/woodzone/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/home.webp',
					),
				array(
						'title' => 'About Us',
						'page'  => 'About Us',
						'demo'  => 'https://fse.jegtheme.com/woodzone/about-us/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/about.webp',
					),
				array(
						'title' => 'Services',
						'page'  => 'Services',
						'demo'  => 'https://fse.jegtheme.com/woodzone/services/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/services.webp',
					),
				array(
						'title' => 'Projects',
						'page'  => 'Projects',
						'demo'  => 'https://fse.jegtheme.com/woodzone/projects/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/projects.webp',
					),
				array(
						'title' => 'Project Details',
						'page'  => 'Project Details',
						'demo'  => 'https://fse.jegtheme.com/woodzone/project-details/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/project-details.webp',
					),
				array(
						'title' => 'Our Carpenter',
						'page'  => 'Our Carpenter',
						'demo'  => 'https://fse.jegtheme.com/woodzone/our-carpenter/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/our-carpenter.webp',
					),
				array(
						'title' => 'Pricing',
						'page'  => 'Pricing',
						'demo'  => 'https://fse.jegtheme.com/woodzone/pricing/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/pricing.webp',
					),
				array(
						'title' => 'FAQ',
						'page'  => 'FAQ',
						'demo'  => 'https://fse.jegtheme.com/woodzone/faq/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/faq.webp',
					),
				array(
						'title' => 'Blog',
						'page'  => 'Blog',
						'demo'  => 'https://fse.jegtheme.com/woodzone/blog/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/blog.webp',
					),
				array(
						'title' => 'Contact',
						'page'  => 'Contact',
						'demo'  => 'https://fse.jegtheme.com/woodzone/contact/',
						'slug'  => 'blank-canvas',
						'thumb' => WOODZONE_URI . 'assets/img/contact.webp',
					)
			),
			'dashboardData'=> array(
				
			),
			'isThemeforest' => true,
		);

		if ( isset( $config['assign'] ) && $config['assign'] ) {
			$assign = $config['assign'];
			foreach ( $assign as $key => $value ) {
				$query = new \WP_Query(
					array(
						'post_type'      => 'page',
						'post_status'    => 'publish',
						'title'          => '' !== $value['page'] ? $value['page'] : $value['title'],
						'posts_per_page' => 1,
					)
				);

				if ( $query->have_posts() ) {
					$post                     = $query->posts[0];
					$page_template            = get_page_template_slug( $post->ID );
					$assign[ $key ]['status'] = array(
						'exists'         => true,
						'using_template' => $page_template === $value['slug'],
					);

				} else {
					$assign[ $key ]['status'] = array(
						'exists'         => false,
						'using_template' => false,
					);
				}

				wp_reset_postdata();
			}
			$config['assign'] = $assign;
		}

		return $config;
	}

	/**
	 * Add Menu
	 */
	public function admin_menu() {
		add_theme_page(
			'Woodzone Dashboard',
			'Woodzone Dashboard',
			'manage_options',
			'woodzone-dashboard',
			array( $this, 'load_dashboard' ),
			1
		);
	}

	/**
	 * Template page
	 */
	public function load_dashboard() {
		?>
			<div id="gutenverse-theme-dashboard">
			</div>
		<?php
	}
}
