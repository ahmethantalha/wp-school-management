<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
// Bu dosyadaki tüm $wpdb sorguları eklentiye özel tablolar (wp_nizamiye_*) üzerinde çalışır;
// tüm parametreler $wpdb->prepare() ile bağlanır ya da intval()/whitelist ile temizlenir.
// WordPress çekirdeğinde özel tablolar için bir soyutlama/önbellekleme API'si olmadığından
// doğrudan $wpdb kullanımı kaçınılmazdır; bkz. güvenlik incelemesinde doğrulanan analiz.

/**
 * Alışkanlıklar, öğrenci atamaları ve günlük takip kayıtları.
 * track_type: 'binary' (yaptı/yapmadı), 'scale' (1..scale_max derece) veya
 * 'reading' (kitap okuma: value=o günkü sayfa sayısı, note=kitap adı).
 */
class Nizamiye_Habits {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nizamiye_habits';
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	/**
	 * Dönemin alışkanlıkları. Öğretmenler için: kendi oluşturdukları
	 * VEYA öğrencilerinden en az birinin atandığı alışkanlıklar.
	 */
	public static function for_term( $term_id, $restrict_teacher_id = 0 ) {
		global $wpdb;
		$sql    = 'SELECT h.*,
				(SELECT COUNT(*) FROM ' . $wpdb->prefix . 'nizamiye_habit_students hs WHERE hs.habit_id = h.id) AS student_count
			 FROM ' . self::table() . ' h WHERE h.term_id = %d';
		$params = array( (int) $term_id );

		if ( $restrict_teacher_id ) {
			$student_ids = nizamiye_teacher_student_ids( $restrict_teacher_id, $term_id );
			$in          = $student_ids ? implode( ',', array_map( 'intval', $student_ids ) ) : '0';
			$sql        .= " AND (h.created_by = %d OR EXISTS (
				SELECT 1 FROM {$wpdb->prefix}nizamiye_habit_students hs2
				WHERE hs2.habit_id = h.id AND hs2.student_id IN ($in)))";
			$params[]    = (int) $restrict_teacher_id;
		}

		$sql .= ' ORDER BY h.id DESC';
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function save( $data, $id = 0 ) {
		global $wpdb;
		$track_type = $data['track_type'] ?? '';
		$row        = array(
			'term_id'     => (int) ( $data['term_id'] ?? 0 ),
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			'track_type'  => in_array( $track_type, array( 'scale', 'reading' ), true ) ? $track_type : 'binary',
			'scale_max'   => max( 2, min( 10, (int) ( $data['scale_max'] ?? 5 ) ) ),
		);
		if ( $id ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}
		$row['created_by'] = get_current_user_id();
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		$wpdb->delete( $wpdb->prefix . 'nizamiye_habit_students', array( 'habit_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'nizamiye_habit_logs', array( 'habit_id' => $id ) );
		$wpdb->delete( self::table(), array( 'id' => $id ) );
	}

	public static function student_ids( $habit_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			"SELECT student_id FROM {$wpdb->prefix}nizamiye_habit_students WHERE habit_id = %d",
			$habit_id
		) ) );
	}

	public static function students( $habit_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT s.* FROM {$wpdb->prefix}nizamiye_habit_students hs
			 INNER JOIN {$wpdb->prefix}nizamiye_students s ON s.id = hs.student_id
			 WHERE hs.habit_id = %d ORDER BY s.first_name",
			$habit_id
		) );
	}

	public static function set_students( $habit_id, array $student_ids ) {
		global $wpdb;
		$habit_id    = (int) $habit_id;
		$student_ids = array_unique( array_map( 'intval', $student_ids ) );
		$current     = self::student_ids( $habit_id );

		foreach ( array_diff( $current, $student_ids ) as $remove ) {
			$wpdb->delete( $wpdb->prefix . 'nizamiye_habit_students', array( 'habit_id' => $habit_id, 'student_id' => $remove ) );
		}
		foreach ( array_diff( $student_ids, $current ) as $add ) {
			$wpdb->insert( $wpdb->prefix . 'nizamiye_habit_students', array( 'habit_id' => $habit_id, 'student_id' => $add ) );
		}
	}

	/** Belirli gün için kayıtlar: student_id => satır. */
	public static function logs_for_date( $habit_id, $date ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nizamiye_habit_logs WHERE habit_id = %d AND log_date = %s",
			$habit_id, $date
		) );
		$map = array();
		foreach ( $rows as $r ) {
			$map[ (int) $r->student_id ] = $r;
		}
		return $map;
	}

	/**
	 * Günlük kayıtları topluca kaydeder. $entries: student_id => ['value' =>, 'note' =>, 'filled' => bool].
	 * 'filled' false ise (dereceli tipte boş bırakıldıysa) mevcut kayıt silinir, yenisi eklenmez.
	 */
	public static function save_logs( $habit_id, $date, array $entries, $recorded_by ) {
		global $wpdb;
		$habit = self::get( $habit_id );
		if ( ! $habit ) {
			return;
		}
		if ( 'binary' === $habit->track_type ) {
			$max = 1;
		} elseif ( 'reading' === $habit->track_type ) {
			$max = 3000; // makul bir sayfa sayısı üst sınırı (güvenlik/sağlık kontrolü).
		} else {
			$max = (int) $habit->scale_max;
		}
		$existing = self::logs_for_date( $habit_id, $date );

		foreach ( $entries as $student_id => $entry ) {
			$student_id = (int) $student_id;

			if ( empty( $entry['filled'] ) ) {
				if ( isset( $existing[ $student_id ] ) ) {
					$wpdb->delete( $wpdb->prefix . 'nizamiye_habit_logs', array( 'id' => (int) $existing[ $student_id ]->id ) );
				}
				continue;
			}

			$value = max( 0, min( $max, (int) ( $entry['value'] ?? 0 ) ) );
			$note  = sanitize_text_field( $entry['note'] ?? '' );

			if ( isset( $existing[ $student_id ] ) ) {
				$wpdb->update( $wpdb->prefix . 'nizamiye_habit_logs', array(
					'value'       => $value,
					'note'        => $note,
					'recorded_by' => (int) $recorded_by,
				), array( 'id' => (int) $existing[ $student_id ]->id ) );
			} else {
				$wpdb->insert( $wpdb->prefix . 'nizamiye_habit_logs', array(
					'habit_id'    => (int) $habit_id,
					'student_id'  => $student_id,
					'log_date'    => $date,
					'value'       => $value,
					'note'        => $note,
					'recorded_by' => (int) $recorded_by,
				) );
			}
		}
	}

	/** Alışkanlığın genel tamamlama yüzdesi. */
	public static function completion_rate( $habit_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare(
			"SELECT ROUND(AVG(CASE WHEN h.track_type IN ('binary','reading') THEN LEAST(l.value,1) * 100
				ELSE l.value / h.scale_max * 100 END))
			 FROM {$wpdb->prefix}nizamiye_habit_logs l
			 INNER JOIN " . self::table() . ' h ON h.id = l.habit_id
			 WHERE l.habit_id = %d',
			$habit_id
		) );
	}

	/** Öğrenci bazında dönem alışkanlık tamamlama: student_id => yüzde. Tek istek içinde memoize edilir. */
	public static function rates_by_student( $term_id ) {
		static $cache = array();
		$term_id = (int) $term_id;
		if ( isset( $cache[ $term_id ] ) ) {
			return $cache[ $term_id ];
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.student_id,
				ROUND(AVG(CASE WHEN h.track_type IN ('binary','reading') THEN LEAST(l.value,1) * 100
					ELSE l.value / h.scale_max * 100 END)) AS rate
			 FROM {$wpdb->prefix}nizamiye_habit_logs l
			 INNER JOIN " . self::table() . ' h ON h.id = l.habit_id
			 WHERE h.term_id = %d GROUP BY l.student_id',
			$term_id
		) );
		$out = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->student_id ] = (int) $r->rate;
		}
		$cache[ $term_id ] = $out;
		return $out;
	}

	/** Son N günün günlük genel tamamlama yüzdesi: [tarih => yüzde|null]. */
	public static function daily_rates( $term_id, $days = 14, array $student_ids = null ) {
		global $wpdb;
		$start = gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) ) );

		$sql = "SELECT l.log_date,
				ROUND(AVG(CASE WHEN h.track_type IN ('binary','reading') THEN LEAST(l.value,1) * 100
					ELSE l.value / h.scale_max * 100 END)) AS rate
			 FROM {$wpdb->prefix}nizamiye_habit_logs l
			 INNER JOIN " . self::table() . ' h ON h.id = l.habit_id
			 WHERE h.term_id = %d AND l.log_date >= %s';
		if ( null !== $student_ids ) {
			if ( ! $student_ids ) {
				$rows = array();
			} else {
				$sql .= ' AND l.student_id IN (' . implode( ',', array_map( 'intval', $student_ids ) ) . ')';
			}
		}
		if ( ! isset( $rows ) ) {
			$sql .= ' GROUP BY l.log_date';
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $term_id, $start ) );
		}

		$by_date = array();
		foreach ( $rows as $r ) {
			$by_date[ $r->log_date ] = (int) $r->rate;
		}

		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$d            = gmdate( 'Y-m-d', strtotime( "-$i days", current_time( 'timestamp' ) ) );
			$series[ $d ] = $by_date[ $d ] ?? null;
		}
		return $series;
	}

	/** Öğrencinin alışkanlık bazında dönem özeti. */
	public static function student_habit_summary( $student_id, $term_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT h.id, h.name, h.track_type, h.scale_max,
				COUNT(l.id) AS log_count,
				ROUND(AVG(CASE WHEN h.track_type IN ('binary','reading') THEN LEAST(l.value,1) * 100
					ELSE l.value / h.scale_max * 100 END)) AS rate
			 FROM {$wpdb->prefix}nizamiye_habit_students hs
			 INNER JOIN " . self::table() . " h ON h.id = hs.habit_id
			 LEFT JOIN {$wpdb->prefix}nizamiye_habit_logs l ON l.habit_id = h.id AND l.student_id = hs.student_id
			 WHERE hs.student_id = %d AND h.term_id = %d
			 GROUP BY h.id, h.name, h.track_type, h.scale_max
			 ORDER BY h.name",
			$student_id, $term_id
		) );
	}

	/**
	 * Öğrencinin bu alışkanlıkta daha önce girdiği kitap adları (en son girilen önce),
	 * takip doldurma ekranında öneri (dropdown/datalist) olarak kullanılır.
	 */
	public static function book_titles_for_student( $habit_id, $student_id ) {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT note FROM {$wpdb->prefix}nizamiye_habit_logs
			 WHERE habit_id = %d AND student_id = %d AND note IS NOT NULL AND note != ''
			 GROUP BY note ORDER BY MAX(log_date) DESC LIMIT 30",
			$habit_id, $student_id
		) );
	}

	/**
	 * Öğrencinin 'reading' (kitap okuma) türündeki alışkanlıklarında okuduğu kitaplar
	 * ve toplam sayfa sayısı. Kitap adı serbest metin (note) olduğundan aynı isim
	 * (baş/son boşluk temizlenmiş) altında gruplanır.
	 *
	 * @return array [ ['habit_id'=>, 'habit_name'=>, 'total_pages'=>, 'books'=> [ ['title'=>,'pages'=>,'days'=>,'last_date'=>] ] ] ]
	 */
	public static function reading_summary( $student_id, $term_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT h.id AS habit_id, h.name AS habit_name,
				COALESCE(NULLIF(TRIM(l.note),''), '(İsimsiz kitap)') AS book,
				SUM(l.value) AS pages, COUNT(*) AS days, MAX(l.log_date) AS last_date
			 FROM {$wpdb->prefix}nizamiye_habit_logs l
			 INNER JOIN " . self::table() . " h ON h.id = l.habit_id
			 WHERE l.student_id = %d AND h.term_id = %d AND h.track_type = 'reading'
			 GROUP BY h.id, h.name, book
			 ORDER BY h.name, last_date DESC",
			$student_id, $term_id
		) );

		$out = array();
		foreach ( $rows as $r ) {
			$hid = (int) $r->habit_id;
			if ( ! isset( $out[ $hid ] ) ) {
				$out[ $hid ] = array(
					'habit_id'    => $hid,
					'habit_name'  => $r->habit_name,
					'total_pages' => 0,
					'books'       => array(),
				);
			}
			$out[ $hid ]['total_pages'] += (int) $r->pages;
			$out[ $hid ]['books'][]      = array(
				'title'     => $r->book,
				'pages'     => (int) $r->pages,
				'days'      => (int) $r->days,
				'last_date' => $r->last_date,
			);
		}
		return array_values( $out );
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
