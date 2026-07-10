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

	/** Öğrencinin tam karne verisi. */
	public static function student_report( $student_id, $term_id ) {
		return array(
			'student'    => SMS_Students::get( $student_id ),
			'enrollment' => SMS_Students::enrollment( $student_id, $term_id ),
			'history'    => SMS_Students::enrollment_history( $student_id ),
			'classes'    => SMS_Classes::for_student( $student_id, $term_id ),
			'attendance' => SMS_Attendance::student_summary( $student_id, $term_id ),
			'recent_att' => SMS_Attendance::recent_for_student( $student_id, $term_id ),
			'habits'     => SMS_Habits::student_habit_summary( $student_id, $term_id ),
			'grades'     => SMS_Grades::for_student( $student_id, $term_id ),
			'grade_avgs' => SMS_Grades::student_class_averages( $student_id, $term_id ),
		);
	}
}
