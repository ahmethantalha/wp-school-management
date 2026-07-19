<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
// Bu dosyadaki tüm $wpdb sorguları eklentiye özel tablolar (wp_sms_*) üzerinde çalışır;
// tüm parametreler $wpdb->prepare() ile bağlanır ya da intval()/whitelist ile temizlenir.
// WordPress çekirdeğinde özel tablolar için bir soyutlama/önbellekleme API'si olmadığından
// doğrudan $wpdb kullanımı kaçınılmazdır; bkz. güvenlik incelemesinde doğrulanan analiz.

/**
 * Dönemler ve dönem geçişi (otomatik sınıf atlatma / mezuniyet).
 */
class SMS_Terms {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'sms_terms';
	}

	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY is_active DESC, id DESC' );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	public static function active() {
		global $wpdb;
		return $wpdb->get_row( 'SELECT * FROM ' . self::table() . ' WHERE is_active = 1 ORDER BY id DESC LIMIT 1' );
	}

	public static function create( $name, $start_date = null, $end_date = null, $activate = true ) {
		global $wpdb;
		$wpdb->insert( self::table(), array(
			'name'       => $name,
			'start_date' => $start_date ?: null,
			'end_date'   => $end_date ?: null,
			'is_active'  => 0,
			'created_at' => current_time( 'mysql' ),
		) );
		$id = (int) $wpdb->insert_id;
		if ( $activate ) {
			self::set_active( $id );
		}
		return $id;
	}

	public static function set_active( $id ) {
		global $wpdb;
		$wpdb->query( 'UPDATE ' . self::table() . ' SET is_active = 0' );
		$wpdb->update( self::table(), array( 'is_active' => 1 ), array( 'id' => (int) $id ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		// Döneme bağlı kayıt varsa silme.
		$has = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sms_enrollments WHERE term_id = %d", $id
		) );
		$has += (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}sms_classes WHERE term_id = %d", $id
		) );
		if ( $has > 0 ) {
			return new WP_Error( 'sms_term_in_use', 'Bu döneme bağlı kayıtlar var, silinemez.' );
		}
		$wpdb->delete( self::table(), array( 'id' => $id ) );
		return true;
	}

	/**
	 * Dönem geçişi önizlemesi: kaç öğrenci terfi eder, kaç öğrenci mezun olur.
	 */
	public static function rollover_preview( $from_term_id ) {
		global $wpdb;
		$settings    = sms_get_settings();
		$final_grade = (int) $settings['final_grade'];

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.grade_level, COUNT(*) AS cnt
			 FROM {$wpdb->prefix}sms_enrollments e
			 INNER JOIN {$wpdb->prefix}sms_students s ON s.id = e.student_id
			 WHERE e.term_id = %d AND e.status = 'active' AND s.status = 'active'
			 GROUP BY e.grade_level ORDER BY e.grade_level",
			$from_term_id
		) );

		$promote = 0;
		$graduate = 0;
		$breakdown = array();
		foreach ( $rows as $r ) {
			$grade = (int) $r->grade_level;
			$cnt   = (int) $r->cnt;
			if ( $grade >= $final_grade ) {
				$graduate += $cnt;
				$breakdown[] = array( 'from' => $grade, 'to' => null, 'count' => $cnt );
			} else {
				$promote += $cnt;
				$breakdown[] = array( 'from' => $grade, 'to' => $grade + 1, 'count' => $cnt );
			}
		}

		return array(
			'promote'     => $promote,
			'graduate'    => $graduate,
			'final_grade' => $final_grade,
			'breakdown'   => $breakdown,
		);
	}

	/**
	 * Yeni dönem açar; aktif dönemdeki öğrencileri bir üst sınıfa aktarır,
	 * son sınıftakileri mezun statüsüne alıp arşivler.
	 *
	 * @return array İstatistikler: term_id, promoted, graduated.
	 */
	public static function open_new_term( $name, $start_date, $end_date, $auto_promote = true ) {
		global $wpdb;

		$old_term    = self::active();
		$new_term_id = self::create( $name, $start_date, $end_date, false );

		$promoted  = 0;
		$graduated = 0;

		if ( $auto_promote && $old_term ) {
			$settings    = sms_get_settings();
			$final_grade = (int) $settings['final_grade'];

			$enrollments = $wpdb->get_results( $wpdb->prepare(
				"SELECT e.student_id, e.grade_level
				 FROM {$wpdb->prefix}sms_enrollments e
				 INNER JOIN {$wpdb->prefix}sms_students s ON s.id = e.student_id
				 WHERE e.term_id = %d AND e.status = 'active' AND s.status = 'active'",
				$old_term->id
			) );

			foreach ( $enrollments as $enr ) {
				$grade = (int) $enr->grade_level;
				if ( $grade >= $final_grade ) {
					// Mezun: yeni döneme aktarılmaz, arşivde kalır.
					$wpdb->update(
						$wpdb->prefix . 'sms_students',
						array( 'status' => 'graduated' ),
						array( 'id' => (int) $enr->student_id )
					);
					$wpdb->update(
						$wpdb->prefix . 'sms_enrollments',
						array( 'status' => 'graduated' ),
						array( 'term_id' => (int) $old_term->id, 'student_id' => (int) $enr->student_id )
					);
					$graduated++;
				} else {
					$wpdb->insert( $wpdb->prefix . 'sms_enrollments', array(
						'student_id'  => (int) $enr->student_id,
						'term_id'     => $new_term_id,
						'grade_level' => $grade + 1,
						'status'      => 'active',
						'created_at'  => current_time( 'mysql' ),
					) );
					$promoted++;
				}
			}
		}

		self::set_active( $new_term_id );

		return array(
			'term_id'   => $new_term_id,
			'promoted'  => $promoted,
			'graduated' => $graduated,
		);
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
