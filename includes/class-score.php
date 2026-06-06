<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Score {

	public static function calculate( $post_id ) {
		$post        = get_post( $post_id );
		$focus_kw    = get_post_meta( $post_id, '_aseo_focus_kw',    true );
		$title       = get_post_meta( $post_id, '_aseo_title',       true );
		$description = get_post_meta( $post_id, '_aseo_description', true );
		$raw_content = $post->post_content;
		$content     = wp_strip_all_tags( $raw_content );

		$score = 0;
		$tips  = array();

		// ── Parse keyphrases (comma-separated) ───────────────────
		$phrases = array();
		if ( $focus_kw ) {
			foreach ( explode( ',', $focus_kw ) as $p ) {
				$p = trim( $p );
				if ( $p !== '' ) $phrases[] = $p;
			}
		}

		$keyphrases = array();

		if ( ! empty( $phrases ) ) {
			$title_check = strtolower( $title ?: get_the_title( $post_id ) );
			$desc_check  = strtolower( $description );
			$word_count  = max( 1, str_word_count( $content ) );

			// First paragraph text
			$first_para_text = '';
			if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $raw_content, $pm ) ) {
				$first_para_text = strtolower( wp_strip_all_tags( $pm[1] ) );
			}

			// Heading texts
			$heading_texts = array();
			if ( preg_match_all( '/<h[2-4][^>]*>(.*?)<\/h[2-4]>/is', $raw_content, $hm ) ) {
				foreach ( $hm[1] as $h ) {
					$heading_texts[] = strtolower( wp_strip_all_tags( $h ) );
				}
			}

			foreach ( $phrases as $idx => $phrase ) {
				$kw = strtolower( $phrase );

				$in_title      = strpos( $title_check, $kw ) !== false;
				$in_desc       = $description && strpos( $desc_check, $kw ) !== false;
				$kw_count      = substr_count( strtolower( $content ), $kw );
				$in_content    = $kw_count > 0;
				$kw_density    = round( ( $kw_count / $word_count ) * 100, 1 );
				$in_first_para = $first_para_text && strpos( $first_para_text, $kw ) !== false;
				$in_heading    = false;
				foreach ( $heading_texts as $ht ) {
					if ( strpos( $ht, $kw ) !== false ) { $in_heading = true; break; }
				}

				$keyphrases[] = array(
					'phrase'      => $phrase,
					'in_title'    => $in_title,
					'in_desc'     => $in_desc,
					'in_content'  => $in_content,
					'in_first'    => $in_first_para,
					'in_heading'  => $in_heading,
					'count'       => $kw_count,
					'density'     => $kw_density,
				);

				// Primary keyphrase scores fully; additional ones add small bonuses
				if ( $idx === 0 ) {
					$score += 10;
					if ( $in_title )      { $score += 20; } else { $tips[] = 'Add "' . $phrase . '" to the SEO title.'; }
					if ( $in_desc )       { $score += 20; } else { $tips[] = 'Add "' . $phrase . '" to the meta description.'; }
					if ( $kw_density >= 0.5 && $kw_density <= 2.5 ) {
						$score += 15;
					} elseif ( $in_content ) {
						$score += 5;
						$tips[] = '"' . $phrase . '" density is ' . $kw_density . '% — aim for 0.5–2.5%.';
					} else {
						$tips[] = '"' . $phrase . '" not found in post content.';
					}
					if ( $in_first )   { $score += 5; } else { $tips[] = 'Add "' . $phrase . '" to the first paragraph.'; }
					if ( $in_heading ) { $score += 5; } else { $tips[] = 'Add "' . $phrase . '" to at least one subheading.'; }
				} else {
					// Secondary keyphrases: up to 5 bonus points each, capped
					$bonus = 0;
					if ( $in_title )   $bonus++;
					if ( $in_desc )    $bonus++;
					if ( $in_content ) $bonus += 2;
					if ( $in_heading ) $bonus++;
					$score += min( 5, $bonus );
					if ( ! $in_content ) {
						$tips[] = '"' . $phrase . '" not found in post content.';
					}
				}
			}
		} else {
			$tips[] = 'Set a focus keyphrase for this post.';
		}

		// ── Title length ──────────────────────────────────────────
		if ( $title ) {
			$len = strlen( $title );
			if ( $len >= 30 && $len <= 60 ) {
				$score += 15;
			} else {
				$score += 5;
				$tips[] = 'SEO title should be 30–60 characters (currently ' . $len . ').';
			}
		} else {
			$tips[] = 'Add a custom SEO title.';
		}

		// ── Description length ────────────────────────────────────
		if ( $description ) {
			$len = strlen( $description );
			if ( $len >= 100 && $len <= 160 ) {
				$score += 15;
			} else {
				$score += 5;
				$tips[] = 'Meta description should be 100–160 characters (currently ' . $len . ').';
			}
		} else {
			$tips[] = 'Add a meta description.';
		}

		// ── Featured image ────────────────────────────────────────
		if ( has_post_thumbnail( $post_id ) ) {
			$score += 5;
		} else {
			$tips[] = 'Add a featured image.';
		}

		// ── Links ─────────────────────────────────────────────────
		$internal_links = 0;
		$external_links = 0;
		$host           = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $raw_content, $lm ) ) {
			foreach ( $lm[1] as $href ) {
				if ( strpos( $href, '#' ) === 0 || strpos( $href, 'mailto:' ) === 0 || strpos( $href, 'tel:' ) === 0 ) continue;
				$link_host = wp_parse_url( $href, PHP_URL_HOST );
				if ( ! $link_host || $link_host === $host || strpos( $href, '/' ) === 0 ) {
					$internal_links++;
				} else {
					$external_links++;
				}
			}
		}
		if ( $internal_links === 0 ) $tips[] = 'Add at least one internal link to related content.';
		if ( $external_links === 0 ) $tips[] = 'Consider adding an external link to a credible source.';

		// ── Image alt text ────────────────────────────────────────
		$images_total  = 0;
		$images_no_alt = 0;
		if ( preg_match_all( '/<img\s[^>]+>/i', $raw_content, $im ) ) {
			foreach ( $im[0] as $img_tag ) {
				$images_total++;
				if ( ! preg_match( '/\balt=["\'][^"\']+["\']/i', $img_tag ) ) {
					$images_no_alt++;
				}
			}
		}
		if ( $images_no_alt > 0 ) {
			$tips[] = $images_no_alt . ' image' . ( $images_no_alt > 1 ? 's are' : ' is' ) . ' missing alt text.';
		}

		$score = min( 100, $score );

		// Back-compat single-phrase keys (first phrase)
		$primary = ! empty( $keyphrases ) ? $keyphrases[0] : array();

		return array(
			'score'            => $score,
			'color'            => self::color_class( $score ),
			'tips'             => $tips,
			'keyphrases'       => $keyphrases,
			// Legacy single-phrase keys for anything still reading them
			'kw_count'         => $primary['count']      ?? 0,
			'kw_density'       => $primary['density']    ?? 0.0,
			'kw_in_title'      => $primary['in_title']   ?? false,
			'kw_in_desc'       => $primary['in_desc']    ?? false,
			'kw_in_content'    => $primary['in_content'] ?? false,
			'kw_in_first_para' => $primary['in_first']   ?? false,
			'kw_in_heading'    => $primary['in_heading'] ?? false,
			'internal_links'   => $internal_links,
			'external_links'   => $external_links,
			'images_total'     => $images_total,
			'images_no_alt'    => $images_no_alt,
		);
	}

	public static function color_class( $score ) {
		if ( $score >= 70 ) return 'green';
		if ( $score >= 40 ) return 'orange';
		return 'red';
	}
}
