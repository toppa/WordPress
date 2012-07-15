<?php

class WP_Post {
	public $ID;
	public $post_author;
	public $post_date;
	public $post_date_gmt;
	public $post_content;
	public $post_title;
	public $post_excerpt;
	public $post_status;
	public $comment_status;
	public $ping_status;
	public $post_password;
	public $post_name;
	public $to_ping;
	public $pinged;
	public $post_modified;
	public $post_modified_gmt;
	public $post_content_filtered;
	public $post_parent;
	public $guid;
	public $menu_order;
	public $post_type;
	public $post_mime_type;
	public $comment_count;
	public $filter;

	/*
	 * @param array|object $post_data post data, in the form of an array or stdClass object
	 */
	public static function get_instance( $post_data ) {
		$post = new WP_Post();

		foreach ( $post_data as $k=>$v ) {
			if ( property_exists('WP_Post', $k) ) {
				$post->$k = $v;
			}
		}

		return $post;
	}

	/*
	 * @param array $posts_data an array of posts (the posts themselves can be arrays or stdClass objects)
	 */
	public static function get_instances( array $posts_data ) {
		return array_map(array( 'WP_Post', 'get_instance' ), $posts_data );
	}

	// since all properties are public, this is only
	// needed to prevent the addition of other properties
	public function __set( $name, $value ) {
		return false;
	}

	public function get_ID() {
		return $this->ID;
	}

	public function get_title( $id = 0 ) {
		$title = isset($this->post_title) ? $this->post_title : '';
		$id = isset($this->ID) ? $this->ID : (int) $id;

		if ( !is_admin() ) {
			if ( !empty($this->post_password) ) {
				$protected_title_format = apply_filters('protected_title_format', __('Protected: %s'));
				$title = sprintf($protected_title_format, $title);
			} else if ( isset($this->post_status) && 'private' == $this->post_status ) {
				$private_title_format = apply_filters('private_title_format', __('Private: %s'));
				$title = sprintf($private_title_format, $title);
			}
		}
		return apply_filters( 'the_title', $title, $id );
	}

	public function get_guid() {
		return apply_filters('get_the_guid', $this->guid);
	}

	public function get_content($more_link_text = null, $stripteaser = false) {
		global $more, $page, $pages, $multipage, $preview;

		if ( null === $more_link_text )
			$more_link_text = __( '(more...)' );

		$output = '';
		$hasTeaser = false;

		// If post password required and it doesn't match the cookie.
		if ( post_password_required($this) )
			return get_the_password_form();

		if ( $page > count($pages) ) // if the requested page doesn't exist
			$page = count($pages); // give them the highest numbered page that DOES exist

		$content = $pages[$page-1];
		if ( preg_match('/<!--more(.*?)?-->/', $content, $matches) ) {
			$content = explode($matches[0], $content, 2);
			if ( !empty($matches[1]) && !empty($more_link_text) )
				$more_link_text = strip_tags(wp_kses_no_null(trim($matches[1])));

			$hasTeaser = true;
		} else {
			$content = array($content);
		}
		if ( (false !== strpos($this->post_content, '<!--noteaser-->') && ((!$multipage) || ($page==1))) )
			$stripteaser = true;
		$teaser = $content[0];
		if ( $more && $stripteaser && $hasTeaser )
			$teaser = '';
		$output .= $teaser;
		if ( count($content) > 1 ) {
			if ( $more ) {
				$output .= '<span id="more-' . $this->ID . '"></span>' . $content[1];
			} else {
				if ( ! empty($more_link_text) )
					$output .= apply_filters( 'the_content_more_link', ' <a href="' . get_permalink() . "#more-{$this->ID}\" class=\"more-link\">$more_link_text</a>", $more_link_text );
				$output = force_balance_tags($output);
			}

		}
		if ( $preview ) // preview fix for javascript bug with foreign languages
			$output =	preg_replace_callback('/\%u([0-9A-F]{4})/', '_convert_urlencoded_to_entities', $output);

		return $output;
	}

	public function get_excerpt( $deprecated = '' ) {
		if ( !empty( $deprecated ) )
			_deprecated_argument( __FUNCTION__, '2.3' );

		if ( post_password_required($this) ) {
			return __( 'There is no excerpt because this is a protected post.' );
		}

		return apply_filters( 'get_the_excerpt', $this->post_excerpt );
	}

	public function has_excerpt() {
		return !empty( $this->post_excerpt );
	}

	public function get_post_class( $class = '' ) {
		$classes = array();

		$object_vars = get_object_vars($this);
		if ( empty( $object_vars ) )
			return $classes;

		$classes[] = 'post-' . $this->ID;
		$classes[] = $this->post_type;
		$classes[] = 'type-' . $this->post_type;
		$classes[] = 'status-' . $this->post_status;

		// Post Format
		if ( post_type_supports( $this->post_type, 'post-formats' ) ) {
			$post_format = get_post_format( $this->ID );

			if ( $post_format && !is_wp_error($post_format) )
				$classes[] = 'format-' . sanitize_html_class( $post_format );
			else
				$classes[] = 'format-standard';
		}

		// post requires password
		if ( post_password_required($this->ID) )
			$classes[] = 'post-password-required';

		// sticky for Sticky Posts
		if ( is_sticky($this->ID) && is_home() && !is_paged() )
			$classes[] = 'sticky';

		// hentry for hAtom compliance
		$classes[] = 'hentry';

		// Categories
		if ( is_object_in_taxonomy( $this->post_type, 'category' ) ) {
			foreach ( (array) get_the_category($this->ID) as $cat ) {
				if ( empty($cat->slug ) )
					continue;
				$classes[] = 'category-' . sanitize_html_class($cat->slug, $cat->term_id);
			}
		}

		// Tags
		if ( is_object_in_taxonomy( $this->post_type, 'post_tag' ) ) {
			foreach ( (array) get_the_tags($this->ID) as $tag ) {
				if ( empty($tag->slug ) )
					continue;
				$classes[] = 'tag-' . sanitize_html_class($tag->slug, $tag->term_id);
			}
		}

		if ( !empty($class) ) {
			if ( !is_array( $class ) )
				$class = preg_split('#\s+#', $class);
			$classes = array_merge($classes, $class);
		}

		$classes = array_map('esc_attr', $classes);

		return apply_filters('post_class', $classes, $class, $this->ID);
	}
}
