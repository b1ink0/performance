<?php
/**
 * Theme related functions for View Transitions.
 *
 * @package view-transitions
 * @since 1.0.0
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Polyfills theme support for 'view-transitions', regardless of the theme.
 *
 * In WordPress Core, the 'view-transitions' feature may end up as an optional feature, or it may be added by default.
 * In any case, in the scope of the plugin it does not make sense to have the feature as opt-in, since it is the entire
 * purpose of the plugin.
 *
 * Therefore, this function will unconditionally add support with the default configuration, unless the theme itself
 * actually added support for it already.
 *
 * This function must run at the latest possible priority for `after_setup_theme`.
 *
 * @since 1.0.0
 * @access private
 *
 * @global bool|null            $plvt_has_theme_support_with_args Whether the current theme explicitly supports view transitions with custom config.
 * @global array<string, mixed> $_wp_theme_features               Theme support features added and their arguments.
 */
function plvt_polyfill_theme_support(): void {
	global $plvt_has_theme_support_with_args, $_wp_theme_features;

	if ( current_theme_supports( 'view-transitions' ) ) {
		// If the current theme actually supports view transitions with a custom config, set a flag to inform the user.
		if ( isset( $_wp_theme_features['view-transitions'] ) && true !== $_wp_theme_features['view-transitions'] ) {
			$plvt_has_theme_support_with_args = true;
		}
		return;
	}

	add_theme_support( 'view-transitions' );
}

/**
 * Sanitizes theme support arguments for the 'view-transitions' feature.
 *
 * If the feature was part of WordPress Core, the logic of this function would become part of the `add_theme_support()`
 * function instead. There is no action or filter that could be used though, hence it is implemented here in a separate
 * function that runs after `after_setup_theme`, but before the 'view-transitions' feature arguments are possibly used.
 *
 * @since 1.0.0
 * @access private
 *
 * @global bool|null            $plvt_has_theme_support_with_args Whether the current theme explicitly supports view transitions with custom config.
 * @global array<string, mixed> $_wp_theme_features               Theme support features added and their arguments.
 */
function plvt_sanitize_view_transitions_theme_support(): void {
	global $plvt_has_theme_support_with_args, $_wp_theme_features;

	if ( ! isset( $_wp_theme_features['view-transitions'] ) ) {
		$plvt_has_theme_support_with_args = false;
		return;
	}

	$args                             = $_wp_theme_features['view-transitions'];
	$plvt_has_theme_support_with_args = true !== $args;

	$defaults = array(
		'post-selector'                     => '.wp-block-post.post, article.post, body.single main',
		'global-transition-names'           => array(
			'header' => 'header',
			'main'   => 'main',
		),
		'post-transition-names'             => array(
			'.wp-block-post-title, .entry-title'     => 'post-title',
			'.wp-post-image'                         => 'post-thumbnail',
			'.wp-block-post-content, .entry-content' => 'post-content',
		),
		'default-animation'                 => 'fade',
		'default-animation-duration'        => 400,
		'chronological-forwards-animation'  => false,
		'chronological-backwards-animation' => false,
		'pagination-forwards-animation'     => false,
		'pagination-backwards-animation'    => false,
	);

	// If no specific `$args` were provided, simply use the defaults.
	if ( true === $args ) {
		$args = $defaults;
	} else {
		/*
		 * By default, `add_theme_support()` will take all function parameters as `$args`, but for the
		 * 'view-transitions' feature, only a single associative array of arguments is relevant, which is expected as
		 * the sole (optional) parameter.
		 */
		if ( count( $args ) === 1 && isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$args = wp_parse_args( $args, $defaults );
		// Enforce correct types.
		if ( ! is_array( $args['global-transition-names'] ) ) {
			$args['global-transition-names'] = array();
		}
		if ( ! is_array( $args['post-transition-names'] ) ) {
			$args['post-transition-names'] = array();
		}
		if ( ! is_string( $args['default-animation'] ) ) {
			$args['default-animation'] = 'fade';
		}

		$transition_animation_keys = array(
			'chronological-forwards-animation',
			'chronological-backwards-animation',
			'pagination-forwards-animation',
			'pagination-backwards-animation',
		);

		foreach ( $transition_animation_keys as $transition_animation_key ) {
			if ( ! is_string( $args[ $transition_animation_key ] ) || '' === $args[ $transition_animation_key ] ) {
				$args[ $transition_animation_key ] = false;
			}
		}

		// If specific transition animations match the default animations, they are irrelevant.
		if ( $args['chronological-forwards-animation'] === $args['default-animation'] ) {
			$args['chronological-forwards-animation'] = false;
		}
		if ( $args['chronological-backwards-animation'] === $args['default-animation'] ) {
			$args['chronological-backwards-animation'] = false;
		}
		if ( $args['pagination-forwards-animation'] === $args['default-animation'] ) {
			$args['pagination-forwards-animation'] = false;
		}
		if ( $args['pagination-backwards-animation'] === $args['default-animation'] ) {
			$args['pagination-backwards-animation'] = false;
		}
	}
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$_wp_theme_features['view-transitions'] = $args;
}

/**
 * Registers the default view transition animations and fires an action to register additional ones.
 *
 * @since 1.0.0
 * @access private
 *
 * @param PLVT_View_Transition_Animation_Registry $animation_registry Registry instance to register animations on.
 */
function plvt_register_view_transition_animations( PLVT_View_Transition_Animation_Registry $animation_registry ): void {
	/*
	 * This callback is used for certain kinds of animations that move content around, to determine whether specific
	 * view transition names should be applied for an animation or not. If a specific target name (i.e. not '*') is
	 * provided, they should be applied. But if the entire page is the target, they would visually mess with the
	 * animation.
	 */
	$is_specific_target_name = static fn ( string $alias, array $args ): bool => ! ( '*' === $args['target-name'] );

	/*
	 * This callback is used to return horizontal and vertical offsets (-1, 0, or 1) based on whether the given alias
	 * ends in a certain directional term ('left', 'top', 'bottom', 'right'). If none is used, the callback returns
	 * `null` for both offsets.
	 */
	$get_hv_offsets_based_on_alias = static function ( string $alias ): array {
		if ( str_ends_with( $alias, 'left' ) ) {
			return array( -1, 0 );
		}
		if ( str_ends_with( $alias, 'top' ) ) {
			return array( 0, -1 );
		}
		if ( str_ends_with( $alias, 'bottom' ) ) {
			return array( 0, 1 );
		}
		if ( str_ends_with( $alias, 'right' ) ) {
			return array( 1, 0 );
		}
		return array( null, null );
	};

	// Register default animations.
	$animation_registry->register_animation(
		'fade', // This is how view transitions are animated without any extra CSS.
		array(
			'use_stylesheet'              => false,
			'use_global_transition_names' => true,
			'use_post_transition_names'   => true,
		)
	);
	$animation_registry->register_animation(
		'slide',
		array(
			'aliases'                     => array(
				'slide-from-right',
				'slide-from-bottom',
				'slide-from-left',
				'slide-from-top',
			),
			'use_stylesheet'              => true,
			'use_global_transition_names' => $is_specific_target_name,
			'use_post_transition_names'   => $is_specific_target_name,
			'get_stylesheet_callback'     => static function ( string $css, string $alias, array $args ) use ( $get_hv_offsets_based_on_alias ) {
				// Set offsets based on alias, if relevant.
				list( $horizontal_offset, $vertical_offset ) = $get_hv_offsets_based_on_alias( $alias );
				if ( null !== $horizontal_offset && null !== $vertical_offset ) {
					$args['horizontal-offset'] = $horizontal_offset;
					$args['vertical-offset']   = $vertical_offset;
				}

				// Inject offsets as CSS variable to take effect.
				$css .= sprintf(
					'::view-transition-old(*), ::view-transition-new(*) { --plvt-view-transition-animation-slide-horizontal-offset: %d; --plvt-view-transition-animation-slide-vertical-offset: %d; }',
					$args['horizontal-offset'],
					$args['vertical-offset']
				);

				// If a specific element view transition name is targeted, scope the animation to only that name.
				if ( '*' !== $args['target-name'] ) {
					$css = str_replace( '(*)', "({$args['target-name']})", $css );
				}

				return $css;
			},
		),
		array(
			'horizontal-offset' => 1,
			'vertical-offset'   => 0,
			'target-name'       => '*',
		)
	);
	$animation_registry->register_animation(
		'swipe',
		array(
			'aliases'                     => array(
				'swipe-from-right',
				'swipe-from-bottom',
				'swipe-from-left',
				'swipe-from-top',
			),
			'use_stylesheet'              => true,
			'use_global_transition_names' => $is_specific_target_name,
			'use_post_transition_names'   => $is_specific_target_name,
			'get_stylesheet_callback'     => static function ( string $css, string $alias, array $args ) use ( $get_hv_offsets_based_on_alias ) {
				// Set offsets based on alias, if relevant.
				list( $horizontal_offset, $vertical_offset ) = $get_hv_offsets_based_on_alias( $alias );
				if ( null !== $horizontal_offset && null !== $vertical_offset ) {
					$args['horizontal-offset'] = $horizontal_offset;
					$args['vertical-offset']   = $vertical_offset;
				}

				// Inject offsets as CSS variable to take effect.
				$css .= sprintf(
					'::view-transition-old(*), ::view-transition-new(*) { --plvt-view-transition-animation-swipe-horizontal-offset: %d; --plvt-view-transition-animation-swipe-vertical-offset: %d; }',
					$args['horizontal-offset'],
					$args['vertical-offset']
				);

				// If a specific element view transition name is targeted, scope the animation to only that name.
				if ( '*' !== $args['target-name'] ) {
					$css = str_replace( '(*)', "({$args['target-name']})", $css );
				}

				return $css;
			},
		),
		array(
			'horizontal-offset' => 1,
			'vertical-offset'   => 0,
			'target-name'       => '*',
		)
	);
	$animation_registry->register_animation(
		'wipe',
		array(
			'aliases'                     => array(
				'wipe-from-right',
				'wipe-from-bottom',
				'wipe-from-left',
				'wipe-from-top',
			),
			'use_stylesheet'              => true,
			'use_global_transition_names' => false,
			'use_post_transition_names'   => true,
			'get_stylesheet_callback'     => static function ( string $css, string $alias, array $args ) {
				// Set angle based on alias, if relevant.
				if ( str_ends_with( $alias, 'left' ) ) {
					$args['angle'] = 90;
				} elseif ( str_ends_with( $alias, 'top' ) ) {
					$args['angle'] = 180;
				} elseif ( str_ends_with( $alias, 'bottom' ) ) {
					$args['angle'] = 0;
				} elseif ( str_ends_with( $alias, 'right' ) ) {
					$args['angle'] = 270;
				}

				// Inject angle as CSS variable to take effect.
				$css .= sprintf(
					'::view-transition-new(root) { --plvt-view-transition-animation-wipe-angle: %ddeg; }',
					$args['angle']
				);

				return $css;
			},
		),
		array( 'angle' => 270 )
	);

	/**
	 * Fires when view transition animations are being registered.
	 *
	 * This is only triggered if the theme supports view transitions, as otherwise the functionality is not relevant.
	 *
	 * @since 1.0.0
	 *
	 * @param PLVT_View_Transition_Animation_Registry $animation_registry Registry instance on which to register view
	 *                                                                    transition animations which can be used by
	 *                                                                    the theme.
	 */
	do_action( 'plvt_register_view_transition_animations', $animation_registry );
}

/**
 * Loads view transitions based on the current configuration.
 *
 * @since 1.0.0
 */
function plvt_load_view_transitions(): void {
	if ( ! current_theme_supports( 'view-transitions' ) ) {
		return;
	}

	// Instantiate transition animation registry and register available animations on it.
	$animation_registry = new PLVT_View_Transition_Animation_Registry();
	plvt_register_view_transition_animations( $animation_registry );

	// Use an inline style to avoid an extra request.
	$stylesheet = '@view-transition { navigation: auto; }';
	wp_register_style( 'plvt-view-transitions', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_add_inline_style( 'plvt-view-transitions', $stylesheet );
	wp_enqueue_style( 'plvt-view-transitions' );

	$theme_support = get_theme_support( 'view-transitions' );

	/*
	 * Add the animation stylesheet for the default animation, if any.
	 */
	$default_animation_args       = isset( $theme_support['default-animation-args'] ) ? (array) $theme_support['default-animation-args'] : array();
	$default_animation_stylesheet = $animation_registry->get_animation_stylesheet( $theme_support['default-animation'], $default_animation_args );
	$default_animation_stylesheet = plvt_inject_animation_duration( $default_animation_stylesheet, absint( $theme_support['default-animation-duration'] ) );
	wp_add_inline_style( 'plvt-view-transitions', '@media (prefers-reduced-motion: no-preference) {' . $default_animation_stylesheet . '}' );

	/*
	 * Add the CSS that assigns the transition type, based on the URLs the current request can navigate to.
	 */
	wp_add_inline_style( 'plvt-view-transitions', plvt_get_navigation_matching_stylesheet( plvt_get_directional_transition_conditions() ) );

	// Must be in the <head> for the same reason as the main script: `pagereveal` fires before the first rAF.
	wp_register_script( 'plvt-navigation-context-workaround', false, array(), null, array() ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_add_inline_script( 'plvt-navigation-context-workaround', plvt_get_navigation_context_workaround_script() );
	wp_enqueue_script( 'plvt-navigation-context-workaround' );

	$animations_js_config = array(
		'default' => array(
			'useGlobalTransitionNames' => $animation_registry->use_animation_global_transition_names( $theme_support['default-animation'], $default_animation_args ),
			'usePostTransitionNames'   => $animation_registry->use_animation_post_transition_names( $theme_support['default-animation'], $default_animation_args ),
		),
	);

	/*
	 * Add the animation stylesheet for each directional transition type, scoped to that type.
	 */
	foreach ( array( 'chronological-forwards', 'chronological-backwards', 'pagination-forwards', 'pagination-backwards' ) as $transition_type ) {
		$transition_animation_alias = $theme_support[ $transition_type . '-animation' ] ?? false;
		if ( ! is_string( $transition_animation_alias ) || '' === $transition_animation_alias ) {
			$animations_js_config[ $transition_type ] = false;
			continue;
		}

		$additional_animation_args       = isset( $theme_support[ $transition_type . '-animation-args' ] ) ? (array) $theme_support[ $transition_type . '-animation-args' ] : array();
		$additional_animation_stylesheet = $animation_registry->get_animation_stylesheet( $transition_animation_alias, $additional_animation_args );
		$additional_animation_stylesheet = plvt_inject_animation_duration( $additional_animation_stylesheet, absint( $theme_support['default-animation-duration'] ) );
		if ( '' !== $additional_animation_stylesheet ) {
			wp_add_inline_style(
				'plvt-view-transitions',
				'@media (prefers-reduced-motion: no-preference) {' . plvt_scope_animation_stylesheet_to_transition_type( $additional_animation_stylesheet, $transition_type ) . '}'
			);
		}

		$animations_js_config[ $transition_type ] = array(
			'useGlobalTransitionNames' => $animation_registry->use_animation_global_transition_names( $transition_animation_alias, $additional_animation_args ),
			'usePostTransitionNames'   => $animation_registry->use_animation_post_transition_names( $transition_animation_alias, $additional_animation_args ),
			'targetName'               => $additional_animation_args['target-name'] ?? '*', // Special argument.
		);
	}

	/*
	 * No point in loading the script if no specific view transition names are configured.
	 */
	if (
		( ! is_array( $theme_support['global-transition-names'] ) || count( $theme_support['global-transition-names'] ) === 0 ) &&
		( ! is_array( $theme_support['post-transition-names'] ) || count( $theme_support['post-transition-names'] ) === 0 )
	) {
		return;
	}

	$config = array(
		'postSelector'          => $theme_support['post-selector'],
		'globalTransitionNames' => $theme_support['global-transition-names'],
		'postTransitionNames'   => $theme_support['post-transition-names'],
		'animations'            => $animations_js_config,
	);

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$src_script = file_get_contents( plvt_get_asset_path( 'js/view-transitions.js' ) );
	if ( false === $src_script || '' === $src_script ) {
		// This clause should never be entered, but is needed to please PHPStan. Can't hurt to be safe.
		return;
	}

	$init_script = sprintf(
		'plvtInitViewTransitions( %s )',
		(string) wp_json_encode( $config, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
	);

	/*
	 * This must be in the <head>, not in the footer.
	 * This is because the pagereveal event listener must be added before the first rAF occurs since that is when the event fires. See <https://issues.chromium.org/issues/40949146#comment10>.
	 * An inline script is used to avoid an extra request.
	 */
	wp_register_script( 'plvt-view-transitions', false, array(), null, array() ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	wp_add_inline_script( 'plvt-view-transitions', $src_script );
	wp_add_inline_script( 'plvt-view-transitions', $init_script );
	wp_enqueue_script( 'plvt-view-transitions' );
}

/**
 * Injects the animation duration placeholder in the provided CSS with a value based on the transition duration.
 *
 * @since 1.1.0
 * @access private
 *
 * @param string $css                The raw CSS string containing the placeholder `plvt-view-transition-duration;`.
 * @param int    $animation_duration Transition duration in milliseconds. Will be converted to seconds. Defaults to 1000ms if invalid.
 * @return string Modified CSS with the actual animation duration in seconds.
 */
function plvt_inject_animation_duration( string $css, int $animation_duration ): string {
	$seconds = $animation_duration / 1000;

	// Inject animation duration as CSS variable to take effect.
	$css .= sprintf(
		/* translators: %1$s: CSS property name. %2$s: Animation duration in seconds. */
		'::view-transition-group(*) { %1$s: %2$ss; }',
		'' !== $css ? '--plvt-view-transition-animation-duration' : 'animation-duration',
		$seconds
	);

	return $css;
}

/**
 * Returns the directional navigations available from the current request, as ordered pairs of location descriptors.
 *
 * The condition grammar has no numeric comparison — `navigation_query.cc` evaluates every relation with `==` and
 * boolean logic only — so no pattern can tell 3 → 4 apart from 5 → 4 by itself. What a pattern *can* do is carry the
 * comparison the server already made: a capture group constrained to the page numbers above the current one matches
 * only forward destinations, and one constrained to those below matches only backward destinations. A jump of any
 * distance therefore resolves, without listing a URL per page.
 *
 * Both orderings of every pair are emitted, because each document only knows its own neighbours, and it has to match
 * whether it is the document being left or the one being entered: page 4 needs `(from: 3) and (to: 4)` to exist in its
 * own stylesheet to animate on arrival, and page 3 needs the same pair to animate on departure.
 *
 * Traversals are handled separately and do not need locations at all — see the `history:` conditions below.
 *
 * @since n.e.x.t
 * @access private
 *
 * @global WP_Query $wp_query
 * @return array<int, array{from?: non-empty-string, to?: non-empty-string, condition?: non-empty-string, type: non-empty-string}>
 *         Navigation conditions, most general first.
 */
function plvt_get_directional_transition_conditions(): array {
	global $wp_query;

	$pairs = array();

	/**
	 * Adds both orderings of a navigation between two locations.
	 *
	 * @param string $current     Descriptors for the location navigated from.
	 * @param string $other       Descriptors for the location navigated to.
	 * @param bool   $is_forwards Whether navigating from the first to the second counts as forwards.
	 * @param string $prefix      Transition type prefix, i.e. 'chronological-' or 'pagination-'.
	 */
	$add_pair = static function ( string $current, string $other, bool $is_forwards, string $prefix ) use ( &$pairs ): void {
		if ( '' === $current || '' === $other || $current === $other ) {
			return;
		}

		$pairs[] = array(
			'from' => $current,
			'to'   => $other,
			'type' => $prefix . ( $is_forwards ? 'forwards' : 'backwards' ),
		);
		$pairs[] = array(
			'from' => $other,
			'to'   => $current,
			'type' => $prefix . ( $is_forwards ? 'backwards' : 'forwards' ),
		);
	};

	// On paginated archives, a higher page number counts as forwards.
	if ( is_home() || is_archive() || is_search() ) {
		$max_pages = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : 0;
		if ( $max_pages > 1 ) {
			$paged    = max( 1, (int) get_query_var( 'paged' ) );
			$root_url = (string) get_pagenum_link( 1 );
			$current  = plvt_get_route_descriptors( (string) get_pagenum_link( $paged ) );

			// Traversing the archive with the browser buttons already carries its own direction.
			array_unshift(
				$pairs,
				array(
					'condition' => 'history: forward',
					'type'      => 'chronological-forwards',
				),
				array(
					'condition' => 'history: back',
					'type'      => 'chronological-backwards',
				)
			);

			/*
			 * Page 1 has no page segment, so it is reached by its own URL rather than by the range pattern.
			 */
			if ( $paged > 1 ) {
				$add_pair( $current, plvt_get_route_descriptors( $root_url ), false, 'chronological-' );
			}

			$forwards_pattern  = plvt_get_paged_pathname_pattern( $paged + 1, $max_pages );
			$backwards_pattern = plvt_get_paged_pathname_pattern( 2, $paged - 1 );

			if ( '' !== $forwards_pattern ) {
				$add_pair( $current, plvt_get_pathname_descriptor( $forwards_pattern ), true, 'chronological-' );
			}
			if ( '' !== $backwards_pattern ) {
				$add_pair( $current, plvt_get_pathname_descriptor( $backwards_pattern ), false, 'chronological-' );
			}

			/*
			 * Without a range pattern — no pretty permalinks, or an archive too large to enumerate — only the adjacent
			 * step can be expressed, so a jump falls back to the default transition.
			 */
			if ( '' === $forwards_pattern && $paged < $max_pages ) {
				$add_pair( $current, plvt_get_route_descriptors( (string) get_pagenum_link( $paged + 1 ) ), true, 'chronological-' );
			}
			if ( '' === $backwards_pattern && $paged > 2 ) {
				$add_pair( $current, plvt_get_route_descriptors( (string) get_pagenum_link( $paged - 1 ) ), false, 'chronological-' );
			}
		}

		return $pairs;
	}

	if ( ! is_singular() ) {
		return $pairs;
	}

	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return $pairs;
	}

	/*
	 * Within a multipage post, a higher page number counts as forwards. The page count is derived from the post
	 * content rather than the `$numpages` global, because that global is only populated once the template loop calls
	 * `the_post()`, which happens after this runs.
	 */
	$page_count   = 1 + substr_count( $post->post_content, '<!--nextpage-->' );
	$current_page = max( 1, (int) get_query_var( 'page' ) );
	$current_url  = plvt_get_post_page_url( $post, $current_page );

	// Traversing with the browser buttons already carries its own direction, whatever the URLs involved are.
	$traversal_prefix = $page_count > 1 ? 'pagination-' : 'chronological-';
	array_unshift(
		$pairs,
		array(
			'condition' => 'history: forward',
			'type'      => $traversal_prefix . 'forwards',
		),
		array(
			'condition' => 'history: back',
			'type'      => $traversal_prefix . 'backwards',
		)
	);

	if ( $page_count > 1 ) {
		// Exact URLs only. A `post-slug/*` pattern would also match attachments and child pages.
		if ( $current_page < $page_count ) {
			$add_pair(
				plvt_get_route_descriptors( $current_url ),
				plvt_get_route_descriptors( plvt_get_post_page_url( $post, $current_page + 1 ) ),
				true,
				'pagination-'
			);
		}
		if ( $current_page > 1 ) {
			$add_pair(
				plvt_get_route_descriptors( $current_url ),
				plvt_get_route_descriptors( plvt_get_post_page_url( $post, $current_page - 1 ) ),
				false,
				'pagination-'
			);
		}
	}

	// Between posts, a newer post counts as forwards. Publish order is not derivable from a URL pattern.
	$newer_post = get_adjacent_post( false, '', false );
	if ( $newer_post instanceof WP_Post ) {
		$add_pair(
			plvt_get_route_descriptors( $current_url ),
			plvt_get_route_descriptors( (string) get_permalink( $newer_post ) ),
			true,
			'chronological-'
		);
	}
	$older_post = get_adjacent_post( false, '', true );
	if ( $older_post instanceof WP_Post ) {
		$add_pair(
			plvt_get_route_descriptors( $current_url ),
			plvt_get_route_descriptors( (string) get_permalink( $older_post ) ),
			false,
			'chronological-'
		);
	}

	return $pairs;
}

/**
 * Returns the URL for a specific page of the current multipage post.
 *
 * This mirrors the URL portion of the Core `_wp_link_page()` function, which is private and returns markup.
 *
 * @since n.e.x.t
 * @access private
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param WP_Post $post        Post to link to.
 * @param int     $page_number Page number within the post.
 * @return string Page URL.
 */
function plvt_get_post_page_url( WP_Post $post, int $page_number ): string {
	global $wp_rewrite;

	$permalink = (string) get_permalink( $post );
	if ( 1 === $page_number ) {
		return $permalink;
	}

	if ( ! (bool) get_option( 'permalink_structure' ) || in_array( $post->post_status, array( 'draft', 'pending' ), true ) ) {
		return add_query_arg( 'page', $page_number, $permalink );
	}

	// The front page uses the pagination base to avoid colliding with its own pagination.
	if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post->ID ) {
		return trailingslashit( $permalink ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $page_number, 'single_paged' );
	}

	return trailingslashit( $permalink ) . user_trailingslashit( (string) $page_number, 'single_paged' );
}

/**
 * Returns the CSS that declaratively assigns view transition types based on the URL being navigated to.
 *
 * Each rule has to repeat `navigation: auto` because the last matching `@view-transition` rule replaces earlier ones
 * outright rather than merging descriptors with them.
 *
 * @since n.e.x.t
 * @access private
 *
 * Entries are emitted in order, so the caller controls precedence: the `history:` traversal rules come first and any
 * location pair matching the same navigation overrides them.
 *
 * @param array<int, array<string, string>> $pairs Navigation conditions, each either an ordered location pair
 *                                                (`from`, `to`, `type`) or a ready-made `condition` with a `type`.
 * @return string Inline CSS, or an empty string if no URL could be expressed as a location.
 */
function plvt_get_navigation_matching_stylesheet( array $pairs ): string {
	$location_names = array();
	$locations_css  = '';
	$rules_css      = '';

	/**
	 * Returns the location name for a set of descriptors, defining the location on first use.
	 *
	 * A location is just a named URL matcher, so one definition serves every condition referencing it.
	 *
	 * @param string $descriptors Location descriptors.
	 * @return string Location name, or an empty string if there is nothing to match on.
	 */
	$get_location_name = static function ( string $descriptors ) use ( &$location_names, &$locations_css ): string {
		if ( '' === $descriptors ) {
			return '';
		}

		if ( ! isset( $location_names[ $descriptors ] ) ) {
			$location_name                  = sprintf( '--plvt-location-%d', count( $location_names ) );
			$location_names[ $descriptors ] = $location_name;
			$locations_css                 .= sprintf( '@location %1$s { %2$s }', $location_name, $descriptors );
		}

		return $location_names[ $descriptors ];
	};

	foreach ( $pairs as $pair ) {
		if ( isset( $pair['condition'] ) ) {
			// A condition needing no locations, i.e. one of the `history:` traversal rules.
			$condition = sprintf( '(%s)', $pair['condition'] );
		} else {
			$from_name = $get_location_name( $pair['from'] );
			$to_name   = $get_location_name( $pair['to'] );
			if ( '' === $from_name || '' === $to_name ) {
				continue;
			}

			$condition = sprintf( '(from: %1$s) and (to: %2$s)', $from_name, $to_name );
		}

		$rules_css .= sprintf(
			'@navigation %1$s { @view-transition { navigation: auto; types: %2$s; } }',
			$condition,
			$pair['type']
		);
	}

	return $locations_css . $rules_css;
}


/**
 * Returns the script that keeps the cross-document navigation context intact until the incoming document is revealed.
 *
 * Core's Interactivity API stamps a session id into `history.state` while its module is evaluating, in
 * `wp-includes/js/dist/script-modules/interactivity/index.js` (Gutenberg source:
 * `packages/interactivity/src/index.ts`). `history.replaceState()` registers a same-document navigation, after which
 * the `from:` and `to:` relations both resolve to the current URL, so no `@navigation` condition comparing the two can
 * match. Whether that lands before or after `pagereveal` is a race, which makes directional transitions intermittent
 * on any page loading an interactive block.
 *
 * Deferring those calls past the reveal preserves what core is doing while letting the reveal observe the real
 * navigation. This is a workaround for a core issue, not a fix; see the accompanying Trac ticket.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return string Inline JavaScript.
 */
function plvt_get_navigation_context_workaround_script(): string {
	return <<<'JS'
( () => {
	let revealed = false;
	const deferred = [];
	const replaceState = history.replaceState.bind( history );

	const flush = () => {
		revealed = true;
		while ( deferred.length > 0 ) {
			replaceState( ...deferred.shift() );
		}
	};

	history.replaceState = ( ...args ) => {
		if ( revealed ) {
			return replaceState( ...args );
		}
		deferred.push( args );
		return undefined;
	};

	addEventListener( 'pagereveal', flush );

	// Safety net, so state is never stranded when no view transition occurs.
	addEventListener( 'load', flush );
} )();
JS;
}

/**
 * Returns a pathname pattern matching the paginated archive URLs for an inclusive range of page numbers.
 *
 * The page number is captured by a group constrained to exactly the numbers in the range, which is how the comparison
 * the server already made survives into CSS. The pattern is derived by asking `get_pagenum_link()` for an
 * implausibly-numbered page and substituting the capture group for that number, so it holds for any permalink
 * structure and any `pagination_base` without reimplementing either.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param int $min First page number in the range.
 * @param int $max Last page number in the range.
 * @return string Pathname pattern, or an empty string if the range cannot be expressed as one.
 */
function plvt_get_paged_pathname_pattern( int $min, int $max ): string {
	/*
	 * Without pretty permalinks the page number lives in the query string, leaving every page with an identical
	 * pathname. The `search` component would have to carry it, but the surrounding query parameters and their order
	 * vary per request, so exact URLs are used for those sites instead.
	 */
	if ( $min < 2 || $max < $min || ! (bool) get_option( 'permalink_structure' ) ) {
		return '';
	}

	/*
	 * The alternation is one term per page, so it is bounded by the size of the archive. Past this many pages the
	 * stylesheet cost outweighs animating a jump, and the caller falls back to the adjacent page only.
	 */
	if ( $max - $min > 250 ) {
		return '';
	}

	$placeholder = 999999999;
	$path        = (string) wp_parse_url( (string) get_pagenum_link( $placeholder ), PHP_URL_PATH );
	if ( '' === $path || 1 !== substr_count( $path, (string) $placeholder ) ) {
		return '';
	}

	return str_replace(
		(string) $placeholder,
		sprintf( ':plvtpage(%s)', implode( '|', range( $min, $max ) ) ),
		$path
	);
}

/**
 * Returns the `@location` descriptor for a pathname pattern.
 *
 * The value is a URLPattern pathname pattern rather than a literal path, so a single location can stand for a whole
 * family of URLs, e.g. `/blog/page/*` for every paginated page of an archive.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $pathname_pattern Pathname pattern.
 * @return string Descriptor declaration, or an empty string if the pattern is empty.
 */
function plvt_get_pathname_descriptor( string $pathname_pattern ): string {
	if ( '' === $pathname_pattern ) {
		return '';
	}

	return sprintf( 'pathname: "%s";', addcslashes( $pathname_pattern, '"\\' ) );
}

/**
 * Returns the `@location` descriptors matching the given URL.
 *
 * The `search` descriptor is included whenever the URL carries a query string, so that routes remain distinguishable
 * when the site does not use pretty permalinks and the page number lives in the query.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $url URL to express as route descriptors.
 * @return string Descriptor declarations, or an empty string if the URL has no path to match on.
 */
function plvt_get_route_descriptors( string $url ): string {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ! isset( $parts['path'] ) || '' === $parts['path'] ) {
		return '';
	}

	$descriptors = sprintf( 'pathname: "%s";', addcslashes( $parts['path'], '"\\' ) );
	if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
		$descriptors .= sprintf( ' search: "%s";', addcslashes( $parts['query'], '"\\' ) );
	}

	return $descriptors;
}

/**
 * Scopes the given view transition animation CSS to apply only to a specific transition type.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $css             Animation stylesheet as inline CSS.
 * @param string $transition_type Transition type to scope the CSS to.
 * @return string Scoped animation stylesheet.
 */
function plvt_scope_animation_stylesheet_to_transition_type( string $css, string $transition_type ): string {
	$indent = static function ( string $input, $indent_tabs = 1 ): string {
		return implode(
			"\n",
			array_map(
				static function ( string $line ) use ( $indent_tabs ): string {
					return str_repeat( "\t", $indent_tabs ) . $line;
				},
				explode( "\n", $input )
			)
		);
	};

	// This is very fragile, but it works well enough for now. TODO: Find a better solution to scope the CSS selectors.
	if ( (bool) preg_match_all( '/(\s*)([^{}]+)\{[^{}]*?\}/m', $css, $matches ) ) {
		// Wrap all `::view-transition-*` selectors to scope them to the transition type.
		$view_transition_rule_pattern = '/::view-transition-/';

		foreach ( $matches[0] as $index => $match ) {
			$rule      = $match;
			$rule_name = $matches[2][ $index ];
			if ( (bool) preg_match( $view_transition_rule_pattern, $rule_name ) ) {
				$rule_whitespace    = $matches[1][ $index ];
				$prefixed_rule_name = preg_replace( $view_transition_rule_pattern, '&\0', $rule_name );
				if ( null === $prefixed_rule_name ) {
					continue;
				}

				$rule = str_replace( $rule_name, $prefixed_rule_name, $rule );

				if ( str_contains( $rule, "\n" ) ) { // Non-minified.
					$rule = $rule_whitespace .
						"html:active-view-transition-type($transition_type) {\n" .
						$indent( substr( $rule, strlen( $rule_whitespace ) ), 1 ) .
						"\n}";
				} else { // Minified.
					$rule = $rule_whitespace .
					"html:active-view-transition-type($transition_type){" .
					substr( $rule, strlen( $rule_whitespace ) ) .
					'}';
				}

				// Replace the original rule with the wrapped/scoped one.
				$css = str_replace( $match, $rule, $css );
			}
		}
	}
	return $css;
}
