<?php
/**
 * PSToolkit Dashboard Feeds class.
 *
 * @package PSToolkit
 * @subpackage Widgets
 */
if ( ! class_exists( 'PSToolkit_Dashboard_Feeds' ) ) {

	class PSToolkit_Dashboard_Feeds extends PSToolkit_Helper {
		private $version_compare;
		private $list_table;
		private $items_name = 'pstoolkit_dashboard_feeds';

		public function __construct() {
			parent::__construct();
			$this->module = 'dashboard-feeds';
			global $wp_version;
			$this->version_compare = version_compare( $wp_version, '3.7.1' );
			/**
			 * hooks
			 */
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ), 99 );
			add_action( 'wp_network_dashboard_setup', array( $this, 'add_dashboard_widgets' ), 99 );
			add_action( 'wp_user_dashboard_setup', array( $this, 'add_dashboard_widgets' ), 99 );
			add_filter( 'pstoolkit_settings_dashboard_feeds', array( $this, 'admin_options_page' ) );
			/**
			 * Disable button "Save Changes".
			 *
			 * @since 1.0.0
			 */
			add_filter( 'pstoolkit_settings_panel_show_submit', array( $this, 'disable_save_changes' ), 10, 2 );
			/**
			 * Add settings button.
			 *
			 * @since 1.0.0
			 */
			add_filter( 'pstoolkit_settings_after_box_title', array( $this, 'add_button_after_title' ), 10, 2 );
			/**
			 * Handla AJAX actions
			 *
			 * @since 1.0.0
			 */
			add_action( 'wp_ajax_pstoolkit_dashboard_feed_save', array( $this, 'ajax_save' ) );
			add_action( 'wp_ajax_pstoolkit_dashboard_feed_delete', array( $this, 'ajax_delete' ) );
			add_action( 'wp_ajax_pstoolkit_dashboard_feed_delete_bulk', array( $this, 'ajax_delete_bulk' ) );
			/**
			 *
			 * @since 3.0.1
			 */
			add_action( 'wp_ajax_pstoolkit_get_site_data', array( $this, 'ajax_get_site_data' ) );
			/**
			 * upgrade options
			 *
			 * @since 1.0.0
			 */
			add_action( 'init', array( $this, 'upgrade_options' ) );
			/**
			 * Add dialog
			 *
			 * @since 3.0,0
			 */
			add_filter( 'pstoolkit_get_module_content', array( $this, 'add_modal' ), 10, 2 );
			/**
			 * Single item delete
			 */
			add_filter( 'pstoolkit_dialog_delete_attr', array( $this, 'dialog_delete_attr_filter' ), 10, 3 );
			add_filter( 'pstoolkit_options_names', array( $this, 'add_options_names' ) );
		}

		public function add_options_names( $options ) {
			$options[] = $this->items_name;

			return $options;
		}

		/**
		 * Add button after title.
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
		 * Upgrade option
		 *
		 * @since 1.0.0
		 */
		public function upgrade_options() {
			if ( is_multisite() ) {
				global $current_blog;
				if ( $current_blog->site_id == $current_blog->blog_id ) {
					$df_widgets = get_blog_option( $current_blog->site_id, 'psource_df_widget_options' );
					if ( ! is_array( $df_widgets ) ) {
						$df_widgets = get_option( 'psource_df_widget_options' );
					}
				} else {
					$df_widgets = get_blog_option( $current_blog->blog_id, 'psource_df_widget_options' );
				}
			} else {
				$df_widgets = get_option( 'psource_df_widget_options' );
			}
			if ( empty( $df_widgets ) || ! is_array( $df_widgets ) ) {
				return;
			}
			$items = $this->get_df_feed_widgets_items();
			foreach ( $df_widgets as $one ) {
				if ( isset( $one['show-on'] ) ) {
					$one['network'] = isset( $one['show-on']['network'] ) ? $one['show-on']['network'] : 'hide';
					$one['site']    = isset( $one['show-on']['site'] ) ? $one['show-on']['site'] : 'hide';
					unset( $one['show-on'] );
				}
				foreach ( $one as $key => $value ) {
					switch ( $key ) {
						case 'show_author':
						case 'show_date':
						case 'site':
						case 'network':
							$one[ $key ] = 'hide';
							if ( 'on' === $value || 1 === intval( $value ) ) {
								$one[ $key ] = 'show';
							}
							break;
						case 'show_summary':
							$one[ $key ] = 'excerpt';
							if ( 'on' === $value || 1 === intval( $value ) ) {
								$one[ $key ] = 'full';
							}
							break;
						default:
							break;
					}
				}
				$id           = $this->get_max_feed_id() + 1;
				$one['id']    = sprintf( 'df-%d', $id );
				$items[ $id ] = $one;
			}
			pstoolkit_update_option( $this->items_name, $items );
			if ( $this->is_network && function_exists( 'delete_blog_option' ) ) {
				delete_blog_option( $current_blog->site_id, 'psource_df_widget_options' );
			}
			delete_option( 'psource_df_widget_options' );
		}

		/**
		 * Disable button "Save Changes".
		 *
		 * @since 1.0.0
		 */
		public function disable_save_changes( $status, $module ) {
			if ( $this->module !== $module['module'] ) {
				return $status;
			}
			return false;
		}

		public function admin_options_page( $content ) {
			require_once 'dashboard-feeds-table.php';
			global $ub_version;
			$this->list_table = new PSToolkit_Dashboard_Feeds_table();
			$content          = '<div id="pstoolkit-dashboard-feeds-panel" class="pstoolkit-dashboard-feeds-wrap">';
			$df_widgets       = $this->get_df_feed_widgets_items();
			$content         .= $this->show_dashboard_feed_list_table( $df_widgets );
			$content         .= '</div>';
			add_action( 'pstoolkit_ubadmin_footer', array( $this, 'add_modals' ) );
			return $content;
		}

		public function add_dashboard_widgets() {
			require_once 'dashboard-feeds-widget.php';
			$widget_items = array();
			$df_widgets   = $this->get_df_feed_widgets_items();
			$df_widgets   = is_array( $df_widgets )
				? $df_widgets
				: array();
			foreach ( $df_widgets as $widget_id => $widget_options ) {
				/**
				 * check is proper url
				 */
				if ( ! isset( $widget_options['url'] ) ) {
					continue;
				}
				$is_url = filter_var( $widget_options['url'], FILTER_VALIDATE_URL );
				if ( ! $is_url ) {
					continue;
				}
				if ( is_multisite() && is_network_admin() ) {
					if (
						isset( $widget_options['network'] )
						&& 'show' === $widget_options['network']
					) {
						$widget_items[ $widget_id ] = new PSToolkit_Dashboard_Feeds_widget();
						$widget_items[ $widget_id ]->init( $widget_id, $widget_options );
					}
				} elseif (
					isset( $widget_options['site'] )
					&& 'show' === $widget_options['site']
				) {
					$widget_items[ $widget_id ] = new PSToolkit_Dashboard_Feeds_widget();
					$widget_items[ $widget_id ]->init( $widget_id, $widget_options );
				}
			}
		}

		/**
		 * Get widgets
		 *
		 * @since 1.0.0
		 *
		 * @return array $df_widgets Arary of defined widgets.
		 */
		public function get_df_feed_widgets_items() {
			$df_widgets = pstoolkit_get_option_filtered( $this->items_name );
			return $df_widgets;
		}

		public function show_dashboard_feed_list_table( $df_items = array() ) {
			ob_start();
			$this->list_table->set_config( $this );
			$this->list_table->prepare_items( $df_items );
			$this->list_table->display();
			$content = ob_get_contents();
			ob_end_clean();
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
					'modal-open' => $this->get_name( 'new' ),
				),
				'icon' => 'plus',
				'text' => __( 'Feed hinzufügen', 'ub' ),
				'sui'  => 'blue',
			);
			return $this->button( $args );
		}

		/**
		 * Get SUI configuration for modal window.
		 *
		 * @since 1.0.0
		 *
		 * @return array $config Configuration of modal window.
		 */
		public function get_sui_tabs_config( $item = array() ) {
			$id = isset( $item['id'] ) ? $item['id'] : 0;

			$after  = '<button type="button" class="sui-button-icon"><i aria-hidden="true" class="sui-icon-magnifying-glass-search"></i></button>';
			$after .= sprintf( '<div class="%s">', $this->get_name( 'list' ) );
			$after .= PSToolkit_Helper::sui_inline_notice( 'pstoolkit-feeds-info', 'default' );
			$after .= '<ul class="pstoolkit-list hidden"></ul>';
			$after .= '</div>';
			$after .= '</div>';
			$config = array(
				array(
					'tab'      => __( 'Allgemeines', 'ub' ),
					'tab_name' => 'general',
					'fields'   => array(
						'link'  => array(
							'type'        => 'url',
							'label'       => __( 'Seiten-URL', 'ub' ),
							'placeholder' => esc_attr__( 'z.B. http://www.example.com', 'ub' ),
							'value'       => isset( $item['link'] ) ? $item['link'] : '',
							'classes'     => array(
								$this->get_name( 'url' ),
							),
							'data'        => array(
								'nonce'  => $this->get_nonce_value( 'link', $id ),
								'id'     => $id,
								'tmpl'   => $this->get_name( 'select' ),
								'target' => $this->get_name( 'list' ),
							),
							'field'       => array(
								'before' => '<div class="sui-with-button sui-with-button-icon">',
								'after'  => $after,
							),
						),
						'title' => array(
							'label'       => __( 'Feed-Titel (optional)', 'ub' ),
							'label_after' => __( '(Optionen)', 'ub' ),
							'placeholder' => esc_attr__( 'z.B. Täglicher Job-Feed.', 'ub' ),
							'value'       => isset( $item['title'] ) ? $item['title'] : '',
						),
						'url'   => array(
							'type'        => 'url',
							'label'       => __( 'Feed URL', 'ub' ),
							'placeholder' => esc_attr__( 'z.B. http://www.example.com/feed', 'ub' ),
							'value'       => isset( $item['url'] ) ? $item['url'] : '',
							'required'    => 'required',
						),
					),
				),
				array(
					'tab'      => __( 'Anzeige', 'ub' ),
					'tab_name' => 'display',
					'fields'   => array(
						'items'        => array(
							'type'        => 'number',
							'label'       => __( 'Anzahl', 'ub' ),
							'description' => __( 'Die Anzahl der Feed-Elemente, die Du anzeigen möchtest.', 'ub' ),
							'value'       => isset( $item['items'] ) ? $item['items'] : 10,
							'default'     => 10,
						),
						'show_summary' => array(
							'label'       => __( 'Inhalt', 'ub' ),
							'description' => __( 'Wähle ob der gesamte Inhalt oder nur der Auszug angezeigt werden soll.', 'ub' ),
							'type'        => 'sui-tab',
							'options'     => array(
								'excerpt' => __( 'Auszug', 'ub' ),
								'full'    => __( 'Voller Inhalt', 'ub' ),
							),
							'value'       => isset( $item['show_summary'] ) ? $item['show_summary'] : 'excerpt',
							'default'     => 'excerpt',
						),
						'show_author'  => array(
							'label'       => __( 'Autor', 'ub' ),
							'description' => __( 'Zeige den Autor des Feed-Elements an, ob verfügbar oder nicht.', 'ub' ),
							'type'        => 'sui-tab',
							'options'     => array(
								'hide' => __( 'Ausblenden', 'ub' ),
								'show' => __( 'Anzeigen', 'ub' ),
							),
							'value'       => isset( $item['show_author'] ) ? $item['show_author'] : 'hide',
							'default'     => 'hide',
						),
						'show_date'    => array(
							'label'       => __( 'Datum', 'ub' ),
							'description' => __( 'Zeigt das Erstellungsdatum des Feedelements an oder nicht.', 'ub' ),
							'type'        => 'sui-tab',
							'options'     => array(
								'hide' => __( 'Ausblenden', 'ub' ),
								'show' => __( 'Anzeigen', 'ub' ),
							),
							'value'       => isset( $item['show_date'] ) ? $item['show_date'] : 'hide',
							'default'     => 'hide',
						),
					),
				),
				array(
					'tab'      => __( 'Sichtbarkeit', 'ub' ),
					'tab_name' => 'visibility',
					'fields'   => array(
						'site'    => array(
							'label'       => __( 'Webseiten-Dashboard', 'ub' ),
							'description' => __( 'Wähle aus ob dieser Feed im Webseiten-Dashboard angezeigt werden soll oder nicht.', 'ub' ),
							'type'        => 'sui-tab',
							'options'     => array(
								'hide' => __( 'Ausblenden', 'ub' ),
								'show' => __( 'Anzeigen', 'ub' ),
							),
							'value'       => isset( $item['site'] ) ? $item['site'] : 'show',
							'default'     => 'show',
						),
						'network' => array(
							'label'       => __( 'Netzwerk-Dashboard', 'ub' ),
							'description' => __( 'Wähle aus ob dieser Feed im Netzwerk-Dashboard angezeigt werden soll oder nicht.', 'ub' ),
							'type'        => 'sui-tab',
							'options'     => array(
								'hide' => __( 'Ausblenden', 'ub' ),
								'show' => __( 'Anzeigen', 'ub' ),
							),
							'value'       => isset( $item['network'] ) ? $item['network'] : 'show',
							'default'     => 'show',
						),
					),
				),
			);
			/**
			 * remove multisite show
			 */
			if ( ! $this->is_network || ! wp_doing_ajax() && ! is_network_admin() ) {
				unset( $config[2]['fields']['network'] );
				$config[2]['fields']['site']['label'] = __( 'Dashboard', 'ub' );
			}
			return $config;
		}

		/**
		 * Add modal windows.
		 *
		 * @since 1.0.0
		 */
		public function add_modals() {
			$config  = $this->get_sui_tabs_config();
			$content = $this->sui_tabs( $config, 0, true );
			/**
			 * Footer
			 */
			$footer  = '';
			$args    = array(
				'text' => __( 'Abbrechen', 'ub' ),
				'sui'  => 'ghost',
				'data' => array(
					'modal-close' => '',
				),
			);
			$footer .= $this->button( $args );
			$args    = array(
				'data'  => array(
					'nonce' => wp_create_nonce( $this->get_nonce_action( 'df-0' ) ),
					'id'    => 0,
				),
				'icon'  => 'check',
				'text'  => __( 'Hinzufügen', 'ub' ),
				'class' => $this->get_name( 'add' ),
			);
			$footer .= $this->button( $args );
			/**
			 * Dialog
			 */
			$args = array(
				'id'           => $this->get_name( 'new' ),
				'title'        => __( 'Dashboard-Feed hinzufügen', 'ub' ),
				'content'      => $content,
				'confirm_type' => false,
				'footer'       => array(
					'content' => $footer,
					'classes' => array( 'sui-space-between' ),
				),
				'classes'      => array(
					'sui-modal-lg',
					$this->get_name( 'dialog' ),
				),
			);
			echo $this->sui_dialog( $args );
		}

		/**
		 * Get modal for to edit feed
		 *
		 * @since 1.0.0
		 */
		public function get_feed_form( $item ) {
			$id      = isset( $item['id'] ) ? $item['id'] : $this->generate_id( serialize( $item ) );
			$config  = $this->get_sui_tabs_config( $item );
			$content = $this->sui_tabs( $config, $id, true );
			/**
			 * Footer
			 */
			$footer  = '';
			$args    = array(
				'text' => __( 'Abbrechen', 'ub' ),
				'sui'  => 'ghost',
				'data' => array(
					'modal-close' => '',
				),
			);
			$footer .= $this->button( $args );
			$args    = array(
				'data'    => array(
					'nonce' => wp_create_nonce( $this->get_nonce_action( $item['id'] ) ),
					'id'    => $id,
				),
				'text'    => __( 'Aktualisieren', 'ub' ),
				'classes' => array(
					$this->get_name( 'save' ),
				),
			);
			$footer .= $this->button( $args );
			/**
			 * Dialog
			 */
			$args = array(
				'id'      => $this->get_nonce_action( $item['id'], 'edit' ),
				'title'   => __( 'Dashboard-Feed bearbeiten', 'ub' ),
				'content' => $content,
				'footer'  => array(
					'content' => $footer,
					'classes' => array( 'sui-space-between' ),
				),
				'classes' => array(
					'sui-modal-lg',
					$this->get_name( 'dialog' ),
				),
			);
			return $this->sui_dialog( $args );
		}

		/**
		 * AJAX: delete feed data
		 *
		 * @since 1.0.0
		 */
		public function ajax_delete() {
			$nonce_action = 0;
			if ( isset( $_POST['id'] ) ) {
				$nonce_action = $this->get_nonce_action( $_POST['id'], 'delete' );
			}
			$widget_id = 'psource_dashboard_item_' . $_POST['id'];
			do_action( 'pstoolkit_delete_available_widget', $widget_id );
			$this->check_input_data( $nonce_action, array( 'id' ) );
			$items = $this->get_df_feed_widgets_items();
			if ( isset( $items[ $_POST['id'] ] ) ) {
				$uba = pstoolkit_get_uba_object();
				unset( $items[ $_POST['id'] ] );
				pstoolkit_update_option( $this->items_name, $items );
				$message = array(
					'type'    => 'success',
					'message' => sprintf( 'Feed wurde gelöscht.', 'ub' ),
				);
				$uba->add_message( $message );
				wp_send_json_success();
			}
			wp_send_json_error( array( 'message' => __( 'Ausgewählter Feed existiert nicht!', 'ub' ) ) );
		}

		/**
		 * AJAX: delete feed data (bulk)
		 *
		 * @since 1.0.0
		 */
		public function ajax_delete_bulk() {
			$this->check_input_data( $this->get_nonce_action( 'bulk', 'delete' ), array( 'ids' ) );
			$update = false;
			if ( is_array( $_POST['ids'] ) ) {
				$items = $this->get_df_feed_widgets_items();
				foreach ( $_POST['ids'] as $id ) {
					if ( isset( $items[ $id ] ) ) {
						unset( $items[ $id ] );
						$widget_id = 'psource_dashboard_item_' . $id;
						do_action( 'pstoolkit_delete_available_widget', $widget_id );
						$update = true;
						continue;
					}
					foreach ( $items as $id2 => $data ) {
						if ( $id === $data['id'] ) {
							unset( $items[ $id2 ] );
							$update = true;
							continue;
						}
					}
				}
			}
			if ( $update ) {
				if ( empty( $items ) ) {
					pstoolkit_delete_option( $this->items_name );
				} else {
					pstoolkit_update_option( $this->items_name, $items );
				}
				$uba     = pstoolkit_get_uba_object();
				$message = array(
					'type'    => 'success',
					'message' => __( 'Ausgewählte Feeds wurden gelöscht!', 'ub' ),
				);
				$uba->add_message( $message );
				wp_send_json_success();
			}
			$this->json_error();
		}

		/**
		 * AJAX: save feed data
		 *
		 * @since 1.0.0
		 */
		public function ajax_save() {
			$uba     = pstoolkit_get_uba_object();
			$message = $nonce_action = $id = 0;
			if ( isset( $_POST['id'] ) ) {
				$id           = intval( preg_replace( '/[^\d]+/', '', $_POST['id'] ) );
				$nonce_action = $this->get_nonce_action( 'df-' . $id );
				$message      = sprintf( 'Feed wurde aktualisiert.', 'ub' );
			}
			if ( 0 === $id ) {
				$message = sprintf( 'Feed wurde hinzugefügt.', 'ub' );
			}
			$this->check_input_data( $nonce_action, array( 'id', 'url' ) );
			$data   = array();
			$config = $this->get_sui_tabs_config();
			foreach ( $config as $tab ) {
				foreach ( $tab['fields'] as $key => $one ) {
					$value = isset( $one['default'] ) ? $one['default'] : '';
					switch ( $key ) {
						case 'link':
							if ( isset( $_POST[ $key ] ) && ! empty( $_POST[ $key ] ) ) {
								$value = filter_input( INPUT_POST, $key, FILTER_VALIDATE_URL );
								if ( false === $value ) {
									$args = array(
										'fields' => array(
											'input[name="pstoolkit[link]"]' => __( 'Dieses Feld muss eine richtige URL sein!', 'ub' ),
										),
									);
									wp_send_json_error( $args );
								}
							}
							break;
						case 'url':
							if ( isset( $_POST[ $key ] ) ) {
								$value = filter_input( INPUT_POST, $key, FILTER_VALIDATE_URL );
								if ( false === $value ) {
									$args = array(
										'fields' => array(
											'input[name="pstoolkit[url]"]' => __( 'Dieses Feld muss eine richtige URL sein!', 'ub' ),
										),
									);
									wp_send_json_error( $args );
								}
							} else {
								$args = array(
									'fields' => array(
										'input[name="pstoolkit[url]"]' => __( 'Dieses Feld kann nicht leer sein!', 'ub' ),
									),
								);
								wp_send_json_error( $args );
							}
							break;
						case 'show_summary':
							$v = filter_input( INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							if ( preg_match( '/^(excerpt|full)$/', $v ) ) {
								$value = $v;
							}
							break;
						case 'show_author':
						case 'show_date':
						case 'site':
						case 'network':
							$v = filter_input( INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							if ( preg_match( '/^(hide|show)$/', $v ) ) {
								$value = $v;
							}
							break;
						case 'items':
							$value = max( 1, filter_input( INPUT_POST, $key, FILTER_SANITIZE_NUMBER_INT ) );
							break;
						default:
							$value = filter_input( INPUT_POST, $key, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
							break;
					}
					$data[ $key ] = $value;
				}
			}
			if ( 0 === $id ) {
				$id = 1 + $this->get_max_feed_id();
			}
			$data['id']           = sprintf( 'df-%d', $id );
			$items                = $this->get_df_feed_widgets_items();
			$items[ $data['id'] ] = $data;
			pstoolkit_update_option( $this->items_name, $items );
			$message = array(
				'type'    => 'success',
				'message' => $message,
			);
			$uba->add_message( $message );
			wp_send_json_success();
		}

		/**
		 * Get max id
		 *
		 * @since 1.0.0
		 *
		 * return integer
		 */
		private function get_max_feed_id() {
			$value = 0;
			$items = $this->get_df_feed_widgets_items();
			if ( empty( $items ) ) {
				return $value;
			}
			$keys = array_keys( $items );
			foreach ( $keys as $key ) {
				$v = intval( preg_replace( '/[^\d]+/', '', $key ) );
				if ( $v > $value ) {
					$value = $v;
				}
			}
			return $value;
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
			$content .= $this->get_dialog_delete( 'bulk' );
			/**
			 * Add js templates
			 */
			$template = sprintf( '/admin/modules/%s/js/templates', $this->module );
			$args     = array(
				'id' => $this->get_name( 'select' ),
			);
			$content .= $this->render( $template, $args, true );
			return $content;
		}

		/**
		 * Replace default by module related
		 */
		public function dialog_delete_attr_filter( $args, $module, $id ) {
			if ( $this->module === $module ) {
				$args['title']       = __( 'Dashboard-Feed löschen', 'ub' );
				$args['description'] = __( 'Möchtest Du diesen Dashboard-Feed wirklich dauerhaft löschen?', 'ub' );
				if ( 'bulk' === $id ) {
					$args['title']       = __( 'Dashboard-Feeds löschen', 'ub' );
					$args['description'] = __( 'Möchtest Du ausgewählte Dashboard-Feeds wirklich dauerhaft löschen?', 'ub' );
				}
			}
			return $args;
		}

		/**
		 * AJAX: try to get site data by url
		 *
		 * @since 3.0.1
		 */
		public function ajax_get_site_data() {
			$id = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$this->check_input_data( $this->get_nonce_action( 'link', $id ), array( 'id', 'url' ) );
			$url = filter_input( INPUT_POST, 'url', FILTER_VALIDATE_URL );
			/**
			 * try to add protocol
			 */
			if ( false === $url ) {
				$url = filter_input( INPUT_POST, 'url', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
				if ( ! preg_match( '/^https?:/', $url ) ) {
					$url = filter_var( 'http://' . $url, FILTER_VALIDATE_URL );
				}
			}
			if ( false === $url ) {
				wp_send_json_error();
			}
			$response = wp_remote_get( $url );
			if ( ! is_array( $response ) ) {
				wp_send_json_error();
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				wp_send_json_error();
			}
			$content = wp_remote_retrieve_body( $response );
			if ( empty( $content ) ) {
				wp_send_json_error();
			}
			$data = $this->find_feed( $content );
			wp_send_json_success( $data );
		}

		/**
		 * Get feed data from content helper
		 *
		 * @since 3.0.1
		 */
		private function find_feed( $content ) {
			$data = array();
			if ( ! preg_match_all( '@<link[^>]+>@', $content, $matches ) ) {
				return $data;
			}
			foreach ( $matches[0] as $one ) {
				if ( preg_match_all( '/ (\w+)="([^"]+)"/', $one, $value ) ) {
					$rels = array();
					foreach ( $value[0] as $index => $v ) {
						$rels[ $value[1][ $index ] ] = $value[2][ $index ];
					}
					if ( ! isset( $rels['rel'] ) ) {
						continue;
					}
					if ( ! isset( $rels['href'] ) ) {
						continue;
					}
					if ( 'alternate' !== $rels['rel'] ) {
						continue;
					}
					if (
						isset( $rels['type'] )
						&& 'application/rss+xml' === $rels['type']
					) {
						$one         = array();
						$one['href'] = $rels['href'];
						if ( isset( $rels['title'] ) ) {
							$one['title'] = html_entity_decode( $rels['title'] );
						}
						$data[] = $one;
					}
				}
			}
			return $data;
		}
	}
}
new PSToolkit_Dashboard_Feeds();
