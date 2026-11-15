<?php
/**
 * Self-contained static review rules for taxocommerce-logistica.
 *
 * These rules reflect common WordPress.org review criteria; they do not
 * execute, bundle or claim equivalence with the official Plugin Check.
 *
 * @package TaxoCommerceLogistica
 */

defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_Logistica_Code_Review_Rules {

	/**
	 * Scan PHP tokens without executing the inspected source.
	 *
	 * @param string $source   File contents.
	 * @param string $relative Path relative to Core.
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function scan( $source, $relative ) {
		$tokens    = token_get_all( $source );
		$scopes    = self::spans( $tokens, 'function' );
		$classes   = self::spans( $tokens, 'class' );
		$findings  = array( 'php' => array(), 'wordpress' => array(), 'security' => array() );
		$seen      = array();
		$count     = count( $tokens );
		$has_files = false;
		$namespace = false;

		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && T_VARIABLE === $token[0] && '$_FILES' === $token[1] ) {
				$has_files = true;
			}
			if ( is_array( $token ) && T_NAMESPACE === $token[0] ) {
				$namespace = true;
			}
		}

		for ( $i = 0; $i < $count; ++$i ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				continue;
			}
			$id   = $token[0];
			$text = $token[1];
			$line = $token[2];

			if ( T_VARIABLE === $id ) {
				$scope = self::innermost( $scopes, $i );
				$body  = $scope ? $scope['body'] : '';
				$key   = $scope ? $scope['start'] : 'global';

				if ( in_array( $text, array( '$_POST', '$_REQUEST' ), true ) ) {
					if ( ! preg_match( '/\b(?:check_admin_referer|check_ajax_referer|wp_verify_nonce)\s*\(/i', $body ) ) {
						self::add( $findings, $seen, 'security', 'review_request_nonce', $line, $key );
					}
					if ( ! preg_match( '/\b(?:current_user_can|user_can)\s*\(/i', $body ) ) {
						self::add( $findings, $seen, 'security', 'review_request_capability', $line, $key );
					}
					if ( ! preg_match( '/\b(?:sanitize_[a-z_]+|absint|intval|filter_input|wp_kses|esc_sql)\s*\(/i', $body ) ) {
						self::add( $findings, $seen, 'security', 'review_request_sanitization', $line, $key );
					}
				}

				if ( '$_FILES' === $text ) {
					// A direct upload needs path, size, MIME and upload-error validation.
					// The presence of wp_handle_upload() alone is not sufficient proof.
					self::add( $findings, $seen, 'security', 'review_file_upload', $line, $key );
				}
				if ( '$_SESSION' === $text ) {
					self::add( $findings, $seen, 'php', 'review_php_session', $line, $key );
				}
			}

			if ( $has_files && T_CONSTANT_ENCAPSED_STRING === $id && 'tmp_name' === trim( $text, "'\"" ) ) {
				self::add( $findings, $seen, 'security', 'review_upload_tmp_name', $line, $line );
			}

			if ( T_ECHO === $id || T_PRINT === $id ) {
				$next = self::next( $tokens, $i );
				if ( null !== $next && is_array( $tokens[ $next ] ) && T_VARIABLE === $tokens[ $next ][0] ) {
					$scope = self::innermost( $scopes, $i );
					$body  = $scope ? $scope['body'] : '';
					// JSON endpoints emit JSON, not HTML, and must not be esc_html()-escaped.
					if ( ! ( '$json' === $tokens[ $next ][1] && false !== strpos( $body, 'application/json' ) ) ) {
						self::add( $findings, $seen, 'security', 'review_unescaped_output', $line, $line );
					}
				}
			}

			if ( T_CLASS === $id || T_INTERFACE === $id || T_TRAIT === $id || ( defined( 'T_ENUM' ) && T_ENUM === $id ) ) {
				$name_index = self::next( $tokens, $i );
				if ( ! $namespace && null !== $name_index && is_array( $tokens[ $name_index ] )
					&& T_STRING === $tokens[ $name_index ][0]
					&& ! self::prefixed( $tokens[ $name_index ][1] ) ) {
					self::add( $findings, $seen, 'wordpress', 'review_generic_class_name', $line, $line );
				}
			}

			if ( T_FUNCTION === $id && ! $namespace && ! self::innermost( $classes, $i ) ) {
				$name_index = self::next( $tokens, $i );
				if ( null !== $name_index && '&' === $tokens[ $name_index ] ) {
					$name_index = self::next( $tokens, $name_index );
				}
				if ( null !== $name_index && is_array( $tokens[ $name_index ] )
					&& T_STRING === $tokens[ $name_index ][0]
					&& ! self::prefixed( $tokens[ $name_index ][1] ) ) {
					self::add( $findings, $seen, 'wordpress', 'review_generic_function_name', $line, $line );
				}
			}

			if ( T_STRING !== $id ) {
				continue;
			}
			$name = strtolower( $text );
			$next = self::next( $tokens, $i );
			$prev = self::previous( $tokens, $i );
			$call = null !== $next && '(' === $tokens[ $next ];
			$builtin_call = $call && ( null === $prev || ! is_array( $tokens[ $prev ] )
				|| ! in_array( $tokens[ $prev ][0], array( T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON ), true ) );

			if ( 'plugin_upgrader' === $name && null !== $prev && is_array( $tokens[ $prev ] ) && T_NEW === $tokens[ $prev ][0] ) {
				self::add( $findings, $seen, 'wordpress', 'review_plugin_installation', $line, $line );
			}
			if ( $builtin_call && in_array( $name, array( 'activate_plugin', 'deactivate_plugins' ), true ) ) {
				self::add( $findings, $seen, 'wordpress', 'review_plugin_lifecycle_' . $name, $line, $line );
			}
			if ( $builtin_call && 'session_start' === $name ) {
				self::add( $findings, $seen, 'php', 'review_php_session', $line, $line );
			}
			if ( $builtin_call && 'ob_start' === $name ) {
				$scope = self::innermost( $scopes, $i );
				$body  = $scope ? $scope['body'] : '';
				$opens = preg_match_all( '/\bob_start\s*\(/i', $body );
				$closes = preg_match_all( '/\b(?:ob_get_clean|ob_get_flush|ob_end_clean|ob_end_flush)\s*\(/i', $body );
				if ( ! $scope || $opens > $closes ) {
					self::add( $findings, $seen, 'wordpress', 'review_unclosed_output_buffer', $line, $scope ? $scope['start'] : $line );
				} elseif ( false === stripos( $body, 'finally' ) && preg_match( '/\breturn\b/i', $body ) ) {
					// Same-scope close exists, but early exits may bypass it.
					self::add( $findings, $seen, 'wordpress', 'review_output_buffer_flow', $line, $scope['start'] );
				}
			}
			if ( $builtin_call && 'wp_add_inline_style' === $name ) {
				$args = self::argument_tokens( $tokens, $next );
				$argument = isset( $args[1] ) ? $args[1] : array();
				foreach ( $argument as $arg_token ) {
					if ( is_array( $arg_token ) && T_VARIABLE === $arg_token[0] ) {
						self::add( $findings, $seen, 'security', 'review_dynamic_inline_style', $line, $line );
						break;
					}
				}
			}

			if ( $builtin_call && 'define' === $name ) {
				$literal = self::first_literal( $tokens, $next );
				if ( '' !== $literal && ! self::prefixed( $literal ) && ! $namespace ) {
					self::add( $findings, $seen, 'wordpress', 'review_generic_constant_name', $line, $line );
				}
			}
			if ( $builtin_call && in_array( $name, array( 'apply_filters', 'add_filter', 'do_action', 'add_action' ), true ) ) {
				$hook = self::first_literal( $tokens, $next );
				// Names used by the old, unrelated SEO subsystem are not
				// sufficiently namespaced for a WordPress.org Core release.
				if ( 0 === strpos( $hook, 'seo_reports_' ) ) {
					self::add( $findings, $seen, 'wordpress', 'review_nonprefixed_hook', $line, $line );
				}
			}

			if ( in_array( $name, array( 'query', 'get_results', 'get_var', 'get_row', 'get_col' ), true )
				&& $call && null !== $prev && is_array( $tokens[ $prev ] ) && T_OBJECT_OPERATOR === $tokens[ $prev ][0] ) {
				$receiver = self::previous( $tokens, $prev );
				if ( null !== $receiver && is_array( $tokens[ $receiver ] )
					&& T_VARIABLE === $tokens[ $receiver ][0] && '$wpdb' === $tokens[ $receiver ][1] ) {
					$args = self::argument_tokens( $tokens, $next );
					$arg_text = self::render_tokens( $args[0] ?? array() );
					if ( preg_match( '/\$[a-z_][a-z0-9_]*/i', $arg_text )
						&& ! preg_match( '/->\s*prepare\s*\(/i', $arg_text ) ) {
						self::add( $findings, $seen, 'security', 'review_dynamic_sql_query', $line, $line );
					}
				}
			}
		}

		return $findings;
	}

	/** Bound repeated messages while preserving different locations. */
	private static function add( &$findings, &$seen, $group, $rule, $line, $scope_key ) {
		$identifier = $group . ':' . $rule . ':' . $scope_key;
		if ( isset( $seen[ $identifier ] ) ) {
			return;
		}
		$seen[ $identifier ] = true;
		$existing = 0;
		foreach ( $findings[ $group ] as $finding ) {
			if ( $rule === $finding['rule'] ) {
				++$existing;
			}
		}
		if ( $existing < 100 ) {
			$findings[ $group ][] = array(
				'rule'     => $rule,
				'line'     => (int) $line,
				'origin'   => 'local_static_review',
				'severity' => 'review',
			);
		}
	}

	/** A collision-resistant prefix, not generic PHP or WordPress names. */
	private static function prefixed( $name ) {
		$name = strtolower( $name );
		// Accept established TaxoCommerce family prefixes in standalone plugins.
		return 0 === strpos( $name, 'taxocommerce_' )
			|| 0 === strpos( $name, 'taxo_commerce_' )
			|| 0 === strpos( $name, 'tci_' )
			|| 0 === strpos( $name, 'tcs_' )
			|| 0 === strpos( $name, 'tctasks_' );
	}

	private static function next( $tokens, $from ) {
		$n = count( $tokens );
		for ( $i = $from + 1; $i < $n; ++$i ) {
			if ( ! is_array( $tokens[ $i ] ) || ! in_array( $tokens[ $i ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				return $i;
			}
		}
		return null;
	}

	private static function previous( $tokens, $from ) {
		for ( $i = $from - 1; $i >= 0; --$i ) {
			if ( ! is_array( $tokens[ $i ] ) || ! in_array( $tokens[ $i ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				return $i;
			}
		}
		return null;
	}

	private static function first_literal( $tokens, $opening ) {
		$index = self::next( $tokens, $opening );
		if ( null === $index || ! is_array( $tokens[ $index ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $index ][0] ) {
			return '';
		}
		return trim( $tokens[ $index ][1], "'\"" );
	}

	/** Return arguments without being confused by nested PHP function calls. */
	private static function argument_tokens( $tokens, $opening ) {
		$args = array( array() );
		$depth = 1;
		$index = 0;
		for ( $i = $opening + 1, $n = count( $tokens ); $i < $n && $depth > 0; ++$i ) {
			$token = $tokens[ $i ];
			if ( '(' === $token ) {
				++$depth;
			}
			if ( ')' === $token ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			}
			if ( ',' === $token && 1 === $depth ) {
				++$index;
				$args[ $index ] = array();
				continue;
			}
			$args[ $index ][] = $token;
		}
		return $args;
	}

	private static function render_tokens( $tokens ) {
		$body = '';
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML ), true ) ) {
				continue;
			}
			$body .= is_array( $token ) ? $token[1] : $token;
		}
		return $body;
	}

	/** Determine function and class bodies for per-handler permission checks. */
	private static function spans( $tokens, $kind ) {
		$result = array();
		$length = count( $tokens );
		for ( $i = 0; $i < $length; ++$i ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				continue;
			}
			$class_type = in_array( $token[0], array( T_CLASS, T_INTERFACE, T_TRAIT ), true )
				|| ( defined( 'T_ENUM' ) && T_ENUM === $token[0] );
			if ( ( 'function' === $kind && T_FUNCTION !== $token[0] )
				|| ( 'class' === $kind && ! $class_type ) ) {
				continue;
			}
			$parentheses = 0;
			$opening = null;
			for ( $j = $i + 1; $j < $length; ++$j ) {
				if ( '(' === $tokens[ $j ] ) {
					++$parentheses;
				} elseif ( ')' === $tokens[ $j ] ) {
					--$parentheses;
				} elseif ( '{' === $tokens[ $j ] && 0 === $parentheses ) {
					$opening = $j;
					break;
				} elseif ( ';' === $tokens[ $j ] && 0 === $parentheses ) {
					break;
				}
			}
			if ( null === $opening ) {
				continue;
			}
			$braces = 1;
			$end = $opening;
			for ( $j = $opening + 1; $j < $length; ++$j ) {
				if ( '{' === $tokens[ $j ] || ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
					++$braces;
				} elseif ( '}' === $tokens[ $j ] ) {
					--$braces;
					if ( 0 === $braces ) {
						$end = $j;
						break;
					}
				}
			}
			$result[] = array(
				'start' => $opening,
				'end'   => $end,
				'body'  => self::render_tokens( array_slice( $tokens, $opening, $end - $opening + 1 ) ),
			);
		}
		return $result;
	}

	private static function innermost( $spans, $offset ) {
		$result = null;
		foreach ( $spans as $span ) {
			if ( $span['start'] <= $offset && $offset <= $span['end']
				&& ( null === $result || $span['start'] > $result['start'] ) ) {
				$result = $span;
			}
		}
		return $result;
	}
}
