<?php
/**
 * Runs the checker over published content in small batches and stores the results.
 *
 * @package OpenAccessToolbar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content scanner and site checks.
 */
class OATB_Scanner {

	const OPTION = 'oatb_check';

	/**
	 * Posts per batch request.
	 */
	const BATCH = 10;

	/**
	 * At most this many issues are kept per post.
	 */
	const MAX_ISSUES = 50;

	/**
	 * Post types to scan: public ones with content, except attachments.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );

		/**
		 * Filters the post types the accessibility check scans.
		 *
		 * @param string[] $types Post type names.
		 */
		return array_values( array_filter( (array) apply_filters( 'oatb_check_post_types', $types ), 'post_type_exists' ) );
	}

	/**
	 * Stored results.
	 *
	 * @return array{started:int,finished:int,total:int,done:int,posts:array<int,array<string,mixed>>}
	 */
	public static function results() {
		$saved = get_option( self::OPTION, array() );
		return array_merge(
			array(
				'started'  => 0,
				'finished' => 0,
				'total'    => 0,
				'done'     => 0,
				'posts'    => array(),
			),
			is_array( $saved ) ? $saved : array()
		);
	}

	/**
	 * Starts a new scan and returns the number of posts to check.
	 *
	 * @return int
	 */
	public static function start() {
		$total = self::count_posts();
		update_option(
			self::OPTION,
			array(
				'started'  => time(),
				'finished' => 0,
				'total'    => $total,
				'done'     => 0,
				'posts'    => array(),
			),
			false
		);
		return $total;
	}

	/**
	 * Checks the next batch.
	 *
	 * @param int $offset Posts already checked.
	 * @return array{done:int,total:int,finished:bool}
	 */
	public static function batch( $offset ) {
		$results = self::results();
		$ids     = self::query_ids( max( 0, (int) $offset ), self::BATCH );

		foreach ( $ids as $id ) {
			$issues = self::check_post( $id );
			if ( $issues ) {
				$results['posts'][ $id ] = array(
					'title'  => get_the_title( $id ),
					'type'   => get_post_type( $id ),
					'issues' => array_slice( $issues, 0, self::MAX_ISSUES ),
					'more'   => max( 0, count( $issues ) - self::MAX_ISSUES ),
				);
			} else {
				unset( $results['posts'][ $id ] );
			}
		}

		$results['done'] = min( $results['total'], (int) $offset + count( $ids ) );
		$finished        = count( $ids ) < self::BATCH || $results['done'] >= $results['total'];
		if ( $finished ) {
			$results['finished'] = time();
			$results['done']     = $results['total'];
		}
		update_option( self::OPTION, $results, false );

		return array(
			'done'     => $results['done'],
			'total'    => $results['total'],
			'finished' => $finished,
		);
	}

	/**
	 * Checks one post's rendered content.
	 *
	 * Blocks and shortcodes are rendered so images and embeds they produce are
	 * seen; anything a block or shortcode prints directly is discarded.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{type:string,context:string}>
	 */
	public static function check_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$previous = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		// Rendering blocks needs the global post, as on the front end.
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		ob_start();
		$html = do_shortcode( do_blocks( $post->post_content ) );
		ob_end_clean();

		$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $previous instanceof WP_Post ) {
			setup_postdata( $previous );
		}

		return OATB_Checker::check( $html, 1 );
	}

	/**
	 * Fetches the home page and checks things themes control.
	 *
	 * @return array<int,array{id:string,status:string,label:string,detail:string}>|WP_Error
	 */
	public static function site_check() {
		$response = wp_safe_remote_get(
			home_url( '/' ),
			array(
				'timeout'     => 15,
				'redirection' => 3,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			/* translators: %d: HTTP status code. */
			return new WP_Error( 'oatb_http', sprintf( __( 'Your home page answered with HTTP status %d.', 'open-access-toolbar' ), $code ) );
		}
		return self::analyze_page( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Checks a full HTML page for language, zoom, skip link and main landmark.
	 *
	 * @param string $html Full page HTML.
	 * @return array<int,array{id:string,status:string,label:string,detail:string}>
	 */
	public static function analyze_page( $html ) {
		$p         = new WP_HTML_Tag_Processor( $html );
		$lang      = null;
		$zoom      = true;
		$skip      = false;
		$main      = false;
		$links     = 0;
		$skip_tags = array( 'A', 'HTML', 'META', 'MAIN' );

		while ( $p->next_tag() ) {
			$tag = $p->get_tag();
			if ( ! in_array( $tag, $skip_tags, true ) && 'main' !== $p->get_attribute( 'role' ) ) {
				continue;
			}
			if ( 'HTML' === $tag && null === $lang ) {
				$value = $p->get_attribute( 'lang' );
				$lang  = is_string( $value ) ? trim( $value ) : '';
			} elseif ( 'META' === $tag && 'viewport' === strtolower( (string) $p->get_attribute( 'name' ) ) ) {
				$zoom = self::viewport_allows_zoom( (string) $p->get_attribute( 'content' ) );
			} elseif ( 'A' === $tag && $links < 5 && null !== $p->get_attribute( 'href' ) ) {
				// A skip link is one of the first links on the page and points to an anchor.
				++$links;
				if ( 0 === strpos( (string) $p->get_attribute( 'href' ), '#' ) && strlen( (string) $p->get_attribute( 'href' ) ) > 1 ) {
					$skip = true;
				}
			}
			if ( 'MAIN' === $tag || 'main' === $p->get_attribute( 'role' ) ) {
				$main = true;
			}
		}

		return array(
			array(
				'id'     => 'lang',
				'status' => $lang ? 'pass' : 'fail',
				'label'  => __( 'Page language is set', 'open-access-toolbar' ),
				/* translators: %s: language code such as en-US. */
				'detail' => $lang ? sprintf( __( 'The page declares lang="%s".', 'open-access-toolbar' ), $lang ) : __( 'The <html> tag has no lang attribute, so screen readers may read the page in the wrong language. Your theme should print language_attributes().', 'open-access-toolbar' ),
			),
			array(
				'id'     => 'zoom',
				'status' => $zoom ? 'pass' : 'fail',
				'label'  => __( 'Visitors can zoom', 'open-access-toolbar' ),
				'detail' => $zoom ? __( 'The viewport does not block pinch zoom.', 'open-access-toolbar' ) : __( 'The viewport meta tag blocks zooming (user-scalable=no or a low maximum-scale). Ask your theme author to remove it.', 'open-access-toolbar' ),
			),
			array(
				'id'     => 'skip',
				'status' => $skip ? 'pass' : 'warn',
				'label'  => __( 'Skip link', 'open-access-toolbar' ),
				'detail' => $skip ? __( 'One of the first links jumps to a section of the page.', 'open-access-toolbar' ) : __( 'No "skip to content" link found near the top of the page. You can turn on the skip link in the toolbar settings.', 'open-access-toolbar' ),
			),
			array(
				'id'     => 'main',
				'status' => $main ? 'pass' : 'warn',
				'label'  => __( 'Main content landmark', 'open-access-toolbar' ),
				'detail' => $main ? __( 'The page marks its main content with <main>.', 'open-access-toolbar' ) : __( 'No <main> element found, so screen reader users cannot jump straight to the content.', 'open-access-toolbar' ),
			),
		);
	}

	/**
	 * Whether a viewport meta content value allows pinch zoom.
	 *
	 * @param string $content The content attribute.
	 * @return bool
	 */
	public static function viewport_allows_zoom( $content ) {
		$content = strtolower( preg_replace( '/\s+/', '', $content ) );
		if ( preg_match( '/user-scalable=(no|0)(?:[,;]|$)/', $content ) ) {
			return false;
		}
		if ( preg_match( '/maximum-scale=([0-9.]+)/', $content, $m ) && (float) $m[1] < 2 ) {
			return false;
		}
		return true;
	}

	/**
	 * Number of published posts to scan.
	 *
	 * @return int
	 */
	private static function count_posts() {
		$total = 0;
		foreach ( self::post_types() as $type ) {
			$counts = wp_count_posts( $type );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}
		return $total;
	}

	/**
	 * Published post IDs in a stable order.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return int[]
	 */
	private static function query_ids( $offset, $limit ) {
		$types = self::post_types();
		if ( ! $types ) {
			return array();
		}
		$query = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'posts_per_page'         => $limit,
				'offset'                 => $offset,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);
		return array_map( 'intval', $query->posts );
	}
}
