<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Ateculus_SEO_Readability {

	private static $transition_words = array(
		'however', 'therefore', 'furthermore', 'moreover', 'consequently',
		'additionally', 'nevertheless', 'meanwhile', 'subsequently',
		'accordingly', 'thus', 'hence', 'firstly', 'secondly', 'thirdly',
		'finally', 'in addition', 'as a result', 'for example', 'for instance',
		'in conclusion', 'in summary', 'on the other hand', 'in contrast',
		'similarly', 'likewise', 'in other words', 'that is', 'above all',
		'after all', 'in fact', 'as a consequence', 'in particular',
		'notably', 'specifically', 'to summarize', 'in short', 'overall',
		'to begin with', 'to conclude',
	);

	public function analyze( $content ) {
		$score = 0;
		$tips  = array();
		$text  = wp_strip_all_tags( $content );

		// Word count
		$words = str_word_count( $text );
		if ( $words >= 300 ) {
			$score += 20;
		} elseif ( $words >= 150 ) {
			$score += 10;
			$tips[] = 'Content is short (' . $words . ' words). Aim for 300+.';
		} else {
			$tips[] = 'Content is very short (' . $words . ' words). Aim for at least 300 words.';
		}

		// Avg sentence length
		$sentences      = preg_split( '/[.!?]+(?:\s|$)/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentence_count = max( 1, count( $sentences ) );
		$avg_sent       = $words / $sentence_count;
		if ( $avg_sent <= 20 ) {
			$score += 20;
		} elseif ( $avg_sent <= 25 ) {
			$score += 10;
			$tips[] = 'Average sentence length is ' . round( $avg_sent ) . ' words. Try to keep sentences under 20 words.';
		} else {
			$tips[] = 'Sentences are too long (avg ' . round( $avg_sent ) . ' words). Break them up.';
		}

		// Subheadings
		$h_count = preg_match_all( '/<h[2-4][^>]*>/i', $content );
		if ( $h_count >= 2 ) {
			$score += 20;
		} elseif ( $h_count === 1 ) {
			$score += 10;
			$tips[] = 'Add more subheadings (H2/H3) to structure your content.';
		} else {
			$tips[] = 'Add subheadings (H2/H3) to break up your content.';
		}

		// Long paragraphs
		$paragraphs  = preg_split( '/<\/p>/i', $content, -1, PREG_SPLIT_NO_EMPTY );
		$long_paras  = 0;
		foreach ( $paragraphs as $p ) {
			$p_words = str_word_count( wp_strip_all_tags( $p ) );
			if ( $p_words > 150 ) $long_paras++;
		}
		if ( $long_paras === 0 ) {
			$score += 20;
		} else {
			$score += 8;
			$tips[] = $long_paras . ' paragraph(s) are very long. Consider breaking them up.';
		}

		// Transition words
		$text_lower      = strtolower( $text );
		$transition_hits = 0;
		foreach ( self::$transition_words as $tw ) {
			$transition_hits += substr_count( $text_lower, $tw );
		}
		$transition_ratio = $transition_hits / $sentence_count;
		if ( $transition_ratio >= 0.25 ) {
			$score += 20;
		} elseif ( $transition_ratio >= 0.10 ) {
			$score += 10;
			$tips[] = 'Use more transition words to improve flow (however, therefore, furthermore…).';
		} else {
			$tips[] = 'Add transition words to improve readability.';
		}

		$score = min( 100, $score );

		return array(
			'score'        => $score,
			'color'        => $score >= 70 ? 'green' : ( $score >= 40 ? 'orange' : 'red' ),
			'tips'         => $tips,
			'word_count'   => $words,
			'sentence_count' => $sentence_count,
			'avg_sentence' => round( $avg_sent, 1 ),
		);
	}
}
