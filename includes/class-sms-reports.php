<?php
defined( 'ABSPATH' ) || exit;

/**
 * Dashboard ve karne istatistikleri.
 */
class SMS_Reports {

	/** Dashboard sayaçları. */
	public static function counts( $term_id ) {
		global $wpdb;
		$teacher_id = sms_is_teacher() ? get_current_user_id() : 0;

		if ( $teacher_id ) {
			$student_ids = sms_teacher_student_ids( $teacher_id, $term_id );
			$students    = count( $student_ids );
			$classes     = SMS_Classes::count_for_term( $term_id, $teacher_id );
			$habits      = count( SMS_Habits::for_term( $term_id, $teacher_id ) );
		} else {
			$students = SMS_Students::count_for_term( $term_id );
			$classes  = SMS_Classes::count_for_term( $term_id );
			$habits   = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sms_habits WHERE term_id = %d", $term_id
			) );
		}

		return array(
			'students' => $students,
			'teachers' => count( get_users( array( 'role' => 'sms_teacher', 'fields' => 'ID' ) ) ),
			'classes'  => $classes,
			'habits'   => $habits,
		);
	}

	/**
	 * Bileşik başarı skoru: mevcut bileşenlerin ağırlıklı ortalaması.
	 * devam %40 + alışkanlık %40 + not %20 (eksik bileşenin ağırlığı diğerlerine dağıtılır).
	 *
	 * @return array [ ['student' => satır, 'score' =>, 'attendance' =>, 'habit' =>, 'grade' =>], ... ] skora göre azalan.
	 */
	public static function student_scores( $term_id, array $limit_student_ids = null ) {
		$att    = SMS_Attendance::rates_by_student( $term_id );
		$habit  = SMS_Habits::rates_by_student( $term_id );
		$grade  = SMS_Grades::rates_by_student( $term_id );

		$students = SMS_Students::query( array( 'term_id' => $term_id, 'status' => 'active', 'ids' => $limit_student_ids ) );

		$scores = array();
		foreach ( $students as $s ) {
			$id         = (int) $s->id;
			$components = array();
			if ( isset( $att[ $id ] ) ) {
				$components[] = array( 0.4, $att[ $id ] );
			}
			if ( isset( $habit[ $id ] ) ) {
				$components[] = array( 0.4, $habit[ $id ] );
			}
			if ( isset( $grade[ $id ] ) ) {
				$components[] = array( 0.2, $grade[ $id ] );
			}
			if ( ! $components ) {
				continue;
			}
			$weight_sum = array_sum( array_column( $components, 0 ) );
			$score      = 0;
			foreach ( $components as $c ) {
				$score += ( $c[0] / $weight_sum ) * $c[1];
			}
			$scores[] = array(
				'student'    => $s,
				'score'      => round( $score ),
				'attendance' => $att[ $id ] ?? null,
				'habit'      => $habit[ $id ] ?? null,
				'grade'      => $grade[ $id ] ?? null,
			);
		}

		usort( $scores, function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		return $scores;
	}

	/**
	 * Yoklama matrisi: öğrenci × oturum, tarih aralığında durum kırılımı.
	 * Namaz raporu bunun category=Namaz halidir.
	 *
	 * @return array [
	 *   'sessions' => oturum satırları,
	 *   'rows'     => [ student_id => ['student'=>, 'cells'=> [session_id => counts], 'overall'=> counts] ],
	 *   'totals'   => [ session_id => counts, 'overall' => counts ]
	 * ]  counts = [present, absent, late, excused, total, rate]
	 */
	public static function attendance_matrix( $term_id, $category_id, $date_from, $date_to, $grade = 0, array $student_ids = null ) {
		global $wpdb;

		$students = SMS_Students::query( array(
			'term_id' => $term_id,
			'status'  => 'active',
			'grade'   => $grade,
			'ids'     => $student_ids,
		) );
		if ( ! $students ) {
			return array( 'sessions' => SMS_Attendance_Types::sessions( $category_id ), 'rows' => array(), 'totals' => array() );
		}

		$ids = implode( ',', array_map( function ( $s ) { return (int) $s->id; }, $students ) );
		$raw = $wpdb->get_results( $wpdb->prepare(
			"SELECT student_id, session_id, status, COUNT(*) AS cnt
			 FROM {$wpdb->prefix}sms_attendance
			 WHERE term_id = %d AND category_id = %d AND att_date >= %s AND att_date <= %s
			   AND student_id IN ($ids)
			 GROUP BY student_id, session_id, status",
			$term_id, $category_id, $date_from, $date_to
		) );

		$empty = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0, 'rate' => null );
		$calc  = function ( $c ) {
			if ( $c['total'] > 0 ) {
				$c['rate'] = round( ( $c['present'] + 0.5 * $c['late'] ) / $c['total'] * 100 );
			}
			return $c;
		};

		$cells = array();
		foreach ( $raw as $r ) {
			$sid = (int) $r->student_id;
			$ses = (int) $r->session_id;
			if ( ! isset( $cells[ $sid ][ $ses ] ) ) {
				$cells[ $sid ][ $ses ] = $empty;
			}
			$cells[ $sid ][ $ses ][ $r->status ] += (int) $r->cnt;
			$cells[ $sid ][ $ses ]['total']      += (int) $r->cnt;
		}

		$sessions = SMS_Attendance_Types::sessions( $category_id );
		$rows     = array();
		$totals   = array( 'overall' => $empty );
		foreach ( $sessions as $s ) {
			$totals[ (int) $s->id ] = $empty;
		}

		foreach ( $students as $st ) {
			$sid     = (int) $st->id;
			$row     = array( 'student' => $st, 'cells' => array(), 'overall' => $empty );
			$has_any = false;
			foreach ( $sessions as $s ) {
				$ses  = (int) $s->id;
				$cell = $cells[ $sid ][ $ses ] ?? $empty;
				foreach ( array( 'present', 'absent', 'late', 'excused', 'total' ) as $k ) {
					$row['overall'][ $k ] += $cell[ $k ];
					$totals[ $ses ][ $k ] += $cell[ $k ];
					$totals['overall'][ $k ] += $cell[ $k ];
				}
				if ( $cell['total'] > 0 ) {
					$has_any = true;
				}
				$row['cells'][ $ses ] = $calc( $cell );
			}
			$row['overall'] = $calc( $row['overall'] );
			$row['has_any'] = $has_any;
			$rows[ $sid ]   = $row;
		}
		foreach ( $totals as $k => $c ) {
			$totals[ $k ] = $calc( $c );
		}

		return array( 'sessions' => $sessions, 'rows' => $rows, 'totals' => $totals );
	}

	/** Alışkanlık analizi: öğrenci × alışkanlık tamamlama yüzdesi. */
	public static function habit_matrix( $term_id, $grade = 0, array $student_ids = null ) {
		global $wpdb;

		$students = SMS_Students::query( array( 'term_id' => $term_id, 'status' => 'active', 'grade' => $grade, 'ids' => $student_ids ) );
		$habits   = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, name, track_type, scale_max FROM {$wpdb->prefix}sms_habits WHERE term_id = %d ORDER BY name", $term_id
		) );
		if ( ! $students || ! $habits ) {
			return array( 'habits' => $habits, 'rows' => array(), 'totals' => array() );
		}

		$ids = implode( ',', array_map( function ( $s ) { return (int) $s->id; }, $students ) );
		$raw = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.student_id, l.habit_id, COUNT(*) AS logs,
				ROUND(AVG(CASE WHEN h.track_type IN ('binary','reading') THEN LEAST(l.value,1) * 100 ELSE l.value / h.scale_max * 100 END)) AS rate
			 FROM {$wpdb->prefix}sms_habit_logs l
			 INNER JOIN {$wpdb->prefix}sms_habits h ON h.id = l.habit_id
			 WHERE h.term_id = %d AND l.student_id IN ($ids)
			 GROUP BY l.student_id, l.habit_id",
			$term_id
		) );

		$map = array();
		foreach ( $raw as $r ) {
			$map[ (int) $r->student_id ][ (int) $r->habit_id ] = array( 'logs' => (int) $r->logs, 'rate' => (int) $r->rate );
		}

		$rows   = array();
		$sum    = array();
		$cnt    = array();
		foreach ( $students as $st ) {
			$sid   = (int) $st->id;
			$cells = array();
			$s_sum = 0;
			$s_cnt = 0;
			foreach ( $habits as $h ) {
				$cell = $map[ $sid ][ (int) $h->id ] ?? null;
				$cells[ (int) $h->id ] = $cell;
				if ( $cell ) {
					$s_sum += $cell['rate'];
					$s_cnt++;
					$sum[ (int) $h->id ] = ( $sum[ (int) $h->id ] ?? 0 ) + $cell['rate'];
					$cnt[ (int) $h->id ] = ( $cnt[ (int) $h->id ] ?? 0 ) + 1;
				}
			}
			$rows[ $sid ] = array(
				'student' => $st,
				'cells'   => $cells,
				'overall' => $s_cnt ? (int) round( $s_sum / $s_cnt ) : null,
				'has_any' => $s_cnt > 0,
			);
		}

		$totals = array();
		foreach ( $habits as $h ) {
			$hid            = (int) $h->id;
			$totals[ $hid ] = isset( $cnt[ $hid ] ) ? (int) round( $sum[ $hid ] / $cnt[ $hid ] ) : null;
		}

		return array( 'habits' => $habits, 'rows' => $rows, 'totals' => $totals );
	}

	/** Not analizi: öğrenci × derslik ortalaması. */
	public static function grade_matrix( $term_id, $grade = 0, array $student_ids = null ) {
		global $wpdb;

		$students = SMS_Students::query( array( 'term_id' => $term_id, 'status' => 'active', 'grade' => $grade, 'ids' => $student_ids ) );
		$classes  = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT c.id, c.name FROM {$wpdb->prefix}sms_classes c
			 INNER JOIN {$wpdb->prefix}sms_grades g ON g.class_id = c.id
			 WHERE c.term_id = %d ORDER BY c.name",
			$term_id
		) );
		if ( ! $students || ! $classes ) {
			return array( 'classes' => $classes, 'rows' => array(), 'totals' => array() );
		}

		$ids = implode( ',', array_map( function ( $s ) { return (int) $s->id; }, $students ) );
		$raw = $wpdb->get_results( $wpdb->prepare(
			"SELECT g.student_id, g.class_id, COUNT(*) AS exams,
				ROUND(AVG(g.score / g.max_score * 100)) AS rate
			 FROM {$wpdb->prefix}sms_grades g
			 INNER JOIN {$wpdb->prefix}sms_classes c ON c.id = g.class_id
			 WHERE c.term_id = %d AND g.max_score > 0 AND g.student_id IN ($ids)
			 GROUP BY g.student_id, g.class_id",
			$term_id
		) );

		$map = array();
		foreach ( $raw as $r ) {
			$map[ (int) $r->student_id ][ (int) $r->class_id ] = array( 'exams' => (int) $r->exams, 'rate' => (int) $r->rate );
		}

		$rows = array();
		$sum  = array();
		$cnt  = array();
		foreach ( $students as $st ) {
			$sid   = (int) $st->id;
			$cells = array();
			$s_sum = 0;
			$s_cnt = 0;
			foreach ( $classes as $c ) {
				$cell = $map[ $sid ][ (int) $c->id ] ?? null;
				$cells[ (int) $c->id ] = $cell;
				if ( $cell ) {
					$s_sum += $cell['rate'];
					$s_cnt++;
					$sum[ (int) $c->id ] = ( $sum[ (int) $c->id ] ?? 0 ) + $cell['rate'];
					$cnt[ (int) $c->id ] = ( $cnt[ (int) $c->id ] ?? 0 ) + 1;
				}
			}
			$rows[ $sid ] = array(
				'student' => $st,
				'cells'   => $cells,
				'overall' => $s_cnt ? (int) round( $s_sum / $s_cnt ) : null,
				'has_any' => $s_cnt > 0,
			);
		}

		$totals = array();
		foreach ( $classes as $c ) {
			$cid            = (int) $c->id;
			$totals[ $cid ] = isset( $cnt[ $cid ] ) ? (int) round( $sum[ $cid ] / $cnt[ $cid ] ) : null;
		}

		return array( 'classes' => $classes, 'rows' => $rows, 'totals' => $totals );
	}

	/** Sınıf seviyesi bazında özet: öğrenci sayısı, devam, alışkanlık, not ortalamaları. */
	public static function grade_level_summary( $term_id, array $student_ids = null ) {
		$students = SMS_Students::query( array( 'term_id' => $term_id, 'status' => 'active', 'ids' => $student_ids ) );
		if ( ! $students ) {
			return array();
		}

		$att   = SMS_Attendance::rates_by_student( $term_id );
		$habit = SMS_Habits::rates_by_student( $term_id );
		$grade = SMS_Grades::rates_by_student( $term_id );

		$groups = array();
		foreach ( $students as $s ) {
			$g = (int) ( $s->grade_level ?? 0 );
			if ( ! isset( $groups[ $g ] ) ) {
				$groups[ $g ] = array( 'grade' => $g, 'count' => 0, 'att' => array(), 'habit' => array(), 'grade_avg' => array() );
			}
			$groups[ $g ]['count']++;
			$sid = (int) $s->id;
			if ( isset( $att[ $sid ] ) ) {
				$groups[ $g ]['att'][] = $att[ $sid ];
			}
			if ( isset( $habit[ $sid ] ) ) {
				$groups[ $g ]['habit'][] = $habit[ $sid ];
			}
			if ( isset( $grade[ $sid ] ) ) {
				$groups[ $g ]['grade_avg'][] = $grade[ $sid ];
			}
		}
		ksort( $groups );

		$avg = function ( $arr ) {
			return $arr ? (int) round( array_sum( $arr ) / count( $arr ) ) : null;
		};
		$out = array();
		foreach ( $groups as $g ) {
			$out[] = array(
				'grade'     => $g['grade'],
				'count'     => $g['count'],
				'att'       => $avg( $g['att'] ),
				'habit'     => $avg( $g['habit'] ),
				'grade_avg' => $avg( $g['grade_avg'] ),
			);
		}
		return $out;
	}

	/** Yoklama türü (kategori) bazında dönem özeti. */
	public static function category_summary( $term_id, array $student_ids = null ) {
		global $wpdb;
		$sql = "SELECT category_id, status, COUNT(*) AS cnt FROM {$wpdb->prefix}sms_attendance WHERE term_id = %d";
		if ( null !== $student_ids ) {
			if ( ! $student_ids ) {
				return array();
			}
			$sql .= ' AND student_id IN (' . implode( ',', array_map( 'intval', $student_ids ) ) . ')';
		}
		$sql .= ' GROUP BY category_id, status';
		$raw  = $wpdb->get_results( $wpdb->prepare( $sql, $term_id ) );

		$by_cat = array();
		foreach ( $raw as $r ) {
			$cid = (int) $r->category_id;
			if ( ! isset( $by_cat[ $cid ] ) ) {
				$by_cat[ $cid ] = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0 );
			}
			$by_cat[ $cid ][ $r->status ] += (int) $r->cnt;
			$by_cat[ $cid ]['total']      += (int) $r->cnt;
		}

		$out = array();
		foreach ( SMS_Attendance_Types::categories( false ) as $cat ) {
			$cid = (int) $cat->id;
			if ( empty( $by_cat[ $cid ] ) ) {
				continue;
			}
			$c         = $by_cat[ $cid ];
			$c['rate'] = $c['total'] ? round( ( $c['present'] + 0.5 * $c['late'] ) / $c['total'] * 100 ) : null;
			$out[]     = array_merge( array( 'name' => $cat->name, 'icon' => $cat->icon, 'scope' => $cat->scope ), $c );
		}
		return $out;
	}

	/** Öğrencinin tam karne verisi. */
	public static function student_report( $student_id, $term_id ) {
		return array(
			'student'    => SMS_Students::get( $student_id ),
			'enrollment' => SMS_Students::enrollment( $student_id, $term_id ),
			'history'    => SMS_Students::enrollment_history( $student_id ),
			'classes'    => SMS_Classes::for_student( $student_id, $term_id ),
			'attendance' => SMS_Attendance::student_summary( $student_id, $term_id, sms_ders_category_id() ),
			'att_all'    => SMS_Attendance::student_summary( $student_id, $term_id ),
			'att_cats'   => SMS_Attendance::student_category_breakdown( $student_id, $term_id ),
			'recent_att' => SMS_Attendance::recent_for_student( $student_id, $term_id, 3 ),
			'habits'     => SMS_Habits::student_habit_summary( $student_id, $term_id ),
			'reading'    => SMS_Habits::reading_summary( $student_id, $term_id ),
			'grades'     => SMS_Grades::for_student( $student_id, $term_id ),
			'grade_avgs' => SMS_Grades::student_class_averages( $student_id, $term_id ),
		);
	}
}
