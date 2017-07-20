<?php
require_once ZAMEN_PLUGIN_PATH . '/includes/zamen-response-class.php';

/**
 * Class Zamen
 */
class Zamen {
	/**
	 * Zamen constructor.
	 */
	public function __construct() {
		register_activation_hook( ZAMEN_PLUGIN_MAIN_FILE, array( $this, 'activation_check' ) );
		add_action( 'admin_init', array( $this, 'check_version' ) );
		// Don't run anything else in the plugin, if we're on an incompatible env
		if ( self::not_compatible_version() ) {
			return;
		}

		// admin
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'add_meta_boxes', array( $this, 'post_push_text_add_meta_box' ) );
		add_action( 'save_post', array( $this, 'post_push_text_save_meta_box_data' ) );
		// frontend
		add_filter( 'query_vars', array( $this, 'add_query_vars_filter' ) );
		add_action( 'parse_request', array( $this, 'url_handler' ) );
	}


	// The primary check, automatically disable the plugin on activation if it doesn't meet minimum requirements.
	static function activation_check() {
		$not_compatible_version = self::not_compatible_version();
		if ( $not_compatible_version ) {
			deactivate_plugins( ZAMEN_PLUGIN_NAME );
			wp_die( $not_compatible_version, ZAMEN_PLUGIN_NAME );
		}
	}

	// The backup check, in case the plugin is activated in a weird way,
	// or the versions change after activation.
	function check_version() {
		if ( self::not_compatible_version() ) {
			if ( is_plugin_active( ZAMEN_PLUGIN_NAME ) ) {
				deactivate_plugins( ZAMEN_PLUGIN_NAME );
				add_action( 'admin_notices', array( $this, 'disabled_notice' ) );
				if ( isset( $_GET['activate'] ) ) {
					unset( $_GET['activate'] );
				}
			}
		}
	}

	function disabled_notice() {
		echo '<strong>Zamen requires WordPress ' . MINIMUM_WORDPRESS_VERSION . ' or higher AND PHP ' . MINIMUM_PHP_VERSION . ' or higher!', ZAMEN_PLUGIN_NAME . '</strong>';
	}

	static function not_compatible_version() {
		if ( version_compare( PHP_VERSION, MINIMUM_PHP_VERSION, '<' ) ) {
			return '<strong>Zamen requires PHP ' . MINIMUM_PHP_VERSION . ' or higher! current version is ' . PHP_VERSION . '</strong>';
		}
		if ( version_compare( $GLOBALS['wp_version'], MINIMUM_WORDPRESS_VERSION, '<' ) ) {
			return '<strong>Zamen requires WordPress ' . MINIMUM_WORDPRESS_VERSION . ' or higher!</strong>';
		}

		return false;
	}

	function add_query_vars_filter( $vars ) {
		$vars[] = "zamen_action";
		$vars[] = "page";
		$vars[] = "limit";
		$vars[] = "post_id";
		$vars[] = "user_email";
		// timestamp
		$vars[] = "t";

		return $vars;
	}

	/**
	 * add plugin links to admin menu
	 */
	public function register_admin_menu() {
		add_utility_page( 'زامن - Zamen', 'زامن - Zamen', 'edit_posts', 'zamen-about', array( $this, 'zamen_about_page_callback' ), ZAMEN_PLUGIN_URL . '/img/zamen-16.png' );
	}

	/**
	 * render admin about zamen page
	 */
	function zamen_about_page_callback() {
		require_once ZAMEN_PLUGIN_PATH . '/includes/about.page.php';
	}

	/**
	 *
	 */
	function post_push_text_add_meta_box() {
		$post_type = 'post';
		$context   = 'side';
		$priority  = 'high';
		add_meta_box( 'zamen_post_push_text_content', __( 'نص التنبيه لبرنامج زامن' ), array( $this, 'post_push_text_content_meta_box' ), $post_type, $context, $priority );
	}

	/**
	 * Render push message text box
	 *
	 * @param $post
	 */
	function post_push_text_content_meta_box( $post ) {
		$value = get_post_meta( $post->ID, '_zamen_post_push_text_content', true );
		echo '<div><textarea name="zamen_post_push_text_content" class="large-text">' . trim( $value ) . '</textarea></div>';
	}

	/**
	 * When the post is saved, saves our custom data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	function post_push_text_save_meta_box_data( $post_id ) {
		// If this is just a revision, don't contain.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'post' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		if ( ! isset( $_POST['zamen_post_push_text_content'] ) ) {
			return;
		}

		$my_data = strip_tags( $_POST['zamen_post_push_text_content'] );

		// Update the meta field in the database.
		update_post_meta( $post_id, '_zamen_post_push_text_content', $my_data );
	}

	/**
	 * Handle Zamen requests
	 *
	 * @param $query
	 */
	function url_handler( $query ) {
		$allowedActions = array(
			'list_posts',
			'list_post_comments',
			'list_post_user_comments',
			'post_comment',
			'list_filters',
		);
		$zamenAction    = (string) @$query->query_vars["zamen_action"];
		$page           = @$query->query_vars["page"] ? (int) $query->query_vars["page"] : 1;
		$limit          = @$query->query_vars["limit"] ? (int) $query->query_vars["limit"] : 1;
		$postId         = (int) @$query->query_vars["post_id"];
		$userEmail      = (string) @$query->query_vars["user_email"];

		if ( $limit > 30 ) {
			$limit = 30;
		}
		// If coming zamen request
		if ( in_array( $zamenAction, $allowedActions ) ) {
			$response            = new ZamenResponse;
			$response->page      = $page;
			$response->limit     = $limit;
			$response->postId    = $postId;
			$response->userEmail = $userEmail;

			// return response ans exit
			@header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			echo json_encode( $response->$zamenAction() );
			exit;
		}
	}
}