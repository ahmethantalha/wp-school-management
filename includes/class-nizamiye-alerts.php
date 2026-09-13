<?php
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// Bu dosyadaki tüm $wpdb sorguları eklentiye özel tablolar (wp_nizamiye_*) üzerinde çalışır;
// tüm parametreler $wpdb->prepare() ile bağlanır. WordPress çekirdeğinde özel tablolar için
// bir soyutlama/önbellekleme API'si olmadığından doğrudan $wpdb kullanımı kaçınılmazdır.

/**
 * Panelin operasyonel uyarı katmanı.
 *
 * Paneldeki diğer kartlar analitiktir (dönem ortalamaları, 14 günlük trendler);
 * bu sınıf "bugün neye bakmam lazım" sorusunu cevaplar. Yeni veri toplamaz,
 * mevcut yoklama / alışkanlık / not kayıtlarını okur.
 *
 * Her metot $student_ids ile daraltılabilir; panel bunu öğretmenler için zaten
 * hesaplıyor (nizamiye_teacher_student_ids), böylece kayıt düzeyi yetki korunur.
 */
class Nizamiye_Alerts {

	/** Devamsızlık taramasının geriye bakacağı gün sayısı (sorguyu sınırlamak için). */
	const ABSENCE_WINDOW_DAYS = 45;

	/** "Hiç kitap okumadı" penceresi. Takvim haftası yerine kayan pencere; bkz. no_reading(). */
	const READING_WINDOW_DAYS = 7;

	/** Notu düşenler karşılaştırmasında "son sınavlar" sayısı. */
	const GRADE_RECENT_EXAMS = 3;

	/**
	 * Üç uyarıyı birlikte döndürür. Eşikler Ayarlar sayfasından gelir.
	 *
	 * @return array{absence:array,reading:array,grades:array,total:int}
	 */
	public static function summary( $term_id, ?array $student_ids = null ) {
		$settings = nizamiye_get_settings();
		$absence  = self::consecutive_absences( $term_id, (int) $settings['alert_absence_days'], $student_ids );
		$reading  = self::no_reading( $term_id, $student_ids );
		$grades   = self::declining_grades( $term_id, (int) $settings['alert_grade_drop'], $student_ids );

		return array(
			'absence' => $absence,
			'reading' => $reading,
			'grades'  => $grades,
			'total'   => count( $absence ) + count( $reading ) + count( $grades ),
		);
	}

	/** Sorgulara öğrenci kısıtı eklemek için ortak yardımcı. */
	private static function id_clause( ?array $student_ids, &$params ) {
		if ( null === $student_ids ) {
			return '';
		}
		$ids = array_values( array_unique( array_map( 'intval', $student_ids ) ) );
		if ( ! $ids ) {
			return ' AND 1 = 0'; // Kısıt var ama liste boş: hiçbir şey dönmemeli.
		}
		$params = array_merge( $params, $ids );
		return ' AND student_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
	}

	/**
	 * Üst üste devamsız öğrenciler, yoklama türü bazında.
	 *
	 * İki tasarım kararı:
	 *  1. Ardışıklık takvim günü değil KAYIT GÜNÜ üzerinden sayılır. Hafta sonu ve
	 *     tatillerde kayıt olmadığından takvim günü saymak yanıltıcı olurdu.
	 *  2. Bir gün ancak o kategorideki TÜM kayıtlar 'absent' ise "kayıp" sayılır.
	 *     Namaz'ın beş vaktinden birine gelmemek devamsızlık değildir; Ders gibi
	 *     tek oturumlu kategorilerde bu kural kendiliğinden "o günkü ders" olur.
	 *
	 * @return array<int,array{student_id:int,category_id:int,category:string,days:int,last_date:string}>
	 */
	public static function consecutive_absences( $term_id, $threshold, ?array $student_ids = null ) {
		global $wpdb;
		$threshold = max( 2, (int) $threshold );
		$since     = gmdate( 'Y-m-d', strtotime( '-' . self::ABSENCE_WINDOW_DAYS . ' days', current_time( 'timestamp' ) ) );

		$params = array( (int) $term_id, $since );
		$sql    = "SELECT student_id, category_id, att_date, status
			 FROM {$wpdb->prefix}nizamiye_attendance
			 WHERE term_id = %d AND att_date >= %s";
		$sql   .= self::id_clause( $student_ids, $params );
		$sql   .= ' ORDER BY student_id, category_id, att_date DESC';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		if ( ! $rows ) {
			return array();
		}

		// (öğrenci, kategori, gün) → o gün hiç 'absent' dışında kayıt var mı?
		$days = array();
		foreach ( $rows as $r ) {
			$key = (int) $r->student_id . '|' . (int) $r->category_id;
			if ( ! isset( $days[ $key ][ $r->att_date ] ) ) {
				$days[ $key ][ $r->att_date ] = true; // true = şimdiye dek hepsi absent
			}
			if ( 'absent' !== $r->status ) {
				$days[ $key ][ $r->att_date ] = false;
			}
		}

		$names = self::category_names();
		$out   = array();
		foreach ( $days as $key => $by_date ) {
			// att_date DESC sırası korunur: en yeni günden geriye doğru say.
			$streak = 0;
			$last   = '';
			foreach ( $by_date as $date => $all_absent ) {
				if ( ! $all_absent ) {
					break;
				}
				if ( '' === $last ) {
					$last = $date;
				}
				$streak++;
			}
			if ( $streak < $threshold ) {
				continue;
			}
			list( $sid, $cid ) = array_map( 'intval', explode( '|', $key ) );
			$out[]             = array(
				'student_id'  => $sid,
				'category_id' => $cid,
				'category'    => $names[ $cid ] ?? 'Yoklama',
				'days'        => $streak,
				'last_date'   => $last,
			);
		}

		usort( $out, function ( $a, $b ) {
			return $b['days'] <=> $a['days'];
		} );
		return $out;
	}

	/**
	 * Kitap okuma alışkanlığına atanmış olup son 7 günde tek kaydı olmayan öğrenciler.
	 *
	 * Takvim haftası yerine kayan pencere kullanılır: "bu hafta" denseydi Pazartesi
	 * sabahı herkes listeye düşer, uyarı ilk günden görmezden gelinirdi.
	 *
	 * @return array<int,array{student_id:int,habit_id:int,habit:string}>
	 */
	public static function no_reading( $term_id, ?array $student_ids = null ) {
		global $wpdb;
		$to   = current_time( 'Y-m-d' );
		$from = gmdate( 'Y-m-d', strtotime( '-' . ( self::READING_WINDOW_DAYS - 1 ) . ' days', current_time( 'timestamp' ) ) );

		$params = array( (int) $term_id, $from, $to );
		$sql    = "SELECT hs.student_id, h.id AS habit_id, h.name AS habit
			 FROM {$wpdb->prefix}nizamiye_habit_students hs
			 INNER JOIN {$wpdb->prefix}nizamiye_habits h ON h.id = hs.habit_id
			 INNER JOIN {$wpdb->prefix}nizamiye_students s ON s.id = hs.student_id
			 WHERE h.term_id = %d AND h.track_type = 'reading' AND s.status = 'active'
			   AND NOT EXISTS (
			     SELECT 1 FROM {$wpdb->prefix}nizamiye_habit_logs l
			      WHERE l.habit_id = h.id AND l.student_id = hs.student_id
			        AND l.log_date >= %s AND l.log_date <= %s )";

		// id_clause 'student_id' kolonunu varsayar; burada pivot takma adıyla yazılır.
		if ( null !== $student_ids ) {
			$ids = array_values( array_unique( array_map( 'intval', $student_ids ) ) );
			if ( ! $ids ) {
				return array();
			}
			$sql   .= ' AND hs.student_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params = array_merge( $params, $ids );
		}
		$sql .= ' ORDER BY h.name, hs.student_id';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'student_id' => (int) $r->student_id,
				'habit_id'   => (int) $r->habit_id,
				'habit'      => $r->habit,
			);
		}
		return $out;
	}

	/**
	 * Notu düşen öğrenciler: son sınavların ortalaması, önceki sınavların
	 * ortalamasından $drop puandan fazla geride kalanlar.
	 *
	 * Ölçüt öğrencinin KENDİ gidişatıdır — paneldeki dönem geneli "Destek
	 * Bekleyenler" kartı sürekli düşük olanı gösterir, bu ise yeni bozulmayı
	 * yakalar. Anlamlı bir kıyas için en az iki önceki sınav aranır.
	 *
	 * @return array<int,array{student_id:int,recent:int,earlier:int,drop:int}>
	 */
	public static function declining_grades( $term_id, $drop, ?array $student_ids = null ) {
		global $wpdb;
		$drop = max( 1, (int) $drop );

		$params = array( (int) $term_id );
		$sql    = "SELECT g.student_id, g.exam_date, g.id, (g.score / g.max_score * 100) AS pct
			 FROM {$wpdb->prefix}nizamiye_grades g
			 INNER JOIN {$wpdb->prefix}nizamiye_classes c ON c.id = g.class_id
			 WHERE c.term_id = %d AND g.max_score > 0";
		if ( null !== $student_ids ) {
			$ids = array_values( array_unique( array_map( 'intval', $student_ids ) ) );
			if ( ! $ids ) {
				return array();
			}
			$sql   .= ' AND g.student_id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$params = array_merge( $params, $ids );
		}
		$sql .= ' ORDER BY g.student_id, g.exam_date DESC, g.id DESC';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		if ( ! $rows ) {
			return array();
		}

		$by_student = array();
		foreach ( $rows as $r ) {
			$by_student[ (int) $r->student_id ][] = (float) $r->pct;
		}

		$out = array();
		foreach ( $by_student as $sid => $scores ) {
			// Sorgu exam_date DESC sıralı: baştaki N tanesi "son sınavlar".
			$recent  = array_slice( $scores, 0, self::GRADE_RECENT_EXAMS );
			$earlier = array_slice( $scores, self::GRADE_RECENT_EXAMS );
			if ( count( $earlier ) < 2 ) {
				continue; // Tek sınavla kıyas anlamsız.
			}
			$r_avg = array_sum( $recent ) / count( $recent );
			$e_avg = array_sum( $earlier ) / count( $earlier );
			$diff  = $e_avg - $r_avg;
			if ( $diff < $drop ) {
				continue;
			}
			$out[] = array(
				'student_id' => (int) $sid,
				'recent'     => (int) round( $r_avg ),
				'earlier'    => (int) round( $e_avg ),
				'drop'       => (int) round( $diff ),
			);
		}

		usort( $out, function ( $a, $b ) {
			return $b['drop'] <=> $a['drop'];
		} );
		return $out;
	}

	/** category_id => ad haritası (uyarı satırlarını etiketlemek için). */
	private static function category_names() {
		$names = array();
		foreach ( Nizamiye_Attendance_Types::categories( false ) as $cat ) {
			$names[ (int) $cat->id ] = $cat->name;
		}
		return $names;
	}
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
