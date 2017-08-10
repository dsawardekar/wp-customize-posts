<?php
/**
 * Customize Page Template Controller Class
 *
 * @package WordPress
 * @subpackage Customize
 */

/**
 * Class WP_Customize_Page_Template_Controller
 */
class WP_Customize_Page_Template_Controller extends WP_Customize_Postmeta_Controller {

	/**
	 * Meta key.
	 *
	 * @var string
	 */
	public $meta_key = '_wp_page_template';

	/**
	 * Post type support for the postmeta.
	 *
	 * @var string
	 */
	public $post_type_supports = 'page-attributes';

	/**
	 * Setting transport.
	 *
	 * @var string
	 */
	public $setting_transport = 'refresh';

	/**
	 * Default value.
	 *
	 * @var string
	 */
	public $default = 'default';

	/**
	 * Enqueue customize scripts.
	 */
	public function enqueue_customize_pane_scripts() {
		$handle = 'customize-page-template';
		wp_enqueue_script( $handle );
		$exports = array(
			'defaultPageTemplateChoices' => $this->get_page_template_choices(),
			'l10n' => array(
				'controlLabel' => __( 'Page Template', 'customize-posts' ),
			),
		);
		wp_add_inline_script( $handle, sprintf( 'CustomizePageTemplate.init( %s );', wp_json_encode( $exports ) ) );
	}

	/**
	 * Enqueue edit post scripts.
	 */
	public function enqueue_edit_post_scripts() {
		wp_enqueue_script( 'edit-post-preview-admin-page-template' );
	}

	/**
	 * Get page template choices.
	 *
	 * @return array
	 */
	public function get_page_template_choices() {
		$choices = array();
		$choices[] = array(
			'value' => 'default',
			'text' => __( '(Default)', 'customize-posts' ),
		);

		$queried_post_type = $this->get_queried_post_type();
		$current_theme     = wp_get_theme();

		if ( ! is_null( $queried_post_type ) ) {
			$page_templates = $current_theme->get_page_templates( null, $queried_post_type );
		} else {
			$page_templates = $current_theme->get_page_templates();
		}

		foreach ( $page_templates as $template_file => $template_name ) {
			$choices[] = array(
				'text' => $template_name,
				'value' => $template_file,
			);
		}

		return $choices;
	}

	/**
	 * Apply rudimentary sanitization of a file path for a generic setting instance.
	 *
	 * @see sanitize_meta()
	 *
	 * @param string $raw_path Path.
	 * @return string Path.
	 */
	public function sanitize_value( $raw_path ) {
		$path = $raw_path;
		$special_chars = array( '..', './', chr( 0 ) );
		$path = str_replace( $special_chars, '', $path );
		$path = trim( $path, '/' );
		return $path;
	}

	/**
	 * Sanitize (and validate) an input for a specific setting instance.
	 *
	 * @see update_metadata()
	 *
	 * @param string                        $page_template The value to sanitize.
	 * @param WP_Customize_Postmeta_Setting $setting       Setting.
	 * @return mixed|WP_Error Sanitized value or WP_Error if invalid valid.
	 */
	public function sanitize_setting( $page_template, WP_Customize_Postmeta_Setting $setting ) {
		$post = get_post( $setting->post_id );
		$post_type = $post->post_type;
		$page_templates = wp_get_theme()->get_page_templates( $post, $post_type );
		$has_setting_validation = method_exists( 'WP_Customize_Setting', 'validate' );

		if ( 'default' !== $page_template && ! isset( $page_templates[ $page_template ] ) ) {
			return $has_setting_validation ? new WP_Error( 'invalid_page_template', __( 'The page template is invalid.', 'customize-posts' ) ) : null;
		}
		return $page_template;
	}

	/**
	 * If WordPress 4.7+ all post types can have a page template. So
	 * we enable template control for all of them.
	 *
	 * @return array
	 */
	public function get_post_types_for_meta() {
		$post_types      = parent::get_post_types_for_meta();
		$current_version = $this->get_current_wp_version();

		if ( version_compare( $current_version, '4.7', '>=' ) ) {
			$all_post_types = get_post_types();
			$post_types = array_merge( $post_types, $all_post_types );
			array_unique( $post_types );
		}

		return $post_types;
	}

	/**
	 * Returns the current WordPress version after minor cleanup for
	 * version_compare.
	 *
	 * @return string
	 */
	public function get_current_wp_version() {
		global $wp_version;
		$current_version = $wp_version;
		$current_version = str_replace( '-src', '', $current_version );
		$current_version = str_replace( '-beta', '', $current_version );

		return $current_version;
	}

	/**
	 * Returns the current post type name from the preview url.
	 *
	 * @return string|null
	 */
	public function get_queried_post_type() {
		if ( ! empty( $_GET['url'] ) ) {
			$url        = sanitize_text_field( $_GET['url'] );
			$url_params = parse_url( $url, PHP_URL_QUERY );

			parse_str( $url_params, $parsed_params );

			if ( ! empty( $parsed_params['post_type'] ) ) {
				$post_type = $parsed_params['post_type'];

				if ( post_type_exists( $post_type ) ) {
					return $post_type;
				} else {
					return null;
				}
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

}
