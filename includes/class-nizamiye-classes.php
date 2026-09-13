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
		$sql .= ' ORDER BY c.grade_level, c.section, c.name';
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$row = array(
			'term_id'     => (int) ( $data['term_id'] ?? 0 ),
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'subject'     => sanitize_text_field( $data['subject'] ?? '' ),
			'grade_level' => ! empty( $data['grade_level'] ) ? (int) $data['grade_level'] : null,
			'section'     => ! empty( $data['section'] ) ? nizamiye_normalize_section( $data['section'] ) : null,
			'auto_roster' => ! empty( $data['auto_roster'] ) ? 1 : 0,
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

	/**
	 * Bir "kural"a (sınıf seviyesi + şube) uyan aktif öğrencilerin id'leri.
	 * $section boşsa kural yalnızca sınıf seviyesidir — o sınıfın tüm şubeleri.
	 */
	public static function rule_student_ids( $term_id, $grade, $section = '' ) {
		global $wpdb;
		$grade = (int) $grade;
		if ( ! $term_id || ! $grade ) {
			return array();
		}
		$sql    = "SELECT e.student_id
			 FROM {$wpdb->prefix}nizamiye_enrollments e
			 INNER JOIN {$wpdb->prefix}nizamiye_students s ON s.id = e.student_id
			 WHERE e.term_id = %d AND e.grade_level = %d AND e.status = 'active' AND s.status = 'active'";
		$params = array( (int) $term_id, $grade );

		$section = nizamiye_normalize_section( $section );
		if ( '' !== $section ) {
			$sql     .= ' AND e.section = %s';
			$params[] = $section;
		}
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) );
	}

	/**
	 * Dersliğin mevcut kadrosu ile kuralının öngördüğü kadro arasındaki fark.
	 * auto_roster kapalıysa fark yoktur (kadro tamamen elle yönetilir).
	 *
	 * @return array{add:int[],remove:int[]}
	 */
	public static function roster_diff( $class_id ) {
		$none  = array( 'add' => array(), 'remove' => array() );
		$class = self::get( $class_id );
		if ( ! $class || empty( $class->auto_roster ) ) {
			return $none;
		}
		$expected = self::rule_student_ids( (int) $class->term_id, (int) $class->grade_level, (string) $class->section );
		$current  = self::student_ids( (int) $class->id );
		return array(
			'add'    => array_values( array_diff( $expected, $current ) ),
			'remove' => array_values( array_diff( $current, $expected ) ),
		);
	}

	/**
	 * Dersliğin kadrosunu kuralıyla tam olarak eşitler (ekler VE çıkarır).
	 * Yalnızca kullanıcı onayıyla çağrılır — çıkarma işlemi asla kendiliğinden olmaz.
	 *
	 * @return array{added:int,removed:int}
	 */
	public static function sync_roster( $class_id ) {
		$class = self::get( $class_id );
		if ( ! $class || empty( $class->auto_roster ) ) {
			return array( 'added' => 0, 'removed' => 0 );
		}
		$diff     = self::roster_diff( $class_id );
		$expected = self::rule_student_ids( (int) $class->term_id, (int) $class->grade_level, (string) $class->section );
		self::set_students( (int) $class->id, $expected );
		return array( 'added' => count( $diff['add'] ), 'removed' => count( $diff['remove'] ) );
	}

	/**
	 * Öğrenciyi kuralına uyan kurallı dersliklere EKLER; hiçbir yerden çıkarmaz.
	 * Şubesi değişen öğrenci eski dersliklerinde kalır ve ancak derslik ekranındaki
	 * onaylı senkronla çıkarılır — böylece seviye grubu gibi bilinçli istisnalar
	 * ve elle yapılmış düzenlemeler habersizce silinmez.
	 */
	public static function sync_student( $student_id, $term_id ) {
		global $wpdb;
		$student_id = (int) $student_id;
		$term_id    = (int) $term_id;
		if ( ! $student_id || ! $term_id ) {
			return 0;
		}

		$enrollment = Nizamiye_Students::enrollment( $student_id, $term_id );
		if ( ! $enrollment || 'active' !== $enrollment->status ) {
			return 0;
		}
		$grade   = (int) $enrollment->grade_level;
		$section = nizamiye_normalize_section( $enrollment->section ?? '' );
		if ( ! $grade ) {
			return 0;
		}

		// Kuralı öğrenciyle eşleşen derslikler: aynı sınıf seviyesi ve
		// (şubesiz kural VEYA öğrencinin şubesiyle aynı şube).
		$sql     = "SELECT id FROM {$wpdb->prefix}nizamiye_classes
			 WHERE term_id = %d AND auto_roster = 1 AND grade_level = %d
			   AND (section IS NULL OR section = '' OR section = %s)";
		$classes = $wpdb->get_col( $wpdb->prepare( $sql, $term_id, $grade, $section ) );

		$added = 0;
		foreach ( array_map( 'intval', $classes ) as $class_id ) {
			if ( in_array( $student_id, self::student_ids( $class_id ), true ) ) {
				continue;
			}
			$wpdb->insert( $wpdb->prefix . 'nizamiye_class_students', array(
				'class_id'   => $class_id,
				'student_id' => $student_id,
			) );
			$added++;
		}
		return $added;
	}

	/**
	 * Kadrosu kuralıyla uyuşmayan derslik sayısı (liste sayfasındaki uyarı için).
	 * Tek sorguda: kurallı her dersliğin kadro sayısı ile kuralın öngördüğü sayı
	 * ve kesişim sayısı karşılaştırılır.
	 */
	public static function stale_ids( $term_id ) {
		global $wpdb;

		// Her derslik için ayrı ayrı roster_diff() çağırmak derslik başına üç sorgu
		// demekti; bu metot derslikler listesinin her açılışında çalıştığından
		// karşılaştırma tek sorguya indirildi. Derslik başına üç sayı hesaplanır:
		//   expected — kuralın öngördüğü öğrenci sayısı
		//   actual   — kadroda fiilen kaç kişi var
		//   matched  — kadrodakilerden kaça kuralın da uyduğu
		// Kadro kuralla birebir aynıysa üçü de eşittir; herhangi bir sapma eksik
		// ya da fazla öğrenci olduğu anlamına gelir.
		$sql = "SELECT t.id FROM (
			SELECT c.id AS id,
				(SELECT COUNT(*)
				   FROM {$wpdb->prefix}nizamiye_enrollments e
				   INNER JOIN {$wpdb->prefix}nizamiye_students s ON s.id = e.student_id
				  WHERE e.term_id = c.term_id AND e.grade_level = c.grade_level
				    AND e.status = 'active' AND s.status = 'active'
				    AND (c.section IS NULL OR c.section = '' OR e.section = c.section)
				) AS expected,
				(SELECT COUNT(*)
				   FROM {$wpdb->prefix}nizamiye_class_students cs
				  WHERE cs.class_id = c.id
				) AS actual,
				(SELECT COUNT(*)
				   FROM {$wpdb->prefix}nizamiye_class_students cs
				   INNER JOIN {$wpdb->prefix}nizamiye_enrollments e
				           ON e.student_id = cs.student_id AND e.term_id = c.term_id
				   INNER JOIN {$wpdb->prefix}nizamiye_students s ON s.id = cs.student_id
				  WHERE cs.class_id = c.id AND e.grade_level = c.grade_level
				    AND e.status = 'active' AND s.status = 'active'
				    AND (c.section IS NULL OR c.section = '' OR e.section = c.section)
				) AS matched
			  FROM {$wpdb->prefix}nizamiye_classes c
			 WHERE c.term_id = %d AND c.auto_roster = 1
		) t WHERE t.expected <> t.matched OR t.actual <> t.matched";

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, (int) $term_id ) ) );
	}

	/** Dönemde kullanılan branşlar (toplu oluşturma formunu önceden doldurur). */
	public static function subjects_in_term( $term_id ) {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT subject FROM {$wpdb->prefix}nizamiye_classes
			 WHERE term_id = %d AND subject IS NOT NULL AND subject != '' ORDER BY subject",
			$term_id
		) );
	}

	/**
	 * Aynı dönemde aynı branş + sınıf + şube için derslik var mı?
	 * Toplu oluşturmanın idempotent olmasını sağlar (tekrar çalıştırmak kopya üretmez).
	 */
	public static function find_by_rule( $term_id, $subject, $grade, $section ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}nizamiye_classes
			 WHERE term_id = %d AND subject = %s AND grade_level = %d AND COALESCE(section,'') = %s LIMIT 1",
			(int) $term_id,
			sanitize_text_field( $subject ),
			(int) $grade,
			nizamiye_normalize_section( $section )
		) );
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
