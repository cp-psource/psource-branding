<?php
/**
 * PSToolkit Text Replacement class.
 *
 * @package PSToolkit
 * @subpackage Utilites
 */
if ( ! class_exists( 'PSToolkit_Text_Replacement' ) ) {
	class PSToolkit_Text_Replacement extends PSToolkit_Helper {

		protected $option_name = 'ub_text_replacement';

		private $re = array();

		public function __construct() {
			parent::__construct();
			$this->module = 'text-replacement';
			pstoolkit_get_uba_object();
			/**
			 * hooks
			 */
			add_filter( 'pstoolkit_settings_text_replacement', array( $this, 'admin_options_page' ) );
			add_filter( 'pstoolkit_settings_text_replacement_process', array( $this, 'update' ) );
			add_filter( 'gettext', array( $this, 'replace_text' ), 10, 3 );
			add_filter( 'gettext_with_context', array( $this, 'replace_gettext_with_context' ), 10, 4 );
			add_filter( 'pstoolkit_settings_text_replacement_after_title', array( $this, 'add_new_button' ) );
			/**
			 * Add settings button.
			 *
			 * @since 1.0.0
			 */
			add_filter( 'pstoolkit_settings_after_box_title', array( $this, 'add_button_after_title' ), 10, 2 );
			/**
			 * Add dialog
			 *
			 * @since 3.0,0
			 */
			add_filter( 'pstoolkit_get_module_content', array( $this, 'add_modal' ), 10, 2 );
			/**
			 * Disable button "Save Changes".
			 *
			 * @since 1.0.0
			 */
			add_filter( 'pstoolkit_settings_panel_show_submit', array( $this, 'disable_save_changes' ), 10, 2 );
			/**
			 * Handla AJAX actions
			 *
			 * @since 1.0.0
			 */
			add_action( 'wp_ajax_pstoolkit_text_replacement_save', array( $this, 'ajax_save' ) );
			add_action( 'wp_ajax_pstoolkit_text_replacement_delete', array( $this, 'ajax_delete' ) );
			add_action( 'wp_ajax_pstoolkit_text_replacement_delete_bulk', array( $this, 'ajax_delete_bulk' ) );
			/**
			 * upgrade options
			 *
			 * @since 1.0.0
			 */
			add_action( 'init', array( $this, 'upgrade_options' ) );
			/**
			 * Single item delete
			 */
			add_filter( 'pstoolkit_dialog_delete_attr', array( $this, 'dialog_delete_attr_filter' ), 10, 3 );
		}

		/**
		 * Upgrade option
		 *
		 * @since 1.0.0
		 */
		public function upgrade_options() {
			$value = $this->get_value();
			if (
				isset( $value['plugin_version'] )
				&& -1 !== version_compare( $value['plugin_version'], $this->build )
			) {
				return;
			}
			$value = pstoolkit_get_option( 'translation_table' );
			if ( empty( $value ) ) {
				return;
			}
			$list = array();
			foreach ( $value as $id => $one ) {
				$one['scope'] = 'both';
				$one['id']    = $id;
				/**
				 * scope
				 */
				if ( isset( $one['admin_front'] ) ) {
					$one['scope'] = $one['admin_front'];
					unset( $one['admin_front'] );
				}
				/**
				 * ignorecase
				 */
				$v = 0;
				if ( isset( $one['ignorecase'] ) ) {
					$v = intval( $one['ignorecase'] );
				}
				$one['ignorecase'] = $v ? 'insensitive' : 'sensitive';
				/**
				 * exclude_url
				 */
				$v = 0;
				if ( isset( $one['exclude_url'] ) ) {
					$v = intval( $one['exclude_url'] );
				}
				$one['exclude_url'] = $v ? 'exclude' : 'include';
				/**
				 * regexp
				 */
				$one['re']   = $this->calculate_regexp( $one );
				$list[ $id ] = $one;
			}
			$data = array( 'list' => $list );
			$this->update_value( $data );
			pstoolkit_delete_option( 'translation_table' );
		}

		public function set_options() {
			$options       = array(
				'list' => array(
					'fields' => array(
						'list' => array(
							'type'     => 'callback',
							'callback' => array( $this, 'get_list' ),
						),
					),
				),
			);
			$this->options = $options;
		}

		public function get_list() {
			include_once dirname( __FILE__ ) . '/text-replacement-list-table.php';
			$data = $this->get_value( 'list' );
			if ( empty( $data ) || ! is_array( $data ) ) {
				$data = array();
			} else {
				uasort( $data, array( $this, 'sort_by_find' ) );
			}
			ob_start();
			$list_table = new PSToolkit_Text_Replacement_List_Table();
			$list_table->set_config( $this );
			$list_table->prepare_items( $data );
			$list_table->display();
			$content = ob_get_contents();
			ob_end_clean();
			$content .= $this->get_dialog_delete( 'bulk' );
			return $content;
		}

		/**
		 * Filters gettext
		 *
		 * @param $transtext
		 * @param $normtext
		 * @param $domain
		 * @return mixed
		 */
		public function replace_text( $transtext, $normtext, $domain ) {
			$data = $this->get_value( 'list' );
			if ( empty( $data ) ) {
				return $transtext;
			}
			$re = $this->re;
			if ( empty( $re ) || ! isset( $re[ $domain ] ) ) {
				foreach ( $data as $one ) {
					if ( ! empty( $one['domain'] ) && $domain !== $one['domain'] ) {
						continue;
					}
					$scope                                 = isset( $one['scope'] ) ? $one['scope'] : 'both';
					$re[ $domain ][ $scope ][ $one['re'] ] = $one['replace'];
				}
				$this->re = $re;
			}
			if ( empty( $re[ $domain ] ) ) {
				return $transtext;
			}
			$scopes = array( 'admin', 'front', 'both' );
			foreach ( $scopes as $scope ) {
				if ( empty( $re[ $domain ][ $scope ] ) ) {
					continue;
				}
				/**
				 * Only front
				 */
				if ( is_admin() && 'front' === $scope ) {
					continue;
				}
				/**
				 * Only admin
				 */
				if ( ! is_admin() && 'admin' === $scope ) {
					continue;
				}
				$from      = array_keys( $re[ $domain ][ $scope ] );
				$to        = array_values( $re[ $domain ][ $scope ] );
				$transtext = preg_replace( $from, $to, $transtext );
			}
			return $transtext;
		}

		/**
		 * Filters gettext_with_context
		 *
		 * @param $translations
		 * @param $text
		 * @param $context
		 * @param $domain
		 * @return mixed
		 */
		public function replace_gettext_with_context( $translations, $text, $context, $domain ) {
			return $this->replace_text( $translations, $text, $domain );
		}

		/**
		 * Add new button
		 *
		 * @since 1.8.6
		 */
		public function add_new_button() {
			printf(
				'<a class="add-new-h2" href="#addnew" id="addnewtextchange">%s</a>',
				esc_html__( 'Neue hinzufügen', 'ub' )
			);
		}

		/**
		 * Add "add" button.
		 *
		 * @since 1.0.0
		 */
		public function add_button_after_title( $content, $module ) {
			if ( $this->module !== $module['module'] ) {
				return $content;
			}
			$content .= '<div class="sui-actions-left">';
			$content .= $this->button_add();
			$content .= '</div>';
			return $content;
		}

		/**
		 * SUI: button add
		 *
		 * @since 1.0.0
		 *
		 * @return string Button HTML.
		 */
		public function button_add() {
			$args = array(
				'data' => array(
					'modal-open' => $this->get_name(),
				),
				'icon' => 'plus',
				'text' => __( 'Regel hinzufügen', 'ub' ),
				'sui'  => 'blue',
			);
			return $this->button( $args );
		}

		/**
		 * Add SUI dialog
		 *
		 * @since 1.0.0
		 *
		 * @param string $content Current module content.
		 * @param array  $module Current module.
		 */
		public function add_modal( $content, $module ) {
			if ( $this->module !== $module['module'] ) {
				return $content;
			}
			$content .= $this->add_dialog();
			return $content;
		}

		/**
		 * Add modal windows.
		 *
		 * @since 1.0.0
		 */
		public function add_dialog( $item = array() ) {
			$id      = isset( $item['id'] ) ? $item['id'] : 0;
			$name    = 0 === $id ? 'new' : 'edit';
			$config  = $this->get_sui_tabs_config( $item );
			$content = $this->sui_tabs( $config, $id );
			/**
			 * Footer
			 */
			$footer  = '';
			$args    = array(
				'data'  => array(
					'modal-close' => '',
				),
				'text'  => __( 'Abbrechen', 'ub' ),
				'sui'   => 'ghost',
				'class' => $this->get_name( 'reset' ),
			);
			$footer .= $this->button( $args );
			$args    = array(
				'data'  => array(
					'nonce' => $this->get_nonce_value( $id ),
					'id'    => $id,
				),
				'text'  => 'new' === $name ? __( 'Hinzufügen', 'ub' ) : __( 'Aktualisieren', 'ub' ),
				'class' => $this->get_name( $name ),
			);
			if ( 'new' === $name ) {
				$args['icon'] = 'check';
			}
			$footer .= $this->button( $args );
			/**
			 * Dialog
			 */
			$args   = array(
				'id'           => $this->get_name( $id ),
				'title'        => 'new' === $name ? __( 'Textersetzungsregel hinzufügen', 'ub' ) : __( 'Bearbeite die Textersetzungsregel', 'ub' ),
				'content'      => $content,
				'confirm_type' => false,
				'footer'       => array(
					'content' => $footer,
					'classes' => array( 'sui-space-between' ),
				),
				'classes'      => array( 'sui-modal-lg' ),
			);
			$output = $this->sui_dialog( $args );
			return $output;
		}

		/**
		 * Get SUI configuration for modal window.
		 *
		 * @since 1.0.0
		 *
		 * @return array $config Configuration of modal window.
		 */
		public function get_sui_tabs_config( $item = array() ) {
			$domain_description  = __( 'Gib die Textdomäne ein, um den Text nur für ein bestimmtes Plugin/Theme zu ersetzen. Die Textdomäne von PSToolkit lautet beispielsweise "ub".', 'ub' );
			$domain_description .= sprintf(
				' <a href="%s" target="_blank">%s</a>',
				esc_url( __( 'https://codex.classicpress.org/I18n_for_ClassicPress_Developers#Text_Domains', 'ub' ) ),
				__( 'Klicke hier, um mehr über Textdomänen zu erfahren.', 'ub' )
			);
			$config              = array(
				array(
					'id'     => 'text-replacement',
					'fields' => array(
						'find'        => array(
							'label'       => __( 'Text finden', 'ub' ),
							'placeholder' => esc_attr__( 'z.B. Beiträge', 'ub' ),
							'value'       => isset( $item['find'] ) ? $item['find'] : '',
							'required'    => true,
						),
						'replace'     => array(
							'label'       => __( 'Ersetzen mit', 'ub' ),
							'placeholder' => esc_attr__( 'z.B. Blog-Artikel', 'ub' ),
							'value'       => isset( $item['replace'] ) ? $item['replace'] : '',
							'description' => array(
								'content'  => __( 'Wenn Du den Text ausblenden möchtest, lasse diese Eingabe leer.', 'ub' ),
								'position' => 'bottom',
							),
						),
						'domain'      => array(
							'label'       => __( 'Textdomäne (optional)', 'ub' ),
							'placeholder' => esc_attr__( ' z.B. ub', 'ub' ),
							'value'       => isset( $item['domain'] ) ? $item['domain'] : '',
							'description' => array(
								'content'  => $domain_description,
								'position' => 'bottom',
							),
						),
						'scope'       => array(
							'label'   => __( 'Umfang', 'ub' ),
							'type'    => 'sui-tab',
							'options' => array(
								'both'  => __( 'Alle Seiten', 'ub' ),
								'admin' => __( 'Admin-Seiten', 'ub' ),
								'front' => __( 'Front-End-Seiten', 'ub' ),
							),
							'value'   => isset( $item['scope'] ) && ! empty( $item['scope'] ) ? $item['scope'] : 'both',
							'default' => 'both',
						),
						'ignorecase'  => array(
							'label'   => __( 'Fallübereinstimmung', 'ub' ),
							'type'    => 'sui-tab',
							'options' => array(
								'insensitive' => __( 'Groß- und Kleinschreibung nicht berücksichtigen', 'ub' ),
								'sensitive'   => __( 'Groß- und Kleinschreibung beachten', 'ub' ),
							),
							'value'   => isset( $item['ignorecase'] ) && ! empty( $item['ignorecase'] ) ? $item['ignorecase'] : 'sensitive',
							'default' => 'sensitive',
						),
						'exclude_url' => array(
							'label'       => __( 'Verlinkt Text', 'ub' ),
							'type'        => 'sui-tab',
							'options'     => array(
								'include' => __( 'Einschließen', 'ub' ),
								'exclude' => __( 'Ausschließen', 'ub' ),
							),
							'value'       => isset( $item['exclude_url'] ) && ! empty( $item['exclude_url'] ) ? $item['exclude_url'] : 'exclude',
							'description' => array(
								'content'  => __( 'Wähle aus, ob der Text innerhalb des Tags &lt;a&gt; ersetzt werden soll.', 'ub' ),
								'position' => 'bottom',
							),
							'default'     => 'exclude',
						),
					),
				),
			);
			return $config;
		}

		/**
		 * AJAX: save data
		 *
		 * @since 1.0.0
		 */
		public function ajax_save() {
			$uba          = pstoolkit_get_uba_object();
			$message      = $nonce_action = $id = 0;
			$id           = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$nonce_action = $this->get_nonce_action( $id );
			$message      = sprintf( 'Element wurde aktualisiert.', 'ub' );
			if ( '0' == $id ) {
				$message = sprintf( 'Item was added.', 'ub' );
			}
			$this->check_input_data( $nonce_action, array( 'id', 'find', 'replace' ) );
			$data   = array();
			$config = $this->get_sui_tabs_config();
			foreach ( $config as $tab ) {
				foreach ( $tab['fields'] as $key => $one ) {
					$value = isset( $one['default'] ) ? $one['default'] : '';
					if ( isset( $_POST[ $key ] ) ) {
						$value = filter_input( INPUT_POST, $key, FILTER_UNSAFE_RAW );
					}
					$data[ $key ] = trim( $value );
				}
			}
			if ( empty( $data['find'] ) ) {
				$args = array(
					'fields' => array(),
				);
				if ( empty( $data['find'] ) ) {
					$args['fields']['input[name="pstoolkit[find]"]'] = __( 'Dieses Feld kann nicht leer sein!', 'ub' );
				}
				wp_send_json_error( $args );
			}
			if ( '0' == $id ) {
				$id = $this->generate_id( $data );
			}
			$data['id'] = $id;
			$data['re'] = $this->calculate_regexp( $data );
			$this->set_value( 'list', $id, $data );
			$message = array(
				'type'    => 'success',
				'message' => $message,
			);
			$uba->add_message( $message );
			wp_send_json_success();
		}

		/**
		 * Helper to calculate regexp
		 *
		 * @since 1.0.0
		 */
		private function calculate_regexp( $data ) {
			$exclude_url = 'exclude' === $data['exclude_url'];
			$ignorecase  = ( 'sensitive' !== $data['ignorecase'] );
			$find        = str_replace( '#', '\#', stripslashes( $data['find'] ) );
			$modifier    = $ignorecase ? 'i' : '';
			if ( $exclude_url ) {
				return '#(?' . $modifier . ')<a.*?</a>(*SKIP)(*F)|\b\Q' . $find . '\E\b#';
			}
			return '#\b\Q' . $find . '\E\b#' . $modifier;
		}

		/**
		 * AJAX: delete item
		 *
		 * @since 1.0.0
		 */
		public function ajax_delete() {
			$nonce_action = 0;
			$id           = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( $id ) {
				$nonce_action = $this->get_nonce_action( $id, 'delete' );
			}
			$this->check_input_data( $nonce_action, array( 'id' ) );
			$this->set_value( 'list', $id, null );
			$message = array(
				'type'    => 'success',
				'message' => esc_html__( 'Element wurde gelöscht.', 'ub' ),
			);
			$uba     = pstoolkit_get_uba_object();
			$uba->add_message( $message );
			wp_send_json_success();
		}

		/**
		 * AJAX: delete feed data (bulk)
		 *
		 * @since 1.0.0
		 */
		public function ajax_delete_bulk() {
			$this->check_input_data( $this->get_nonce_action( 'bulk', 'delete' ), array( 'ids' ) );
			$ids = $this->sanitize_request_payload( $_POST['ids'] );
			if ( is_array( $ids ) ) {
				foreach ( $ids as $id ) {
					$this->set_value( 'list', $id, null );
				}
				$uba     = pstoolkit_get_uba_object();
				$message = array(
					'type'    => 'success',
					'message' => esc_html__( 'Ausgewählte Regeln wurden gelöscht!', 'ub' ),
				);
				$uba->add_message( $message );
				wp_send_json_success();
			}
			$this->json_error();
		}

		/**
		 * Replace default by module related
		 */
		public function dialog_delete_attr_filter( $args, $module, $id ) {
			if ( $this->module === $module ) {
				$args['title']       = __( 'Textersetzungsregel löschen', 'ub' );
				$args['description'] = __( 'Möchtest Du diese Textersetzungsregel wirklich dauerhaft löschen?', 'ub' );
				if ( 'bulk' === $id ) {
					$args['title']       = __( 'Regeln zum Ersetzen von Text löschen', 'ub' );
					$args['description'] = __( 'Möchtest Du wirklich ausgewählte Textersetzungsregeln dauerhaft löschen?', 'ub' );
				}
			}
			return $args;
		}

		/**
		 * Sort items
		 *
		 * @since 3.1.0
		 */
		private function sort_by_find( $a, $b ) {
			if (
				isset( $a['find'] )
				&& isset( $b['find'] )
			) {
				return strcasecmp( $a['find'], $b['find'] );
			}
			if (
				isset( $a['replace'] )
				&& isset( $b['replace'] )
			) {
				return strcasecmp( $a['replace'], $b['replace'] );
			}
			return 0;
		}
	}
}
new PSToolkit_Text_Replacement();
