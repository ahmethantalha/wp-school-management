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
 * Dönen $sheet dizisinin sözleşmesi. Klasik gövde (_roster-report-body.php) ilk
 * bloğu, afiş gövdesi (_roster-poster-body.php) ikisini birden kullanır:
 *   title, subtitle, school, term, from, to, period_mode, note, density
 *   columns[] => ['label'=>, 'class'=>]
 *   rows[]    => ['student'=>, 'cells'=> [ ['text'=>, 'lines'=>[], 'sub'=>, 'class'=>], ... ] ]
 *   summary[] => ['l'=>etiket, 'v'=>değer]
 *
 * Yalnızca afişin kullandıkları:
 *   layout, kind, heading, motto
 *   meta_pills[]   => ['l'=>etiket, 'v'=>değer]   künye hapları
 *   summary_line[] => ['v'=>sayı, 's'=>açıklama]  alt şerit
 *   rows[]         => ['group'=>şube, 'count'=>n] şube ayırıcı satırı
 *   hücre ekleri   => 'chip', 'check', 'field', 'unit'
 */
class Nizamiye_Sheet {

	/**
	 * Tek sayfaya sığdırmak için satır sayısına göre yoğunluk sınıfı.
	 *
	 * Eşikler düzene göre ayrı: afiş satırları iri punto, rozet ve açıklama
	 * kutuları yüzünden klasik satırlardan belirgin biçimde uzun, o yüzden daha
	 * erken küçülmesi gerekiyor.
	 *
	 * Afiş değerleri gerçek A4 PDF'i üretilip ölçülerek bulundu; belirleyici olan
	 * okuma çizelgesidir, çünkü altında bir de motto şeridi taşır:
	 *   taban 21 satır · is-compact 30 satır · is-dense 44 satır
	 * Eşikler bu kapasitelerin tam üstüne oturur. Taban ölçüler ya da CSS
	 * kademeleri değişirse bu sayılar yeniden ölçülmelidir.
	 */
	private static function density( $row_count, $layout = 'classic' ) {
		if ( 'poster' === $layout ) {
			if ( $row_count > 30 ) {
				return 'is-dense';
			}
			return $row_count > 21 ? 'is-compact' : '';
		}
		if ( $row_count > 60 ) {
			return 'is-dense';
		}
		return $row_count > 30 ? 'is-compact' : '';
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
	private static function roster( $term_id, array $ids, $grade, $section = '' ) {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		return Nizamiye_Students::query( array(
			'term_id' => (int) $term_id,
			'ids'     => $ids,
			'grade'   => (int) $grade,
			'section' => nizamiye_normalize_section( $section ),
			'status'  => 'active',
		) );
	}

	/**
	 * Ortak başlık alanları.
	 *
	 * @param string $title   Klasik düzenin başlığı ve dosya adının kaynağı —
	 *                        kapsam ekleriyle birlikte uzun olabilir.
	 * @param string $heading Afiş künyesinin iri başlığı: yoklama türünün ya da
	 *                        alışkanlığın çıplak adı. Kapsam bilgisi künyede
	 *                        ayrı haplara dağıldığı için başlığa tekrar girmez.
	 */
	private static function base( $title, array $period, $term_id, $heading = '', $layout = 'classic' ) {
		$settings = nizamiye_get_settings();
		$term     = Nizamiye_Terms::get( $term_id );
		return array(
			'title'       => $title,
			'heading'     => '' !== $heading ? $heading : $title,
			'subtitle'    => self::mode_label( $period['mode'] ) . ' • ' . $period['label'],
			'school'      => $settings['school_name'],
			'term'        => $term ? $term->name : '',
			'from'        => $period['from'],
			'to'          => $period['to'],
			'period_mode' => $period['mode'],
			'layout'      => nizamiye_normalize_sheet_layout( $layout ),
			'kind'        => '',
			'columns'     => array(),
			'rows'        => array(),
			'meta_pills'  => array(),
			'summary'     => array(),
			'summary_line' => array(),
			'motto'       => '',
			'note'        => '',
			'density'     => '',
		);
	}

	/** Gün/tarih haplarını kurar; günlük modda gün adı ayrı hap olarak çıkar. */
	private static function period_pills( array $period ) {
		if ( 'day' === $period['mode'] ) {
			$ts = strtotime( $period['date'] );
			return array(
				array( 'l' => 'Tarih', 'v' => gmdate( 'd.m.Y', $ts ) ),
				array( 'l' => '', 'v' => nizamiye_day_names()[ (int) gmdate( 'N', $ts ) ] ),
			);
		}
		return array(
			array( 'l' => self::mode_label( $period['mode'] ), 'v' => $period['label'] ),
		);
	}

	/**
	 * Afiş künyesindeki kapsam hapı: "hangi sınıf?" sorusunun cevabı.
	 *
	 * Bazı yoklamalar birden çok şubeyi kapsadığı için tek bir sınıf adı yazmak
	 * yanıltıcı olurdu; filtre yoksa kapsam doğrudan listedeki öğrencilerden
	 * türetilir. Tabloda ayrıca şube ayırıcıları çıkar (bkz. group_rows()).
	 *
	 * @param array  $roster     Rapor kadrosu (grade_level + section alanlarıyla).
	 * @param string $class_name Derslik bazlı yoklamada dersliğin adı.
	 */
	private static function scope_pill( array $roster, $grade, $section, $class_name = '' ) {
		$grade   = (int) $grade;
		$section = nizamiye_normalize_section( $section );

		if ( '' !== $class_name ) {
			return array( 'l' => 'Derslik', 'v' => $class_name );
		}
		if ( $grade ) {
			return array(
				'l' => 'Sınıf',
				'v' => '' !== $section ? nizamiye_section_label( $grade, $section ) : nizamiye_grade_label( $grade ),
			);
		}
		if ( '' !== $section ) {
			return array( 'l' => 'Şube', 'v' => $section . ' Şubesi' );
		}

		$grades = array();
		foreach ( $roster as $s ) {
			$g = (int) ( $s->grade_level ?? 0 );
			if ( $g ) {
				$grades[ $g ] = true;
			}
		}
		$grades = array_keys( $grades );
		sort( $grades );

		if ( ! $grades ) {
			return array( 'l' => '', 'v' => 'Tüm Öğrenciler' );
		}
		if ( 1 === count( $grades ) ) {
			return array( 'l' => 'Sınıf', 'v' => nizamiye_grade_label( $grades[0] ) );
		}
		if ( count( $grades ) <= 4 ) {
			return array( 'l' => 'Kapsam', 'v' => implode( ' · ', $grades ) . '. Sınıf' );
		}
		return array( 'l' => 'Kapsam', 'v' => 'Tüm Sınıflar (' . count( $grades ) . ')' );
	}

	/**
	 * Birden çok şubeyi kapsayan afişlerde tabloya şube ayırıcı satırları serper
	 * ve her adın altına kendi şubesini yazar. Tek şubelik listede ikisi de
	 * gereksiz gürültü olurdu, o yüzden hiç eklenmez.
	 *
	 * Kadro zaten grade_level, section, first_name sıralı geldiği için (bkz.
	 * Nizamiye_Students::query) ayırıcılar tek geçişte, sıra bozulmadan konur.
	 *
	 * @param array $rows   Satırlar — her biri 'student' anahtarıyla kadro kaydını taşır.
	 * @return array Ayırıcılarla harmanlanmış satır listesi.
	 */
	private static function group_rows( array $rows ) {
		$labels = array();
		foreach ( $rows as $row ) {
			$s = isset( $row['student'] ) ? $row['student'] : null;
			if ( $s ) {
				$labels[ self::section_key( $s ) ] = true;
			}
		}
		if ( count( $labels ) < 2 ) {
			return $rows;
		}

		// Önce her şubenin kaç öğrenci taşıdığını say — ayırıcıda gösterilecek.
		$counts = array();
		foreach ( $rows as $row ) {
			if ( isset( $row['student'] ) ) {
				$key = self::section_key( $row['student'] );
				$counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;
			}
		}

		$out     = array();
		$current = null;
		foreach ( $rows as $row ) {
			if ( isset( $row['student'] ) ) {
				$key = self::section_key( $row['student'] );
				if ( $key !== $current ) {
					$current = $key;
					$out[]   = array( 'group' => $key, 'count' => $counts[ $key ] );
				}
			}
			$out[] = $row;
		}
		return $out;
	}

	/** Kadro birden çok şubeye yayılıyor mu? Ayırıcı ve ad altı etiketi buna bakar. */
	private static function spans_sections( array $roster ) {
		$seen = array();
		foreach ( $roster as $s ) {
			$seen[ self::section_key( $s ) ] = true;
			if ( count( $seen ) > 1 ) {
				return true;
			}
		}
		return false;
	}

	/** Şube ayırıcısının etiketi: "4-A" ya da sınıfsızsa "Sınıfsız". */
	private static function section_key( $student ) {
		$grade   = (int) ( $student->grade_level ?? 0 );
		$section = nizamiye_normalize_section( $student->section ?? '' );
		if ( ! $grade ) {
			return '' !== $section ? $section . ' Şubesi' : 'Sınıfsız';
		}
		return '' !== $section ? nizamiye_section_label( $grade, $section ) : nizamiye_grade_label( $grade );
	}

	/** Başlığa eklenen sınıf/şube kısıtı etiketi: " — 6-A" ya da " — 6. Sınıf". */
	private static function scope_note( $grade, $section ) {
		$grade   = (int) $grade;
		$section = nizamiye_normalize_section( $section );
		if ( ! $grade && '' === $section ) {
			return '';
		}
		if ( ! $grade ) {
			return ' — ' . $section . ' şubesi';
		}
		return ' — ' . nizamiye_section_label( $grade, $section );
	}

	/**
	 * Öğrenci adı hücresi (ad + küçük punto sınıf bilgisi).
	 *
	 * Afişte alt satır hiç yazılmaz: tek şubeli listede künyedeki kapsam hapı,
	 * çok şubeli listede de şube ayırıcı şeridi zaten aynı bilgiyi veriyor.
	 * Her adın altında tekrarlamak hem gürültü olurdu hem de satır başına
	 * fazladan bir satır yüksekliği getirip listeyi ikinci sayfaya taşırdı.
	 */
	private static function name_cell( $student, $is_poster = false ) {
		$grade = (int) ( $student->grade_level ?? 0 );
		return array(
			'text'  => nizamiye_student_name( $student ),
			'sub'   => ( ! $is_poster && $grade ) ? nizamiye_grade_label( $grade ) : '',
			'class' => 'name',
		);
	}

	/**
	 * Afişte elle konulan işareti satıra uygular: ad sütunundan sonrası tek bir
	 * renkli etikete iner, kalan sütunlar tire ile doldurulur.
	 *
	 * İşaretler veritabanına yazılmaz (gerekçe: nizamiye_sheet_mark_types()),
	 * bu yüzden burada yalnızca görüntü katmanında iş görürler.
	 *
	 * @param array  $cells  Normal yoldan kurulmuş hücreler (0: sıra, 1: ad).
	 * @param string $type   nizamiye_sheet_mark_types() anahtarı.
	 * @param int    $column Satırın toplam sütun sayısı.
	 */
	private static function apply_mark( array $cells, $type, $column ) {
		$types = nizamiye_sheet_mark_types();
		if ( ! isset( $types[ $type ] ) ) {
			return $cells;
		}
		$kept = array_slice( $cells, 0, 2 );
		$kept[] = array(
			'text' => $types[ $type ]['label'],
			'chip' => $types[ $type ]['class'],
		);
		while ( count( $kept ) < $column ) {
			$kept[] = array( 'text' => '—', 'class' => 'c empty', 'chip' => 'empty' );
		}
		return $kept;
	}

	/**
	 * Bir alışkanlığın dönem raporu.
	 *
	 * @param array  $period nizamiye_resolve_period() çıktısı.
	 * @param string $layout 'classic' ya da 'poster'.
	 * @param array  $marks  [öğrenci id => işaret türü] — yalnızca afişte,
	 *                       yalnızca görüntü için; kaydedilmez.
	 * @return array|WP_Error
	 */
	public static function habit_sheet( $habit_id, array $period, $grade, $term_id, $section = '', $layout = 'classic', array $marks = array() ) {
		$habit_id = (int) $habit_id;
		$term_id  = (int) $term_id;
		$habit    = Nizamiye_Habits::get( $habit_id );
		$layout    = nizamiye_normalize_sheet_layout( $layout );
		$is_poster = 'poster' === $layout;

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

		$roster = self::roster( $term_id, wp_list_pluck( $students, 'id' ), $grade, $section );
		$report = Nizamiye_Habits::report_rows( $habit_id, $period['from'], $period['to'], wp_list_pluck( $roster, 'id' ) );
		$data   = $report['students'];
		$tracked = max( 1, (int) $report['tracked_days'] );

		$scope_note = self::scope_note( $grade, $section );
		$sheet      = self::base( $habit->name . $scope_note, $period, $term_id, $habit->name, $layout );
		$sheet['kind'] = 'habit';
		// Afişte çok şubeli listelerde ad altına şube yazılır; tek şubelikte yazılmaz.
		$with_section = $is_poster && self::spans_sections( $roster );
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
				self::name_cell( $s, $is_poster ),
			);

			// Elle işaretlenen öğrencide veri hücreleri hiç kurulmaz: öğretmen
			// çıktı için bilinçli olarak kaydın üstüne yazıyor.
			if ( $is_poster && isset( $marks[ $sid ] ) ) {
				$rows[] = array(
					'student' => $s,
					'cells'   => self::apply_mark( $cells, $marks[ $sid ], count( $cols ) ),
				);
				continue;
			}

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
						'unit'  => 's.',
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
						'unit'  => 's.',
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

			$rows[] = array( 'student' => $s, 'cells' => $cells );
		}

		if ( $is_poster ) {
			$rows = self::group_rows( $rows );
		}
		$sheet['rows']    = $rows;
		$sheet['density'] = self::density( count( $rows ), $layout );

		// --- Özet kutucukları ---
		// $i öğrenci sayısıdır; $rows afişte şube ayırıcılarını da taşıdığı için
		// sayım ona değil sayaca bağlanır.
		$student_count = $i;
		$summary = array( array( 'l' => 'Öğrenci', 'v' => (string) $student_count ) );
		if ( $is_reading ) {
			$summary[] = array( 'l' => 'Toplam Sayfa', 'v' => number_format_i18n( $total_pages ) );
			if ( ! $is_day ) {
				$summary[] = array( 'l' => 'Farklı Kitap', 'v' => (string) count( $all_books ) );
			}
			$summary[] = array( 'l' => 'Kayıt Girilen', 'v' => $with_record . ' / ' . $student_count );
		} else {
			if ( ! $is_day ) {
				$summary[] = array( 'l' => 'Takip Edilen Gün', 'v' => (string) $report['tracked_days'] );
				$summary[] = array(
					'l' => 'Ortalama Oran',
					'v' => $rate_count ? round( $rate_sum / $rate_count ) . '%' : '—',
				);
			}
			$summary[] = array( 'l' => 'Kayıt Girilen', 'v' => $with_record . ' / ' . $student_count );
		}
		$sheet['summary'] = $summary;

		if ( $is_poster ) {
			$sheet['meta_pills'] = array_merge(
				self::period_pills( $period ),
				array( self::scope_pill( $roster, $grade, $section ) )
			);

			// Afişin alt şeridi: kutucuk yığını yerine tek cümlelik özet.
			$parts = array( array( 'v' => $with_record, 's' => 'öğrenci kayıt girdi' ) );
			if ( $is_reading ) {
				$parts[] = array( 'v' => number_format_i18n( $total_pages ), 's' => 'sayfa okundu' );
				if ( $all_books ) {
					$parts[] = array( 'v' => count( $all_books ), 's' => 'farklı kitap' );
				}
			} elseif ( $rate_count ) {
				$parts[] = array( 'v' => '%' . round( $rate_sum / $rate_count ), 's' => 'ortalama' );
			}
			// Yalnızca bu kadroda yer alan işaretler sayılır; filtre daraltıldığında
			// adres satırında kalan eski id'ler özeti şişirmesin.
			$marked = count( array_intersect_key( $marks, array_flip( wp_list_pluck( $roster, 'id' ) ) ) );
			if ( $marked ) {
				$parts[] = array( 'v' => $marked, 's' => 'elle işaretlendi' );
			}
			$sheet['summary_line'] = $parts;
			$sheet['motto']        = $is_reading ? 'Okumak, geleceğe açılan en güzel kapıdır.' : '';
		}

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
	public static function attendance_sheet( $term_id, $category_id, $session_id, $class_id, array $period, $grade, $section = '', $layout = 'classic' ) {
		$term_id     = (int) $term_id;
		$category_id = (int) $category_id;
		$session_id  = (int) $session_id;
		$class_id    = (int) $class_id;
		$layout      = nizamiye_normalize_sheet_layout( $layout );
		$is_poster   = 'poster' === $layout;

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
		$title      = $category->name;
		$class_name = '';
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
				$title     .= ' — ' . $class->name;
				$class_name = $class->name;
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
		$title .= self::scope_note( $grade, $section );

		$roster  = self::roster( $term_id, wp_list_pluck( $students, 'id' ), $grade, $section );
		$report  = Nizamiye_Attendance::roster_report( $term_id, $category_id, $session_id, $class_id, $period['from'], $period['to'], wp_list_pluck( $roster, 'id' ) );
		$data    = $report['students'];

		// Tek gün + tek oturum ise tek bir durum gösterilebilir; aksi halde
		// (haftalık/aylık ya da "tüm oturumlar") sayım sütunları anlamlıdır.
		$single = 'day' === $period['mode'] && ( $session_id || ! $multi_session );

		// Afişte künye başlığı yoklama türünün çıplak adıdır; derslik, oturum ve
		// tarih künyedeki haplara dağılır, başlıkta tekrar edilmez.
		$sheet = self::base( $title, $period, $term_id, $category->name, $layout );
		$sheet['kind'] = 'attendance';

		// Tek günün tek oturumu afişte kâğıt üstünde işaretlenebilir bir forma
		// dönüşür: durum/not yerine geldi ✓ / gelmedi ✗ ve açıklama kutusu.
		$poster_form  = $is_poster && $single;
		$with_section = $is_poster && self::spans_sections( $roster );

		$cols = array( array( 'label' => $is_poster ? 'Sıra' : '#', 'class' => 'num' ), array( 'label' => $is_poster ? 'İsim Soyisim' : 'Öğrenci', 'class' => 'name' ) );
		if ( $poster_form ) {
			$cols[] = array( 'label' => 'Geldi', 'class' => 'tick' );
			$cols[] = array( 'label' => 'Gelmedi', 'class' => 'tick' );
			$cols[] = array( 'label' => 'Açıklama', 'class' => 'c' );
		} elseif ( $single ) {
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
		$here_count  = 0;
		$away_count  = 0;

		foreach ( $roster as $s ) {
			$i++;
			$sid   = (int) $s->id;
			$d     = isset( $data[ $sid ] ) ? $data[ $sid ] : null;
			$cells = array(
				array( 'text' => (string) $i, 'class' => 'num' ),
				self::name_cell( $s, $is_poster ),
			);

			if ( $d ) {
				$with_record++;
				$absent_sum += (int) $d['absent'];
				if ( null !== $d['rate'] ) {
					$rate_sum += (int) $d['rate'];
					$rate_count++;
				}
			}

			if ( $poster_form ) {
				$entry  = $d && $d['entries'] ? $d['entries'][0] : null;
				$status = $entry ? $entry['status'] : '';
				$here   = 'present' === $status;
				if ( $entry ) {
					$here ? $here_count++ : $away_count++;
				}
				// Kayıt yoksa iki sütun da boş halka kalır — çıktı elde
				// doldurulabilen bir yoklama formu olarak da kullanılabilsin diye.
				$cells[] = array( 'check' => $entry ? ( $here ? 'yes' : 'off' ) : 'off', 'class' => 'tick' );
				$cells[] = array( 'check' => $entry && ! $here ? 'no' : 'off', 'class' => 'tick' );

				// Açıklama: öğretmenin notu, yoksa "geç"/"izinli" gibi durumun adı.
				$explain = '';
				if ( $entry && '' !== $entry['note'] ) {
					$explain = $entry['note'];
				} elseif ( $entry && ! $here && 'absent' !== $status ) {
					$explain = $labels[ $status ];
				}
				$cells[] = array( 'text' => $explain, 'field' => true, 'class' => 'c' );
			} elseif ( $single ) {
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

			$rows[] = array( 'student' => $s, 'cells' => $cells );
		}

		if ( $is_poster ) {
			$rows = self::group_rows( $rows );
		}

		// $i öğrenci sayısıdır; $rows afişte şube ayırıcılarını da taşır.
		$student_count    = $i;
		$sheet['rows']    = $rows;
		$sheet['density'] = self::density( count( $rows ), $layout );
		$sheet['summary'] = array(
			array( 'l' => 'Öğrenci', 'v' => (string) $student_count ),
			array( 'l' => 'Kayıt Girilen', 'v' => $with_record . ' / ' . $student_count ),
		);
		if ( ! $single ) {
			$sheet['summary'][] = array( 'l' => 'Ortalama Katılım', 'v' => $rate_count ? round( $rate_sum / $rate_count ) . '%' : '—' );
			$sheet['summary'][] = array( 'l' => 'Toplam Devamsızlık', 'v' => (string) $absent_sum );
		}

		if ( $is_poster ) {
			$pills = self::period_pills( $period );
			if ( $session ) {
				$pills[] = array( 'l' => 'Oturum', 'v' => $session->name );
			}
			$pills[] = self::scope_pill( $roster, $grade, $section, $class_name );
			$sheet['meta_pills'] = $pills;

			if ( $poster_form ) {
				$line = array(
					array( 'v' => $here_count, 's' => 'geldi' ),
					array( 'v' => $away_count, 's' => 'gelmedi' ),
				);
				$done = $here_count + $away_count;
				if ( $done ) {
					$line[] = array( 'v' => '%' . round( $here_count / $done * 100 ), 's' => 'katılım' );
				}
			} else {
				$line = array(
					array( 'v' => $with_record . ' / ' . $student_count, 's' => 'kayıt girildi' ),
					array( 'v' => $rate_count ? '%' . round( $rate_sum / $rate_count ) : '—', 's' => 'ortalama katılım' ),
				);
			}
			if ( $with_section ) {
				$line[] = array( 'v' => count( array_unique( array_map( array( __CLASS__, 'section_key' ), $roster ) ) ), 's' => 'şube' );
			}
			$sheet['summary_line'] = $line;
		}

		if ( $poster_form ) {
			$sheet['note'] = 'İki sütunu da boş halka olan satırlarda bu güne ait yoklama girilmemiştir.';
		} elseif ( $single ) {
			$sheet['note'] = '"—" işaretli öğrenciler için bu güne ait yoklama girilmemiştir.';
		} else {
			$sheet['note'] = 'Katılım oranı hesabında geç kalma yarım devam sayılır.'
				. ( ! $session_id && $multi_session ? ' Sayılar kategorinin tüm oturumlarını kapsar.' : '' );
		}

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

	/** Bir sheet'in gövde şablonu — önizleme ve PDF aynısını include eder. */
	public static function body_template( array $sheet ) {
		return 'poster' === ( isset( $sheet['layout'] ) ? $sheet['layout'] : 'classic' )
			? NIZAMIYE_DIR . 'admin/views/print/_roster-poster-body.php'
			: NIZAMIYE_DIR . 'admin/views/print/_roster-report-body.php';
	}

	/** Sheet'in dış sarmalayıcı sınıfı (.sheet / .sheet-poster) + yoğunluk. */
	public static function wrapper_class( array $sheet ) {
		$base = 'poster' === ( isset( $sheet['layout'] ) ? $sheet['layout'] : 'classic' ) ? 'sheet-poster' : 'sheet';
		return trim( $base . ' ' . $sheet['density'] );
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
