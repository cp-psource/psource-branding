<?php
/**
 * PSToolkit Tracking Codes class.
 *
 * @since 2.3.0
 *
 * @package PSToolkit
 * @subpackage FrontEnd
 */
if ( ! class_exists( 'PSToolkit_Tracking_Codes' ) ) {

	class PSToolkit_Tracking_Codes extends PSToolkit_Helper {

		protected $option_name = 'ub_tracking_codes';

		public function __construct() {
			parent::__construct();
			$this->module = 'tracking-codes';
			/**
			 * handle
			 */
			add_filter( 'pstoolkit_settings_tracking_codes', array( $this, 'admin_options_page' ) );
			/**
			 * AJAX
			 *
			 * @since 1.0.0
			 */
			add_action( 'wp_ajax_pstoolkit_tracking_codes_save', array( $this, 'ajax_save' ) );
			add_action( 'wp_ajax_pstoolkit_tracking_codes_delete', array( $this, 'ajax_delete' ) );
			add_action( 'wp_ajax_pstoolkit_tracking_codes_bulk_delete', array( $this, 'ajax_bulk_delete' ) );
			/**
			 * @since 3.0.1
			 */
			add_action( 'wp_ajax_pstoolkit_admin_panel_tips_reset', array( $this, 'ajax_reset' ) );
			/**
			 * frontend
			 */
			add_action( 'wp_body_open', array( $this, 'target_begin_of_body' ), 0 );
			add_action( 'wp_footer', array( $this, 'target_footer' ), PHP_INT_MAX );
			add_action( 'wp_head', array( $this, 'target_head' ), PHP_INT_MAX );
			/**
			 * Add settings button.
			 *
			 * @since 1.0.0
			 */
			add_filter( 'pstoolkit_settings_after_box_title', array( $this, 'add_button_after_title' ), 10, 2 );
			/**
			 * Single item delete
			 */
			add_filter( 'pstoolkit_dialog_delete_attr', array( $this, 'dialog_delete_attr_filter' ), 10, 3 );
			/**
			 * Upgrade options
			 */
			add_action( 'init', array( $this, 'upgrade_options' ) );
		}

		/**
		 * Upgrade options to new.
		 *
		 * @since 1.0.0
		 */
		public function upgrade_options() {
			$value = $this->get_value();
			if ( empty( $value ) ) {
				return;
			}
			if ( isset( $value['plugin_version'] ) ) {
				return;
			}
			/**
			 * Convert old
			 */
			$data = array();
			foreach ( $value as $key => $one ) {
				$new = array();
				/**
				 * checl multisite settings
				 */
				if ( isset( $one['sites_active'] ) && 'on' === $one['sites_active'] ) {
					unset( $one['sites_active'] );
					$one['filters_active'] = 'on';
				}
				foreach ( $one as $subkey => $value ) {
					/**
					 * ignore subkey
					 */
					if ( 'tracking_ub_tc_action' === $subkey ) {
						continue;
					}
					/**
					 * raname subkey
					 */
					if ( 'filters_active' === $subkey ) {
						$subkey = 'filters_filter';
					}
					$subkey         = preg_replace( '/^(filters|tracking|sites)_/', '', $subkey );
					$new[ $subkey ] = $value;
				}
				/**
				 * sanitize place
				 */
				if ( ! isset( $new['place'] ) ) {
					$new['place'] = 'head';
				}
				/**
				 * stripslashes on code
				 */
				if ( isset( $new['code'] ) ) {
					$new['code'] = stripslashes( $new['code'] );
				}
				$data[ $key ] = $new;
			}
			$this->update_value( $data );
		}

		/**
		 * Get data by target
		 *
		 * @since 2.3.0
		 *
		 * @param string $target Target for tracking code.
		 */
		private function get_data( $target ) {
			/**
			 * Prevent on WP Admin
			 *
			 * @since 3.1.2
			 */
			if ( is_admin() ) {
				return;
			}
			$results = array();
			$data    = $this->local_get_value();
			if ( empty( $data ) ) {
				return;
			}
			foreach ( $data as $one ) {
				/**
				 * ignore inactive
				 */
				if ( ! isset( $one['active'] ) || 'on' !== $one['active'] ) {
					continue;
				}
				/**
				 * ignore not HEAD section
				 */
				if ( ! isset( $one['place'] ) || $target !== $one['place'] ) {
					continue;
				}
				/**
				 * ignore empty
				 */
				if ( ! isset( $one['code'] ) || empty( $one['code'] ) ) {
					continue;
				}
				/**
				 * check filters
				 */
				$show = $this->check_filters( $one );
				if ( false === $show ) {
					continue;
				}
				/**
				 * YES! Show it.
				 */
				$results[] = $one;
			}
			/**
			 * print it!
			 */
			foreach ( $results as $one ) {
				$this->debug( $one['id'], __CLASS__ );
				echo stripslashes( $one['code'] );
				$this->debug( $one['id'], __CLASS__, false );
			}
			return $results;
		}

		/**
		 * Get data for head
		 *
		 * @since 2.3.0
		 */
		public function target_head() {
			$this->get_data( 'head' );
		}

		/**
		 * Get data for body
		 *
		 * @since 2.3.0
		 */
		public function target_begin_of_body() {
			$this->get_data( 'body' );
		}

		/**
		 * Get data for footer
		 *
		 * @since 2.3.0
		 */
		public function target_footer() {
			$this->get_data( 'footer' );
		}

		/**
		 * Set options
		 *
		 * @since 2.3.0
		 */
		protected function set_options() {
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

		/**
		 * Get list of trackin codes.
		 *
		 * @since 2.3.0
		 */
		public function get_list() {
			require_once 'tracking-codes-list-table.php';
			$data = $this->local_get_value();
			if ( empty( $data ) ) {
				$data = array();
			}
			ob_start();
			$list_table = new PSToolkit_Tracking_Codes_List_Table();
			$list_table->set_config( $this );
			$list_table->prepare_items( $data );
			$list_table->display();
			$content = ob_get_contents();
			ob_end_clean();
			$content .= $this->get_dialog_delete( 'bulk' );
			return $content;
		}

		/**
		 * Check visibility by filter.
		 *
		 * @since 2.3.0
		 *
		 * @param array $data Configuration data of single tracking code.
		 * @return boolean show or hide value.
		 */
		private function check_filters( $data ) {
			$show = true;
			/**
			 * Handle only Main Query and leave the admin alone!
			 */
			if ( ! is_main_query() || is_admin() ) {
				return $show;
			}
			/**
			 * Subsite limit
			 */
			if ( isset( $data['sites'] ) && is_array( $data['sites'] ) ) {
				$blog_id = get_current_blog_id();
				$show    = in_array( $blog_id, $data['sites'] );
			}
			/**
			 * Filters are off or misconfigured
			 */
			if ( ! isset( $data['filter'] ) || 'on' !== $data['filter'] ) {
				return $show;
			}
			/**
			 * filter by user
			 */
			if ( $show && isset( $data['users'] ) ) {
				$show = $this->filter_by_user( $data['users'] );
			}
			/**
			 * filter by author
			 */
			if ( $show && isset( $data['authors'] ) ) {
				$show = $this->filter_by_author( $data['authors'] );
			}
			/**
			 * filter by archive
			 */
			if ( $show && isset( $data['archives'] ) ) {
				$show = $this->filter_by_archive( $data['archives'] );
			}
			/**
			 * By default return true
			 */
			return $show;
		}

		/**
		 * Check visibility by filter.
		 *
		 * @since 2.3.0
		 *
		 * @param array $data Configuration data of single tracking code.
		 * @return boolean show or hide value.
		 */
		private function filter_by_user( $filter ) {
			if ( ! is_array( $filter ) || empty( $filter ) ) {
				return true;
			}
			$logged = is_user_logged_in();
			if ( in_array( 'anonymous', $filter ) && ! $logged ) {
				return true;
			}
			if ( in_array( 'logged', $filter ) && $logged ) {
				return true;
			}
			$roles = array();
			foreach ( $filter as $one ) {
				if ( preg_match( '/^wp:role:(.+)$/', $one, $mataches ) ) {
					$roles[] = $mataches[1];
				}
			}
			if ( ! empty( $roles ) && ! $logged ) {
				return false;
			}
			$user = wp_get_current_user();
			foreach ( $roles as $role ) {
				if ( 'super' === $role ) {
					return is_super_admin();
				}
				if ( in_array( $role, $user->roles ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Check visibility by filter.
		 *
		 * @since 2.3.0
		 *
		 * @param array $data Configuration data of single tracking code.
		 * @return boolean show or hide value.
		 */
		private function filter_by_author( $filter ) {
			if ( ! is_array( $filter ) || empty( $filter ) ) {
				return true;
			}
			if ( ! is_singular() ) {
				return false;
			}
			global $post;
			return in_array( $post->post_author, $filter );
		}

		/**
		 * Check visibility by filter.
		 *
		 * @since 2.3.0
		 *
		 * @param array $data Configuration data of single tracking code.
		 * @return boolean show or hide value.
		 */
		private function filter_by_archive( $filter ) {
			if ( ! is_array( $filter ) || empty( $filter ) ) {
				return true;
			}
			/**
			 * 404
			 */
			if ( in_array( '404', $filter ) && is_404() ) {
				return true;
			}
			/**
			 * author archive
			 */
			if ( in_array( 'authors', $filter ) && is_author() ) {
				return true;
			}
			/**
			 * category archive
			 */
			if ( in_array( 'category', $filter ) && is_category() ) {
				return true;
			}
			/**
			 * tag archive
			 */
			if ( in_array( 'tags', $filter ) && is_tag() ) {
				return true;
			}
			/**
			 * The Home Page
			 */
			if ( in_array( 'home', $filter ) && is_front_page() && is_home() ) {
				return true;
			}
			/**
			 * The Front Page
			 */
			if ( in_array( 'front', $filter ) && is_front_page() ) {
				return true;
			}
			/**
			 * The Blog Page
			 */
			if ( in_array( 'blog', $filter ) && is_home() ) {
				return true;
			}
			/**
			 * The Single Post Page
			 */
			if ( in_array( 'single', $filter ) && is_single() ) {
				return true;
			}
			/**
			 * The Sticky Post Page
			 */
			if ( in_array( 'sticky', $filter ) && is_single() && is_sticky() ) {
				return true;
			}
			/**
			 * The Page
			 */
			if ( in_array( 'page', $filter ) && is_page() ) {
				return true;
			}
			/**
			 * The archive
			 */
			if ( in_array( 'archive', $filter ) && is_archive() ) {
				return true;
			}
			/**
			 * The search
			 */
			if ( in_array( 'search', $filter ) && is_search() ) {
				return true;
			}
			/**
			 * The attachment
			 */
			if ( in_array( 'attachment', $filter ) && is_attachment() ) {
				return true;
			}
			/**
			 * The singular
			 */
			if ( in_array( 'singular', $filter ) && is_singular() ) {
				return true;
			}
			return false;
		}

		/**
		 * Populates the response object for the "get-location" ajax call.
		 * Location data defines where a custom sidebar is displayed, i.e. on which
		 * pages it is used and which theme-sidebars are replaced.
		 *
		 * @since  2.3.0
		 * @return array $archive_type Array of Archive types.
		 */
		private function get_location_data() {
			$archive_type = array(
				'attachment' => __( 'Beliebige Anhangsseite', 'ub' ),
				'archive'    => __( 'Beliebige Archivseite', 'ub' ),
				'sticky'     => __( 'Sticky Beitrag', 'ub' ),
				'singular'   => __( 'Beliebige Einstiegsseite', 'ub' ),
				'page'       => __( 'Einzelne Seite', 'ub' ),
				'single'     => __( 'Einzelne Beiträge', 'ub' ),
				'front'      => __( 'Titelseite', 'ub' ),
				'home'       => __( 'Startseite', 'ub' ),
				'blog'       => __( 'Blog-Seite', 'ub' ),
				'search'     => __( 'Suchergebnisse', 'ub' ),
				// '404' => __( 'Not Found (404)', 'ub' ), currently we can not handle 404 page, because we use `loop_start` filter.
				'authors'    => __( 'Beliebiges Autorenarchiv', 'ub' ),
			);
			$all          = get_taxonomies(
				array(
					'public'   => true,
					'_builtin' => true,
				),
				'object'
			);
			foreach ( $all as $taxonomy ) {
				$default_taxonomies[] = $taxonomy->labels->singular_name;
				switch ( $taxonomy->name ) {
					case 'post_format':
						break;
					case 'post_tag':
						/**
						* this a legacy and backward compatibility
						*/
						$archive_type['tags'] = sprintf( __( '%s Archive', 'ub' ), $taxonomy->labels->singular_name );
						break;
					case 'category':
						$archive_type[ $taxonomy->name ] = sprintf( __( '%s Archive', 'ub' ), $taxonomy->labels->singular_name );
						break;
					default:
						break;
				}
			}
			/**
			 * sort array by values
			 */
			asort( $archive_type );
			return $archive_type;
		}

		/**
		 * Allow to get value from provate/protection function.
		 *
		 * @since 2.3.0
		 */
		public function local_get_value() {
			$codes = $this->get_value();
			if ( empty( $codes ) ) {
				return array();
			}
			if ( isset( $codes['plugin_version'] ) ) {
				unset( $codes['plugin_version'] );
			}
			if ( isset( $codes['imported'] ) ) {
				unset( $codes['imported'] );
			}
			/**
			 * sanitize
			 */
			foreach ( $codes as $id => $code ) {
				if ( ! isset( $code['active'] ) ) {
					$codes[ $id ]['active'] = 'off';
				}
				if ( ! isset( $code['filter'] ) ) {
					$codes[ $id ]['filter'] = 'off';
				}
				if ( ! isset( $code['place'] ) ) {
					$codes[ $id ]['place'] = 'head';
				}
			}
			return $codes;
		}

		/**
		 * Allow to update value from provate/protection function.
		 *
		 * @since 2.3.0
		 */
		public function local_update_value( $value ) {
			return $this->update_value( $value );
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
			$content .= $this->add_dialog();
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
				'text' => _x( 'Tracking-Code hinzufügen', 'button', 'ub' ),
				'sui'  => 'blue',
			);
			return $this->button( $args );
		}

		/**
		 * Add modal windows.
		 *
		 * @since 1.0.0
		 */
		public function add_dialog( $atts = array() ) {
			global $wp_roles;
			$defaults              = array(
				'module'           => $this->module,
				'is_network_admin' => $this->is_network_admin,
				'id'               => 'new',
				'active'           => 'off',
				'title'            => '',
				'code'             => '',
				'place'            => 'head',
				'filter'           => 'off',
				'users'            => array(),
				'authors'          => array(),
				'archives'         => array(),
				'sites'            => array(),
			);
			$args                  = wp_parse_args( $atts, $defaults );
			$args['data_archives'] = $this->get_location_data();
			/**
			 * Add authors
			 */
			$authors        = array();
			$get_query_args = array(
				'fields'  => array( 'ID', 'display_name' ),
				'orderby' => 'display_name',
			);
			if ( $this->is_network ) {
				$get_query_args['blog_id'] = 0;
			} else {
				$get_query_args['who'] = 'authors';
			}
			$users = get_users( $get_query_args );
			foreach ( $users as $user ) {
				$authors[ $user->ID ] = $user->display_name;
			}
			/**
			 * Add superadmins
			 */
			if ( $this->is_network ) {
				$users = get_super_admins();
				foreach ( $users as $login ) {
					$user = get_user_by( 'login', $login );
					if ( is_a( $user, 'WP_User' ) ) {
						$authors[ $user->data->ID ] = $user->data->display_name;
					}
				}
			}
			natcasesort( $authors );
			$args['data_authors'] = $authors;
			/**
			 * Add users roles
			 */
			$roles = array(
				'logged'    => __( 'Nur angemeldete Benutzer', 'ub' ),
				'anonymous' => __( 'Nur nicht angemeldete Benutzer', 'ub' ),
			);
			foreach ( $wp_roles->roles as $slug => $data ) {
				$roles[ 'wp:role:' . $slug ] = $data['name'];
			}
			if ( $this->is_network ) {
				$roles['wp:role:super'] = __( 'Super Admin', 'ub' );
			}
			natcasesort( $roles );
			$args['data_users'] = $roles;
			/**
			 * Sites
			 */
			if ( $this->is_network && function_exists( 'get_sites' ) ) {
				$args['data_sites'] = $this->get_sites_by_args();
			}
			/**
			 * generate
			 */
			$template = $this->get_template_name( 'dialogs/edit' );
			$content  = $this->render( $template, $args, true );
			/**
			 * Footer
			 */
			$footer      = '';
			$button_args = array(
				'sui'  => 'ghost',
				'text' => __( 'Abbrechen', 'ub' ),
				'data' => array(
					'modal-close' => '',
				),
			);
			$footer     .= $this->button( $button_args );
			$button_args = array(
				'data'  => array(
					'nonce' => $this->get_nonce_value(),
				),
				'text'  => 'new' === $args['id'] ? __( 'Hinzufügen', 'ub' ) : __( 'Aktualisieren', 'ub' ),
				'class' => $this->get_name( 'save' ),
			);
			if ( 'new' === $args['id'] ) {
				$button_args['icon'] = 'check';
			}
			$footer .= $this->button( $button_args );
			/**
			 * Dialog
			 */
			$dialog_args = array(
				'id'           => $this->get_name( $args['id'] ),
				'title'        => 'new' === $args['id'] ? __( 'Tracking-Code hinzufügen', 'ub' ) : __( 'Tracking-Code bearbeiten', 'ub' ),
				'content'      => $content,
				'confirm_type' => false,
				'classes'      => array( 'sui-modal-lg' ),
				'footer'       => array(
					'content' => $footer,
					'classes' => array( 'sui-space-between' ),
				),
			);
			return $this->sui_dialog( $dialog_args );
		}

		/**
		 * Use get_sites() helper.
		 *
		 * @since 2.3.0
		 */
		private function get_sites_by_args( $args = array(), $mode = 'search' ) {
			$results = array();
			if ( ! function_exists( 'get_sites' ) ) {
				return $results;
			}
			$args['orderby'] = 'domain';
			$sites           = get_sites( $args );
			foreach ( $sites as $site ) {
				$details = get_blog_details( $site->blog_id );
				if ( 'search' === $mode ) {
					$results[] = array(
						'id'       => $site->blog_id,
						'title'    => $site->blogname,
						'subtitle' => $site->siteurl,
					);
				} else {
					$results[ $site->blog_id ] = $site->blogname;
				}
			}
			return $results;
		}

		/**
		 * Save code
		 *
		 * @since 1.0.0
		 */
		public function ajax_save() {
			$nonce_action = $this->get_nonce_action();
			$this->check_input_data( $nonce_action, array( 'pstoolkit' ) );
			$pstoolkit  = $this->sanitize_request_payload(
				$_POST['pstoolkit'],
				array(
					'code' => array( $this, 'sanitize_tracking_code' ),
				)
			);
			$id      = isset( $pstoolkit['id'] ) ? $pstoolkit['id'] : 'new';
			$message = esc_html__( 'Der Tracking-Code %s wurde aktualisiert.', 'ub' );
			if ( 'new' === $id ) {
				$message = esc_html__( 'Tracking-Code %s wurde erstellt.', 'ub' );
				$id      = $this->generate_id( $pstoolkit );
			}
			$this->uba->add_message(
				array(
					'type'    => 'success',
					'message' => sprintf( $message, $this->bold( $pstoolkit['title'] ) ),
				)
			);
			$pstoolkit['id'] = $id;
			/**
			 * strip Backslashes
			 */
			if ( isset( $pstoolkit['code'] ) ) {
				$pstoolkit['code'] = stripslashes( $pstoolkit['code'] );
			}
			/**
			 * save
			 */
			$data        = $this->local_get_value();
			$data[ $id ] = $pstoolkit;
			$this->update_value( $data );
			$this->delete_humminbird_cache();
			wp_send_json_success();
		}

		protected function sanitize_tracking_code( $code ) {
			$attrs = array(
				'script' => array(
					'async'          => array(),
					'crossorigin'    => array(),
					'defer'          => array(),
					'integrity'      => array(),
					'nomodule'       => array(),
					'nonce'          => array(),
					'referrerpolicy' => array(),
					'src'            => array(),
					'type'           => array(),
					'charset'        => array(),
					'language'       => array(),
					'data-ad-client' => array(),
				),
			);
			$attrs = apply_filters( 'pstoolkit_tracking_codes_allowed_script_attributes', $attrs );

			return wp_kses( $code, $attrs );
		}

		/**
		 * delete single code
		 *
		 * @since 1.0.0
		 */
		public function ajax_delete() {
			$id           = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$nonce_action = $this->get_nonce_action( $id, 'delete' );
			$this->check_input_data( $nonce_action, array( 'id' ) );
			$data = $this->local_get_value();
			if ( ! isset( $data[ $id ] ) ) {
				$this->json_error();
			}
			$message = esc_html__( 'Tracking-Code %s wurde gelöscht.', 'ub' );
			$this->uba->add_message(
				array(
					'type'    => 'success',
					'message' => sprintf( $message, $this->bold( $data[ $id ]['title'] ) ),
				)
			);
			unset( $data[ $id ] );
			$this->update_value( $data );
			$this->delete_humminbird_cache();
			wp_send_json_success();
		}

		/**
		 * delete bulk codes.
		 *
		 * @since 1.0.0
		 */
		public function ajax_bulk_delete() {
			$id           = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$nonce_action = $this->get_nonce_action( $id, 'delete' );
			$this->check_input_data( $nonce_action, array( 'ids' ) );
			$data   = $this->local_get_value();
			$titles = array();
			$ids    = $this->sanitize_request_payload( $_POST['ids'] );
			if ( ! is_array( $ids ) || empty( $ids ) ) {
				$this->json_error();
			}
			foreach ( $ids as $id ) {
				if ( isset( $data[ $id ] ) ) {
					$titles[] = $this->bold( $data[ $id ]['title'] );
					unset( $data[ $id ] );
				}
			}
			if ( empty( $titles ) ) {
				$this->json_error();
			}
			$message = esc_html(
				_n(
					'Tracking-Code %s wurde gelöscht.',
					'Tracking-Codes %s wurden gelöscht.',
					count( $titles ),
					'ub'
				)
			);
			$this->uba->add_message(
				array(
					'type'    => 'success',
					'message' => sprintf( $message, implode( ', ', $titles ) ),
				)
			);
			$this->update_value( $data );
			$this->delete_humminbird_cache();
			wp_send_json_success();
		}

		/**
		 * Delete Humminbird cache
		 *
		 * @since 1.0.0
		 */
		private function delete_humminbird_cache() {
			if ( class_exists( 'WP_Hummingbird' ) ) {
				$hummingbird = WP_Hummingbird::get_instance();
				if ( is_object( $hummingbird ) ) {
					foreach ( $hummingbird->core->modules as $module ) {
						if ( ! $module->is_active() ) {
							continue;
						}
						$module->clear_cache();
					}
				}
			}
		}

		/**
		 * Replace default by module related
		 */
		public function dialog_delete_attr_filter( $args, $module, $id ) {
			if ( $this->module === $module ) {
				$args['title']       = __( 'Tracking-Code löschen', 'ub' );
				$args['description'] = __( 'Möchtest Du diesen Tracking-Code wirklich dauerhaft löschen?', 'ub' );
				if ( 'bulk' === $id ) {
					$args['title']       = __( 'Tracking-Codes löschen', 'ub' );
					$args['description'] = __( 'Möchtest Du ausgewählte Tracking-Codes wirklich dauerhaft löschen?', 'ub' );
				}
			}
			return $args;
		}

		/**
		 * Save code
		 *
		 * @since 3.0.1
		 */
		public function ajax_reset() {
			$id           = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$nonce_action = $this->get_nonce_action( 'reset', $id );
			$this->check_input_data( $nonce_action );
			$data = $this->local_get_value();
			if ( isset( $data[ $id ] ) ) {
				wp_send_json_success( $data[ $id ] );
			}
			wp_send_json_error();
		}
	}
}
new PSToolkit_Tracking_Codes();
