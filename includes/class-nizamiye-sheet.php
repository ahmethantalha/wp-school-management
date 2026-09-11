<?php
defined( 'ABSPATH' ) || exit;

/**
 * Velilere gönderilen toplu liste raporlarını ("sheet") hazırlar.
 *
 * Tek bir veri kaynağı olması kritik: hem ekrandaki önizleme (admin/views/
 * habit-report.php, attendance-report.php) hem de PDF endpoint'i
 * (Nizamiye_Actions::handle_print_roster) buradaki aynı metotları çağırır.
 * Böylece önizleme, PDF ve önizlemeden üretilen PNG/JPG birbirinden ayrışamaz.
 *
 * Yetki daraltması da burada yapılır — iki çağıran tarafta kopyalanıp zamanla
 * birbirinden uzaklaşmasın diye.
 *
 * Dönen $sheet dizisinin sözleşmesi (admin/views/print/_roster-report-body.php):
 *   title, subtitle, school, term, from, to, period_mode,
 *   columns[] => ['label'=>, 'class'=>]
 *   rows[]    => ['cells'=> [ ['text'=>, 'lines'=>[], 'sub'=>, 'class'=>], ... ] ]
 *   summary[] => ['l'=>etiket, 'v'=>değer]
 *   note, density
 */
class Nizamiye_Sheet {

	/** Tek sayfaya sığdırmak için satır sayısına göre yoğunluk sınıfı. */
	private static function density( $row_count ) {
		if ( $row_count > 60 ) {
			return 'is-dense';
		}
		if ( $row_count > 30 ) {
			return 'is-compact';
		}
		return '';
	}

	/** Yüzdeyi sheet CSS'indeki renk sınıfına çevirir (admin.css'ten bağımsız). */
	private static function rate_class( $rate ) {
		if ( null === $rate ) {
			return 'empty';
		}
		if ( $rate >= 75 ) {
			return 'good';
		}
		return $rate >= 50 ? 'mid' : 'low';
	}

	private static function mode_label( $mode ) {
		if ( 'week' === $mode ) {
			return 'Haftalık Rapor';
		}
		return 'month' === $mode ? 'Aylık Rapor' : 'Günlük Rapor';
	}

	/**
	 * Öğrenci id listesini rapor kadrosuna çevirir: sınıf seviyesi filtresi
	 * uygulanır, grade_level alanı eklenir ve sıralama (sınıf, ad) sabitlenir.
	 * Nizamiye_Habits::students() / Nizamiye_Classes::students() yalnızca s.*
	 * döndürdüğü ve grade_level içermediği için bu adım gerekli.
	 */
	private static function roster( $term_id, array $ids, $grade ) {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		return Nizamiye_Students::query( array(
			'term_id' => (int) $term_id,
			'ids'     => $ids,
			'grade'   => (int) $grade,
			'status'  => 'active',
		) );
	}

	/** Ortak başlık alanları. */
	private static function base( $title, array $period, $term_id ) {
		$settings = nizamiye_get_settings();
		$term     = Nizamiye_Terms::get( $term_id );
		return array(
			'title'       => $title,
			'subtitle'    => self::mode_label( $period['mode'] ) . ' • ' . $period['label'],
			'school'      => $settings['school_name'],
			'term'        => $term ? $term->name : '',
			'from'        => $period['from'],
			'to'          => $period['to'],
			'period_mode' => $period['mode'],
			'columns'     => array(),
			'rows'        => array(),
			'summary'     => array(),
			'note'        => '',
			'density'     => '',
		);
	}

	/** Öğrenci adı hücresi (ad + küçük punto sınıf bilgisi). */
	private static function name_cell( $student ) {
		$grade = (int) ( $student->grade_level ?? 0 );
		return array(
			'text'  => nizamiye_student_name( $student ),
			'sub'   => $grade ? nizamiye_grade_label( $grade ) : '',
			'class' => 'name',
		);
	}

	/**
	 * Bir alışkanlığın dönem raporu.
	 *
	 * @param array $period nizamiye_resolve_period() çıktısı.
	 * @return array|WP_Error
	 */
	public static function habit_sheet( $habit_id, array $period, $grade, $term_id ) {
		$habit_id = (int) $habit_id;
		$term_id  = (int) $term_id;
		$habit    = Nizamiye_Habits::get( $habit_id );

		if ( ! $habit ) {
			return new WP_Error( 'nizamiye_sheet_habit', 'Alışkanlık bulunamadı.' );
		}
		if ( (int) $habit->term_id !== $term_id ) {
			return new WP_Error( 'nizamiye_sheet_term', 'Bu alışkanlık seçili döneme ait değil.' );
		}

		// Yetki: öğretmen, alışkanlığı kendi oluşturmadıysa yalnızca kendi
		// öğrencilerini görür — habit-track.php'deki kuralın aynısı.
		$students = Nizamiye_Habits::students( $habit_id );
		if ( nizamiye_is_teacher() && (int) $habit->created_by !== get_current_user_id() ) {
			$mine     = nizamiye_teacher_student_ids( 0, $term_id );
			$students = array_values( array_filter( $students, function ( $s ) use ( $mine ) {
				return in_array( (int) $s->id, $mine, true );
			} ) );
		}

		$roster = self::roster( $term_id, wp_list_pluck( $students, 'id' ), $grade );
		$report = Nizamiye_Habits::report_rows( $habit_id, $period['from'], $period['to'], wp_list_pluck( $roster, 'id' ) );
		$data   = $report['students'];
		$tracked = max( 1, (int) $report['tracked_days'] );

		$sheet      = self::base( $habit->name, $period, $term_id );
		$is_reading = 'reading' === $habit->track_type;
		$is_scale   = 'scale' === $habit->track_type;
		$is_day     = 'day' === $period['mode'];
		$scale_max  = max( 1, (int) $habit->scale_max );

		// --- Sütunlar ---
		$cols = array( array( 'label' => '#', 'class' => 'num' ), array( 'label' => 'Öğrenci', 'class' => 'name' ) );
		if ( $is_reading ) {
			$cols[] = $is_day
				? array( 'label' => 'Kitap Adı', 'class' => 'books' )
				: array( 'label' => 'Okunan Kitaplar', 'class' => 'books' );
			$cols[] = array( 'label' => $is_day ? 'Sayfa' : 'Toplam Sayfa', 'class' => 'c' );
			if ( ! $is_day ) {
				$cols[] = array( 'label' => 'Gün', 'class' => 'c' );
			}
		} elseif ( $is_scale ) {
			$cols[] = array( 'label' => $is_day ? 'Derece' : 'Ortalama Derece', 'class' => 'c' );
			$cols[] = $is_day
				? array( 'label' => 'Not', 'class' => '' )
				: array( 'label' => 'Gün', 'class' => 'c' );
			if ( ! $is_day ) {
				$cols[] = array( 'label' => 'Oran', 'class' => 'c' );
			}
		} else {
			// Günlük: tek günün durumu. Haftalık/aylık: kaç günde yapıldığı ("5 / 6").
			$cols[] = array( 'label' => $is_day ? 'Durum' : 'Yapılan Gün', 'class' => 'c' );
			$cols[] = $is_day
				? array( 'label' => 'Not', 'class' => '' )
				: array( 'label' => 'Oran', 'class' => 'c' );
		}
		$sheet['columns'] = $cols;

		// --- Satırlar ---
		$rows          = array();
		$i             = 0;
		$total_pages   = 0;
		$with_record   = 0;
		$rate_sum      = 0;
		$rate_count    = 0;
		$all_books     = array();

		foreach ( $roster as $s ) {
			$i++;
			$sid   = (int) $s->id;
			$d     = isset( $data[ $sid ] ) ? $data[ $sid ] : null;
			$cells = array(
				array( 'text' => (string) $i, 'class' => 'num' ),
				self::name_cell( $s ),
			);

			if ( $d ) {
				$with_record++;
			}

			if ( $is_reading ) {
				$total_pages += $d ? (int) $d['total'] : 0;
				if ( $d ) {
					foreach ( array_keys( $d['books'] ) as $b ) {
						$all_books[ $b ] = true;
					}
				}
				if ( $is_day ) {
					$entry   = $d && $d['entries'] ? $d['entries'][0] : null;
					$cells[] = array(
						'text'  => $entry && '' !== $entry['note'] ? $entry['note'] : ( $entry ? '(İsimsiz kitap)' : '—' ),
						'class' => $entry ? 'books' : 'empty',
					);
					$cells[] = array(
						'text'  => $entry ? (string) $entry['value'] : '—',
						'class' => $entry ? 'c' : 'c empty',
					);
				} else {
					$lines = array();
					if ( $d ) {
						foreach ( $d['books'] as $title => $pages ) {
							$lines[] = $title . ' — ' . $pages . ' s.';
						}
					}
					$cells[] = $lines
						? array( 'lines' => $lines, 'class' => 'books' )
						: array( 'text' => '—', 'class' => 'empty' );
					$cells[] = array(
						'text'  => $d ? (string) $d['total'] : '—',
						'class' => $d ? 'c' : 'c empty',
					);
					$cells[] = array(
						'text'  => $d ? (string) $d['days'] : '—',
						'class' => $d ? 'c' : 'c empty',
					);
				}
			} elseif ( $is_scale ) {
				if ( $is_day ) {
					$entry   = $d && $d['entries'] ? $d['entries'][0] : null;
					$cells[] = array(
						'text'  => $entry ? $entry['value'] . ' / ' . $scale_max : '—',
						'class' => $entry ? 'c ' . self::rate_class( round( $entry['value'] / $scale_max * 100 ) ) : 'c empty',
					);
					$cells[] = array(
						'text'  => $entry && '' !== $entry['note'] ? $entry['note'] : '—',
						'class' => $entry && '' !== $entry['note'] ? '' : 'empty',
					);
				} else {
					$rate = $d && null !== $d['avg'] ? (int) round( $d['avg'] / $scale_max * 100 ) : null;
					if ( null !== $rate ) {
						$rate_sum += $rate;
						$rate_count++;
					}
					$cells[] = array(
						'text'  => $d && null !== $d['avg'] ? $d['avg'] . ' / ' . $scale_max : '—',
						'class' => $d ? 'c' : 'c empty',
					);
					$cells[] = array(
						'text'  => $d ? (string) $d['days'] : '—',
						'class' => $d ? 'c' : 'c empty',
					);
					$cells[] = array(
						'text'  => null !== $rate ? $rate . '%' : '—',
						'class' => 'c ' . self::rate_class( $rate ),
					);
				}
			} else {
				if ( $is_day ) {
					$entry   = $d && $d['entries'] ? $d['entries'][0] : null;
					$done    = $entry && $entry['value'] > 0;
					$cells[] = array(
						'text'  => $entry ? ( $done ? '✓ Yaptı' : '✗ Yapmadı' ) : '—',
						'class' => $entry ? 'c ' . ( $done ? 'good' : 'low' ) : 'c empty',
					);
					$cells[] = array(
						'text'  => $entry && '' !== $entry['note'] ? $entry['note'] : '—',
						'class' => $entry && '' !== $entry['note'] ? '' : 'empty',
					);
				} else {
					$rate = $d ? (int) round( $d['done'] / $tracked * 100 ) : null;
					if ( null !== $rate ) {
						$rate_sum += $rate;
						$rate_count++;
					}
					$cells[] = array(
						'text'  => $d ? $d['done'] . ' / ' . $tracked : '—',
						'class' => $d ? 'c' : 'c empty',
					);
					$cells[] = array(
						'text'  => null !== $rate ? $rate . '%' : '—',
						'class' => 'c ' . self::rate_class( $rate ),
					);
				}
			}

			$rows[] = array( 'cells' => $cells );
		}

		$sheet['rows']    = $rows;
		$sheet['density'] = self::density( count( $rows ) );

		// --- Özet kutucukları ---
		$summary = array( array( 'l' => 'Öğrenci', 'v' => (string) count( $rows ) ) );
		if ( $is_reading ) {
			$summary[] = array( 'l' => 'Toplam Sayfa', 'v' => number_format_i18n( $total_pages ) );
			if ( ! $is_day ) {
				$summary[] = array( 'l' => 'Farklı Kitap', 'v' => (string) count( $all_books ) );
			}
			$summary[] = array( 'l' => 'Kayıt Girilen', 'v' => $with_record . ' / ' . count( $rows ) );
		} else {
			if ( ! $is_day ) {
				$summary[] = array( 'l' => 'Takip Edilen Gün', 'v' => (string) $report['tracked_days'] );
				$summary[] = array(
					'l' => 'Ortalama Oran',
					'v' => $rate_count ? round( $rate_sum / $rate_count ) . '%' : '—',
				);
			}
			$summary[] = array( 'l' => 'Kayıt Girilen', 'v' => $with_record . ' / ' . count( $rows ) );
		}
		$sheet['summary'] = $summary;

		if ( $is_reading ) {
			$sheet['note'] = $is_day
				? 'Kitap/sayfa girilmemiş öğrenciler için o güne ait kayıt bulunmuyor.'
				: 'Sayfa sayıları öğrencinin o dönemde girilen günlük kayıtlarının toplamıdır.';
		} elseif ( $is_scale ) {
			$sheet['note'] = $is_day
				? '"—" işaretli öğrenciler için o güne ait kayıt girilmemiştir.'
				: 'Oran, öğrencinin ortalama derecesinin en yüksek dereceye (' . $scale_max . ') bölünmesiyle bulunur.';
		} else {
			$sheet['note'] = $is_day
				? '"—" işaretli öğrenciler için o güne ait kayıt girilmemiştir.'
				: 'Oranlar, bu alışkanlıkta kayıt girilen ' . (int) $report['tracked_days'] . ' güne göre hesaplanmıştır.';
		}

		return $sheet;
	}

	/**
	 * Bir yoklama türünün (kategori/oturum/derslik) dönem raporu.
	 *
	 * @param array $period nizamiye_resolve_period() çıktısı.
	 * @return array|WP_Error
	 */
	public static function attendance_sheet( $term_id, $category_id, $session_id, $class_id, array $period, $grade ) {
		$term_id     = (int) $term_id;
		$category_id = (int) $category_id;
		$session_id  = (int) $session_id;
		$class_id    = (int) $class_id;

		$category = Nizamiye_Attendance_Types::get_category( $category_id );
		if ( ! $category ) {
			return new WP_Error( 'nizamiye_sheet_cat', 'Yoklama türü bulunamadı.' );
		}

		$sessions      = Nizamiye_Attendance_Types::sessions( $category_id );
		$multi_session = count( $sessions ) > 1;
		$session       = $session_id ? Nizamiye_Attendance_Types::get_session( $session_id ) : null;
		if ( $session_id && ( ! $session || (int) $session->category_id !== $category_id ) ) {
			return new WP_Error( 'nizamiye_sheet_session', 'Oturum bu yoklama türüne ait değil.' );
		}

		// Yetki + kadro: attendance.php'deki kuralların aynısı.
		$title = $category->name;
		if ( 'class' === $category->scope ) {
			if ( ! $class_id ) {
				return new WP_Error( 'nizamiye_sheet_class', 'Bu yoklama türü derslik bazlıdır; önce yoklama ekranından bir derslik seçin.' );
			}
			if ( ! nizamiye_can_manage_class( $class_id ) ) {
				return new WP_Error( 'nizamiye_sheet_perm', 'Bu dersliğin yoklamasına erişim yetkiniz yok.' );
			}
			$class    = Nizamiye_Classes::get( $class_id );
			$students = Nizamiye_Classes::students( $class_id );
			if ( $class ) {
				$title .= ' — ' . $class->name;
			}
		} else {
			if ( ! nizamiye_can_take_general_attendance() ) {
				return new WP_Error( 'nizamiye_sheet_perm', 'Genel yoklama raporuna erişim yetkiniz yok.' );
			}
			$class_id = 0;
			$ids      = nizamiye_general_attendance_student_ids( $term_id, 0, $category_id );
			$students = $ids ? Nizamiye_Students::query( array( 'term_id' => $term_id, 'status' => 'active', 'ids' => $ids ) ) : array();
		}
		if ( $session ) {
			$title .= ' — ' . $session->name;
		}

		$roster  = self::roster( $term_id, wp_list_pluck( $students, 'id' ), $grade );
		$report  = Nizamiye_Attendance::roster_report( $term_id, $category_id, $session_id, $class_id, $period['from'], $period['to'], wp_list_pluck( $roster, 'id' ) );
		$data    = $report['students'];

		// Tek gün + tek oturum ise tek bir durum gösterilebilir; aksi halde
		// (haftalık/aylık ya da "tüm oturumlar") sayım sütunları anlamlıdır.
		$single = 'day' === $period['mode'] && ( $session_id || ! $multi_session );

		$sheet = self::base( $title, $period, $term_id );

		$cols = array( array( 'label' => '#', 'class' => 'num' ), array( 'label' => 'Öğrenci', 'class' => 'name' ) );
		if ( $single ) {
			$cols[] = array( 'label' => 'Durum', 'class' => 'c' );
			$cols[] = array( 'label' => 'Not', 'class' => '' );
		} else {
			$cols[] = array( 'label' => 'Geldi', 'class' => 'c' );
			$cols[] = array( 'label' => 'Gelmedi', 'class' => 'c' );
			$cols[] = array( 'label' => 'Geç', 'class' => 'c' );
			$cols[] = array( 'label' => 'İzinli', 'class' => 'c' );
			$cols[] = array( 'label' => 'Katılım', 'class' => 'c' );
		}
		$sheet['columns'] = $cols;

		$labels      = nizamiye_attendance_statuses();
		$rows        = array();
		$i           = 0;
		$with_record = 0;
		$rate_sum    = 0;
		$rate_count  = 0;
		$absent_sum  = 0;

		foreach ( $roster as $s ) {
			$i++;
			$sid   = (int) $s->id;
			$d     = isset( $data[ $sid ] ) ? $data[ $sid ] : null;
			$cells = array(
				array( 'text' => (string) $i, 'class' => 'num' ),
				self::name_cell( $s ),
			);

			if ( $d ) {
				$with_record++;
				$absent_sum += (int) $d['absent'];
				if ( null !== $d['rate'] ) {
					$rate_sum += (int) $d['rate'];
					$rate_count++;
				}
			}

			if ( $single ) {
				$entry   = $d && $d['entries'] ? $d['entries'][0] : null;
				$status  = $entry ? $entry['status'] : '';
				$cells[] = array(
					'text'  => $entry ? $labels[ $status ] : '—',
					'class' => $entry ? 'c ' . ( 'present' === $status ? 'good' : ( 'absent' === $status ? 'low' : 'mid' ) ) : 'c empty',
				);
				$cells[] = array(
					'text'  => $entry && '' !== $entry['note'] ? $entry['note'] : '—',
					'class' => $entry && '' !== $entry['note'] ? '' : 'empty',
				);
			} else {
				foreach ( array( 'present', 'absent', 'late', 'excused' ) as $key ) {
					$cells[] = array(
						'text'  => $d ? (string) $d[ $key ] : '—',
						'class' => $d ? 'c' : 'c empty',
					);
				}
				$cells[] = array(
					'text'  => $d && null !== $d['rate'] ? $d['rate'] . '%' : '—',
					'class' => 'c ' . self::rate_class( $d ? $d['rate'] : null ),
				);
			}

			$rows[] = array( 'cells' => $cells );
		}

		$sheet['rows']    = $rows;
		$sheet['density'] = self::density( count( $rows ) );
		$sheet['summary'] = array(
			array( 'l' => 'Öğrenci', 'v' => (string) count( $rows ) ),
			array( 'l' => 'Kayıt Girilen', 'v' => $with_record . ' / ' . count( $rows ) ),
		);
		if ( ! $single ) {
			$sheet['summary'][] = array( 'l' => 'Ortalama Katılım', 'v' => $rate_count ? round( $rate_sum / $rate_count ) . '%' : '—' );
			$sheet['summary'][] = array( 'l' => 'Toplam Devamsızlık', 'v' => (string) $absent_sum );
		}

		$sheet['note'] = $single
			? '"—" işaretli öğrenciler için bu güne ait yoklama girilmemiştir.'
			: 'Katılım oranı hesabında geç kalma yarım devam sayılır.'
				. ( ! $session_id && $multi_session ? ' Sayılar kategorinin tüm oturumlarını kapsar.' : '' );

		return $sheet;
	}

	/** Sheet'i dompdf'e verilecek bağımsız HTML belgesine çevirir. */
	public static function render_html( array $sheet ) {
		// Şablon dosyaları önekli değişken adları bekler (WordPress.NamingConventions.PrefixAllGlobals).
		$nizamiye_sheet = $sheet;
		ob_start();
		include NIZAMIYE_DIR . 'admin/views/print/roster-report-print.php';
		return ob_get_clean();
	}

	/**
	 * İndirilen dosya adının uzantısız çekirdeği: "Kitap_Okuma-2026-09-08_2026-09-14".
	 * PNG/JPG'yi tarayıcı ürettiği için bu değer JS'e de veri özniteliğiyle geçer.
	 */
	public static function filename_base( array $sheet ) {
		$name = remove_accents( (string) $sheet['title'] );
		$name = preg_replace( '/[^A-Za-z0-9]+/', '_', $name );
		$name = trim( (string) $name, '_' );
		$name = '' !== $name ? $name : 'Rapor';
		$span = $sheet['from'] === $sheet['to'] ? $sheet['from'] : $sheet['from'] . '_' . $sheet['to'];
		return $name . '-' . $span;
	}

	/** İndirilen dosya için güvenli ad: "Kitap_Okuma-2026-09-08_2026-09-14.pdf". */
	public static function filename( array $sheet, $ext ) {
		return self::filename_base( $sheet ) . '.' . $ext;
	}
}
