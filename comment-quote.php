<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Comment_Quote' ) ) :

class Comment_Quote {
	/**
	 * Init method.
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Do a song-n-dance to wrap the original comment before plugins touch it.
		add_filter( 'get_comment_text', function( $retval ) {
			return sprintf( '%s<!-- .comment-original -->', $retval );
		}, -9999 );


		// Stupid wpautop. Do this before wpautop.
		add_filter( 'comment_text', function( $retval ) {
			$before = '';
			if ( '<' !== substr( $retval, 0, 1 ) ) {
				$before = '<p>';
			}

			$retval = sprintf( '<div class="comment-original">%s', $before . $retval );
			$retval = str_replace( '<!-- .comment-original -->', '</div><!-- .comment-original -->', $retval );
			return $retval;
		}, 29 );


		// Stupid wpautop, pt. 2. Do this after wpautop.
		add_filter( 'comment_text', function( $retval ) {
			$retval = str_replace( '<p><!-- .comment-original --></p>', '<!-- .comment-original -->', $retval );
			return $retval;
		}, 31 );

		// Inject quote link after reply link.
		add_filter( 'comment_reply_link', function( $retval, $args, $comment, $post ) {
			return $retval . self::insert( $comment );
		}, 99999, 4 );

		add_action( 'wp_footer', array( $this, 'javascript' ), 999 );

		// editor CSS
		add_filter( 'mce_css',   array( $this, 'editor_styles' ) );
	}

	/**
	 * Static method to render the quote markup.
	 *
	 * @param  WP_Comment $comment Comment object.
	 * @param  array      $r       Array of arguments. Supports 'label' for now.
	 * @return string
	 */
	public static function insert( $comment, $r = [] ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$label = esc_html__( 'Quote', 'comment-quote' );
		if ( ! empty( $r['label'] ) ) {
			$label = esc_html( $r['label'] );
		}

		return sprintf(
			'<div class="comment-quote reply"><a class="comment-quote-link" href="javascript:;" data-commentid="%1$d" data-commentauthor="%2$s" data-commenturl="%3$s">%4$s</a></div>',
			(int) $comment->comment_ID,
			esc_attr( $comment->comment_author ),
			get_comment_link( $comment ),
			$label
		);
	}

	/**
	 * Outputs the javascript.
	 *
	 * @todo Move JS to static file. Localize citation string.
	 */
	public function javascript() {
	?>

		<script type="text/javascript">
			// Selection function that handles HTML.
			// @link https://stackoverflow.com/a/6668159
			function comment_get_selection() {
				var html = "";
				if (typeof window.getSelection != "undefined") {
					var sel = window.getSelection();
					if (sel.rangeCount) {
						var container = document.createElement("div");
						for (var i = 0, len = sel.rangeCount; i < len; ++i) {
							container.appendChild(sel.getRangeAt(i).cloneContents());
						}
						html = container.innerHTML;
					}
				} else if (typeof document.selection != "undefined") {
					if (document.selection.type == "Text") {
						html = document.selection.createRange().htmlText;
					}
				}
				return html;
			}

			function comment_insert_quote( user, text, permalink ){
				var content = '<blockquote class="comment-the-quote" cite="' + permalink + '"><em class="comment-the-quote-cite"><a href="' + permalink + '">' + user + ' wrote:</a></em>' + text.replace(/(\r\n|\n|\r)/gm,"").replace('<br>',"\n") + '</blockquote>' + "\r\n\n";

				// check if tinyMCE is active and visible
				if ( window.tinyMCE && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden() ) {
					tinyMCE.activeEditor.selection.setContent( content );
					tinyMCE.activeEditor.selection.collapse(false);
					tinyMCE.activeEditor.focus();

				// regular textarea
				} else {
					var textarea = jQuery("#comment");

					// add quote
					textarea.val( textarea.val() + content );

					// scroll to bottom of textarea and focus
					textarea.animate(
						{scrollTop: textarea[0].scrollHeight - textarea.height()},
						800
					).trigger('focus');
				}
			}

			jQuery(document).ready( function($) {
				$(".comment-quote-link").on("click", function(){
					var id          = $(this).data('commentid'),
						permalink   = $(this).data('commenturl'),
						author      = $(this).data('commentauthor'),
						content     = comment_get_selection(),
						sel, parentEl;

					// Check if selection is part of the current comment.
					if ( content ) {
						if (window.getSelection) {
							sel = window.getSelection();
							if (sel.rangeCount) {
								parentEl = sel.getRangeAt(0).commonAncestorContainer;
								if (parentEl.nodeType != 1) {
									parentEl = parentEl.parentNode;
								}
							}
						} else if ( (sel = document.selection) && sel.type != "Control") {
							parentEl = sel.createRange().parentElement();
						}

						if ( parentEl ) {
							parentEl = $(parentEl).closest('.comment-body');
							if ( parentEl && parentEl.prop('id') !== 'div-comment-' + id ) {
								content = false;
							}
						}
					}

					// Fallback to whole forum post for quote.
					if ( ! content ) {
						content = $('#comment-' + id + ' .comment-original' ).html();
					}

					// Scroll to form.
					$("html, body").animate(
						{scrollTop: $("#commentform").offset().top},
						500
					);

					// Set comment parent.
					$('#comment_parent').val( id );

					// insert quote
					comment_insert_quote( author, content, permalink );
				});

				// when clicking on a citation, do fancy scroll
				$(".comment-the-quote-cite a").on("click", function(e){
					var id = $(this.hash);

					// Comment is on this page, so fancy scroll!
					if ( id.length ) {
						e.preventDefault();
						$("html, body").animate(
							{scrollTop: $(id).offset().top},
							500
						);

				        location.hash = id.selector;
			        }
				});
			});
		</script>

	<?php
	}

	/**
	 * Add CSS to style blockquotes in TinyMCE.
	 *
	 * @param  string $css String of CSS assets for TinyMCE.
	 * @return string
	 */
	public function editor_styles( $css ){
		if ( ! apply_filters( 'comment_quote_enable_editor_css', true ) ) {
			return $css;
		}

		$css .= ',' . plugins_url( basename( __DIR__ ) ) . '/style.css';
		return $css;
	}
}

endif;
