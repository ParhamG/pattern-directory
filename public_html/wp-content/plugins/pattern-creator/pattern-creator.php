<?php
/**
 * Plugin Name: Block Pattern Creator
 * Description: Create block patterns on the frontend of a site.
 * Version: 1.0.0
 * Requires at least: 5.5
 * Author: WordPress Meta Team
 * Text Domain: wporg-pattern-creator
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace WordPressdotorg\Pattern_Creator;
use const WordPressdotorg\Pattern_Directory\Pattern_Post_Type\POST_TYPE;

const AUTOSAVE_INTERVAL = 30;

/**
 * Check the conditions of the page to determine if the editor should load.
 *
 * @return boolean
 */
function should_load_creator() {
	return \is_singular( POST_TYPE );
}

/**
 * Register & load the assets.
 *
 * @throws \Error If the build files don't exist.
 */
function enqueue_assets() {
	if ( ! should_load_creator() ) {
		return;
	}

	do_action( 'enqueue_block_editor_assets' );

	$dir = dirname( __FILE__ );

	$script_asset_path = "$dir/build/index.asset.php";
	if ( ! file_exists( $script_asset_path ) ) {
		throw new \Error( 'You need to run `yarn start` or `yarn build` for the Pattern Creator.' );
	}

	$script_asset = require( $script_asset_path );
	wp_register_script(
		'wporg-pattern-creator-script',
		plugins_url( 'build/index.js', __FILE__ ),
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);

	wp_set_script_translations( 'wporg-pattern-creator-script', 'wporg-pattern-creator' );

	// Editor Styles.
	$styles = array(
		array(
			'css' => file_get_contents(
				ABSPATH . WPINC . '/css/dist/editor/editor-styles.css'
			),
		),
	);

	$settings = array(
		'alignWide'                            => true, // Support wide patterns.
		'allowedBlockTypes'                    => true, // All block types.
		'disablePostFormats'                   => true,
		'enableCustomFields'                   => false,
		'bodyPlaceholder'                      => __( 'Start writing or type / to choose a block', 'wporg-patterns' ),
		'isRTL'                                => is_rtl(),
		'autosaveInterval'                     => AUTOSAVE_INTERVAL,
		'maxUploadFileSize'                    => 0,
		'styles'                               => $styles,
		'richEditingEnabled'                   => user_can_richedit(),
		'__experimentalBlockPatterns'          => array(),
		'__experimentalBlockPatternCategories' => array(),

		// Editor features -  @todo Re-enable later?
		'disableCustomColors'                  => true,
		'disableCustomFontSizes'               => true,
		'disableCustomGradients'               => true,
		'enableCustomLineHeight'               => false,
		'enableCustomUnits'                    => false,
	);

	wp_add_inline_script(
		'wporg-pattern-creator-script',
		sprintf(
			'var wporgBlockPattern = JSON.parse( decodeURIComponent( \'%s\' ) );',
			rawurlencode( wp_json_encode( array(
				'settings'   => $settings,
				'postId'     => get_the_ID(),
			) ) )
		),
		'before'
	);

	wp_enqueue_script( 'wporg-pattern-creator-script' );

	wp_register_style(
		'wporg-pattern-creator-style',
		plugins_url( 'build/style-index.css', __FILE__ ),
		array(
			'wp-components',
			'wp-block-editor',
			'wp-edit-blocks', // Includes block-library dependencies.
			'wp-format-library',
		),
		filemtime( "$dir/build/style-index.css" )
	);

	// Postbox is only registered if is_admin.
	wp_enqueue_script( 'postbox', admin_url( 'js/postbox.min.js' ), array( 'jquery-ui-sortable', 'wp-a11y' ), false, 1 );
	wp_enqueue_style( 'dashicons' );
	wp_enqueue_style( 'common' );
	wp_enqueue_style( 'forms' );
	wp_enqueue_style( 'dashboard' );
	wp_enqueue_style( 'media' );
	wp_enqueue_style( 'admin-menu' );
	wp_enqueue_style( 'admin-bar' );
	wp_enqueue_style( 'nav-menus' );
	wp_enqueue_style( 'l10n' );
	wp_enqueue_style( 'buttons' );
	wp_enqueue_style( 'wp-edit-post' );
	wp_enqueue_style( 'wporg-pattern-creator-style' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );

/**
 * Bypass WordPress template system to load only our editor app.
 */
function inject_editor_template( $template ) {
	if ( ! should_load_creator() ) {
		return $template;
	}
	return __DIR__ . '/view/editor.php';
}
add_filter( 'template_include', __NAMESPACE__ . '\inject_editor_template' );
