<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
// Bu dosyadaki tüm $wpdb sorguları eklentiye özel tablolar (wp_nizamiye_*) üzerinde çalışır;
// tüm parametreler $wpdb->prepare() ile bağlanır ya da intval()/whitelist ile temizlenir.
// WordPress çekirdeğinde özel tablolar için bir soyutlama/önbellekleme API'si olmadığından
// doğrudan $wpdb kullanımı kaçınılmazdır; bkz. güvenlik incelemesinde doğrulanan analiz.

/**
 * Derslikler / şubeler ve kadro yönetimi.
 */
class Nizamiye_Classes {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nizamiye_classes';
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nizamiye_classes WHERE id = %d", $id ) );
	}

	/** Dönemin derslikleri; $teacher_id verilirse yalnızca o öğretmeninkiler. */
	public static function for_term( $term_id, $teacher_id = 0 ) {
		global $wpdb;
		$sql    = "SELECT c.*, (SELECT COUNT(*) FROM {$wpdb->prefix}nizamiye_class_students cs WHERE cs.class_id = c.id) AS student_count FROM {$wpdb->prefix}nizamiye_classes c WHERE c.term_id = %d";
		$params = array( (int) $term_id );
		if ( $teacher_id ) {
			$sql     .= ' AND c.teacher_id = %d';
			$params[] = (int) $teacher_id;
		}
		$sql .= ' ORDER BY c.grade_level, c.name';
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$row = array(
			'term_id'     => (int) ( $data['term_id'] ?? 0 ),
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'subject'     => sanitize_text_field( $data['subject'] ?? '' ),
			'grade_level' => ! empty( $data['grade_level'] ) ? (int) $data['grade_level'] : null,
			'teacher_id'  => ! empty( $data['teacher_id'] ) ? (int) $data['teacher_id'] : null,
		);
		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		$wpdb->delete( $wpdb->prefix . 'nizamiye_class_students', array( 'class_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'nizamiye_attendance', array( 'class_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'nizamiye_grades', array( 'class_id' => $id ) );
		$wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	/** Dersliğin öğrenci ID'leri. */
	public static function student_ids( $class_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT student_id FROM {$wpdb->prefix}nizamiye_class_students WHERE class_id = %d",
			$class_id
		) ) );
	}

	/** Dersliğin öğrencileri (öğrenci satırlarıyla). */
	public static function students( $class_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT s.* FROM {$wpdb->prefix}nizamiye_class_students cs
			 INNER JOIN {$wpdb->prefix}nizamiye_students s ON s.id = cs.student_id
			 WHERE cs.class_id = %d ORDER BY s.first_name",
			$class_id
		) );
	}

	/** Kadroyu topluca ayarlar. */
	public static function set_students( $class_id, array $student_ids ) {
		global $wpdb;
		$class_id    = (int) $class_id;
		$student_ids = array_unique( array_map( 'intval', $student_ids ) );
		$current     = self::student_ids( $class_id );

		foreach ( array_diff( $current, $student_ids ) as $remove ) {
			$wpdb->delete( $wpdb->prefix . 'nizamiye_class_students', array( 'class_id' => $class_id, 'student_id' => $remove ) );
		}
		foreach ( array_diff( $student_ids, $current ) as $add ) {
			$wpdb->insert( $wpdb->prefix . 'nizamiye_class_students', array( 'class_id' => $class_id, 'student_id' => $add ) );
		}
	}

	/** Öğrencinin bir dönemdeki derslikleri. */
	public static function for_student( $student_id, $term_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.* FROM {$wpdb->prefix}nizamiye_class_students cs
			 INNER JOIN {$wpdb->prefix}nizamiye_classes c ON c.id = cs.class_id
			 WHERE cs.student_id = %d AND c.term_id = %d ORDER BY c.name",
			$student_id, $term_id
		) );
	}

	public static function count_for_term( $term_id, $teacher_id = 0 ) {
		global $wpdb;
		if ( $teacher_id ) {
			return (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}nizamiye_classes WHERE term_id = %d AND teacher_id = %d",
				$term_id, $teacher_id
			) );
		}
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}nizamiye_classes WHERE term_id = %d", $term_id
		) );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
