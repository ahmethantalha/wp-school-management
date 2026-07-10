<?php
defined( 'ABSPATH' ) || exit;

/**
 * Not / sınav kayıtları.
 */
class SMS_Grades {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'sms_grades';
	}

	/** Dersliğin sınav kayıtları (öğrenci adlarıyla). */
	public static function for_class( $class_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT g.*, s.first_name, s.last_name FROM ' . self::table() . " g
			 INNER JOIN {$wpdb->prefix}sms_students s ON s.id = g.student_id
			 WHERE g.class_id = %d ORDER BY g.exam_date DESC, g.title, s.first_name",
			$class_id
		) );
	}

	/** Öğrencinin dönem notları. */
	public static function for_student( $student_id, $term_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT g.*, c.name AS class_name, c.subject FROM ' . self::table() . " g
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = g.class_id
			 WHERE g.student_id = %d AND c.term_id = %d
			 ORDER BY g.exam_date DESC, g.id DESC",
			$student_id, $term_id
		) );
	}

	/** Tek sınav için toplu puan girişi. $scores: student_id => puan (boşlar atlanır). */
	public static function add_exam( $class_id, $title, $exam_type, $exam_date, $max_score, array $scores, $recorded_by ) {
		global $wpdb;
		$count = 0;
		foreach ( $scores as $student_id => $score ) {
			if ( '' === trim( (string) $score ) ) {
				continue;
			}
			$wpdb->insert( self::table(), array(
				'class_id'    => (int) $class_id,
				'student_id'  => (int) $student_id,
				'title'       => sanitize_text_field( $title ),
				'exam_type'   => sanitize_text_field( $exam_type ),
				'score'       => (float) str_replace( ',', '.', (string) $score ),
				'max_score'   => (float) $max_score ?: 100,
				'exam_date'   => $exam_date ?: null,
				'recorded_by' => (int) $recorded_by,
				'created_at'  => current_time( 'mysql' ),
			) );
			$count++;
		}
		return $count;
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	/** Öğrenci bazında dönem not ortalaması (yüzde): student_id => yüzde. */
	public static function rates_by_student( $term_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT g.student_id, ROUND(AVG(g.score / g.max_score * 100)) AS rate
			 FROM ' . self::table() . " g
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = g.class_id
			 WHERE c.term_id = %d AND g.max_score > 0 GROUP BY g.student_id",
			$term_id
		) );
		$out = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->student_id ] = (int) $r->rate;
		}
		return $out;
	}

	/** Öğrencinin ders bazında ortalamaları. */
	public static function student_class_averages( $student_id, $term_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT c.name AS class_name, c.subject, COUNT(g.id) AS exam_count,
				ROUND(AVG(g.score / g.max_score * 100)) AS avg_rate
			 FROM ' . self::table() . " g
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = g.class_id
			 WHERE g.student_id = %d AND c.term_id = %d AND g.max_score > 0
			 GROUP BY c.id, c.name, c.subject ORDER BY c.name",
			$student_id, $term_id
		) );
	}
}
