<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_AI_Suggest {

	public function __construct() {
		add_action( 'wp_ajax_aseo_ai_suggest', array( $this, 'handle' ) );
	}

	public function handle() {
		check_ajax_referer( 'aseo_ai_suggest', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			wp_send_json_error( 'Post not found. Please save the draft first, then try again.' );
		}

		$raw_content = apply_filters( 'the_content', $post->post_content );

		// Extract first paragraph (same logic as scorer)
		$first_para = '';
		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $raw_content, $pm ) ) {
			$first_para = trim( wp_strip_all_tags( $pm[1] ) );
		}

		// Extract h2/h3 subheadings (same as scorer)
		$headings = array();
		if ( preg_match_all( '/<h[23][^>]*>(.*?)<\/h[23]>/is', $raw_content, $hm ) ) {
			foreach ( $hm[1] as $h ) {
				$headings[] = trim( wp_strip_all_tags( $h ) );
			}
		}

		// Full plain-text content (no truncation) for keyphrase matching
		$content_full = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( $raw_content ) ) );

		// Truncated content for AI context (5 000 chars ≈ ~1 250 tokens)
		$content_ctx = mb_substr( $content_full, 0, 5000 );

		if ( empty( $content_ctx ) ) {
			wp_send_json_error( 'No content found. Write some content first, then try again.' );
		}

		// ── Server-side keyphrase selection ──────────────────────────
		// Returns up to 3 phrases — all verified to exist verbatim in a
		// heading and in the article content.  Primary (index 0) also
		// appears in the first paragraph when possible.
		$keyphrases = $this->pick_keyphrases( $headings, $first_para, $content_full );

		if ( empty( $keyphrases ) ) {
			wp_send_json_error( 'Could not find keyphrases in your headings. Add at least one H2 or H3 subheading, save, then try again.' );
		}

		$primary      = $keyphrases[0];
		$focus_kw_out = implode( ', ', $keyphrases );

		$opts     = get_option( 'aseo_ai_options', array() );
		$provider = $opts['ai_provider'] ?? 'groq';

		// ── Prompt ───────────────────────────────────────────────────
		// focus_kw is set server-side — only ask AI for title, desc, keywords.
		$kw_quoted = '"' . $primary . '"';

		$prompt  = "You are an expert SEO copywriter. Return ONLY a valid JSON object — no markdown, no code fences, no explanation.\n\n";
		$prompt .= "PRIMARY KEYPHRASE (pre-selected): " . $kw_quoted . "\n\n";
		$prompt .= "Generate these JSON fields:\n";
		$prompt .= "- \"title\": 40-60 characters. The phrase " . $kw_quoted . " MUST appear inside it as consecutive words (verbatim, may capitalise).\n";
		$prompt .= "- \"description\": 120-155 characters, no trailing ellipsis. The phrase " . $kw_quoted . " MUST appear inside it verbatim.\n";
		$prompt .= "- \"keywords\": comma-separated STRING (not array) of 6-8 meta keywords.\n\n";
		$prompt .= "Article title: " . get_the_title( $post ) . "\n";
		$prompt .= "Subheadings: " . ( $headings ? implode( ' | ', $headings ) : '(none)' ) . "\n\n";
		$prompt .= "Content:\n" . $content_ctx;

		$result = ( $provider === 'gemini' )
			? $this->call_gemini( $prompt, $opts )
			: $this->call_groq( $prompt, $opts );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// ── Parse JSON ───────────────────────────────────────────────
		$text = trim( $result );
		$text = preg_replace( '/^```(?:json)?[\r\n]*/i', '', $text );
		$text = preg_replace( '/[\r\n]*```\s*$/i', '', $text );
		$text = trim( $text );

		if ( substr( $text, 0, 1 ) !== '{' ) {
			if ( preg_match( '/\{[\s\S]+\}/U', $text, $m ) ) {
				$text = $m[0];
			}
		}

		$data = json_decode( $text, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( 'AI returned an unexpected format. Please try again.' );
		}

		$keywords = $data['keywords'] ?? '';
		if ( is_array( $keywords ) ) $keywords = implode( ', ', $keywords );

		$title       = mb_substr( sanitize_text_field( $data['title'] ?? '' ), 0, 60 );
		$description = mb_substr( sanitize_textarea_field( $data['description'] ?? '' ), 0, 155 );

		// Safety net: if title doesn't contain the primary keyphrase, inject it
		if ( $title && stripos( $title, $primary ) === false ) {
			$title = mb_substr( ucwords( $primary ) . ': ' . $title, 0, 60 );
		}

		// Safety net: if description doesn't contain the primary keyphrase, inject it
		if ( $description && stripos( $description, $primary ) === false ) {
			$description = mb_substr( ucfirst( $primary ) . ' ' . lcfirst( $description ), 0, 155 );
		}

		wp_send_json_success( array(
			'focus_kw'    => sanitize_text_field( $focus_kw_out ),
			'title'       => $title,
			'description' => $description,
			'keywords'    => sanitize_text_field( $keywords ),
		) );
	}

	// ── Server-side keyphrase picker ─────────────────────────────────
	// Returns up to 3 phrases, all sourced from h2/h3 headings and
	// verified to appear verbatim in the article content.
	// Primary (index 0) scores highest — prefers heading + first-para match.
	// Secondaries add bonus points without requiring title/desc placement.
	private function pick_keyphrases( array $headings, string $first_para, string $content ) : array {
		static $stops = [
			'a','an','the','and','or','but','in','on','at','to','for','of','with','by','from',
			'is','it','as','be','was','are','were','been','has','have','had','will','can','could',
			'not','if','its','do','does','get','gets','s','so','we','our','you','your','they',
			'their','this','that','these','those','when','how','what','why','where','which',
		];

		$fp_lower  = mb_strtolower( $first_para );
		$con_lower = mb_strtolower( $content );

		$pool = [];

		foreach ( $headings as $heading ) {
			$h_words = preg_split( '/\s+/', mb_strtolower( trim( $heading ) ), -1, PREG_SPLIT_NO_EMPTY );
			$n       = count( $h_words );

			for ( $len = min( 5, $n ); $len >= 2; $len-- ) {
				for ( $i = 0; $i + $len <= $n; $i++ ) {
					$slice  = array_slice( $h_words, $i, $len );
					$phrase = implode( ' ', $slice );

					// Skip stop-word-dominated phrases
					$stop_count = count( array_filter( $slice, fn( $w ) => in_array( $w, $stops, true ) ) );
					if ( $stop_count >= $len ) continue;
					if ( $len >= 3 && $stop_count > $len - 2 ) continue;

					// Must appear somewhere in content
					if ( strpos( $con_lower, $phrase ) === false ) continue;

					// Score: length bonus + +10 if also in first paragraph, +2 content-only
					$score = $len;
					$score += ( $fp_lower && strpos( $fp_lower, $phrase ) !== false ) ? 10 : 2;

					$pool[ $phrase ] = max( $pool[ $phrase ] ?? 0, $score );
				}
			}
		}

		if ( empty( $pool ) ) {
			// Fallback: 3 meaningful words from first paragraph
			if ( $first_para ) {
				$words    = preg_split( '/\s+/', $fp_lower, -1, PREG_SPLIT_NO_EMPTY );
				$filtered = array_values( array_filter( $words, fn( $w ) => ! in_array( $w, $stops, true ) && strlen( $w ) > 2 ) );
				if ( count( $filtered ) >= 2 ) {
					return [ implode( ' ', array_slice( $filtered, 0, 3 ) ) ];
				}
			}
			if ( $headings ) {
				$words = preg_split( '/\s+/', mb_strtolower( $headings[0] ), -1, PREG_SPLIT_NO_EMPTY );
				return [ implode( ' ', array_slice( $words, 0, 3 ) ) ];
			}
			return [];
		}

		// Sort by score descending
		arsort( $pool );

		// Pick up to 3, skipping any that are a substring of an already-selected phrase
		// (avoids redundant sub-phrases like "wordpress site" when "wordpress site hacked" is selected)
		$selected = [];
		foreach ( $pool as $phrase => $score ) {
			$redundant = false;
			foreach ( $selected as $already ) {
				if ( strpos( $already, $phrase ) !== false ) {
					$redundant = true;
					break;
				}
			}
			if ( ! $redundant ) {
				$selected[] = $phrase;
			}
			if ( count( $selected ) >= 3 ) break;
		}

		return $selected;
	}

	// ── Groq (OpenAI-compatible) ──────────────────────────────────────
	private function call_groq( $prompt, $opts, $attempt = 1 ) {
		$api_key = $opts['groq_api_key'] ?? '';
		$model   = $opts['groq_model']   ?? 'llama-3.1-8b-instant';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_key', 'No Groq API key configured. Go to Ateculus SEO → AI Suggestions.' );
		}

		$response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body' => wp_json_encode( array(
				'model'       => $model,
				'max_tokens'  => 512,
				'temperature' => 0.3,
				'messages'    => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			if ( $attempt === 1 && in_array( $code, array( 429, 503 ), true ) ) {
				sleep( 3 );
				return $this->call_groq( $prompt, $opts, 2 );
			}
			$msg   = $body['error']['message'] ?? 'Groq API error (HTTP ' . $code . ').';
			$first = trim( strtok( str_replace( array( '*', '•' ), '', $msg ), "\n" ) );
			return new WP_Error( 'groq_error', $first ?: 'Groq API error.' );
		}

		$text = trim( $body['choices'][0]['message']['content'] ?? '' );

		if ( $text === '' ) {
			return new WP_Error( 'empty_response', 'Groq returned an empty response. Please try again.' );
		}

		return $text;
	}

	// ── Google Gemini ─────────────────────────────────────────────────
	private function call_gemini( $prompt, $opts, $attempt = 1 ) {
		$api_key = $opts['gemini_api_key'] ?? '';
		$model   = $opts['gemini_model']   ?? 'gemini-2.5-flash';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_key', 'No Gemini API key configured. Go to Ateculus SEO → AI Suggestions.' );
		}

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
		     . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$response = wp_remote_post( $url, array(
			'timeout' => 30,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array(
				'contents'         => array(
					array( 'parts' => array( array( 'text' => $prompt ) ) ),
				),
				'generationConfig' => array(
					'maxOutputTokens' => 512,
					'thinkingConfig'  => array( 'thinkingBudget' => 0 ),
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			if ( $attempt === 1 && in_array( $code, array( 429, 503 ), true ) ) {
				sleep( 3 );
				return $this->call_gemini( $prompt, $opts, 2 );
			}
			$raw = $body['error']['message'] ?? 'Gemini API error (HTTP ' . $code . ').';
			if ( stripos( $raw, 'high demand' ) !== false ) {
				return new WP_Error( 'gemini_busy', 'Gemini is busy right now — wait a moment and try again.' );
			}
			$first = trim( strtok( str_replace( array( '*', '•' ), '', $raw ), "\n" ) );
			return new WP_Error( 'gemini_error', $first ?: 'Gemini API error.' );
		}

		$parts = $body['candidates'][0]['content']['parts'] ?? array();
		$text  = '';
		foreach ( $parts as $part ) {
			if ( empty( $part['thought'] ) && isset( $part['text'] ) ) {
				$text .= $part['text'];
			}
		}
		if ( $text === '' ) {
			foreach ( $parts as $part ) {
				if ( isset( $part['text'] ) ) $text .= $part['text'];
			}
		}

		if ( $text === '' ) {
			return new WP_Error( 'empty_response', 'Gemini returned an empty response. Please try again.' );
		}

		return $text;
	}
}
