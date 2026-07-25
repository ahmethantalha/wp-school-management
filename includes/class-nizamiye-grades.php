<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
// Bu dosyadaki tüm $wpdb sorguları eklentiye özel tablolar (wp_nizamiye_*) üzerinde çalışır;
// tüm parametreler $wpdb->prepare() ile bağlanır ya da intval()/whitelist ile temizlenir.
// WordPress çekirdeğinde özel tablolar için bir soyutlama/önbellekleme API'si olmadığından
// doğrudan $wpdb kullanımı kaçınılmazdır; bkz. güvenlik incelemesinde doğrulanan analiz.

/**
 * Not / sınav kayıtları.
 */
class Nizamiye_Grades {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nizamiye_grades';
	}

	/** Dönemdeki branşlar (derslik ve not sayılarıyla). Boş branş 'Diğer' olarak döner. */
	public static function subjects_for_term( $term_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT COALESCE(NULLIF(TRIM(c.subject), ''), 'Diğer') AS subject,
				COUNT(DISTINCT c.id) AS class_count,
				COUNT(g.id) AS grade_count
			 FROM {$wpdb->prefix}nizamiye_classes c
			 LEFT JOIN {$wpdb->prefix}nizamiye_grades g ON g.class_id = c.id
			 WHERE c.term_id = %d
			 GROUP BY COALESCE(NULLIF(TRIM(c.subject), ''), 'Diğer')
			 ORDER BY subject",
			$term_id
		) );
	}

	/** Branşa ait derslikler (not sayılarıyla). $subject='Diğer' boş branşı da kapsar. */
	public static function classes_for_subject( $term_id, $subject ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*,
				(SELECT COUNT(*) FROM {$wpdb->prefix}nizamiye_class_students cs WHERE cs.class_id = c.id) AS student_count,
				(SELECT COUNT(DISTINCT CONCAT(g.title,'|',COALESCE(g.exam_date,''),'|',COALESCE(g.exam_type,''))) FROM {$wpdb->prefix}nizamiye_grades g WHERE g.class_id = c.id) AS exam_count
			 FROM {$wpdb->prefix}nizamiye_classes c
			 WHERE c.term_id = %d AND COALESCE(NULLIF(TRIM(c.subject), ''), 'Diğer') = %s
			 ORDER BY c.grade_level, c.name",
			$term_id, $subject
		) );
	}

	/** Dersliğin sınavları (başlık+tarih+tür bazında gruplu). */
	public static function exams_for_class( $class_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT title, COALESCE(exam_type,'') AS exam_type, exam_date,
				COUNT(*) AS cnt,
				ROUND(AVG(score), 1) AS avg_score,
				MAX(max_score) AS max_score,
				ROUND(AVG(score / max_score * 100)) AS avg_rate
			 FROM {$wpdb->prefix}nizamiye_grades
			 WHERE class_id = %d AND max_score > 0
			 GROUP BY title, COALESCE(exam_type,''), exam_date
			 ORDER BY exam_date DESC, title",
			$class_id
		) );
	}

	/** Tek sınavın öğrenci bazında puanları. */
	public static function exam_scores( $class_id, $title, $exam_date, $exam_type ) {
		global $wpdb;
		$sql    = "SELECT g.*, s.first_name, s.last_name FROM {$wpdb->prefix}nizamiye_grades g
			 INNER JOIN {$wpdb->prefix}nizamiye_students s ON s.id = g.student_id
			 WHERE g.class_id = %d AND g.title = %s AND COALESCE(g.exam_type,'') = %s";
		$params = array( (int) $class_id, $title, (string) $exam_type );
		if ( '' === (string) $exam_date ) {
			$sql .= ' AND g.exam_date IS NULL';
		} else {
			$sql     .= ' AND g.exam_date = %s';
			$params[] = $exam_date;
		}
		$sql .= ' ORDER BY g.score DESC, s.first_name';
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/** Dersliğin sınav kayıtları (öğrenci adlarıyla). */
	public static function for_class( $class_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT g.*, s.first_name, s.last_name FROM {$wpdb->prefix}nizamiye_grades g
			 INNER JOIN {$wpdb->prefix}nizamiye_students s ON s.id = g.student_id
			 WHERE g.class_id = %d ORDER BY g.exam_date DESC, g.title, s.first_name",
			$class_id
		) );
	}

	/** Öğrencinin dönem notları. */
	public static function for_student( $student_id, $term_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT g.*, c.name AS class_name, c.subject FROM {$wpdb->prefix}nizamiye_grades g
			 INNER JOIN {$wpdb->prefix}nizamiye_classes c ON c.id = g.class_id
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
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nizamiye_grades WHERE id = %d", $id ) );
	}

	/** Öğrenci bazında dönem not ortalaması (yüzde): student_id => yüzde. Tek istek içinde memoize edilir. */
	public static function rates_by_student( $term_id ) {
		static $cache = array();
		$term_id = (int) $term_id;
		if ( isset( $cache[ $term_id ] ) ) {
			return $cache[ $term_id ];
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT g.student_id, ROUND(AVG(g.score / g.max_score * 100)) AS rate
			 FROM {$wpdb->prefix}nizamiye_grades g
			 INNER JOIN {$wpdb->prefix}nizamiye_classes c ON c.id = g.class_id
			 WHERE c.term_id = %d AND g.max_score > 0 GROUP BY g.student_id",
			$term_id
		) );
		$out = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->student_id ] = (int) $r->rate;
		}
		$cache[ $term_id ] = $out;
		return $out;
	}

	/** Öğrencinin ders bazında ortalamaları. */
	public static function student_class_averages( $student_id, $term_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.name AS class_name, c.subject, COUNT(g.id) AS exam_count,
				ROUND(AVG(g.score / g.max_score * 100)) AS avg_rate
			 FROM {$wpdb->prefix}nizamiye_grades g
			 INNER JOIN {$wpdb->prefix}nizamiye_classes c ON c.id = g.class_id
			 WHERE g.student_id = %d AND c.term_id = %d AND g.max_score > 0
			 GROUP BY c.id, c.name, c.subject ORDER BY c.name",
			$student_id, $term_id
		) );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
