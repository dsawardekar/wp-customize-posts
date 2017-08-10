<?php
/**
 * Tests for WP_Customize_Page_Template_Controller
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class Test_WP_Customize_Page_Template_Controller
 */
class Test_WP_Customize_Page_Template_Controller extends WP_UnitTestCase {

	/**
	 * Manager.
	 *
	 * @var WP_Customize_Manager
	 */
	protected $wp_customize;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function setUp() {
		parent::setUp();
		$this->user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );

		require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
		// @codingStandardsIgnoreStart
		$GLOBALS['wp_customize'] = new WP_Customize_Manager();
		// @codingStandardsIgnoreStop
		$this->wp_customize = $GLOBALS['wp_customize'];
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	function tearDown() {
		$this->wp_customize = null;
		unset( $GLOBALS['current_screen'] );
		unset( $GLOBALS['screen'] );
		unset( $_POST['customized'] );
		unset( $GLOBALS['wp_customize'] );

		$_GET = array();

		parent::tearDown();
	}

	/**
	 * Test construct().
	 *
	 * @see WP_Customize_Postmeta_Controller::__construct()
	 */
	public function test_construct() {
		$controller = new WP_Customize_Page_Template_Controller();
		$this->assertEquals( '_wp_page_template', $controller->meta_key );
		$this->assertEquals( 'page-attributes', $controller->post_type_supports );
		$this->assertEquals( 'refresh', $controller->setting_transport );
		$this->assertEquals( 'default', $controller->default );
	}

	/**
	 * Test enqueue_customize_scripts().
	 *
	 * @see WP_Customize_Page_Template_Controller::enqueue_customize_pane_scripts()
	 */
	public function test_enqueue_customize_scripts() {
		$handle = 'customize-page-template';
		$controller = new WP_Customize_Page_Template_Controller();
		$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );
		$controller->enqueue_customize_pane_scripts();
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );

		$data = wp_scripts()->get_data( $handle, 'after' );
		$this->assertNotEmpty( preg_match( '/({.*})/', join( '', $data ), $matches ) );
		$exported = json_decode( $matches[1], true );
		$this->assertInternalType( 'array', $exported );
		$this->assertArrayHasKey( 'defaultPageTemplateChoices', $exported );
		$this->assertArrayHasKey( 'l10n', $exported );
		$this->assertArrayHasKey( 'controlLabel', $exported['l10n'] );

		$after = wp_scripts()->get_data( $handle, 'after' );
		$this->assertInternalType( 'array', $after );
		$this->assertContains( 'CustomizePageTemplate.init(', array_pop( $after ) );
	}

	/**
	 * Test enqueue_edit_post_scripts().
	 *
	 * @see WP_Customize_Page_Template_Controller::enqueue_admin_scripts()
	 * @see WP_Customize_Page_Template_Controller::enqueue_edit_post_scripts()
	 */
	public function test_enqueue_edit_post_scripts() {
		set_current_screen( 'post' );
		$handle = 'edit-post-preview-admin-page-template';
		$controller = new WP_Customize_Page_Template_Controller();
		$this->assertFalse( wp_script_is( $handle, 'enqueued' ) );
		$controller->enqueue_admin_scripts();
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
	}

	/**
	 * Test get_page_template_choices().
	 *
	 * @see WP_Customize_Page_Template_Controller::get_page_template_choices()
	 */
	public function test_get_page_template_choices() {
		switch_theme( 'twentytwelve' );
		$controller = new WP_Customize_Page_Template_Controller();
		$choices = $controller->get_page_template_choices();
		$this->assertCount( 3, $choices );
		foreach ( $choices as $choice ) {
			$this->assertArrayHasKey( 'text', $choice );
			$this->assertArrayHasKey( 'value', $choice );
		}
	}

	/**
	 * Test sanitize_value().
	 *
	 * @see WP_Customize_Page_Template_Controller::sanitize_value()
	 */
	public function test_sanitize_value() {
		$controller = new WP_Customize_Page_Template_Controller();
		$this->assertEquals( 'evil', $controller->sanitize_value( '../evil' ) );
		$this->assertEquals( 'bad', $controller->sanitize_value( './bad/' . chr( 0 ) ) );
	}

	/**
	 * Test sanitize_setting().
	 *
	 * @see WP_Customize_Page_Template_Controller::sanitize_setting()
	 */
	public function test_sanitize_setting() {
		switch_theme( 'twentytwelve' );
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		$controller = new WP_Customize_Page_Template_Controller();
		$post = get_post( $this->factory()->post->create( array( 'post_type' => 'page' ) ) );
		$setting_id = WP_Customize_Postmeta_Setting::get_post_meta_setting_id( $post, $controller->meta_key );
		$setting = new WP_Customize_Postmeta_Setting( $this->wp_customize, $setting_id );

		$value = 'default';
		$this->assertEquals( $value, $controller->sanitize_setting( $value, $setting ) );

		$value = 'page-templates/full-width.php';
		$this->assertEquals( $value, $controller->sanitize_setting( $value, $setting ) );

		$value = '../page-templates/bad.php';
		if ( $has_setting_validation ) {
			$sanitized = $controller->sanitize_setting( $value, $setting );
			$this->assertInstanceOf( 'WP_Error', $sanitized );
			$this->assertEquals( 'invalid_page_template', $sanitized->get_error_code() );
		} else {
			$this->assertNull( $controller->sanitize_setting( $value, $setting ) );
		}
	}

	public function test_it_knows_current_wordpress_version() {
		$controller = new WP_Customize_Page_Template_Controller();

		global $wp_version;

		$actual = $controller->get_current_wp_version();
		$this->assertContains( $actual, $wp_version );
	}

	public function test_it_can_cleanup_src_wordpress_version() {
		$controller = new WP_Customize_Page_Template_Controller();

		global $wp_version;
		$wp_version = '4.5.0-src';

		$actual = $controller->get_current_wp_version();
		$this->assertEquals( '4.5.0', $actual );
	}

	public function test_it_can_cleanup_beta_wordpress_version() {
		$controller = new WP_Customize_Page_Template_Controller();

		global $wp_version;
		$wp_version = '4.5.0-beta';

		$actual = $controller->get_current_wp_version();
		$this->assertEquals( '4.5.0', $actual );
	}

	public function test_it_does_not_have_queried_post_type_if_absent_in_url() {
		$controller = new WP_Customize_Page_Template_Controller();

		$actual = $controller->get_queried_post_type();
		$this->assertNull( $actual );
	}

	public function test_it_does_not_have_queried_post_type_if_absent_in_url_params() {
		$_GET['url'] = home_url( '/wp-admin/customize.php?url=' . urlencode( home_url( '?p=1' ) ) );

		$controller = new WP_Customize_Page_Template_Controller();

		$actual = $controller->get_queried_post_type();
		$this->assertNull( $actual );
	}

	public function test_it_does_not_have_queried_post_type_if_not_registered() {
		$_GET['url'] = home_url( '/wp-admin/customize.php?url=' . urlencode( get_permalink( 1 ) . '?post_type=unknown' ) );

		$controller = new WP_Customize_Page_Template_Controller();

		$actual = $controller->get_queried_post_type();
		$this->assertNull( $actual );
	}

	public function test_it_does_have_queried_post_type_if_present_in_url_params() {
		$_GET['url'] = home_url( '/wp-admin/customize.php?url=' . home_url( '?p=1&post_type=page' ) );

		$controller = new WP_Customize_Page_Template_Controller();

		$actual = $controller->get_queried_post_type();
		$this->assertEquals( 'page', $actual );
	}

	public function test_it_does_not_include_cpts_for_meta_if_wp_less_than_4_7() {
		global $wp_version;
		$wp_version = '4.6.0';
		$controller = new WP_Customize_Page_Template_Controller();

		$actual = $controller->get_post_types_for_meta();
		$this->assertEquals( array( 'page' ), $actual );
	}

	public function test_it_does_include_cpts_for_meta_if_wp_4_7() {
		register_post_type( 'lorem', array() );
		register_post_type( 'ipsum', array() );
		register_post_type( 'dolor', array() );

		global $wp_version;
		$wp_version = '4.7';
		$controller = new WP_Customize_Page_Template_Controller();

		$actual = $controller->get_post_types_for_meta();
		$this->assertContains( 'lorem', $actual );
		$this->assertContains( 'ipsum', $actual );
		$this->assertContains( 'dolor', $actual );
		$this->assertContains( 'page', $actual );
		$this->assertContains( 'post', $actual );
	}

}
