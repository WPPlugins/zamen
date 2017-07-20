<?php

/**
 * Class ZamenResponse
 */
class ZamenResponse {

	/**
	 * @var
	 */
	public $page;
	/**
	 * @var
	 */
	public $limit;
	/**
	 * @var
	 */
	public $postId;
	/**
	 * @var
	 */
	public $userEmail;

	/**
	 * @return json
	 */
	function list_filters() {
		$filters = $this->get_user_filters_info( 'the_content' );

		return $this->response( $filters );
	}

	/**
	 * @return json
	 */
	function list_posts() {

		$offset = ( (int) $this->page - 1 ) * (int) $this->limit;

		$data         = array(
			'version' => ZAMEN_VERSION,
			'charset' => get_option( 'blog_charset' ),
			'page'    => (int) $this->page,
			'limit'   => (int) $this->limit,

			'posts' => array(),
		);
		$posts        = array();
		$args         = array(
			'numberposts' => $this->limit,
			'offset'      => $offset,
			'orderby'     => 'post_date',
			'order'       => 'DESC',
			'post_type'   => 'post',
			'post_status' => 'publish',

		);
		$recent_posts = wp_get_recent_posts( $args );
		foreach ( $recent_posts as $recent ) {

			list( $thumbnail, $thumbnail_width, $thumbnail_height ) = wp_get_attachment_image_src( get_post_thumbnail_id( $recent["ID"] ), 'large' ); //thumbnail, medium, large, or full

			$author     = get_userdata( (int) $recent['post_author'] );
			$categories = wp_get_post_categories( $recent["ID"] );
			$cats       = array();
			foreach ( $categories as $c ) {
				$cat    = get_category( $c );
				$cats[] = $cat->name;
			}

			$comments_count = wp_count_comments( $recent["ID"] );

			$applyFilters = explode( ',', (string) @$_GET['f'] );

			$this->remove_all_user_filters( 'the_content', $applyFilters );

			$content = apply_filters( 'the_content', @$recent["post_content"] );

			$posts[] = array(
				'id'                => (int) @$recent["ID"],
				'author'            => @$author->display_name,
				'categories'        => (array) $cats,
				'url'               => (string) @get_permalink( $recent["ID"] ),
				'date'              => (string) $recent["post_date_gmt"],
				'thumbnail'         => (string) @$thumbnail,
				'thumbnail_width'   => (int) @$thumbnail_width,
				'thumbnail_height'  => (int) @$thumbnail_height,
				'title'             => (string) @$recent["post_title"],
				'notification_text' => (string) get_post_meta( (int) @$recent["ID"], '_zamen_post_push_text_content', true ),
				'content'           => (string) $content,
				'comments_count'    => (int) $comments_count->approved,
				'date_modified'     => (string) @$recent["post_modified_gmt"],
			);
		}

		$data['posts'] = $posts;

		return $this->response( $data );
	}

	/**
	 * @return json
	 */
	function list_post_comments() {

		if ( empty( $this->postId ) ) {
			return $this->response( array() );
		}

		$post         = get_post( $this->postId );
		$postAuthorId = (int) $post->post_author;

		$page = $_GET['page_number'];
		if ( empty( $page ) ) {
			$page = 1;
		}

		$paramCount    = array(
			'post_id' => $this->postId,
			'status'  => 'approve',
			'count'   => true,
		);
		$commentsCount = get_comments( $paramCount );

		$paramParentCount    = array(
			'post_id' => $this->postId,
			'status'  => 'approve',
			'parent'  => 0,
			'count'   => true,
		);
		$parentCommentsCount = get_comments( $paramParentCount );

		$comments_per_page = 15; //get_option( 'comments_per_page' );
		$totalPages        = ceil( $parentCommentsCount / $comments_per_page );

		$param    = array(
			'post_id' => $this->postId,
			'status'  => 'approve',
			'order'   => 'DESC',
			'parent'  => 0,
			'number'  => $comments_per_page,
			'offset'  => ( ( $page - 1 ) * $comments_per_page ),
		);
		$comments = get_comments( $param );

		$commentsArray = array();
		foreach ( $comments as $comment ) {
			$commentArray = $this->buildCommentArray( $comment, $postAuthorId );
			$commentsData = (array) $commentArray;

			$childParam        = array(
				'post_id' => $this->postId,
				'status'  => 'approve',
				'order'   => 'ASC',
				'parent'  => (int) $comment->comment_ID
			);
			$childComments     = get_comments( $childParam );
			$childCommentArray = array();
			if ( ! empty( $childComments ) ) {
				foreach ( $childComments as $childComment ) {
					$childCommentArray[] = $this->buildCommentArray( $childComment, $postAuthorId );
				}
			}
			$commentsData['children'] = (array) $childCommentArray;

			$commentsArray[] = $commentsData;
		}

		$commentsList['current_page'] = (int) @$page;
		$commentsList['last_page']    = (int) @$totalPages;
		$commentsList['total']        = (int) @$commentsCount;
		$commentsList['per_page']     = (int) @$this->limit;

		$commentsList['data'] = (array) $commentsArray;

		return $this->response( $commentsList );
	}

	/**
	 * @return json
	 */
	function list_post_user_comments() {

		if ( empty( $this->postId ) || empty( $this->userEmail ) ) {
			return $this->response( array() );
		}

		// User Id
		$post         = get_post( $this->postId );
		$postAuthorId = (int) $post->post_author;

		$param    = array(
			'post_id'      => $this->postId,
			'author_email' => $this->userEmail,
			'status'       => 'approve',
			'order'        => 'DESC',
		);
		$comments = get_comments( $param );

		$commentsArray = array();
		foreach ( $comments as $comment ) {
			// check if not parent get his parent comment
			if ( (int) $comment->comment_parent != 0 ) {
				$comment = get_comment( $comment->comment_parent, $comment );
			}

			$commentArray = $this->buildCommentArray( $comment, $postAuthorId );
			$commentsData = (array) $commentArray;

			$childParam        = array(
				'post_id' => $this->postId,
				'status'  => 'approve',
				'order'   => 'ASC',
				'parent'  => (int) $comment->comment_ID
			);
			$childComments     = get_comments( $childParam );
			$childCommentArray = array();
			if ( ! empty( $childComments ) ) {
				foreach ( $childComments as $childComment ) {
					$childCommentArray[] = $this->buildCommentArray( $childComment, $postAuthorId );
				}
			}
			$commentsData['children'] = (array) $childCommentArray;

			$commentsArray[] = $commentsData;
		}

		$commentsList['current_page'] = (int) 1;
		$commentsList['last_page']    = (int) 1;
		$commentsList['total']        = (int) 0;
		$commentsList['data']         = (array) $commentsArray;

		return $this->response( $commentsList );
	}

	/**
	 * @return string
	 */
	function post_comment() {
		nocache_headers();

		$comment = wp_handle_comment_submission( wp_unslash( $_POST ) );
		if ( is_wp_error( $comment ) ) {
			$data = $comment->get_error_data();
			if ( ! empty( $data ) ) {
				return (string) $comment->get_error_message();
			} else {
				return 'general_error';
			}
		}

		return '';
	}


	/**
	 * @param $response
	 *
	 * @return json
	 */
	function response( $response ) {
		return $response;
	}

	/**
	 * Build comment array
	 *
	 * @param $comment
	 * @param $postAuthorId
	 *
	 * @return array
	 */
	function buildCommentArray( $comment, $postAuthorId ) {
		$comment_content = $comment->comment_content;
		$comment_content = preg_replace( '/<a.*?href="([^"]+)"[^>]*>([^>]*)<\/a>/i', '$2 [ $1 ] ', $comment_content );
		$comment_content = strip_tags( $comment_content );
		$comment_content = stripslashes( $comment_content );
		$comment_content = html_entity_decode( $comment_content );

		$comment_content = preg_replace( "/\r/", "", $comment_content );
		$comment_content = preg_replace( "/\n+/", "<br/>", $comment_content );
		$comment_content = preg_replace( '/\s+/', ' ', $comment_content );
		$comment_content = preg_replace( "!<br/>!", "\n", $comment_content );

		$comment_author_types = array(
			'manager'     => 1,
			'post_author' => 2,
			'guest'       => 4,
		);

		$commentArray                 = array();
		$commentArray['id']           = (int) $comment->comment_ID;
		$commentArray['author']       = stripslashes( $comment->comment_author ); // display name
		$commentArray['author_photo'] = get_avatar_url( $comment->comment_author_email, 120 );
		$commentArray['author_url']   = $comment->comment_author_url;
		$commentArray['date']         = strtotime( $comment->comment_date_gmt );
		$commentArray['content']      = $comment_content; // content
		$commentArray['parent']       = (int) $comment->comment_parent;
		$commentArray['author_type']  = $comment_author_types['guest'];

		if ( ! empty( $comment->user_id ) ) {
			$user       = new WP_User( $comment->user_id );
			$user_roles = $user->roles;
			if ( in_array( 'administrator', $user_roles ) ) {
				$commentArray['author_type'] = $comment_author_types['manager']; // manager
			}
		}

		if ( (int) $comment->user_id == $postAuthorId ) { // author
			$commentArray['author_type'] = $comment_author_types['post_author'];
		}

		return $commentArray;
	}

	function remove_all_user_filters( $tag = '', $keep ) {
		$filters = $this->get_user_filters_info( $tag );

		foreach ( $filters as $filter ) {
			if ( ! in_array( $filter['name'], (array) $keep ) ) {
				// $filter['k'] - filter key name // $filter['p'] - filter priority
				remove_filter( $tag, $filter['k'], $filter['p'] );
			}
		}
	}

	function get_user_filters_info( $tag = '' ) {
		global $wp_filter;

		$filters = isset( $wp_filter[ $tag ] ) ? $wp_filter[ $tag ] : array();

		$filtersNames = array();
		foreach ( $filters as $priority => $hooks ) {
			foreach ( $hooks as $itemKey => &$item ) {
				// function name as string or static class method eg. 'Foo::Bar'
				if ( is_string( $item['function'] ) ) {
					$ref          = strpos( $item['function'], '::' ) ? new ReflectionClass( strstr( $item['function'], '::', true ) ) : new ReflectionFunction( $item['function'] );
					$item['file'] = $ref->getFileName();
					$item['line'] = get_class( $ref ) == 'ReflectionFunction'
						? $ref->getStartLine()
						: $ref->getMethod( substr( $item['function'], strpos( $item['function'], '::' ) + 2 ) )->getStartLine();

				} else if ( is_array( $item['function'] ) ) {
					$ref = new ReflectionClass( $item['function'][0] );

					$item['function'] = array(
						is_object( $item['function'][0] ) ? get_class( $item['function'][0] ) : $item['function'][0],
						$item['function'][1]
					);

					$item['file'] = $ref->getFileName();
					$item['line'] = strpos( $item['function'][1], '::' )
						? $ref->getParentClass()->getMethod( substr( $item['function'][1], strpos( $item['function'][1], '::' ) + 2 ) )->getStartLine()
						: $ref->getMethod( $item['function'][1] )->getStartLine();

				} elseif ( is_callable( $item['function'] ) ) {

					$ref = new ReflectionFunction( $item['function'] );

					$item['function'] = end( get_class( $item['function'] ) );

					$item['file'] = $ref->getFileName();
					$item['line'] = $ref->getStartLine();
				}

				// Ignore wordpress filters
				if ( strpos( $item['file'], '/wp-includes/' ) === false ) {
					// get plugins and themes filters file path
					$filePath = explode( 'wp-content', $item['file'] );
					$fileName = trim( $filePath[1], '.php' );
					$fileName = trim( $fileName, '/' );

					$filtersNames[] = array(
						'name' => is_array( $item['function'] ) ? implode( '||', $item['function'] ) : $item['function'],
						'f'    => base64_encode( $fileName ),
						'l'    => (int) $item['line'],
						'k'    => $itemKey,
						'p'    => $priority,
					);
				}

			}
		}

		return $filtersNames;
	}

}