<?php
defined( 'ABSPATH' ) || exit;

/**
 * Yoklama kayıtları.
 */
class SMS_Attendance {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'sms_attendance';
	}

	/** Bir dersliğin belirli gündeki yoklaması: student_id => satır. */
	public static function sheet( $class_id, $date ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE class_id = %d AND att_date = %s',
			$class_id, $date
		) );
		$map = array();
		foreach ( $rows as $r ) {
			$map[ (int) $r->student_id ] = $r;
		}
		return $map;
	}

	/** Yoklamayı kaydeder. $entries: student_id => ['status' =>, 'note' =>]. */
	public static function save_sheet( $class_id, $date, array $entries, $recorded_by ) {
		global $wpdb;
		$valid    = array_keys( sms_attendance_statuses() );
		$existing = self::sheet( $class_id, $date );

		foreach ( $entries as $student_id => $entry ) {
			$student_id = (int) $student_id;
			$status     = in_array( $entry['status'] ?? '', $valid, true ) ? $entry['status'] : 'present';
			$note       = sanitize_text_field( $entry['note'] ?? '' );

			if ( isset( $existing[ $student_id ] ) ) {
				$wpdb->update( self::table(), array(
					'status'      => $status,
					'note'        => $note,
					'recorded_by' => (int) $recorded_by,
				), array( 'id' => (int) $existing[ $student_id ]->id ) );
			} else {
				$wpdb->insert( self::table(), array(
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

	/** Öğrencinin dönem devam özeti. */
	public static function student_summary( $student_id, $term_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT a.status, COUNT(*) AS cnt FROM ' . self::table() . " a
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = a.class_id
			 WHERE a.student_id = %d AND c.term_id = %d GROUP BY a.status",
			$student_id, $term_id
		) );
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

	/** Dönem geneli durum dağılımı (halka grafik için). $class_ids ile sınırlandırılabilir. */
	public static function term_breakdown( $term_id, array $class_ids = null ) {
		global $wpdb;
		$sql    = 'SELECT a.status, COUNT(*) AS cnt FROM ' . self::table() . " a
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = a.class_id
			 WHERE c.term_id = %d";
		if ( null !== $class_ids ) {
			if ( ! $class_ids ) {
				return array();
			}
			$sql .= ' AND a.class_id IN (' . implode( ',', array_map( 'intval', $class_ids ) ) . ')';
		}
		$sql .= ' GROUP BY a.status';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $term_id ) );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ $r->status ] = (int) $r->cnt;
		}
		return $out;
	}

	/** Son N günün günlük devam yüzdesi: [tarih => yüzde|null]. */
	public static function daily_rates( $term_id, $days = 14, array $class_ids = null ) {
		global $wpdb;
		$start = gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days', current_time( 'timestamp' ) ) );

		$sql = 'SELECT a.att_date,
				SUM(CASE WHEN a.status = %s THEN 1 WHEN a.status = %s THEN 0.5 ELSE 0 END) AS score,
				COUNT(*) AS total
			 FROM ' . self::table() . " a
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = a.class_id
			 WHERE c.term_id = %d AND a.att_date >= %s";
		if ( null !== $class_ids ) {
			if ( ! $class_ids ) {
				$rows = array();
			} else {
				$sql .= ' AND a.class_id IN (' . implode( ',', array_map( 'intval', $class_ids ) ) . ')';
			}
		}
		if ( ! isset( $rows ) ) {
			$sql .= ' GROUP BY a.att_date';
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, 'present', 'late', $term_id, $start ) );
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

	/** Öğrenci bazında devam yüzdeleri: student_id => yüzde. */
	public static function rates_by_student( $term_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT a.student_id,
				SUM(CASE WHEN a.status = %s THEN 1 WHEN a.status = %s THEN 0.5 ELSE 0 END) AS score,
				COUNT(*) AS total
			 FROM ' . self::table() . " a
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = a.class_id
			 WHERE c.term_id = %d GROUP BY a.student_id",
			'present', 'late', $term_id
		) );
		$out = array();
		foreach ( $rows as $r ) {
			if ( (int) $r->total > 0 ) {
				$out[ (int) $r->student_id ] = round( $r->score / $r->total * 100 );
			}
		}
		return $out;
	}

	/** Öğrencinin son yoklama kayıtları. */
	public static function recent_for_student( $student_id, $term_id, $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT a.*, c.name AS class_name FROM ' . self::table() . " a
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = a.class_id
			 WHERE a.student_id = %d AND c.term_id = %d
			 ORDER BY a.att_date DESC, a.id DESC LIMIT %d",
			$student_id, $term_id, $limit
		) );
	}
}
