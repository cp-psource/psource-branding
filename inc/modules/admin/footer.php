<?php
/**
 * PSToolkit Admin Footer class.
 *
 * Class to handle admin footer content.
 *
 * @package PSToolkit
 * @subpackage AdminArea
 */
if ( ! class_exists( 'PSToolkit_Admin_Footer' ) ) {

	/**
	 * Class PSToolkit_Admin_Footer.
	 */
	class PSToolkit_Admin_Footer extends PSToolkit_Helper {

		/**
		 * Module option name.
		 *
		 * @var string $option_name
		 */
		protected $option_name = 'ub_admin_footer';

		/**
		 * constructor.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'pstoolkit_settings_admin_footer_text', array( $this, 'admin_options_page' ) );
			add_filter( 'pstoolkit_settings_admin_footer_text_process', array( $this, 'update' ), 10, 1 );
			// Remove all the remaining filters for the admin footer so that they don't mess the footer up.
			remove_all_filters( 'admin_footer_text' );
			add_filter( 'admin_footer_text', array( $this, 'output' ), 1, 1 );
			add_filter( 'update_footer', '__return_empty_string', 99 );
			add_action( 'admin_head', array( $this, 'add_css_for_footer' ) );
			add_action( 'init', array( $this, 'upgrade_options' ) );
		}

		/**
		 * Change #wpfooter position to static.
		 *
		 * @since 1.9.9
		 */
		public function add_css_for_footer() {
			$admin_footer_text = $this->get_value( 'footer', 'content' );
			if ( empty( $admin_footer_text ) ) {
				return;
			}
			echo '<style type="text/css">#wpwrap #wpfooter {position:static; display: block;}</style>';
		}

		/**
		 * Output the footer content to footer.
		 *
		 * @param string $footer_text Footer content.
		 *
		 * @return string
		 */
		public function output( $footer_text ) {
			pstoolkit_update_option( 'pstoolkit_original_admin_footer', $footer_text );
			$content = $this->get_value( 'footer', 'content' );
			if ( is_null( $content ) ) {
				return $footer_text;
			}
			?>
			<style type="text/css">
				#wpfooter ul {
					list-style: disc inside;
				}
			</style>
			<?php
			$value = $this->get_value( 'footer', 'content_meta', $content );
			return do_shortcode( $value );
		}

		/**
		 * Set options for the module admin page.
		 *
		 * @since 2.1.0
		 */
		protected function set_options() {
			$current_value  = $this->get_value( 'footer', 'content' );
			$original_value = pstoolkit_get_option( 'pstoolkit_original_admin_footer' );
			$default_value  = '';
			if ( false === $current_value ) {
				// remove old false value
				$this->delete_value( 'footer', 'content' );
			}
			if ( ! is_null( $current_value ) && false !== $current_value ) {
				$default_value = $current_value;
			} elseif ( $original_value ) {
				$default_value = $original_value;
			}
			$options       = array(
				'footer' => array(
					'title'       => __( 'Admin Footer Text', 'ub' ),
					'description' => __( 'Zeige in der Fußzeile jeder Administrationsseite einen benutzerdefinierten Text an.', 'ub' ), 
					'fields'      => array(
						'content' => array(
							'default'     => $default_value,
							'type'        => 'wp_editor',
							'editor'      => array(
								'textarea_rows' => 20,
							),
							'placeholder' => esc_html__( 'Füge hier Deinen benutzerdefinierten Fußzeilentext hinzu...', 'ub' ),
						),
					),
				),
			);
			$this->options = $options;
		}

		/**
		 * Upgrade options from old structure.
		 *
		 * @since 2.1.0
		 */
		public function upgrade_options() {
			$value = $this->get_value();
			if ( ! is_array( $value ) ) {
				$data = array(
					'footer' => array(
						'content' => $value,
					),
				);
				$this->update_value( $data );
			}
			$value = pstoolkit_get_option( 'admin_footer_text' );
			if ( ! empty( $value ) ) {
				$this->update_value( $value );
				pstoolkit_delete_option( 'admin_footer_text' );
			}
		}
	}
}
new PSToolkit_Admin_Footer();
