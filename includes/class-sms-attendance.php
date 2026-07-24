<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
// Bu dosyadaki tüm $wpdb sorguları eklentiye özel tablolar (wp_nizamiye_*) üzerinde çalışır;
// tüm parametreler $wpdb->prepare() ile bağlanır ya da intval()/whitelist ile temizlenir.
// WordPress çekirdeğinde özel tablolar için bir soyutlama/önbellekleme API'si olmadığından
// doğrudan $wpdb kullanımı kaçınılmazdır; bkz. güvenlik incelemesinde doğrulanan analiz.

/**
 * Yoklama kayıtları. Kategori (Ders/Namaz/Temizlik...) + oturum (vakit) + dönem bazlı.
 */
class Nizamiye_Attendance {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nizamiye_attendance';
	}

	/**
	 * Belirli kategori/oturum/derslik/gün için yoklama satırları: student_id => satır.
	 * Genel yoklamalarda $class_id = 0.
	 */
	public static function sheet( $category_id, $session_id, $class_id, $date ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nizamiye_attendance WHERE category_id = %d AND session_id = %d AND class_id = %d AND att_date = %s",
			$category_id, $session_id, $class_id, $date
		) );
		$map = array();
		foreach ( $rows as $r ) {
			$map[ (int) $r->student_id ] = $r;
		}
		return $map;
	}

	/**
	 * Yoklamayı kaydeder. $entries: student_id => ['status' =>, 'note' =>].
	 */
	public static function save_sheet( $term_id, $category_id, $session_id, $class_id, $date, array $entries, $recorded_by ) {
		global $wpdb;
		$valid    = array_keys( nizamiye_attendance_statuses() );
		$existing = self::sheet( $category_id, $session_id, $class_id, $date );

		foreach ( $entries as $student_id => $entry ) {
			$student_id = (int) $student_id;
			$status     = in_array( $entry['status'] ?? '', $valid, true ) ? $entry['status'] : 'present';
			$note       = sanitize_text_field( $entry['note'] ?? '' );

			if ( isset( $existing[ $student_id ] ) ) {
				$wpdb->update( self::table(), array(
					'status'      => $status,
					'note'        => $note,
					'recorded_by' => (int) $recorded_by,
					'term_id'     => (int) $term_id,
				), array( 'id' => (int) $existing[ $student_id ]->id ) );
			} else {
				$wpdb->insert( self::table(), array(
					'term_id'     => (int) $term_id,
					'category_id' => (int) $category_id,
					'session_id'  => (int) $session_id,
					'class_id'    => (int) $class_id,
					'student_id'  => $student_id,
					'att_date'    => $date,
					'status'      => $status,
					'note'        => $note,
					'recorded_by' => (int) $recorded_by,
				) );
			}
		}
	}

	/** Öğrencinin dönem devam özeti. $category_id verilirse o kategoriyle sınırlar. */
	public static function student_summary( $student_id, $term_id, $category_id = 0 ) {
		global $wpdb;
		$sql    = "SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}nizamiye_attendance WHERE student_id = %d AND term_id = %d";
		$params = array( $student_id, $term_id );
		if ( $category_id ) {
			$sql     .= ' AND category_id = %d';
			$params[] = $category_id;
		}
		$sql .= ' GROUP BY status';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$summary = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0, 'rate' => null );
		foreach ( $rows as $r ) {
			$summary[ $r->status ] = (int) $r->cnt;
			$summary['total']     += (int) $r->cnt;
		}
		if ( $summary['total'] > 0 ) {
			$summary['rate'] = round( ( $summary['present'] + 0.5 * $summary['late'] ) / $summary['total'] * 100 );
		}
		return $summary;
	}

	/**
	 * Öğrencinin yoklama türü (kategori) bazında katılımı — Ders dahil TÜM kategoriler.
	 * Her kategori için genel bir oran (overall_rate) döner; birden çok oturumu olan
	 * kategorilerde (örn. Namaz'ın 5 vakti) oturum bazlı kırılım da eklenir.
	 * Karnede tek bir "toplu Geldi/Gelmedi" sayısı yerine tür bazlı yüzde göstermek için kullanılır.
	 *
	 * @return array [ ['category'=>ad, 'icon'=>, 'scope'=>, 'overall_rate'=>, 'overall_total'=>,
	 *                  'multi_session'=>bool, 'sessions'=> [ ['name'=>, 'present'=>, 'total'=>, 'rate'=> ] ] ] ]
	 */
	public static function student_category_breakdown( $student_id, $term_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.category_id, a.session_id,
				SUM(CASE WHEN a.status = %s THEN 1 ELSE 0 END) AS present,
				SUM(CASE WHEN a.status = %s THEN 1 ELSE 0 END) AS late,
				COUNT(*) AS total
			 FROM {$wpdb->prefix}nizamiye_attendance a
			 WHERE a.student_id = %d AND a.term_id = %d
			 GROUP BY a.category_id, a.session_id",
			'present', 'late', $student_id, $term_id
		) );

		$by_cs = array();
		foreach ( $rows as $r ) {
			$by_cs[ (int) $r->category_id ][ (int) $r->session_id ] = $r;
		}

		$out = array();
		foreach ( Nizamiye_Attendance_Types::categories( false ) as $cat ) {
			if ( empty( $by_cs[ (int) $cat->id ] ) ) {
				continue;
			}
			$sessions   = array();
			$ov_present = 0;
			$ov_late    = 0;
			$ov_total   = 0;
			foreach ( Nizamiye_Attendance_Types::sessions( (int) $cat->id ) as $sess ) {
				$row = $by_cs[ (int) $cat->id ][ (int) $sess->id ] ?? null;
				if ( ! $row ) {
					continue;
				}
				$total = (int) $row->total;
				$rate  = $total ? round( ( (int) $row->present + 0.5 * (int) $row->late ) / $total * 100 ) : null;
				$sessions[] = array(
					'name'    => $sess->name,
					'present' => (int) $row->present,
					'total'   => $total,
					'rate'    => $rate,
				);
				$ov_present += (int) $row->present;
				$ov_late    += (int) $row->late;
				$ov_total   += $total;
			}
			if ( $sessions ) {
				$out[] = array(
					'category'      => $cat->name,
					'icon'          => $cat->icon,
					'scope'         => $cat->scope,
					'overall_rate'  => $ov_total ? round( ( $ov_present + 0.5 * $ov_late ) / $ov_total * 100 ) : null,
					'overall_total' => $ov_total,
					'multi_session' => count( $sessions ) > 1,
					'sessions'      => $sessions,
				);
			}
		}
		return $out;
	}

	/** Dönem geneli durum dağılımı (halka grafik). $student_ids ile sınırlandırılabilir. */
	public static function term_breakdown( $term_id, array $student_ids = null ) {
		global $wpdb;
		$sql    = "SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}nizamiye_attendance WHERE term_id = %d";
		$params = array( $term_id );
		if ( null !== $student_ids ) {
			if ( ! $student_ids ) {
				return array();
			}
			$student_id_list  = array_map( 'intval', $student_ids );
			$id_placeholders  = implode( ',', array_fill( 0, count( $student_id_list ), '%d' ) );
			$sql             .= " AND student_id IN ($id_placeholders)";
			$params           = array_merge( $params, $student_id_list );
		}
		$sql .= ' GROUP BY status';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ $r->status ] = (int) $r->cnt;
		}
		return $out;
	}

	/** Son N günün günlük devam yüzdesi: [tarih => yüzde|null]. */
	public static function daily_rates( $term_id, $days = 14, array $student_ids = null ) {
		global $wpdb;
		$start = gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) ) );

		$sql = "SELECT att_date,
				SUM(CASE WHEN status = %s THEN 1 WHEN status = %s THEN 0.5 ELSE 0 END) AS score,
				COUNT(*) AS total
			 FROM {$wpdb->prefix}nizamiye_attendance WHERE term_id = %d AND att_date >= %s";
		$rows   = null;
		$params = array( 'present', 'late', $term_id, $start );
		if ( null !== $student_ids ) {
			if ( ! $student_ids ) {
				$rows = array();
			} else {
				$student_id_list  = array_map( 'intval', $student_ids );
				$id_placeholders  = implode( ',', array_fill( 0, count( $student_id_list ), '%d' ) );
				$sql             .= " AND student_id IN ($id_placeholders)";
				$params           = array_merge( $params, $student_id_list );
			}
		}
		if ( null === $rows ) {
			$sql .= ' GROUP BY att_date';
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		}

		$by_date = array();
		foreach ( $rows as $r ) {
			$by_date[ $r->att_date ] = $r->total > 0 ? round( $r->score / $r->total * 100 ) : null;
		}

		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$d            = gmdate( 'Y-m-d', strtotime( "-$i days", current_time( 'timestamp' ) ) );
			$series[ $d ] = $by_date[ $d ] ?? null;
		}
		return $series;
	}

	/**
	 * Öğrenci bazında devam yüzdeleri: student_id => yüzde.
	 * Tek istek içinde (dashboard/raporlar aynı dönemi birden çok kez sorar) memoize edilir.
	 */
	public static function rates_by_student( $term_id ) {
		static $cache = array();
		$term_id = (int) $term_id;
		if ( isset( $cache[ $term_id ] ) ) {
			return $cache[ $term_id ];
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT student_id,
				SUM(CASE WHEN status = %s THEN 1 WHEN status = %s THEN 0.5 ELSE 0 END) AS score,
				COUNT(*) AS total
			 FROM {$wpdb->prefix}nizamiye_attendance WHERE term_id = %d GROUP BY student_id",
			'present', 'late', $term_id
		) );
		$out = array();
		foreach ( $rows as $r ) {
			if ( (int) $r->total > 0 ) {
				$out[ (int) $r->student_id ] = round( $r->score / $r->total * 100 );
			}
		}
		$cache[ $term_id ] = $out;
		return $out;
	}

	/** Öğrencinin son yoklama kayıtları (kategori/oturum/derslik adlarıyla). */
	public static function recent_for_student( $student_id, $term_id, $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, c.name AS class_name, cat.name AS category_name, s.name AS session_name
			 FROM {$wpdb->prefix}nizamiye_attendance a
			 LEFT JOIN {$wpdb->prefix}nizamiye_classes c ON c.id = a.class_id
			 LEFT JOIN {$wpdb->prefix}nizamiye_att_categories cat ON cat.id = a.category_id
			 LEFT JOIN {$wpdb->prefix}nizamiye_att_sessions s ON s.id = a.session_id
			 WHERE a.student_id = %d AND a.term_id = %d
			 ORDER BY a.att_date DESC, a.id DESC LIMIT %d",
			$student_id, $term_id, $limit
		) );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
