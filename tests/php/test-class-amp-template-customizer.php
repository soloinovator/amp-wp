<?php
/**
 * Tests for AMP_Template_Customizer.
 *
 * @package AMP
 */

use AmpProject\AmpWP\Option;
use AmpProject\AmpWP\ReaderThemeLoader;
use AmpProject\AmpWP\Services;
use AmpProject\AmpWP\Tests\Helpers\AssertContainsCompatibility;

/**
 * Class Test_AMP_Template_Customizer
 *
 * @covers AMP_Template_Customizer
 */
class Test_AMP_Template_Customizer extends WP_UnitTestCase {

	use AssertContainsCompatibility;

	private $original_theme_directories;

	public static function setUpBeforeClass() {
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		return parent::setUpBeforeClass();
	}

	public function setUp() {
		parent::setUp();

		global $wp_theme_directories;
		$this->original_theme_directories = $wp_theme_directories;
		register_theme_directory( ABSPATH . 'wp-content/themes' );
		delete_site_transient( 'theme_roots' );
	}

	public function tearDown() {
		parent::tearDown();
		unset( $GLOBALS['wp_customize'], $GLOBALS['wp_scripts'], $GLOBALS['wp_styles'] );
		global $wp_theme_directories;
		$wp_theme_directories = $this->original_theme_directories;
		delete_site_transient( 'theme_roots' );
	}

	/**
	 * Get Customizer Manager.
	 *
	 * @param array $args Args.
	 * @return WP_Customize_Manager
	 */
	protected function get_customize_manager( $args = [] ) {
		global $wp_customize;
		$wp_customize = new WP_Customize_Manager( $args );
		return $wp_customize;
	}

	/** @covers AMP_Template_Customizer::init() */
	public function test_init_canonical() {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::STANDARD_MODE_SLUG );
		$this->assertTrue( amp_is_canonical() );

		add_theme_support( 'header-video', [ 'video' => [] ] );
		$wp_customize = $this->get_customize_manager();
		$wp_customize->register_controls();
		$header_video_setting          = $wp_customize->get_setting( 'header_video' );
		$external_header_video_setting = $wp_customize->get_setting( 'external_header_video' );
		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertInstanceOf( WP_Customize_Setting::class, $setting );
			$this->assertEquals( 'postMessage', $setting->transport );
		}

		$instance = AMP_Template_Customizer::init( $wp_customize );
		$this->assertEquals( 0, did_action( 'amp_customizer_init' ) );
		$this->assertFalse( has_action( 'customize_controls_enqueue_scripts', [ $instance, 'add_customizer_scripts' ] ) );

		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertEquals( 'refresh', $setting->transport );
		}
	}

	/** @covers AMP_Template_Customizer::init() */
	public function test_init_transitional() {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::TRANSITIONAL_MODE_SLUG );
		$this->assertFalse( amp_is_canonical() );

		add_theme_support( 'header-video', [ 'video' => [] ] );
		$wp_customize = $this->get_customize_manager();
		$wp_customize->register_controls();
		$header_video_setting          = $wp_customize->get_setting( 'header_video' );
		$external_header_video_setting = $wp_customize->get_setting( 'external_header_video' );
		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertInstanceOf( WP_Customize_Setting::class, $setting );
			$this->assertEquals( 'postMessage', $setting->transport );
		}

		$instance = AMP_Template_Customizer::init( $wp_customize );
		$this->assertEquals( 0, did_action( 'amp_customizer_init' ) );
		$this->assertFalse( has_action( 'customize_save_after', [ $instance, 'store_modified_theme_mod_setting_timestamps' ] ) );
		$this->assertFalse( has_action( 'customize_controls_enqueue_scripts', [ $instance, 'add_customizer_scripts' ] ) );

		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertEquals( 'postMessage', $setting->transport );
		}
	}

	/**
	 * @covers AMP_Template_Customizer::init()
	 * @covers AMP_Template_Customizer::register_legacy_ui()
	 * @covers AMP_Template_Customizer::register_legacy_settings()
	 * @covers AMP_Template_Customizer::set_refresh_setting_transport()
	 * @covers AMP_Template_Customizer::remove_cover_template_section()
	 * @covers AMP_Template_Customizer::remove_homepage_settings_section()
	 */
	public function test_init_legacy_reader() {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::READER_MODE_SLUG );
		$this->assertFalse( amp_is_canonical() );
		$this->assertTrue( amp_is_legacy() );

		add_theme_support( 'header-video', [ 'video' => [] ] );
		$wp_customize = $this->get_customize_manager();
		$wp_customize->register_controls();
		$wp_customize->add_section( 'cover_template_options', [] );
		$header_video_setting          = $wp_customize->get_setting( 'header_video' );
		$external_header_video_setting = $wp_customize->get_setting( 'external_header_video' );
		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertInstanceOf( WP_Customize_Setting::class, $setting );
			$this->assertEquals( 'postMessage', $setting->transport );
		}

		$instance = AMP_Template_Customizer::init( $wp_customize );
		$this->assertFalse( has_action( 'customize_save_after', [ $instance, 'store_modified_theme_mod_setting_timestamps' ] ) );
		$this->assertFalse( has_action( 'customize_controls_enqueue_scripts', [ $instance, 'add_customizer_scripts' ] ) );
		$this->assertEquals( 1, did_action( 'amp_customizer_init' ) );
		$this->assertEquals( 1, did_action( 'amp_customizer_register_settings' ) );
		$this->assertEquals( 1, did_action( 'amp_customizer_register_ui' ) );
		$this->assertInstanceOf( WP_Customize_Panel::class, $wp_customize->get_panel( AMP_Template_Customizer::PANEL_ID ) );
		$this->assertEquals( 10, has_action( 'customize_controls_print_footer_scripts', [ $instance, 'print_legacy_controls_templates' ] ) );
		$this->assertEquals( 10, has_action( 'customize_preview_init', [ $instance, 'init_legacy_preview' ] ) );
		$this->assertEquals( 10, has_action( 'customize_controls_enqueue_scripts', [ $instance, 'add_legacy_customizer_scripts' ] ) );

		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertEquals( 'postMessage', $setting->transport );
		}
		$this->assertInstanceOf( WP_Customize_Section::class, $wp_customize->get_section( 'cover_template_options' ) );
		$this->assertInstanceOf( WP_Customize_Section::class, $wp_customize->get_section( 'static_front_page' ) );
	}

	/**
	 * @covers AMP_Template_Customizer::init()
	 * @covers AMP_Template_Customizer::set_refresh_setting_transport()
	 * @covers AMP_Template_Customizer::remove_cover_template_section()
	 * @covers AMP_Template_Customizer::remove_homepage_settings_section()
	 */
	public function test_init_reader_theme_with_amp() {
		if ( ! wp_get_theme( 'twentynineteen' )->exists() || ! wp_get_theme( 'twentytwenty' )->exists() ) {
			$this->markTestSkipped();
		}

		switch_theme( 'twentynineteen' );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::READER_MODE_SLUG );
		AMP_Options_Manager::update_option( Option::READER_THEME, 'twentytwenty' );

		/** @var ReaderThemeLoader $reader_theme_loader */
		$reader_theme_loader = Services::get( 'reader_theme_loader' );

		$_GET[ amp_get_slug() ] = '1';
		$reader_theme_loader->override_theme();
		$this->assertTrue( $reader_theme_loader->is_theme_overridden() );

		$this->assertFalse( amp_is_canonical() );
		$this->assertFalse( amp_is_legacy() );

		add_theme_support( 'header-video', [ 'video' => [] ] );
		$wp_customize = $this->get_customize_manager();
		$wp_customize->register_controls();
		$wp_customize->add_section( 'cover_template_options', [] );
		$header_video_setting          = $wp_customize->get_setting( 'header_video' );
		$external_header_video_setting = $wp_customize->get_setting( 'external_header_video' );
		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertInstanceOf( WP_Customize_Setting::class, $setting );
			$this->assertEquals( 'postMessage', $setting->transport );
		}

		$instance = AMP_Template_Customizer::init( $wp_customize );
		$this->assertEquals( 10, has_action( 'customize_save_after', [ $instance, 'store_modified_theme_mod_setting_timestamps' ] ) );
		$this->assertEquals( 10, has_action( 'customize_controls_enqueue_scripts', [ $instance, 'add_customizer_scripts' ] ) );
		$this->assertEquals( 10, has_action( 'customize_controls_print_footer_scripts', [ $instance, 'render_setting_import_section_template' ] ) );
		$this->assertEquals( 0, did_action( 'amp_customizer_init' ) );
		$this->assertEquals( 0, did_action( 'amp_customizer_register_settings' ) );
		$this->assertEquals( 0, did_action( 'amp_customizer_register_ui' ) );
		$this->assertFalse( has_action( 'customize_controls_print_footer_scripts', [ $instance, 'print_legacy_controls_templates' ] ) );
		$this->assertFalse( has_action( 'customize_preview_init', [ $instance, 'init_legacy_preview' ] ) );
		$this->assertFalse( has_action( 'customize_controls_enqueue_scripts', [ $instance, 'add_legacy_customizer_scripts' ] ) );

		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertEquals( 'refresh', $setting->transport );
		}

		$this->assertNull( $wp_customize->get_section( 'cover_template_options' ) );
		$this->assertNull( $wp_customize->get_section( 'static_front_page' ) );
	}

	/**
	 * @covers AMP_Template_Customizer::init()
	 * @covers AMP_Template_Customizer::set_refresh_setting_transport()
	 * @covers AMP_Template_Customizer::remove_cover_template_section()
	 * @covers AMP_Template_Customizer::remove_homepage_settings_section()
	 */
	public function test_init_reader_theme_without_amp() {
		if ( ! wp_get_theme( 'twentynineteen' )->exists() || ! wp_get_theme( 'twentytwenty' )->exists() ) {
			$this->markTestSkipped();
		}

		switch_theme( 'twentynineteen' );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::READER_MODE_SLUG );
		AMP_Options_Manager::update_option( Option::READER_THEME, 'twentytwenty' );

		/** @var ReaderThemeLoader $reader_theme_loader */
		$reader_theme_loader = Services::get( 'reader_theme_loader' );

		$this->assertFalse( $reader_theme_loader->is_theme_overridden() );

		$this->assertFalse( amp_is_canonical() );
		$this->assertFalse( amp_is_legacy() );

		add_theme_support( 'header-video', [ 'video' => [] ] );
		$wp_customize = $this->get_customize_manager();
		$wp_customize->register_controls();
		$wp_customize->add_section( 'cover_template_options', [] );
		$header_video_setting          = $wp_customize->get_setting( 'header_video' );
		$external_header_video_setting = $wp_customize->get_setting( 'external_header_video' );
		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertInstanceOf( WP_Customize_Setting::class, $setting );
			$this->assertEquals( 'postMessage', $setting->transport );
		}

		$instance = AMP_Template_Customizer::init( $wp_customize );
		$this->assertEquals( 10, has_action( 'customize_save_after', [ $instance, 'store_modified_theme_mod_setting_timestamps' ] ) );

		$this->assertFalse( has_action( 'customize_controls_enqueue_scripts', [ $instance, 'add_customizer_scripts' ] ) );
		$this->assertFalse( has_action( 'customize_controls_print_footer_scripts', [ $instance, 'render_setting_import_section_template' ] ) );
		$this->assertEquals( 0, did_action( 'amp_customizer_init' ) );
		$this->assertEquals( 0, did_action( 'amp_customizer_register_settings' ) );
		$this->assertEquals( 0, did_action( 'amp_customizer_register_ui' ) );
		$this->assertFalse( has_action( 'customize_controls_print_footer_scripts', [ $instance, 'print_legacy_controls_templates' ] ) );
		$this->assertFalse( has_action( 'customize_preview_init', [ $instance, 'init_legacy_preview' ] ) );
		$this->assertFalse( has_action( 'customize_controls_enqueue_scripts', [ $instance, 'add_legacy_customizer_scripts' ] ) );

		foreach ( [ $header_video_setting, $external_header_video_setting ] as $setting ) {
			$this->assertEquals( 'postMessage', $setting->transport );
		}

		$this->assertInstanceOf( WP_Customize_Section::class, $wp_customize->get_section( 'cover_template_options' ) );
		$this->assertInstanceOf( WP_Customize_Section::class, $wp_customize->get_section( 'static_front_page' ) );
	}

	/** @covers AMP_Template_Customizer::init_legacy_preview() */
	public function test_init_legacy_preview_controls() {
		$wp_customize = $this->get_customize_manager();
		$instance     = AMP_Template_Customizer::init( $wp_customize );
		$instance->init_legacy_preview();
		$this->assertEquals( 10, has_action( 'amp_post_template_head', 'wp_no_robots' ) );
		$this->assertEquals( 10, has_action( 'amp_customizer_enqueue_preview_scripts', [ $instance, 'enqueue_legacy_preview_scripts' ] ) );
		$this->assertNull( $wp_customize->get_messenger_channel() );
	}

	/** @covers AMP_Template_Customizer::init_legacy_preview() */
	public function test_init_legacy_preview_iframe() {
		$wp_customize = $this->get_customize_manager( [ 'messenger_channel' => '123' ] );
		$wp_customize->start_previewing_theme();
		$instance = AMP_Template_Customizer::init( $wp_customize );
		$instance->init_legacy_preview();
		$this->assertEquals( 10, has_action( 'amp_post_template_head', 'wp_no_robots' ) );
		$this->assertEquals( 10, has_action( 'amp_customizer_enqueue_preview_scripts', [ $instance, 'enqueue_legacy_preview_scripts' ] ) );
		$this->assertEquals( '123', $wp_customize->get_messenger_channel() );

		$this->assertEquals( 10, has_action( 'amp_post_template_head', [ $wp_customize, 'customize_preview_loading_style' ] ) );
		$this->assertEquals( 10, has_action( 'amp_post_template_css', [ $instance, 'add_legacy_customize_preview_styles' ] ) );
		$this->assertEquals( 10, has_action( 'amp_post_template_head', [ $wp_customize, 'remove_frameless_preview_messenger_channel' ] ) );
		$this->assertEquals( 10, has_action( 'amp_post_template_footer', [ $instance, 'add_legacy_preview_scripts' ] ) );
	}

	/** @covers AMP_Template_Customizer::add_customizer_scripts() */
	public function test_add_customizer_scripts() {
		$instance = AMP_Template_Customizer::init( $this->get_customize_manager() );
		$instance->add_customizer_scripts();

		$this->assertTrue( wp_script_is( 'amp-customize-controls', 'enqueued' ) );
		$this->assertStringContains( 'amp-customize-controls.js', wp_scripts()->registered['amp-customize-controls']->src );
		$this->assertTrue( wp_style_is( 'amp-customizer', 'enqueued' ) );
		$this->assertStringContains( 'amp-customizer.css', wp_styles()->registered['amp-customizer']->src );
		$this->assertEquals( 0, did_action( 'amp_customizer_enqueue_scripts' ) );
	}

	/** @covers AMP_Template_Customizer::store_modified_theme_mod_setting_timestamps() */
	public function test_store_modified_theme_mod_setting_timestamps() {
		if ( ! wp_get_theme( 'twentytwenty' )->exists() ) {
			$this->markTestSkipped();
		}

		$menu_location = 'primary';
		register_nav_menu( $menu_location, 'Primary' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		switch_theme( 'twentytwenty' );
		$wp_customize = $this->get_customize_manager();
		$wp_customize->register_controls();
		$wp_customize->nav_menus->customize_register();
		$instance = AMP_Template_Customizer::init( $wp_customize );

		$option_setting    = $wp_customize->add_setting( 'some_option', [ 'type' => 'option' ] );
		$theme_mod_setting = $wp_customize->add_setting( 'some_theme_mod', [ 'type' => 'theme_mod' ] );
		$filter_setting    = $wp_customize->add_setting( new WP_Customize_Filter_Setting( $wp_customize, 'some_filter' ) );

		$custom_css_setting = $wp_customize->get_setting( sprintf( 'custom_css[%s]', get_stylesheet() ) );
		$this->assertInstanceOf( WP_Customize_Custom_CSS_Setting::class, $custom_css_setting );

		$nav_menu_location_setting = $wp_customize->get_setting( "nav_menu_locations[{$menu_location}]" );
		$this->assertInstanceOf( WP_Customize_Setting::class, $nav_menu_location_setting );

		// Ensure initial state.
		$this->assertFalse( get_theme_mod( AMP_Template_Customizer::THEME_MOD_TIMESTAMPS_KEY ) );

		// Ensure empty when no changes have been made.
		$instance->store_modified_theme_mod_setting_timestamps();
		$this->assertFalse( get_theme_mod( AMP_Template_Customizer::THEME_MOD_TIMESTAMPS_KEY ) );

		// Ensure updating an option does not cause the theme_mod to be updated.
		$wp_customize->set_post_value( $option_setting->id, 'foo' );
		$instance->store_modified_theme_mod_setting_timestamps();
		$this->assertFalse( get_theme_mod( AMP_Template_Customizer::THEME_MOD_TIMESTAMPS_KEY ) );

		// Ensure updating a "filter setting" does not cause the theme_mod to be updated.
		$wp_customize->set_post_value( $filter_setting->id, 'foo2' );
		$instance->store_modified_theme_mod_setting_timestamps();
		$this->assertFalse( get_theme_mod( AMP_Template_Customizer::THEME_MOD_TIMESTAMPS_KEY ) );

		// Ensure updating a theme_mod does cause the theme_mod to be updated.
		$wp_customize->set_post_value( $theme_mod_setting->id, 'bar' );
		$instance->store_modified_theme_mod_setting_timestamps();
		$this->assertEqualSets(
			[ $theme_mod_setting->id ],
			array_keys( get_theme_mod( AMP_Template_Customizer::THEME_MOD_TIMESTAMPS_KEY ) )
		);

		// Ensure that setting a nav menu updates the timestamps.
		$wp_customize->set_post_value( $nav_menu_location_setting->id, wp_create_nav_menu( 'Menu!' ) );
		$instance->store_modified_theme_mod_setting_timestamps();
		$this->assertEqualSets(
			[ $theme_mod_setting->id, $nav_menu_location_setting->id ],
			array_keys( get_theme_mod( AMP_Template_Customizer::THEME_MOD_TIMESTAMPS_KEY ) )
		);

		// Ensure that changing custom CSS also updates timestamps.
		$wp_customize->set_post_value( $custom_css_setting->id, 'body { color:red }' );
		$instance->store_modified_theme_mod_setting_timestamps();
		$this->assertEqualSets(
			[ $theme_mod_setting->id, $nav_menu_location_setting->id, 'custom_css' ],
			array_keys( get_theme_mod( AMP_Template_Customizer::THEME_MOD_TIMESTAMPS_KEY ) )
		);

		foreach ( get_theme_mod( AMP_Template_Customizer::THEME_MOD_TIMESTAMPS_KEY ) as $timestamp ) {
			$this->assertGreaterThanOrEqual( time(), $timestamp );
		}
	}

	/** @covers AMP_Template_Customizer::add_legacy_customizer_scripts() */
	public function test_add_legacy_customizer_scripts() {
		$instance = AMP_Template_Customizer::init( $this->get_customize_manager() );
		$instance->add_legacy_customizer_scripts();

		$this->assertTrue( wp_script_is( 'amp-customize-controls', 'enqueued' ) );
		$this->assertStringContains( 'amp-customize-controls-legacy.js', wp_scripts()->registered['amp-customize-controls']->src );
		$this->assertTrue( wp_style_is( 'amp-customizer', 'enqueued' ) );
		$this->assertStringContains( 'amp-customizer-legacy.css', wp_styles()->registered['amp-customizer']->src );
		$this->assertEquals( 1, did_action( 'amp_customizer_enqueue_scripts' ) );
	}

	/** @covers AMP_Template_Customizer::enqueue_legacy_preview_scripts() */
	public function test_enqueue_legacy_preview_scripts() {
		$post = self::factory()->post->create( [ 'post_type' => 'post' ] );
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::READER_MODE_SLUG );
		$this->assertTrue( amp_is_legacy() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->go_to( amp_get_permalink( $post ) );
		$instance = AMP_Template_Customizer::init( $this->get_customize_manager() );
		$instance->enqueue_legacy_preview_scripts();

		$this->assertTrue( wp_script_is( 'amp-customize-preview', 'enqueued' ) );
		$this->assertStringContains( 'amp-customize-preview-legacy.js', wp_scripts()->registered['amp-customize-preview']->src );

		$this->assertTrue( wp_script_is( 'amp-customize-preview', 'enqueued' ) );
	}

	/** @covers AMP_Template_Customizer::add_legacy_customize_preview_styles() */
	public function test_add_legacy_customize_preview_styles() {
		$instance = AMP_Template_Customizer::init( $this->get_customize_manager() );
		$output   = get_echo( [ $instance, 'add_legacy_customize_preview_styles' ] );
		$this->assertStringContains( '.screen-reader-text', $output );
	}

	/** @covers AMP_Template_Customizer::add_legacy_preview_scripts() */
	public function test_add_legacy_preview_scripts() {
		AMP_Options_Manager::update_option( Option::THEME_SUPPORT, AMP_Theme_Support::READER_MODE_SLUG );
		$this->assertTrue( amp_is_legacy() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$instance = AMP_Template_Customizer::init( $this->get_customize_manager() );
		get_echo( [ $instance, 'add_legacy_preview_scripts' ] );
		$this->assertTrue( wp_script_is( 'customize-selective-refresh', 'enqueued' ) );
		$this->assertEquals( 1, did_action( 'amp_customizer_enqueue_preview_scripts' ) );
	}

	/** @covers AMP_Template_Customizer::print_legacy_controls_templates() */
	public function test_print_legacy_controls_templates() {
		$instance = AMP_Template_Customizer::init( $this->get_customize_manager() );
		$output   = get_echo( [ $instance, 'print_legacy_controls_templates' ] );
		$this->assertStringContains( '<script type="text/html" id="tmpl-customize-amp-enabled-toggle">', $output );
	}
}
