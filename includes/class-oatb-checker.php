<?php
/**
 * Finds common accessibility problems in a piece of HTML.
 *
 * Pure analysis with WP_HTML_Tag_Processor: no database, no output. Each
 * issue is an array with a `type` (one of the TYPE keys of self::types())
 * and a short `context` that helps the site owner find it in the editor.
 *
 * @package OpenAccessToolbar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML accessibility checker.
 */
class OATB_Checker {

	/**
	 * Input types that need no visible label.
	 *
	 * @var string[]
	 */
	const UNLABELLED_INPUTS = array( 'hidden', 'submit', 'button', 'image', 'reset' );

	/**
	 * Issue types with their labels and a one-line fix hint.
	 *
	 * @return array<string,array{label:string,fix:string}>
	 */
	public static function types() {
		return array(
			'img_alt'       => array(
				'label' => __( 'Image without alt text', 'open-access-toolbar' ),
				'fix'   => __( 'Describe the image in its "Alternative text" field. Use empty alt text only for purely decorative images.', 'open-access-toolbar' ),
			),
			'empty_link'    => array(
				'label' => __( 'Link with no text', 'open-access-toolbar' ),
				'fix'   => __( 'Give the link visible text, or alt text on the image inside it, that says where it goes.', 'open-access-toolbar' ),
			),
			'empty_button'  => array(
				'label' => __( 'Button with no text', 'open-access-toolbar' ),
				'fix'   => __( 'Give the button visible text or an accessible name (aria-label).', 'open-access-toolbar' ),
			),
			'empty_heading' => array(
				'label' => __( 'Empty heading', 'open-access-toolbar' ),
				'fix'   => __( 'Remove the empty heading block or give it text.', 'open-access-toolbar' ),
			),
			'heading_skip'  => array(
				'label' => __( 'Skipped heading level', 'open-access-toolbar' ),
				'fix'   => __( 'Use heading levels in order (H2, then H3 inside it) so screen reader users can follow the structure.', 'open-access-toolbar' ),
			),
			'form_label'    => array(
				'label' => __( 'Form field without a label', 'open-access-toolbar' ),
				'fix'   => __( 'Add a label to the field. Placeholder text alone is not a label.', 'open-access-toolbar' ),
			),
			'iframe_title'  => array(
				'label' => __( 'Embedded frame without a title', 'open-access-toolbar' ),
				'fix'   => __( 'Add a title attribute that says what the embed is, for example "Video: product tour".', 'open-access-toolbar' ),
			),
		);
	}

	/**
	 * Checks an HTML fragment.
	 *
	 * @param string $html          HTML (usually rendered post content).
	 * @param int    $heading_start Heading level that precedes the fragment (1: the post title is the H1).
	 * @return array<int,array{type:string,context:string}>
	 */
	public static function check( $html, $heading_start = 1 ) {
		$issues = array();
		if ( '' === trim( (string) $html ) ) {
			return $issues;
		}

		$p          = new WP_HTML_Tag_Processor( $html );
		$open       = array(); // Elements that need text: links, buttons, headings.
		$label_for  = array();
		$fields     = array();
		$in_label   = 0;
		$last_level = max( 0, (int) $heading_start );
		$svg_depth  = 0;

		while ( $p->next_token() ) {
			$token = $p->get_token_type();

			if ( '#text' === $token ) {
				if ( self::has_text( $p->get_modifiable_text() ) ) {
					self::name_all( $open );
				}
				continue;
			}

			if ( '#tag' !== $token ) {
				continue;
			}

			$tag    = $p->get_tag();
			$closer = $p->is_tag_closer();

			if ( 'SVG' === $tag ) {
				$svg_depth += $closer ? -1 : 1;
				$svg_depth  = max( 0, $svg_depth );
				if ( ! $closer && self::has_accessible_name( $p ) ) {
					self::name_all( $open );
				}
				continue;
			}

			if ( $closer ) {
				if ( 'LABEL' === $tag ) {
					$in_label = max( 0, $in_label - 1 );
				}
				for ( $i = count( $open ) - 1; $i >= 0; $i-- ) {
					if ( $open[ $i ]['tag'] === $tag ) {
						$item = $open[ $i ];
						array_splice( $open, $i );
						if ( ! $item['named'] ) {
							$issues[] = array(
								'type'    => $item['type'],
								'context' => $item['context'],
							);
						}
						break;
					}
				}
				continue;
			}

			if ( $svg_depth > 0 ) {
				// The tag processor reads <title> as one token whose text is its content.
				if ( 'TITLE' === $tag && self::has_text( $p->get_modifiable_text() ) ) {
					self::name_all( $open );
				}
				continue;
			}

			switch ( $tag ) {
				case 'A':
					if ( null !== $p->get_attribute( 'href' ) && ! self::is_hidden( $p ) ) {
						$open[] = array(
							'tag'     => 'A',
							'type'    => 'empty_link',
							'named'   => self::has_accessible_name( $p ),
							'context' => self::short( (string) $p->get_attribute( 'href' ) ),
						);
					}
					break;

				case 'BUTTON':
					if ( ! self::is_hidden( $p ) ) {
						$open[] = array(
							'tag'     => 'BUTTON',
							'type'    => 'empty_button',
							'named'   => self::has_accessible_name( $p ),
							'context' => self::short( self::describe( $p, array( 'class', 'id', 'name' ) ) ),
						);
					}
					break;

				case 'H1':
				case 'H2':
				case 'H3':
				case 'H4':
				case 'H5':
				case 'H6':
					$level = (int) substr( $tag, 1 );
					if ( $last_level > 0 && $level > $last_level + 1 ) {
						$issues[] = array(
							'type'    => 'heading_skip',
							/* translators: 1: previous heading level, 2: this heading level. */
							'context' => sprintf( __( 'H%1$d followed by H%2$d', 'open-access-toolbar' ), $last_level, $level ),
						);
					}
					$last_level = $level;
					$open[]     = array(
						'tag'     => $tag,
						'type'    => 'empty_heading',
						'named'   => self::has_accessible_name( $p ),
						'context' => $tag,
					);
					break;

				case 'IMG':
					$alt = $p->get_attribute( 'alt' );
					if ( is_string( $alt ) && self::has_text( $alt ) ) {
						self::name_all( $open );
					} elseif ( self::has_accessible_name( $p ) ) {
						self::name_all( $open );
					} elseif ( null === $alt ) {
						if ( ! self::is_hidden( $p ) && ! self::is_presentational( $p ) ) {
							$issues[] = array(
								'type'    => 'img_alt',
								'context' => self::short( self::file_name( (string) $p->get_attribute( 'src' ) ) ),
							);
						}
					}
					break;

				case 'LABEL':
					++$in_label;
					$for = $p->get_attribute( 'for' );
					if ( is_string( $for ) && '' !== $for ) {
						$label_for[ $for ] = true;
					}
					break;

				case 'INPUT':
				case 'SELECT':
				case 'TEXTAREA':
					$type = strtolower( (string) $p->get_attribute( 'type' ) );
					if ( 'INPUT' === $tag && in_array( $type, self::UNLABELLED_INPUTS, true ) ) {
						break;
					}
					if ( self::is_hidden( $p ) ) {
						break;
					}
					$id       = $p->get_attribute( 'id' );
					$fields[] = array(
						'id'      => is_string( $id ) ? $id : '',
						'named'   => $in_label > 0 || self::has_accessible_name( $p ),
						'context' => self::short( strtolower( $tag ) . ( '' !== $type ? '[' . $type . ']' : '' ) . self::describe( $p, array( 'name', 'id', 'placeholder' ) ) ),
					);
					break;

				case 'IFRAME':
					$title = $p->get_attribute( 'title' );
					if ( ! ( is_string( $title ) && self::has_text( $title ) ) && ! self::has_accessible_name( $p ) && ! self::is_hidden( $p ) ) {
						$src      = (string) $p->get_attribute( 'src' );
						$host     = wp_parse_url( $src, PHP_URL_HOST );
						$issues[] = array(
							'type'    => 'iframe_title',
							'context' => self::short( $host ? $host : $src ),
						);
					}
					break;
			}
		}

		// Elements never closed (broken markup) are judged as they stand.
		foreach ( $open as $item ) {
			if ( ! $item['named'] ) {
				$issues[] = array(
					'type'    => $item['type'],
					'context' => $item['context'],
				);
			}
		}

		foreach ( $fields as $field ) {
			if ( ! $field['named'] && ! ( '' !== $field['id'] && isset( $label_for[ $field['id'] ] ) ) ) {
				$issues[] = array(
					'type'    => 'form_label',
					'context' => $field['context'],
				);
			}
		}

		return $issues;
	}

	/**
	 * Whether a string has visible characters (non-breaking spaces don't count).
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	private static function has_text( $text ) {
		return '' !== preg_replace( '/[\s\x{00A0}\x{200B}]+/u', '', (string) $text );
	}

	/**
	 * Marks every open link, button and heading as having a name.
	 *
	 * @param array<int,array<string,mixed>> $open Open elements (by reference).
	 */
	private static function name_all( array &$open ) {
		foreach ( $open as $i => $item ) {
			$open[ $i ]['named'] = true;
		}
	}

	/**
	 * Whether the current tag carries an accessible name of its own.
	 *
	 * @param WP_HTML_Tag_Processor $p Processor on an opening tag.
	 * @return bool
	 */
	private static function has_accessible_name( $p ) {
		foreach ( array( 'aria-label', 'aria-labelledby', 'title' ) as $attribute ) {
			$value = $p->get_attribute( $attribute );
			if ( is_string( $value ) && self::has_text( $value ) ) {
				return true;
			}
		}
		if ( 'INPUT' === $p->get_tag() ) {
			$value = $p->get_attribute( 'value' );
			$type  = strtolower( (string) $p->get_attribute( 'type' ) );
			return in_array( $type, array( 'submit', 'button', 'reset' ), true ) && is_string( $value ) && self::has_text( $value );
		}
		return false;
	}

	/**
	 * Whether the current tag is hidden from assistive technology.
	 *
	 * @param WP_HTML_Tag_Processor $p Processor on an opening tag.
	 * @return bool
	 */
	private static function is_hidden( $p ) {
		return 'true' === $p->get_attribute( 'aria-hidden' ) || null !== $p->get_attribute( 'hidden' );
	}

	/**
	 * Whether the current tag is marked as decorative.
	 *
	 * @param WP_HTML_Tag_Processor $p Processor on an opening tag.
	 * @return bool
	 */
	private static function is_presentational( $p ) {
		$role = strtolower( (string) $p->get_attribute( 'role' ) );
		return 'presentation' === $role || 'none' === $role;
	}

	/**
	 * A short "#id .class name=..." description of the current tag.
	 *
	 * @param WP_HTML_Tag_Processor $p          Processor on an opening tag.
	 * @param string[]              $attributes Attributes to include, in order.
	 * @return string
	 */
	private static function describe( $p, array $attributes ) {
		$parts = array();
		foreach ( $attributes as $attribute ) {
			$value = $p->get_attribute( $attribute );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$parts[] = $attribute . '="' . trim( $value ) . '"';
			}
		}
		return $parts ? ' ' . implode( ' ', $parts ) : '';
	}

	/**
	 * The last path segment of a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function file_name( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name = basename( $path );
		return '' !== $name ? $name : $url;
	}

	/**
	 * Trims a context string to a readable length.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function short( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 90 ) {
			return mb_substr( $text, 0, 87 ) . '...';
		}
		return strlen( $text ) > 90 ? substr( $text, 0, 87 ) . '...' : $text;
	}
}
