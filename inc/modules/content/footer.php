<?php
/**
 * PSToolkit Content Footer class.
 *
 * @package PSToolkit
 * @subpackage Front-end
 */
if ( ! class_exists( 'PSToolkit_Content_Footer' ) ) {

	/**
	 * Class PSToolkit_Content_Footer.
	 */
	class PSToolkit_Content_Footer extends PSToolkit_Helper {

		/**
		 * Setting option name.
		 *
		 * @var string
		 */
		protected $option_name = 'ub_global_footer_content';

		/**
		 * Constructor for PSToolkit_Content_Footer.
		 *
		 * Register all hooks for the module.
		 */
		public function __construct() {
			parent::__construct();
			$this->module = 'content-footer';
			add_filter( 'pstoolkit_settings_content_footer', array( $this, 'admin_options_page' ) );
			add_filter( 'pstoolkit_settings_content_footer_process', array( $this, 'update' ), 10, 1 );
			add_action( 'wp_footer', array( $this, 'output' ) );
			add_filter( 'pstoolkit_options_names', array( $this, 'add_legacy_options_names' ) );
			/**
			 * Try to get network-wide value
			 *
			 * @since 3.2.0
			 */
			add_filter( 'ub_get_option-' . $this->option_name, array( $this, 'get_network_value' ), 10, 3 );
		}

		/**
		 * Add legacy option names to delete.
		 *
		 * @param array $options Options array.
		 *
		 * @since 2.1.0
		 *
		 * @return array $options
		 */
		public function add_legacy_options_names( $options ) {
			$keys = array( 'content', 'bgcolor', 'fixedheight', 'themefooter', 'shortcodes' );
			foreach ( $keys as $key ) {
				$options[] = 'global_footer_' . $key;
				$options[] = 'global_footer_main_' . $key;
			}
			return $options;
		}

		/**
		 * Get options in section.
		 *
		 * @param string $prefix Prefix.
		 *
		 * @return array
		 */
		private function get_section_options( $prefix ) {
			return array(
				'content'       => array(
					'type'        => 'wp_editor',
					'placeholder' => esc_html__( 'Hier kannst Du Fußzeileninhalte schreiben…', 'ub' ),
					'accordion'   => array(
						'begin' => true,
						'end'   => true,
						'title' => __( 'Inhalt', 'ub' ),
					),
				),
				'height'        => array(
					'label'        => __( 'Höhe', 'ub' ),
					'after_label'  => __( 'px', 'ub' ),
					'type'         => 'number',
					'min'          => 0,
					'default'      => 50,
					'accordion'    => array(
						'begin' => true,
						'title' => __( 'Design', 'ub' ),
					),
					'master'       => $this->get_name( $prefix . '-height' ),
					'master-value' => 'custom',
					'display'      => 'sui-tab-content',
				),
				'height_status' => array(
					'label'       => __( 'Höhe', 'ub' ),
					'description' => __( 'Lasse Deinen Inhalt die Höhe definieren oder lege eine feste benutzerdefinierte Höhe für Fußzeileninhalte fest.', 'ub' ),
					'type'        => 'sui-tab',
					'options'     => array(
						'auto'   => __( 'Automatisch', 'ub' ),
						'custom' => __( 'Benutzerdefiniert', 'ub' ),
					),
					'default'     => 'auto',
					'slave-class' => $this->get_name( $prefix . '-height' ),
				),
				'color'         => array(
					'label'        => __( 'Text', 'ub' ),
					'type'         => 'color',
					'master'       => $this->get_name( $prefix . '-color' ),
					'master-value' => 'custom',
					'display'      => 'sui-tab-content',
					'default'      => '#000',
				),
				'background'    => array(
					'label'        => __( 'Hintergrund', 'ub' ),
					'type'         => 'color',
					'master'       => $this->get_name( $prefix . '-color' ),
					'master-value' => 'custom',
					'display'      => 'sui-tab-content',
					'default'      => '#fff',
				),
				'color_status'  => array(
					'label'       => __( 'Farben', 'ub' ),
					'description' => __( 'Du kannst das Standardfarbschema verwenden oder es an Dein Theme anpassen.', 'ub' ),
					'type'        => 'sui-tab',
					'options'     => array(
						'default' => __( 'Standard', 'ub' ),
						'custom'  => __( 'Benutzerdefinierte Farben', 'ub' ),
					),
					'default'     => 'default',
					'slave-class' => $this->get_name( $prefix . '-color' ),
					'accordion'   => array(
						'end' => true,
					),
				),
				'theme_footer'  => array(
					'type'        => 'sui-tab',
					'label'       => __( 'In den Theme-Footer integrieren', 'ub' ),
					'description' => __( 'Aktiviere diese Option, um den Inhaltsblock der Fußzeile innerhalb des Fußzeilenelements des Themes zu platzieren.', 'ub' ),
					'options'     => array(
						'off' => __( 'Deaktivieren', 'ub' ),
						'on'  => __( 'Aktivieren', 'ub' ),
					),
					'default'     => 'on',
					'accordion'   => array(
						'begin'   => true,
						'title'   => __( 'Einstellungen', 'ub' ),
						'classes' => array(
							'body' => array(
								'pstoolkit-content-settings',
							),
						),
					),
				),
				'shortcodes'    => array(
					'type'        => 'sui-tab',
					'label'       => __( 'Shortcodes analysieren', 'ub' ),
					'description' => __( 'Sei vorsichtig, das Parsen von Shortcodes kann die Benutzeroberfläche des Themes beschädigen.', 'ub' ),
					'options'     => array(
						'off' => __( 'Deaktivieren', 'ub' ),
						'on'  => __( 'Aktivieren', 'ub' ),
					),
					'default'     => 'off',
					'accordion'   => array(
						'end' => true,
					),
				),
			);
		}

		/**
		 * Set options.
		 *
		 * @since 2.1.0
		 */
		protected function set_options() {
			$options = array(
				'content'  => array(
					'title'       => __( 'Inhalt', 'ub' ),
					'description' => __( 'Füge jeden gewünschten Inhalt in die Fußzeile jeder Seite Deiner Webseite ein.', 'ub' ),
					'fields'      => array(
						'content' => array(
							'type'        => 'wp_editor',
							'label'       => __( 'Inhalt', 'ub' ),
							'placeholder' => esc_html__( 'Hier kannst Du Fußzeileninhalte schreiben...', 'ub' ),
						),
					),
				),
				'design'   => array(
					'title'       => __( 'Design', 'ub' ),
					'description' => __( 'Passe das Design des Fußzeileninhalts nach Deinen Wünschen an.', 'ub' ),
					'fields'      => array(
						'height'        => array(
							'label'        => __( 'Höhe', 'ub' ),
							'after_label'  => __( 'px', 'ub' ),
							'type'         => 'number',
							'min'          => 0,
							'default'      => 50,
							'master'       => $this->get_name( 'height' ),
							'master-value' => 'custom',
							'display'      => 'sui-tab-content',
						),
						'height_status' => array(
							'label'       => __( 'Höhe', 'ub' ),
							'description' => __( 'Lasse Deinen Inhalt die Höhe definieren oder lege eine feste benutzerdefinierte Höhe für Fußzeileninhalte fest.', 'ub' ),
							'type'        => 'sui-tab',
							'options'     => array(
								'auto'   => __( 'Automatisch', 'ub' ),
								'custom' => __( 'Benutzerdefiniert', 'ub' ),
							),
							'default'     => 'auto',
							'slave-class' => $this->get_name( 'height' ),
						),
						'color'         => array(
							'label'        => __( 'Text', 'ub' ),
							'type'         => 'color',
							'master'       => $this->get_name( 'color' ),
							'master-value' => 'custom',
							'display'      => 'sui-tab-content',
							'default'      => '#000',
						),
						'background'    => array(
							'label'        => __( 'Hintergrund', 'ub' ),
							'type'         => 'color',
							'master'       => $this->get_name( 'color' ),
							'master-value' => 'custom',
							'display'      => 'sui-tab-content',
							'default'      => '#fff',
						),
						'color_status'  => array(
							'label'       => __( 'Farben', 'ub' ),
							'description' => __( 'Du kannst das Standardfarbschema verwenden oder es an Dein Theme anpassen.', 'ub' ),
							'type'        => 'sui-tab',
							'options'     => array(
								'default' => __( 'Standard', 'ub' ),
								'custom'  => __( 'Benutzerdefinierte Farben', 'ub' ),
							),
							'default'     => 'default',
							'slave-class' => $this->get_name( 'color' ),
						),
					),
				),
				'settings' => array(
					'title'       => __( 'Einstellungen', 'ub' ),
					'description' => __( 'Diese bieten zusätzliche Konfigurationsoptionen für den Fußzeileninhalt.', 'ub' ),
					'fields'      => array(
						'theme_footer' => array(
							'type'        => 'sui-tab',
							'label'       => __( 'In den Theme-Footer integrieren', 'ub' ),
							'description' => __( 'Aktiviere diese Option, um den Inhaltsblock der Fußzeile innerhalb des Fußzeilenelements des Themes zu platzieren.', 'ub' ),
							'options'     => array(
								'off' => __( 'Deaktivieren', 'ub' ),
								'on'  => __( 'Aktivieren', 'ub' ),
							),
							'default'     => 'on',
						),
						'shortcodes'   => array(
							'type'        => 'sui-tab',
							'label'       => __( 'Shortcodes analysieren', 'ub' ),
							'description' => __( 'Sei vorsichtig, das Parsen von Shortcodes kann die Benutzeroberfläche des Themes beschädigen.', 'ub' ),
							'options'     => array(
								'off' => __( 'Deaktivieren', 'ub' ),
								'on'  => __( 'Aktivieren', 'ub' ),
							),
							'default'     => 'off',
						),
					),
				),
			);

			/**
			 * On multisite if we have existing options and they are structured in the old (deprecated)
			 * way, display the old settings interface to match
			 */
			if (
				$this->is_network && is_network_admin()
				&& $this->has_deprecated_structure( pstoolkit_get_option( $this->option_name ) )
			) {
				$options = array(
					'main'            => array(
						'title'       => __( 'Hauptwebseite', 'ub' ),
						'description' => __( 'Füge beliebige Inhalte in die Fußzeile der Hauptseite Deines Netzwerks ein.', 'ub' ),
						'show-as'     => 'accordion',
						'show-reset'  => true,
						'fields'      => $this->get_section_options( 'main' ),
					),
					// Sub section one.
					'subsites_option' => array(
						'network-only' => true,
						'title'        => __( 'Unterwebseiten', 'ub' ),
						'description'  => __( 'Füge beliebige Inhalte in die Fußzeile aller Unterwebseiten Deines Netzwerks ein.', 'ub' ),
						'fields'       => array(
							'different' => array(
								'type'    => 'sui-tab',
								'options' => array(
									'off' => __( 'Gleich wie Hauptwebseite', 'ub' ),
									'on'  => __( 'Füge unterschiedliche Inhalte ein', 'ub' ),
								),
								'default' => 'on',
								'classes' => 'ub-footer-subsites-toggle',
							),
						),
						'sub_section'  => 'start',
					),
					// Sub section two.
					'subsites'        => array(
						'show-as'     => 'accordion',
						'show-reset'  => true,
						'fields'      => $this->get_section_options( 'subsites' ),
						'sub_section' => 'end',
						'accordion'   => array(
							'classes' => array( 'ub-footer-subsites' ),
						),
					),
				);
				// Make sub site options hidden if disabled.
				$subsites_option = $this->get_value( 'subsites_option' );
				if ( isset( $subsites_option['different'] ) && 'off' === $subsites_option['different'] ) {
					$options['subsites']['accordion']['classes'][] = 'hidden';
				}
			} else {
				unset( $options['content']['fields']['content']['label'] );
			}
			$this->options = $options;
		}

		/**
		 * Output common helper.
		 *
		 * @param string/boolean $key Key, false for single site
		 *
		 * @access private
		 * @since  2.1.0
		 */
		private function output_content( $key ) {
			$content   = '';
			$is_single = false === $key;
			/**
			 * get settings
			 */
			if ( $is_single ) {
				$key = 'settings';
			}
			$value            = $this->get_value( $key, 'shortcodes', 'off' );
			$parse_shortcodes = 'on' === $value;
			$value            = $this->get_value( $key, 'theme_footer', 'off' );
			$use_theme_footer = 'on' === $value;
			/**
			 * get content
			 */
			if ( $is_single ) {
				$key = 'content';
			}
			// Try content.
			$value = $this->get_value( $key, 'content', false );
			if ( ! empty( $value ) ) {
				$content = $value;
				if ( ! empty( $content ) ) {
					$filters = array( 'wptexturize', 'convert_smilies', 'convert_chars', 'wpautop' );
					foreach ( $filters as $filter ) {
						$content = apply_filters( $filter, $content );
					}
				}
			}
			// At least - check.
			if ( empty( $content ) ) {
				return array();
			}
			/**
			 * Parese shortcodes if it is needed.
			 */
			if ( $parse_shortcodes ) {
				$content = do_shortcode( $content );
			}
			/**
			 * get design
			 */
			if ( $is_single ) {
				$key = 'design';
			}
			// Style: color & background
			$style = '';
			$value = $this->get_value( $key, 'color_status', false );
			if ( 'custom' === $value ) {
				$value = $this->get_value( $key, 'color', false );
				if ( ! empty( $value ) ) {
					$style .= $this->css_color( $value );
				}
				$value = $this->get_value( $key, 'background', false );
				if ( ! empty( $value ) ) {
					$style .= $this->css_background_color( $value );
				}
			}
			// Style: custom height
			$value = $this->get_value( $key, 'height_status', '' );
			if ( 'custom' === $value ) {
				$value = $this->get_value( $key, 'height', false );
				if ( ! empty( $value ) ) {
					$style .= $this->css_height( $value );
					$style .= 'overflow: hidden;';
				}
			}
			/**
			 * return
			 */
			return array(
				'style'            => preg_replace( '/[\r\n]/', '', $style ),
				'use_theme_footer' => $use_theme_footer,
				'content'          => $content,
			);
		}

		/**
		 * for single site
		 *
		 * @since 1.0.0
		 */
		private function output_content_for_single() {
			return $this->output_content( false );
		}

		/**
		 * Output the custom content.
		 *
		 * @since 2.0.0
		 */
		public function output() {
			$data = $this->output_content_for_single();
			if ( ! isset( $data['content'] ) || empty( $data['content'] ) ) {
				return;
			}
			/**
			 * JavaScript template
			 */
			$template    = sprintf( '/front-end/modules/%s/js-output', $this->module );
			$data['id']  = $this->get_name( 'js' );
			$data['tag'] = $data['use_theme_footer'] ? 'footer' : 'body';
			$this->render( $template, $data );
		}

		private function has_deprecated_structure( $options_array ) {
			return isset( $options_array['main'] )
				   && isset( $options_array['subsites'] );
		}

		/**
		 * Convert from network-wide value into sub-site value.
		 *
		 * @since 3.2.0
		 */
		public function get_network_value( $value, $option, $default ) {
			if ( ! $this->is_network ) {
				return $value;
			}
			if ( is_network_admin() ) {
				return $value;
			}
			if ( ! $this->has_deprecated_structure( $value ) ) {
				return $value;
			}
			$key      = is_main_site() || pstoolkit_get_array_value( $value, array( 'subsites_option', 'different' ) ) !== 'on'
				? 'main'
				: 'subsites';
			$skeleton = array(
				'content'  => array(),
				'design'   => array(),
				'settings' => array(),
			);
			$value    = wp_parse_args( $value[ $key ], $skeleton );
			if ( isset( $value['content'] ) ) {
				$value['content'] = array(
					'content' => $value['content'],
				);
			}
			$map = array(
				'content'  => array(
					'content_meta',
				),
				'design'   => array(
					'height_status',
					'height',
					'color',
					'color_status',
					'background',
				),
				'settings' => array(
					'theme_footer',
					'shortcodes',
				),
			);
			foreach ( $map as $group => $data ) {
				foreach ( $data as $key ) {
					if ( isset( $value[ $key ] ) ) {
						$value[ $group ][ $key ] = $value[ $key ];
						unset( $value[ $key ] );
					}
				}
			}
			return $value;
		}
	}
}

new PSToolkit_Content_Footer();
