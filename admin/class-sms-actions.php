<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tüm form gönderimlerinin işleyicileri (admin-post.php).
 * Her işlem: nonce doğrulaması + yetki kontrolü + geri yönlendirme.
 */
class Nizamiye_Actions {

	public static function init() {
		$actions = array(
			'nizamiye_save_term'       => 'nizamiye_manage',
			'nizamiye_activate_term'   => 'nizamiye_manage',
			'nizamiye_delete_term'     => 'nizamiye_manage',
			'nizamiye_open_term'       => 'nizamiye_manage',
			'nizamiye_save_student'    => 'nizamiye_manage',
			'nizamiye_delete_student'  => 'nizamiye_manage',
			'nizamiye_save_user'       => 'nizamiye_manage',
			'nizamiye_delete_user'     => 'nizamiye_manage',
			'nizamiye_save_class'      => 'nizamiye_manage',
			'nizamiye_delete_class'    => 'nizamiye_manage',
			'nizamiye_class_roster'    => 'nizamiye_teach',
			'nizamiye_save_attendance' => 'nizamiye_teach',
			'nizamiye_save_habit'      => 'nizamiye_teach',
			'nizamiye_delete_habit'    => 'nizamiye_teach',
			'nizamiye_save_habit_logs' => 'nizamiye_teach',
			'nizamiye_save_grades'     => 'nizamiye_teach',
			'nizamiye_delete_grade'    => 'nizamiye_teach',
			'nizamiye_save_settings'   => 'nizamiye_manage',
			'nizamiye_save_category'   => 'nizamiye_manage',
			'nizamiye_delete_category' => 'nizamiye_manage',
			'nizamiye_add_session'     => 'nizamiye_manage',
			'nizamiye_delete_session'  => 'nizamiye_manage',
			'nizamiye_import'          => 'nizamiye_manage',
			'nizamiye_grade_import'    => 'nizamiye_teach',
		);
		// Her handle_* metodu kendi nonce (check_admin_referer) ve yetki (current_user_can)
		// kontrolünü kendi içinde, ilk satır olarak yapar (bkz. ilgili metotlar).
		foreach ( array_keys( $actions ) as $action ) {
			// 'nizamiye_save_term' -> 'handle_save_term'
			add_action( 'admin_post_' . $action, array( __CLASS__, 'handle_' . substr( $action, strlen( 'nizamiye_' ) ) ) );
		}

		// CSV şablon indirme (GET, nonce URL ile).
		add_action( 'admin_post_nizamiye_import_template', array( __CLASS__, 'handle_import_template' ) );
		add_action( 'admin_post_nizamiye_grade_template', array( __CLASS__, 'handle_grade_template' ) );
		add_action( 'admin_post_nizamiye_export_report', array( __CLASS__, 'handle_export_report' ) );
		add_action( 'admin_post_nizamiye_print_report', array( __CLASS__, 'handle_print_report' ) );
		add_action( 'admin_post_nizamiye_print_report_bulk', array( __CLASS__, 'handle_print_report_bulk' ) );
	}

	/**
	 * Öğrenci karnesinin tek sayfalık, yazdırılabilir (PDF olarak kaydedilebilir) görünümü.
	 * WP admin arayüzü (menü/çubuk) olmadan bağımsız bir HTML belgesi döner.
	 */
	/** Bir öğrencinin karne HTML'ini (dompdf'e verilecek) üretir. */
	private static function render_report_html( $student_id, $term_id ) {
		ob_start();
		include NIZAMIYE_DIR . 'admin/views/print/student-report-print.php';
		return ob_get_clean();
	}

	/** Dosya adı için güvenli, öğrenciye özgü bir isim üretir (çakışmaya karşı id son ek). */
	private static function report_filename( $student, $suffix = '' ) {
		$name = nizamiye_student_name( $student );
		$name = remove_accents( $name );
		$name = preg_replace( '/[^A-Za-z0-9]+/', '_', $name );
		$name = trim( $name, '_' ) ?: 'Ogrenci';
		return $name . '-Karne' . ( $suffix ? '-' . $suffix : '' ) . '.pdf';
	}

	public static function handle_print_report() {
		// Nonce action'i ogrenciye ozeldir; id once okunur, ardindan kosulsuz dogrulanir.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- hemen asagida check_admin_referer() ile dogrulanir.
		$student_id = isset( $_GET['student'] ) ? (int) $_GET['student'] : 0;
		check_admin_referer( 'nizamiye_print_report_' . $student_id );
		if ( ! current_user_can( 'nizamiye_access' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		if ( ! $student_id || ! nizamiye_can_access_student( $student_id ) ) {
			wp_die( 'Bu öğrencinin karnesine erişim yetkiniz yok.' );
		}
		$term_id = isset( $_GET['nizamiye_term'] ) ? (int) $_GET['nizamiye_term'] : nizamiye_current_term_id();
		if ( ! $term_id ) {
			wp_die( 'Dönem bulunamadı.' );
		}
		$student = Nizamiye_Students::get( $student_id );
		if ( ! $student ) {
			wp_die( 'Öğrenci bulunamadı.' );
		}

		$html = self::render_report_html( $student_id, $term_id );
		Nizamiye_Pdf::stream( $html, self::report_filename( $student ) );
	}

	/**
	 * Seçilen birden çok öğrencinin karnesini AYRI AYRI PDF dosyaları olarak üretip
	 * tek bir ZIP arşivinde indirir. Karneler sayfasındaki toplu seçim formundan
	 * POST edilir; erişimi olmayan öğrenci id'leri sessizce elenir.
	 */
	public static function handle_print_report_bulk() {
		check_admin_referer( 'nizamiye_print_report_bulk', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		$term_id = isset( $_POST['nizamiye_term'] ) ? (int) $_POST['nizamiye_term'] : nizamiye_current_term_id();
		if ( ! $term_id ) {
			wp_die( 'Dönem bulunamadı.' );
		}

		$requested   = isset( $_POST['student_ids'] ) ? array_map( 'intval', (array) $_POST['student_ids'] ) : array();
		$student_ids = array_values( array_filter( array_unique( $requested ), 'nizamiye_can_access_student' ) );
		if ( ! $student_ids ) {
			wp_die( 'Seçili öğrenci bulunamadı ya da erişim yetkiniz yok.' );
		}
		// Sunucu yükünü sınırlamak için makul bir üst sınır.
		$student_ids = array_slice( $student_ids, 0, 200 );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( 'Sunucuda ZipArchive bulunamadığından toplu indirme kullanılamıyor.' );
		}

		$tmp_zip = wp_tempnam( 'sms-karneler' );
		$zip     = new ZipArchive();
		if ( true !== $zip->open( $tmp_zip, ZipArchive::OVERWRITE ) ) {
			wp_die( 'Geçici ZIP dosyası oluşturulamadı.' );
		}

		$used_names = array();
		foreach ( $student_ids as $student_id ) {
			$student = Nizamiye_Students::get( $student_id );
			if ( ! $student ) {
				continue;
			}
			$html = self::render_report_html( $student_id, $term_id );
			$pdf  = Nizamiye_Pdf::render( $html );
			if ( is_wp_error( $pdf ) ) {
				continue; // tek bir öğrencide sorun olsa da toplu indirme devam eder.
			}
			$filename = self::report_filename( $student );
			if ( isset( $used_names[ $filename ] ) ) {
				$filename = self::report_filename( $student, (string) $student_id );
			}
			$used_names[ $filename ] = true;
			$zip->addFromString( $filename, $pdf );
		}
		$zip->close();

		if ( ! filesize( $tmp_zip ) ) {
			wp_delete_file( $tmp_zip );
			wp_die( 'Hiçbir karne oluşturulamadı.' );
		}

		$term        = Nizamiye_Terms::get( $term_id );
		$zip_name    = 'Karneler-' . sanitize_file_name( $term ? $term->name : current_time( 'Y-m-d' ) ) . '.zip';

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $zip_name . '"' );
		header( 'Content-Length: ' . filesize( $tmp_zip ) );
		readfile( $tmp_zip ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData, WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- geçici, sunucuda üretilmiş ZIP dosyasını indirmeye gönderir; WP_Filesystem büyük dosyaları belleğe yükler.
		wp_delete_file( $tmp_zip );
		exit;
	}

	/**
	 * CSV çıktısı gönderir. Kişisel veri içerdiğinden nonce + yetki şarttır;
	 * formül enjeksiyonuna karşı tüm hücreler etkisizleştirilir.
	 */
	private static function send_csv( $filename, array $lines ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo "\xEF\xBB\xBF";
		foreach ( $lines as $row ) {
			$cells = array_map( function ( $v ) {
				$v = (string) $v;
				// CSV/Excel formül enjeksiyonunu engelle.
				if ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) {
					$v = "'" . $v;
				}
				return '"' . str_replace( '"', '""', $v ) . '"';
			}, $row );
			echo implode( ';', $cells ) . "\r\n"; // phpcs:ignore
		}
		exit;
	}

	/** Raporlar sayfasındaki analiz tablolarını CSV olarak dışa aktarır. */
	public static function handle_export_report() {
		check_admin_referer( 'nizamiye_export_report' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}

		$term_id = nizamiye_current_term_id();
		if ( ! $term_id ) {
			wp_die( 'Aktif dönem yok.' );
		}

		$rtype  = isset( $_GET['rtype'] ) ? sanitize_key( $_GET['rtype'] ) : 'yoklama';
		$group  = isset( $_GET['group'] ) && 'sinif' === $_GET['group'] ? 'sinif' : 'ogrenci';
		$grade  = isset( $_GET['grade'] ) ? (int) $_GET['grade'] : 0;
		$metric = isset( $_GET['metric'] ) ? sanitize_key( $_GET['metric'] ) : 'rate';
		if ( ! in_array( $metric, array( 'rate', 'present', 'absent', 'late', 'excused' ), true ) ) {
			$metric = 'rate';
		}
		$cat_id  = isset( $_GET['cat'] ) ? (int) $_GET['cat'] : 0;
		// Bu işleyici zaten yukarıda kendi (nizamiye_export_report) nonce'uyla doğrulandı.
		$dates   = nizamiye_resolve_report_dates( '', false );
		$from    = $dates['from'];
		$to      = $dates['to'];

		// Öğretmenler yalnızca sorumlu oldukları öğrencilerin verisini dışa aktarabilir.
		$student_ids = nizamiye_is_teacher() ? nizamiye_teacher_student_ids() : null;

		$term          = Nizamiye_Terms::get( $term_id );
		$metric_labels = array( 'rate' => 'Katılım Oranı' ) + nizamiye_attendance_statuses();
		$row_label     = 'sinif' === $group ? 'Sınıf' : 'Öğrenci';
		$lines         = array();

		$empty_c = array( 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0, 'rate' => null );
		$calc_c  = function ( $c ) {
			if ( $c['total'] > 0 ) {
				$c['rate'] = round( ( $c['present'] + 0.5 * $c['late'] ) / $c['total'] * 100 );
			}
			return $c;
		};
		$fmt_att = function ( $cell ) use ( $metric ) {
			if ( ! $cell || $cell['total'] < 1 ) {
				return '';
			}
			$v = 'rate' === $metric ? (int) $cell['rate'] : (int) round( $cell[ $metric ] / $cell['total'] * 100 );
			$n = 'rate' === $metric ? $cell['present'] : $cell[ $metric ];
			return $v . '% (' . $n . '/' . $cell['total'] . ')';
		};

		if ( 'yoklama' === $rtype ) {
			$category = Nizamiye_Attendance_Types::get_category( $cat_id );
			if ( ! $category ) {
				wp_die( 'Geçersiz kategori.' );
			}
			$matrix   = Nizamiye_Reports::attendance_matrix( $term_id, $cat_id, $from, $to, 'sinif' === $group ? 0 : $grade, $student_ids );
			$sessions = $matrix['sessions'];

			// Oturum odağı: geçerli tek bir vakit seçildiyse tam durum kırılımı dışa aktarılır.
			$sess_id       = isset( $_GET['rsession'] ) ? (int) $_GET['rsession'] : 0;
			$focus_session = null;
			foreach ( $sessions as $s ) {
				if ( (int) $s->id === $sess_id ) {
					$focus_session = $s;
					break;
				}
			}

			if ( $focus_session ) {
				$fmt_focus = function ( $c ) {
					if ( ! $c || $c['total'] < 1 ) {
						return array( '', '', '', '', '' );
					}
					return array( (int) $c['present'], (int) $c['absent'], (int) $c['late'], (int) $c['excused'], (int) $c['rate'] . '%' );
				};

				$lines[] = array( $category->name . ' — ' . $focus_session->name . ' vakti — ' . $from . ' / ' . $to . ' — ' . ( $term ? $term->name : '' ) );
				$header  = array( $row_label );
				if ( 'ogrenci' === $group ) {
					$header[] = 'Sınıf';
				}
				$lines[] = array_merge( $header, array( 'Geldi', 'Gelmedi', 'Geç', 'İzinli', 'Katılım %' ) );

				if ( 'sinif' === $group ) {
					$agg = array();
					foreach ( $matrix['rows'] as $row ) {
						$g = (int) ( $row['student']->grade_level ?? 0 );
						if ( ! isset( $agg[ $g ] ) ) {
							$agg[ $g ] = array( 'count' => 0, 'cell' => $empty_c );
						}
						$agg[ $g ]['count']++;
						foreach ( array( 'present', 'absent', 'late', 'excused', 'total' ) as $k ) {
							$agg[ $g ]['cell'][ $k ] += $row['cells'][ $sess_id ][ $k ];
						}
					}
					ksort( $agg );
					foreach ( $agg as $g => $gr ) {
						$lines[] = array_merge(
							array( nizamiye_grade_label( $g ) . ' (' . $gr['count'] . ' öğrenci)' ),
							$fmt_focus( $calc_c( $gr['cell'] ) )
						);
					}
				} else {
					foreach ( $matrix['rows'] as $row ) {
						$st      = $row['student'];
						$lines[] = array_merge(
							array( nizamiye_student_name( $st ), isset( $st->grade_level ) ? nizamiye_grade_label( $st->grade_level ) : '' ),
							$fmt_focus( $row['cells'][ $sess_id ] )
						);
					}
					$lines[] = array_merge( array( 'TOPLU (tüm liste)', '' ), $fmt_focus( $matrix['totals'][ $sess_id ] ?? null ) );
				}
			} else {
				$lines[] = array( $category->name . ' Yoklaması — ' . $metric_labels[ $metric ] . ' — ' . $from . ' / ' . $to . ' — ' . ( $term ? $term->name : '' ) );
				$header  = array( $row_label );
				if ( 'ogrenci' === $group ) {
					$header[] = 'Sınıf';
				}
				foreach ( $sessions as $s ) {
					$header[] = $s->name;
				}
				$header[] = 'Genel';
				$lines[]  = $header;

				if ( 'sinif' === $group ) {
					$agg = array();
					foreach ( $matrix['rows'] as $row ) {
						$g = (int) ( $row['student']->grade_level ?? 0 );
						if ( ! isset( $agg[ $g ] ) ) {
							$agg[ $g ] = array( 'count' => 0, 'cells' => array(), 'overall' => $empty_c );
						}
						$agg[ $g ]['count']++;
						foreach ( $sessions as $s ) {
							$sid = (int) $s->id;
							if ( ! isset( $agg[ $g ]['cells'][ $sid ] ) ) {
								$agg[ $g ]['cells'][ $sid ] = $empty_c;
							}
							foreach ( array( 'present', 'absent', 'late', 'excused', 'total' ) as $k ) {
								$agg[ $g ]['cells'][ $sid ][ $k ] += $row['cells'][ $sid ][ $k ];
								$agg[ $g ]['overall'][ $k ]       += $row['cells'][ $sid ][ $k ];
							}
						}
					}
					ksort( $agg );
					foreach ( $agg as $g => $gr ) {
						$line = array( nizamiye_grade_label( $g ) . ' (' . $gr['count'] . ' öğrenci)' );
						foreach ( $sessions as $s ) {
							$line[] = $fmt_att( $calc_c( $gr['cells'][ (int) $s->id ] ) );
						}
						$line[]  = $fmt_att( $calc_c( $gr['overall'] ) );
						$lines[] = $line;
					}
				} else {
					foreach ( $matrix['rows'] as $row ) {
						$st   = $row['student'];
						$line = array( nizamiye_student_name( $st ), isset( $st->grade_level ) ? nizamiye_grade_label( $st->grade_level ) : '' );
						foreach ( $sessions as $s ) {
							$line[] = $fmt_att( $row['cells'][ (int) $s->id ] );
						}
						$line[]  = $fmt_att( $row['overall'] );
						$lines[] = $line;
					}
					$total_line = array( 'TOPLU (tüm liste)', '' );
					foreach ( $sessions as $s ) {
						$total_line[] = $fmt_att( $matrix['totals'][ (int) $s->id ] ?? null );
					}
					$total_line[] = $fmt_att( $matrix['totals']['overall'] ?? null );
					$lines[]      = $total_line;
				}
			}
		} elseif ( 'aliskanlik' === $rtype || 'not' === $rtype ) {
			$is_habit = 'aliskanlik' === $rtype;
			$matrix   = $is_habit
				? Nizamiye_Reports::habit_matrix( $term_id, 'sinif' === $group ? 0 : $grade, $student_ids )
				: Nizamiye_Reports::grade_matrix( $term_id, 'sinif' === $group ? 0 : $grade, $student_ids );
			$cols = $is_habit ? $matrix['habits'] : $matrix['classes'];

			$lines[] = array( ( $is_habit ? 'Alışkanlık Tamamlama' : 'Not Ortalamaları' ) . ' — ' . ( $term ? $term->name : '' ) );
			$header  = array( $row_label );
			if ( 'ogrenci' === $group ) {
				$header[] = 'Sınıf';
			}
			foreach ( $cols as $c ) {
				$header[] = $c->name;
			}
			$header[] = 'Genel';
			$lines[]  = $header;

			$fmt = function ( $cell ) use ( $is_habit ) {
				if ( ! $cell ) {
					return '';
				}
				return (int) $cell['rate'] . '% (' . ( $is_habit ? $cell['logs'] . ' kayıt' : $cell['exams'] . ' sınav' ) . ')';
			};

			if ( 'sinif' === $group ) {
				$agg = array();
				foreach ( $matrix['rows'] as $row ) {
					$g = (int) ( $row['student']->grade_level ?? 0 );
					if ( ! isset( $agg[ $g ] ) ) {
						$agg[ $g ] = array( 'count' => 0, 'sum' => array(), 'cnt' => array() );
					}
					$agg[ $g ]['count']++;
					foreach ( $cols as $c ) {
						$cell = $row['cells'][ (int) $c->id ];
						if ( $cell ) {
							$agg[ $g ]['sum'][ (int) $c->id ] = ( $agg[ $g ]['sum'][ (int) $c->id ] ?? 0 ) + $cell['rate'];
							$agg[ $g ]['cnt'][ (int) $c->id ] = ( $agg[ $g ]['cnt'][ (int) $c->id ] ?? 0 ) + 1;
						}
					}
				}
				ksort( $agg );
				foreach ( $agg as $g => $gr ) {
					$line  = array( nizamiye_grade_label( $g ) . ' (' . $gr['count'] . ' öğrenci)' );
					$g_sum = 0;
					$g_cnt = 0;
					foreach ( $cols as $c ) {
						$cid = (int) $c->id;
						if ( isset( $gr['cnt'][ $cid ] ) ) {
							$v      = (int) round( $gr['sum'][ $cid ] / $gr['cnt'][ $cid ] );
							$line[] = $v . '%';
							$g_sum += $v;
							$g_cnt++;
						} else {
							$line[] = '';
						}
					}
					$line[]  = $g_cnt ? (int) round( $g_sum / $g_cnt ) . '%' : '';
					$lines[] = $line;
				}
			} else {
				foreach ( $matrix['rows'] as $row ) {
					$st   = $row['student'];
					$line = array( nizamiye_student_name( $st ), isset( $st->grade_level ) ? nizamiye_grade_label( $st->grade_level ) : '' );
					foreach ( $cols as $c ) {
						$line[] = $fmt( $row['cells'][ (int) $c->id ] );
					}
					$line[]  = null !== $row['overall'] ? $row['overall'] . '%' : '';
					$lines[] = $line;
				}
				$total_line = array( 'TOPLU (tüm liste)', '' );
				foreach ( $cols as $c ) {
					$v            = $matrix['totals'][ (int) $c->id ];
					$total_line[] = null !== $v ? $v . '%' : '';
				}
				$total_line[] = '';
				$lines[]      = $total_line;
			}
		} else { // genel
			if ( 'sinif' === $group ) {
				$lines[] = array( 'Sınıf Bazında Genel Özet — ' . ( $term ? $term->name : '' ) );
				$lines[] = array( 'Sınıf', 'Öğrenci Sayısı', 'Devam %', 'Alışkanlık %', 'Not Ort. %' );
				foreach ( Nizamiye_Reports::grade_level_summary( $term_id, $student_ids ) as $row ) {
					$lines[] = array(
						nizamiye_grade_label( $row['grade'] ),
						$row['count'],
						null !== $row['att'] ? $row['att'] : '',
						null !== $row['habit'] ? $row['habit'] : '',
						null !== $row['grade_avg'] ? $row['grade_avg'] : '',
					);
				}
			} else {
				$scores = Nizamiye_Reports::student_scores( $term_id, $student_ids );
				if ( $grade ) {
					$scores = array_values( array_filter( $scores, function ( $r ) use ( $grade ) {
						return (int) ( $r['student']->grade_level ?? 0 ) === $grade;
					} ) );
				}
				$lines[] = array( 'Genel Başarı Sıralaması — ' . ( $term ? $term->name : '' ) );
				$lines[] = array( 'Sıra', 'Öğrenci', 'Sınıf', 'Devam %', 'Alışkanlık %', 'Not Ort. %', 'Bileşik Skor' );
				foreach ( $scores as $i => $row ) {
					$s       = $row['student'];
					$lines[] = array(
						$i + 1,
						nizamiye_student_name( $s ),
						isset( $s->grade_level ) ? nizamiye_grade_label( $s->grade_level ) : '',
						null !== $row['attendance'] ? $row['attendance'] : '',
						null !== $row['habit'] ? $row['habit'] : '',
						null !== $row['grade'] ? $row['grade'] : '',
						$row['score'],
					);
				}
			}
		}

		self::send_csv( 'rapor-' . $rtype . '-' . $group . '-' . current_time( 'Y-m-d' ) . '.csv', $lines );
	}

	/** Doğrulanmış action adı; post() içinde nonce'un yeniden kontrolü için saklanır. */
	private static $verified_action = '';

	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
	// Aşağıdaki handle_* metotlarının HER BİRİ, ilk satırında check_admin_referer() ile
	// nonce'unu ve current_user_can() ile yetkisini kendi içinde doğrular; bu noktadan
	// sonraki $_POST okumaları doğrulanmış istek bağlamındadır.

	private static function back( $msg = '', $err = '', $override_url = '' ) {
		$url = $override_url;
		if ( ! $url && isset( $_POST['_nizamiye_back'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['_nizamiye_back'] ) );
		}
		if ( ! $url ) {
			$url = admin_url( 'admin.php?page=nizamiye-dashboard' );
		}
		$url = remove_query_arg( array( 'nizamiye_msg', 'nizamiye_err' ), $url );
		if ( $msg ) {
			$url = add_query_arg( 'nizamiye_msg', rawurlencode( $msg ), $url );
		}
		if ( $err ) {
			$url = add_query_arg( 'nizamiye_err', rawurlencode( $err ), $url );
		}
		$url = nizamiye_view_nonce_url_raw( $url );
		wp_safe_redirect( $url );
		exit;
	}

	private static function post( $key, $default = '' ) {
		// Ek doğrulama: bu yardımcı yalnızca nonce'u check_admin_referer() ile zaten
		// doğrulanmış bir handle_* metodunun içinden çağrılabilir. Kontroller kasıtlı
		// olarak ayrı ayrı yazılmıştır; nonce doğrulaması hiçbir koşula bağlı değildir.
		if ( ! self::$verified_action ) {
			wp_die( 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.' );
		}
		check_admin_referer( self::$verified_action, '_nizamiye_nonce' );
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;
	}

	/* ---------- Dönemler ---------- */

	public static function handle_save_term() {
		check_admin_referer( 'nizamiye_save_term', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_term';
		$name = self::post( 'name' );
		if ( ! $name ) {
			self::back( '', 'Dönem adı gerekli.' );
		}
		Nizamiye_Terms::create( $name, self::post( 'start_date' ), self::post( 'end_date' ), ! empty( $_POST['activate'] ) );
		self::back( 'Dönem oluşturuldu.' );
	}

	public static function handle_activate_term() {
		check_admin_referer( 'nizamiye_activate_term', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_activate_term';
		Nizamiye_Terms::set_active( (int) self::post( 'term_id' ) );
		self::back( 'Aktif dönem değiştirildi.' );
	}

	public static function handle_delete_term() {
		check_admin_referer( 'nizamiye_delete_term', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_delete_term';
		$result = Nizamiye_Terms::delete( (int) self::post( 'term_id' ) );
		if ( is_wp_error( $result ) ) {
			self::back( '', $result->get_error_message() );
		}
		self::back( 'Dönem silindi.' );
	}

	/** Yeni dönem + otomatik terfi/mezuniyet. */
	public static function handle_open_term() {
		check_admin_referer( 'nizamiye_open_term', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_open_term';
		$name = self::post( 'name' );
		if ( ! $name ) {
			self::back( '', 'Dönem adı gerekli.' );
		}
		$stats = Nizamiye_Terms::open_new_term(
			$name,
			self::post( 'start_date' ),
			self::post( 'end_date' ),
			! empty( $_POST['auto_promote'] )
		);
		$msg = sprintf(
			'"%s" dönemi açıldı. %d öğrenci bir üst sınıfa aktarıldı, %d öğrenci mezun olarak arşivlendi.',
			$name, $stats['promoted'], $stats['graduated']
		);
		self::back( $msg, '', admin_url( 'admin.php?page=nizamiye-terms' ) );
	}

	/* ---------- Öğrenciler ---------- */

	public static function handle_save_student() {
		check_admin_referer( 'nizamiye_save_student', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_student';
		$id      = (int) self::post( 'student_id' );
		$term_id = (int) self::post( 'term_id' );
		$grade   = (int) self::post( 'grade_level' );

		if ( ! self::post( 'first_name' ) || ! self::post( 'last_name' ) ) {
			self::back( '', 'Ad ve soyad zorunludur.' );
		}

		$data = array(
			'first_name'     => self::post( 'first_name' ),
			'last_name'      => self::post( 'last_name' ),
			'birth_date'     => self::post( 'birth_date' ),
			'school'         => self::post( 'school' ),
			'student_no'     => self::post( 'student_no' ),
			'parent_user_id' => (int) self::post( 'parent_user_id' ),
			'status'         => self::post( 'status', 'active' ),
			'notes'          => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
		);

		// Mevcut kullanıcı bağını koru.
		if ( $id ) {
			$existing        = Nizamiye_Students::get( $id );
			$data['user_id'] = $existing ? (int) $existing->user_id : 0;
		}

		// İsteğe bağlı öğrenci giriş hesabı oluştur.
		$username = self::post( 'account_username' );
		if ( $username && empty( $data['user_id'] ) ) {
			$email = sanitize_email( self::post( 'account_email' ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parola değerine kasıtlı olarak sanitize_text_field uygulanmaz (geçerli karakterleri bozar); yalnızca wp_unslash yeterlidir.
			$pass  = isset( $_POST['account_password'] ) ? (string) wp_unslash( $_POST['account_password'] ) : '';
			if ( ! $pass ) {
				$pass = wp_generate_password( 12 );
			}
			$user_id = wp_insert_user( array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $data['first_name'] . ' ' . $data['last_name'],
				'first_name'   => $data['first_name'],
				'last_name'    => $data['last_name'],
				'role'         => 'nizamiye_student',
			) );
			if ( is_wp_error( $user_id ) ) {
				self::back( '', 'Öğrenci kaydedilemedi: ' . $user_id->get_error_message() );
			}
			$data['user_id'] = (int) $user_id;
		}

		$id = Nizamiye_Students::save( $data, $term_id, $grade, $id );
		self::back( 'Öğrenci kaydedildi.', '', nizamiye_view_nonce_url_raw( admin_url( 'admin.php?page=nizamiye-students&view=edit&student=' . $id ) ) );
	}

	public static function handle_delete_student() {
		check_admin_referer( 'nizamiye_delete_student', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_delete_student';
		Nizamiye_Students::delete( (int) self::post( 'student_id' ) );
		self::back( 'Öğrenci ve bağlı tüm kayıtları silindi.', '', admin_url( 'admin.php?page=nizamiye-students' ) );
	}

	/* ---------- Kullanıcılar (öğretmen/veli) ---------- */

	public static function handle_save_user() {
		check_admin_referer( 'nizamiye_save_user', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_user';
		$role = self::post( 'nizamiye_role' );
		if ( ! in_array( $role, array( 'nizamiye_teacher', 'nizamiye_parent' ), true ) ) {
			self::back( '', 'Geçersiz rol.' );
		}
		$user_id = (int) self::post( 'user_id' );
		$name    = self::post( 'display_name' );
		$email   = sanitize_email( self::post( 'email' ) );

		if ( $user_id ) {
			$result = wp_update_user( array(
				'ID'           => $user_id,
				'display_name' => $name,
				'user_email'   => $email,
			) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parola değerine kasıtlı olarak sanitize_text_field uygulanmaz (geçerli karakterleri bozar); yalnızca wp_unslash yeterlidir.
			$pass = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
			if ( ! is_wp_error( $result ) && $pass ) {
				wp_set_password( $pass, $user_id );
			}
		} else {
			$username = self::post( 'username' );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parola değerine kasıtlı olarak sanitize_text_field uygulanmaz (geçerli karakterleri bozar); yalnızca wp_unslash yeterlidir.
			$pass     = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
			if ( ! $username || ! $email ) {
				self::back( '', 'Kullanıcı adı ve e-posta zorunludur.' );
			}
			if ( ! $pass ) {
				$pass = wp_generate_password( 12 );
			}
			$result = wp_insert_user( array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name ?: $username,
				'role'         => $role,
			) );
		}

		if ( is_wp_error( $result ) ) {
			self::back( '', 'Kayıt başarısız: ' . $result->get_error_message() );
		}

		// Öğretmen için sınıf öğretmeni bayrağı + sorumlu sınıf seviyeleri.
		if ( 'nizamiye_teacher' === $role ) {
			$target = $user_id ?: (int) $result;
			if ( ! empty( $_POST['is_class_teacher'] ) ) {
				update_user_meta( $target, 'nizamiye_is_class_teacher', 1 );
				$grades = isset( $_POST['ct_grades'] ) ? array_map( 'intval', (array) $_POST['ct_grades'] ) : array();
				update_user_meta( $target, 'nizamiye_class_teacher_grades', array_values( array_filter( $grades ) ) );
			} else {
				delete_user_meta( $target, 'nizamiye_is_class_teacher' );
				delete_user_meta( $target, 'nizamiye_class_teacher_grades' );
			}
		}

		self::back( 'nizamiye_teacher' === $role ? 'Öğretmen kaydedildi.' : 'Veli kaydedildi.' );
	}

	public static function handle_delete_user() {
		check_admin_referer( 'nizamiye_delete_user', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_delete_user';
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$user_id = (int) self::post( 'user_id' );
		$user    = get_userdata( $user_id );
		if ( ! $user || user_can( $user, 'manage_options' ) ) {
			self::back( '', 'Bu kullanıcı silinemez.' );
		}
		global $wpdb;
		// Bağları temizle: velisi olduğu öğrenciler ve öğretmeni olduğu derslikler.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- özel eklenti tablosu, $wpdb->update() kendi kaçış işlemini yapar.
		$wpdb->update( $wpdb->prefix . 'nizamiye_students', array( 'parent_user_id' => null ), array( 'parent_user_id' => $user_id ) );
		$wpdb->update( $wpdb->prefix . 'nizamiye_students', array( 'user_id' => null ), array( 'user_id' => $user_id ) );
		$wpdb->update( $wpdb->prefix . 'nizamiye_classes', array( 'teacher_id' => null ), array( 'teacher_id' => $user_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		wp_delete_user( $user_id );
		self::back( 'Hesap silindi.' );
	}

	/* ---------- Derslikler ---------- */

	public static function handle_save_class() {
		check_admin_referer( 'nizamiye_save_class', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_class';
		$id   = (int) self::post( 'class_id' );
		$name = self::post( 'name' );
		if ( ! $name ) {
			self::back( '', 'Derslik adı gerekli.' );
		}
		$id = Nizamiye_Classes::save( array(
			'term_id'     => (int) self::post( 'term_id' ),
			'name'        => $name,
			'subject'     => self::post( 'subject' ),
			'grade_level' => (int) self::post( 'grade_level' ),
			'teacher_id'  => (int) self::post( 'teacher_id' ),
		), $id );
		self::back( 'Derslik kaydedildi.', '', nizamiye_view_nonce_url_raw( admin_url( 'admin.php?page=nizamiye-classes&view=edit&class_id=' . $id ) ) );
	}

	public static function handle_delete_class() {
		check_admin_referer( 'nizamiye_delete_class', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_delete_class';
		Nizamiye_Classes::delete( (int) self::post( 'class_id' ) );
		self::back( 'Derslik silindi.', '', admin_url( 'admin.php?page=nizamiye-classes' ) );
	}

	public static function handle_class_roster() {
		check_admin_referer( 'nizamiye_class_roster', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_class_roster';
		$class_id = (int) self::post( 'class_id' );
		if ( ! nizamiye_can_manage_class( $class_id ) ) {
			wp_die( 'Bu dersliği yönetme yetkiniz yok.' );
		}
		$ids = isset( $_POST['student_ids'] ) ? array_map( 'intval', (array) $_POST['student_ids'] ) : array();
		Nizamiye_Classes::set_students( $class_id, $ids );
		self::back( 'Derslik kadrosu güncellendi (' . count( $ids ) . ' öğrenci).' );
	}

	/* ---------- Yoklama ---------- */

	public static function handle_save_attendance() {
		check_admin_referer( 'nizamiye_save_attendance', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_attendance';
		$category_id = (int) self::post( 'category_id' );
		$session_id  = (int) self::post( 'session_id' );
		$class_id    = (int) self::post( 'class_id' );
		$date        = self::post( 'att_date' );

		$category = Nizamiye_Attendance_Types::get_category( $category_id );
		$session  = Nizamiye_Attendance_Types::get_session( $session_id );
		if ( ! $category || ! $session || (int) $session->category_id !== $category_id ) {
			self::back( '', 'Geçersiz yoklama türü.' );
		}
		if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			self::back( '', 'Geçerli bir tarih seçin.' );
		}

		// Yetki + öğrenci kapsamı, kategori türüne göre belirlenir.
		if ( 'class' === $category->scope ) {
			if ( ! nizamiye_can_manage_class( $class_id ) ) {
				wp_die( 'Bu dersliğin yoklamasını alma yetkiniz yok.' );
			}
			$class   = Nizamiye_Classes::get( $class_id );
			$term_id = $class ? (int) $class->term_id : nizamiye_current_term_id();
			$allowed = Nizamiye_Classes::student_ids( $class_id );
		} else {
			if ( ! nizamiye_can_take_general_attendance() ) {
				wp_die( 'Genel yoklama alma yetkiniz yok.' );
			}
			$class_id = 0;
			$term_id  = nizamiye_current_term_id();
			$allowed  = nizamiye_general_attendance_student_ids( $term_id, 0, $category_id );
		}
		$allowed = array_map( 'intval', $allowed );

		// Ham diziler; her öğe aşağıdaki döngüde sanitize_key()/sanitize_text_field(wp_unslash()) ile işlenir.
		$statuses = isset( $_POST['att_status'] ) ? (array) $_POST['att_status'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$notes    = isset( $_POST['att_note'] ) ? (array) $_POST['att_note'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$entries  = array();
		foreach ( $statuses as $student_id => $status ) {
			$student_id = (int) $student_id;
			if ( ! in_array( $student_id, $allowed, true ) ) {
				continue; // yetki dışı öğrenci gönderimini yok say.
			}
			$entries[ $student_id ] = array(
				'status' => sanitize_key( $status ),
				'note'   => isset( $notes[ $student_id ] ) ? sanitize_text_field( wp_unslash( $notes[ $student_id ] ) ) : '',
			);
		}
		Nizamiye_Attendance::save_sheet( $term_id, $category_id, $session_id, $class_id, $date, $entries, get_current_user_id() );
		self::back( $category->name . ' yoklaması kaydedildi (' . count( $entries ) . ' öğrenci).' );
	}

	/* ---------- Alışkanlıklar ---------- */

	public static function handle_save_habit() {
		check_admin_referer( 'nizamiye_save_habit', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_habit';
		$id = (int) self::post( 'habit_id' );

		if ( $id && ! self::can_manage_habit( $id ) ) {
			wp_die( 'Bu alışkanlığı düzenleme yetkiniz yok.' );
		}
		if ( ! self::post( 'name' ) ) {
			self::back( '', 'Alışkanlık adı gerekli.' );
		}

		$id = Nizamiye_Habits::save( array(
			'term_id'     => (int) self::post( 'term_id' ),
			'name'        => self::post( 'name' ),
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'track_type'  => self::post( 'track_type', 'binary' ),
			'scale_max'   => (int) self::post( 'scale_max', 5 ),
		), $id );

		$ids = isset( $_POST['student_ids'] ) ? array_map( 'intval', (array) $_POST['student_ids'] ) : array();
		// Öğretmen yalnızca kendi öğrencilerini ekleyebilir; diğer atamalar korunur.
		if ( nizamiye_is_teacher() ) {
			$allowed  = nizamiye_teacher_student_ids();
			$ids      = array_intersect( $ids, $allowed );
			$existing = Nizamiye_Habits::student_ids( $id );
			$ids      = array_merge( array_diff( $existing, $allowed ), $ids );
		}
		Nizamiye_Habits::set_students( $id, $ids );

		self::back( 'Alışkanlık kaydedildi.', '', nizamiye_view_nonce_url_raw( admin_url( 'admin.php?page=nizamiye-habits&view=edit&habit_id=' . $id ) ) );
	}

	public static function handle_delete_habit() {
		check_admin_referer( 'nizamiye_delete_habit', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_delete_habit';
		$id = (int) self::post( 'habit_id' );
		if ( ! self::can_manage_habit( $id ) ) {
			wp_die( 'Bu alışkanlığı silme yetkiniz yok.' );
		}
		Nizamiye_Habits::delete( $id );
		self::back( 'Alışkanlık silindi.', '', admin_url( 'admin.php?page=nizamiye-habits' ) );
	}

	private static function can_manage_habit( $habit_id ) {
		if ( nizamiye_is_manager() ) {
			return true;
		}
		$habit = Nizamiye_Habits::get( $habit_id );
		return $habit && (int) $habit->created_by === get_current_user_id();
	}

	public static function handle_save_habit_logs() {
		check_admin_referer( 'nizamiye_save_habit_logs', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_habit_logs';
		$habit_id = (int) self::post( 'habit_id' );
		$date     = self::post( 'log_date' );
		$habit    = Nizamiye_Habits::get( $habit_id );

		if ( ! $habit ) {
			self::back( '', 'Alışkanlık bulunamadı.' );
		}
		if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			self::back( '', 'Geçerli bir tarih seçin.' );
		}

		$assigned = Nizamiye_Habits::student_ids( $habit_id );
		// Öğretmen yalnızca kendi öğrencilerinin kaydını doldurabilir (oluşturansa hepsini).
		if ( nizamiye_is_teacher() && (int) $habit->created_by !== get_current_user_id() ) {
			$assigned = array_intersect( $assigned, nizamiye_teacher_student_ids() );
		}

		// Ham diziler; değer (int) cast ile, not aşağıda sanitize_text_field(wp_unslash()) ile işlenir.
		$values  = isset( $_POST['log_value'] ) ? (array) $_POST['log_value'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$notes   = isset( $_POST['log_note'] ) ? (array) $_POST['log_note'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$entries = array();
		foreach ( $assigned as $student_id ) {
			$raw = isset( $values[ $student_id ] ) ? trim( (string) $values[ $student_id ] ) : '';
			$entries[ $student_id ] = array(
				'filled' => '' !== $raw,
				'value'  => (int) $raw,
				'note'   => isset( $notes[ $student_id ] ) ? sanitize_text_field( wp_unslash( $notes[ $student_id ] ) ) : '',
			);
		}
		Nizamiye_Habits::save_logs( $habit_id, $date, $entries, get_current_user_id() );
		self::back( 'Alışkanlık takibi kaydedildi.' );
	}

	/* ---------- Notlar ---------- */

	public static function handle_save_grades() {
		check_admin_referer( 'nizamiye_save_grades', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_grades';
		$class_id = (int) self::post( 'class_id' );
		if ( ! nizamiye_can_manage_class( $class_id ) ) {
			wp_die( 'Bu dersliğe not girme yetkiniz yok.' );
		}
		$title = self::post( 'title' );
		if ( ! $title ) {
			self::back( '', 'Sınav adı gerekli.' );
		}
		$scores = isset( $_POST['score'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['score'] ) ) : array();
		$count  = Nizamiye_Grades::add_exam(
			$class_id,
			$title,
			self::post( 'exam_type' ),
			self::post( 'exam_date' ),
			(float) self::post( 'max_score', '100' ),
			$scores,
			get_current_user_id()
		);
		self::back( $count . ' öğrenci için not kaydedildi.' );
	}

	public static function handle_delete_grade() {
		check_admin_referer( 'nizamiye_delete_grade', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_delete_grade';
		$grade = Nizamiye_Grades::get( (int) self::post( 'grade_id' ) );
		if ( ! $grade || ! nizamiye_can_manage_class( (int) $grade->class_id ) ) {
			wp_die( 'Bu notu silme yetkiniz yok.' );
		}
		Nizamiye_Grades::delete( (int) $grade->id );
		self::back( 'Not silindi.' );
	}

	/* ---------- Ayarlar ---------- */

	public static function handle_save_settings() {
		check_admin_referer( 'nizamiye_save_settings', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_settings';
		nizamiye_update_settings( array(
			'school_name' => self::post( 'school_name' ),
			'final_grade' => max( 1, (int) self::post( 'final_grade', '8' ) ),
			'min_grade'   => max( 1, (int) self::post( 'min_grade', '1' ) ),
			'max_grade'   => max( 1, (int) self::post( 'max_grade', '12' ) ),
		) );
		self::back( 'Ayarlar kaydedildi.' );
	}

	/* ---------- Yoklama türleri (kategori/oturum) ---------- */

	public static function handle_save_category() {
		check_admin_referer( 'nizamiye_save_category', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_save_category';
		$id   = (int) self::post( 'category_id' );
		$name = self::post( 'name' );
		$icon = self::post( 'icon' );
		if ( ! $name ) {
			self::back( '', 'Kategori adı gerekli.' );
		}
		if ( $id ) {
			Nizamiye_Attendance_Types::update_category( $id, $name, $icon );
			$cat = Nizamiye_Attendance_Types::get_category( $id );
			if ( $cat && 'general' === $cat->scope ) {
				$grades = isset( $_POST['cat_grades'] ) ? array_map( 'intval', (array) $_POST['cat_grades'] ) : array();
				Nizamiye_Attendance_Types::set_grade_levels( $id, $grades );
			}
			self::back( 'Kategori güncellendi.' );
		}
		$scope   = self::post( 'scope', 'general' );
		$cat_id  = Nizamiye_Attendance_Types::create_category( $name, $scope, $icon );
		$session = self::post( 'first_session' );
		if ( $session ) {
			Nizamiye_Attendance_Types::add_session( $cat_id, $session );
		} else {
			Nizamiye_Attendance_Types::add_session( $cat_id, $name );
		}
		self::back( 'Yoklama kategorisi oluşturuldu.' );
	}

	public static function handle_delete_category() {
		check_admin_referer( 'nizamiye_delete_category', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_delete_category';
		$result = Nizamiye_Attendance_Types::delete_category( (int) self::post( 'category_id' ) );
		if ( is_wp_error( $result ) ) {
			self::back( '', $result->get_error_message() );
		}
		self::back( 'Kategori silindi.' );
	}

	public static function handle_add_session() {
		check_admin_referer( 'nizamiye_add_session', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_add_session';
		$cat_id = (int) self::post( 'category_id' );
		$name   = self::post( 'name' );
		if ( ! $name || ! Nizamiye_Attendance_Types::get_category( $cat_id ) ) {
			self::back( '', 'Oturum adı gerekli.' );
		}
		Nizamiye_Attendance_Types::add_session( $cat_id, $name );
		self::back( 'Oturum eklendi.' );
	}

	public static function handle_delete_session() {
		check_admin_referer( 'nizamiye_delete_session', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_delete_session';
		$result = Nizamiye_Attendance_Types::delete_session( (int) self::post( 'session_id' ) );
		if ( is_wp_error( $result ) ) {
			self::back( '', $result->get_error_message() );
		}
		self::back( 'Oturum silindi.' );
	}

	/* ---------- İçe aktarma ---------- */

	public static function handle_import() {
		check_admin_referer( 'nizamiye_import', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_import';
		$type    = self::post( 'import_type' );
		$term_id = (int) self::post( 'term_id' );

		if ( ! in_array( $type, array( 'students', 'teachers', 'parents' ), true ) ) {
			self::back( '', 'Geçersiz içe aktarma türü.' );
		}
		if ( empty( $_FILES['import_file']['name'] ) || ! empty( $_FILES['import_file']['error'] ) ) {
			self::back( '', 'Lütfen bir .xlsx veya .csv dosyası seçin.' );
		}

		$tmp  = $_FILES['import_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- yukarıdaki self::back()/exit kontrolü nedeniyle 'error' boşsa (UPLOAD_ERR_OK) tmp_name PHP tarafından garanti doldurulur.
		$ext  = strtolower( pathinfo( sanitize_file_name( wp_unslash( $_FILES['import_file']['name'] ) ), PATHINFO_EXTENSION ) );
		$rows = Nizamiye_Import::read_file( $tmp, $ext );
		if ( is_wp_error( $rows ) ) {
			self::back( '', $rows->get_error_message() );
		}

		if ( 'students' === $type ) {
			if ( ! $term_id ) {
				self::back( '', 'Öğrenci aktarımı için aktif bir dönem gerekli.' );
			}
			$res = Nizamiye_Import::import_students( $rows, $term_id );
		} else {
			$role = 'teachers' === $type ? 'nizamiye_teacher' : 'nizamiye_parent';
			$res  = Nizamiye_Import::import_users( $rows, $role );
		}

		$msg = $res['created'] . ' kayıt içe aktarıldı.';
		if ( ! empty( $res['errors'] ) ) {
			$msg .= ' ' . count( $res['errors'] ) . ' satır atlandı/uyarı.';
			set_transient( 'nizamiye_import_errors_' . get_current_user_id(), $res['errors'], 120 );
		}
		self::back( $msg, '', admin_url( 'admin.php?page=nizamiye-import&tab=' . $type ) );
	}

	/** Seçili derslik için önceden doldurulmuş not listesi indirir (GET + nonce). */
	public static function handle_grade_template() {
		check_admin_referer( 'nizamiye_grade_template' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		$class_id = isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
		if ( ! nizamiye_can_manage_class( $class_id ) ) {
			wp_die( 'Bu derslik için yetkiniz yok.' );
		}
		$title     = isset( $_GET['title'] ) ? sanitize_text_field( wp_unslash( $_GET['title'] ) ) : 'Sınav';
		$exam_type = isset( $_GET['exam_type'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_type'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ham değer yalnızca regex biçim doğrulaması için okunur, kullanılan değer sanitize_text_field(wp_unslash()) ile temizlenir.
		$exam_date = isset( $_GET['exam_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['exam_date'] ) ) ? sanitize_text_field( wp_unslash( $_GET['exam_date'] ) ) : current_time( 'Y-m-d' );
		$max_score = isset( $_GET['max_score'] ) ? max( 1, (float) $_GET['max_score'] ) : 100;

		$content = Nizamiye_Import::grade_template( $class_id, $title ?: 'Sınav', $exam_type, $exam_date, $max_score );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=not-listesi-derslik-' . $class_id . '-' . $exam_date . '.csv' );
		echo "\xEF\xBB\xBF";
		echo $content; // phpcs:ignore
		exit;
	}

	/** Doldurulmuş not listesini yükler. */
	public static function handle_grade_import() {
		check_admin_referer( 'nizamiye_grade_import', '_nizamiye_nonce' );
		if ( ! current_user_can( 'nizamiye_teach' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		self::$verified_action = 'nizamiye_grade_import';
		if ( empty( $_FILES['grade_file']['name'] ) || ! empty( $_FILES['grade_file']['error'] ) ) {
			self::back( '', 'Lütfen doldurulmuş not listesini (.csv veya .xlsx) seçin.' );
		}
		$tmp  = $_FILES['grade_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- yukarıdaki self::back()/exit kontrolü nedeniyle 'error' boşsa (UPLOAD_ERR_OK) tmp_name PHP tarafından garanti doldurulur.
		$ext  = strtolower( pathinfo( sanitize_file_name( wp_unslash( $_FILES['grade_file']['name'] ) ), PATHINFO_EXTENSION ) );
		$rows = Nizamiye_Import::read_file( $tmp, $ext );
		if ( is_wp_error( $rows ) ) {
			self::back( '', $rows->get_error_message() );
		}

		$res = Nizamiye_Import::import_grades( $rows, get_current_user_id(), 'nizamiye_can_manage_class' );

		$msg = $res['created'] . ' not kaydedildi.';
		if ( ! empty( $res['errors'] ) ) {
			$msg .= ' ' . count( $res['errors'] ) . ' satır atlandı.';
			set_transient( 'nizamiye_grade_import_errors_' . get_current_user_id(), $res['errors'], 300 );
		}
		self::back( $msg );
	}

	public static function handle_import_template() {
		check_admin_referer( 'nizamiye_template' );
		if ( ! current_user_can( 'nizamiye_manage' ) ) {
			wp_die( 'Bu işlem için yetkiniz yok.' );
		}
		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
		$content = Nizamiye_Import::template( $type );
		if ( ! $content ) {
			wp_die( 'Geçersiz şablon.' );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=sms-' . $type . '-sablon.csv' );
		echo "\xEF\xBB\xBF"; // Excel için UTF-8 BOM.
		echo $content; // phpcs:ignore
		exit;
	}
}
