<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
// Bu dosyadaki tüm $wpdb sorguları eklentiye özel tablolar (wp_nizamiye_*) üzerinde çalışır;
// tüm parametreler $wpdb->prepare() ile bağlanır ya da intval()/whitelist ile temizlenir.
// WordPress çekirdeğinde özel tablolar için bir soyutlama/önbellekleme API'si olmadığından
// doğrudan $wpdb kullanımı kaçınılmazdır; bkz. güvenlik incelemesinde doğrulanan analiz.

/**
 * Öğrenciler ve dönem kayıtları (enrollment).
 */
class Nizamiye_Students {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nizamiye_students';
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nizamiye_students WHERE id = %d", $id ) );
	}

	/**
	 * Öğrenci listesi. $args: term_id, grade, section, status, search, ids (sınırla).
	 * term_id verilirse enrollment ile birleşir ve grade_level + section döner.
	 * section yalnızca term_id ile birlikte anlamlıdır (şube dönem kaydında yaşar).
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array(
			'term_id' => 0,
			'grade'   => 0,
			'section' => '',
			'status'  => 'active',
			'search'  => '',
			'ids'     => null,
		) );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 's.status = %s';
			$params[] = $args['status'];
		}
		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = "(CONCAT(s.first_name,' ',s.last_name) LIKE %s OR s.student_no LIKE %s OR s.school LIKE %s)";
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( is_array( $args['ids'] ) ) {
			if ( ! $args['ids'] ) {
				return array();
			}
			$id_list          = array_map( 'intval', $args['ids'] );
			$id_placeholders  = implode( ',', array_fill( 0, count( $id_list ), '%d' ) );
			$where[]          = "s.id IN ($id_placeholders)";
			$params           = array_merge( $params, $id_list );
		}

		if ( $args['term_id'] ) {
			$join     = "INNER JOIN {$wpdb->prefix}nizamiye_enrollments e ON e.student_id = s.id AND e.term_id = %d";
			$params_j = array( (int) $args['term_id'] );
			if ( $args['grade'] ) {
				$where[]  = 'e.grade_level = %d';
				$params[] = (int) $args['grade'];
			}
			if ( $args['section'] ) {
				$where[]  = 'e.section = %s';
				$params[] = nizamiye_normalize_section( $args['section'] );
			}
			$sql = "SELECT s.*, e.grade_level, e.section, e.status AS enrollment_status FROM {$wpdb->prefix}nizamiye_students s $join WHERE " . implode( ' AND ', $where ) . ' ORDER BY e.grade_level ASC, e.section ASC, s.first_name ASC';
			$params = array_merge( $params_j, $params );
		} else {
			$sql = "SELECT s.* FROM {$wpdb->prefix}nizamiye_students s WHERE " . implode( ' AND ', $where ) . ' ORDER BY s.first_name ASC';
		}

		return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
	}

	/** Kayıt (dönem) satırı. */
	public static function enrollment( $student_id, $term_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nizamiye_enrollments WHERE student_id = %d AND term_id = %d",
			$student_id, $term_id
		) );
	}

	/** Öğrencinin tüm dönem geçmişi. */
	public static function enrollment_history( $student_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT e.*, t.name AS term_name, t.is_active
			 FROM {$wpdb->prefix}nizamiye_enrollments e
			 INNER JOIN {$wpdb->prefix}nizamiye_terms t ON t.id = e.term_id
			 WHERE e.student_id = %d ORDER BY e.term_id DESC",
			$student_id
		) );
	}

	/**
	 * Ekle/güncelle. $data öğrenci alanları; $grade + $term_id kayıt için.
	 * $section: null = şubeye dokunma, '' = temizle, 'A' = ata (bkz. set_enrollment()).
	 */
	public static function save( $data, $term_id = 0, $grade = 0, $id = 0, $section = null ) {
		global $wpdb;
		$id = (int) $id;
		// $data['status'] yoksa varsayılan bir kez hesaplanır; aksi halde in_array()
		// kontrolü 'active' ile geçerken sonraki okuma tanımsız anahtara düşüyordu.
		$status = $data['status'] ?? 'active';
		$row = array(
			'first_name'     => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'      => sanitize_text_field( $data['last_name'] ?? '' ),
			'birth_date'     => ! empty( $data['birth_date'] ) ? sanitize_text_field( $data['birth_date'] ) : null,
			'school'         => sanitize_text_field( $data['school'] ?? '' ),
			'student_no'     => sanitize_text_field( $data['student_no'] ?? '' ),
			'parent_user_id' => ! empty( $data['parent_user_id'] ) ? (int) $data['parent_user_id'] : null,
			'user_id'        => ! empty( $data['user_id'] ) ? (int) $data['user_id'] : null,
			'status'         => in_array( $status, array( 'active', 'graduated', 'archived' ), true ) ? $status : 'active',
			'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
		);

		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => $id ) );
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$wpdb->insert( self::table(), $row );
			$id = (int) $wpdb->insert_id;
		}

		if ( $term_id && $grade ) {
			self::set_enrollment( $id, $term_id, $grade, $section );
		}

		return $id;
	}

	/**
	 * Dönem kaydını oluşturur/günceller (sınıf seviyesi elle de değiştirilebilir).
	 *
	 * @param string|null $section null ise şubeye DOKUNULMAZ. Şubeyi bilmeyen eski
	 *                             çağrı yollarının var olan şubeyi sessizce silmesini
	 *                             önler. '' temizler, 'A' atar.
	 */
	public static function set_enrollment( $student_id, $term_id, $grade, $section = null ) {
		global $wpdb;
		$existing = self::enrollment( $student_id, $term_id );
		$row      = array( 'grade_level' => (int) $grade );
		if ( null !== $section ) {
			$row['section'] = nizamiye_normalize_section( $section );
		}

		if ( $existing ) {
			$wpdb->update( $wpdb->prefix . 'nizamiye_enrollments', $row, array( 'id' => (int) $existing->id ) );
		} else {
			$wpdb->insert( $wpdb->prefix . 'nizamiye_enrollments', array_merge( $row, array(
				'student_id' => (int) $student_id,
				'term_id'    => (int) $term_id,
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
			) ) );
		}

		// Öğrenciyi kuralına uyan dersliklere ekler (asla çıkarmaz). Şube
		// değişikliği/yeni kayıt, kurallı dersliklerin kadrosuna anında yansısın diye.
		Nizamiye_Classes::sync_student( $student_id, $term_id );
	}

	/** Öğrenciyi ve tüm bağlı kayıtlarını siler. */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		foreach ( array( 'enrollments', 'class_students', 'attendance', 'habit_students', 'habit_logs', 'grades' ) as $t ) {
			$wpdb->delete( $wpdb->prefix . 'nizamiye_' . $t, array( 'student_id' => $id ) );
		}
		$wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	/** Velinin çocukları. */
	public static function children_of( $parent_user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nizamiye_students WHERE parent_user_id = %d ORDER BY first_name",
			$parent_user_id
		) );
	}

	/** Kullanıcı hesabına bağlı öğrenci. */
	public static function by_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nizamiye_students WHERE user_id = %d", $user_id
		) );
	}

	/** Dönemdeki aktif öğrenci sayısı. */
	public static function count_for_term( $term_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}nizamiye_enrollments e
			 INNER JOIN {$wpdb->prefix}nizamiye_students s ON s.id = e.student_id
			 WHERE e.term_id = %d AND e.status = 'active' AND s.status = 'active'",
			$term_id
		) );
	}

	/** Dönemdeki sınıf seviyeleri (filtre için). */
	public static function grades_in_term( $term_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT grade_level FROM {$wpdb->prefix}nizamiye_enrollments WHERE term_id = %d ORDER BY grade_level",
			$term_id
		) ) );
	}

	/**
	 * Dönemde fiilen kullanılan şubeler (filtre ve toplu derslik matrisi için).
	 * $grade verilirse yalnızca o sınıf seviyesinin şubeleri döner. Şubesi
	 * atanmamış kayıtlar (boş dize) listeye girmez.
	 */
	public static function sections_in_term( $term_id, $grade = 0 ) {
		global $wpdb;
		$sql    = "SELECT DISTINCT section FROM {$wpdb->prefix}nizamiye_enrollments WHERE term_id = %d AND section != ''";
		$params = array( (int) $term_id );
		if ( $grade ) {
			$sql     .= ' AND grade_level = %d';
			$params[] = (int) $grade;
		}
		$sql .= ' ORDER BY section';
		return $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
