<?php
defined( 'ABSPATH' ) || exit;

/**
 * Salt görüntüleme amaçlı (GET) iç bağlantılar/formlar için ortak nonce.
 * Durum değiştirmez; nonce eksik/geçersizse (ör. eski bir yer imi) ilgili
 * filtre/parametre yok sayılır ve sayfa varsayılan görünümle yüklenir —
 * sert bir hataya (wp_die) düşülmez.
 */
function nizamiye_view_nonce_url( $url ) {
	return wp_nonce_url( $url, 'nizamiye_view' );
}

/**
 * wp_nonce_url() ile aynı işi görür ama HTML kaçışlaması (esc_html, &amp;) yapmaz —
 * wp_safe_redirect() gibi ham URL bekleyen bağlamlarda (ör. self::back() hedefi) kullanılır.
 */
function nizamiye_view_nonce_url_raw( $url ) {
	return add_query_arg( '_wpnonce', wp_create_nonce( 'nizamiye_view' ), $url );
}

function nizamiye_view_nonce_field() {
	wp_nonce_field( 'nizamiye_view', '_wpnonce', false );
}

/** Eklenti ayarlarını varsayılanlarla birlikte döndürür. */
function nizamiye_get_settings() {
	$defaults = array(
		'school_name' => get_bloginfo( 'name' ),
		'final_grade' => 8,
		'min_grade'   => 1,
		'max_grade'   => 12,
		// Panel uyarı eşikleri. wp_parse_args sayesinde mevcut kurulumlar bu
		// varsayılanları kendiliğinden alır, ayrı bir migration gerekmez.
		'alert_absence_days' => 3,
		'alert_grade_drop'   => 15,
		// Rapor ekranlarının açılışta seçili gelen çıktı düzeni. Mevcut
		// kurulumlar 'classic' ile açılır — görünüm kendiliğinden değişmez.
		'sheet_layout'       => 'classic',
	);
	return wp_parse_args( (array) get_option( 'nizamiye_settings', array() ), $defaults );
}

function nizamiye_update_settings( array $settings ) {
	update_option( 'nizamiye_settings', array_merge( nizamiye_get_settings(), $settings ) );
}

/** Aktif dönem satırı (yoksa null). */
function nizamiye_active_term() {
	return Nizamiye_Terms::active();
}

/**
 * Görüntülenen dönem: ?nizamiye_term=ID parametresi varsa o, yoksa aktif dönem.
 */
function nizamiye_current_term_id() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
	if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) && isset( $_GET['nizamiye_term'] ) && (int) $_GET['nizamiye_term'] > 0 ) {
		$term = Nizamiye_Terms::get( (int) $_GET['nizamiye_term'] );
		if ( $term ) {
			return (int) $term->id;
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$active = nizamiye_active_term();
	return $active ? (int) $active->id : 0;
}

function nizamiye_is_manager() {
	return current_user_can( 'nizamiye_manage' );
}

function nizamiye_is_teacher() {
	return current_user_can( 'nizamiye_teach' ) && ! current_user_can( 'nizamiye_manage' );
}

/** Öğretmenin bu dönemdeki derslik ID'leri. */
function nizamiye_teacher_class_ids( $user_id = 0, $term_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$term_id = $term_id ? (int) $term_id : nizamiye_current_term_id();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- özel eklenti tablosu, parametreler $wpdb->prepare() ile bağlanır.
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT id FROM {$wpdb->prefix}nizamiye_classes WHERE teacher_id = %d AND term_id = %d",
		$user_id, $term_id
	) ) );
}

/**
 * Öğretmenin bu dönemde sorumlu olduğu öğrenci ID'leri.
 * Branş dersliklerindeki öğrenciler + (sınıf öğretmeni ise) sorumlu sınıf seviyelerindeki öğrenciler.
 */
function nizamiye_teacher_student_ids( $user_id = 0, $term_id = 0 ) {
	global $wpdb;
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$term_id = $term_id ? (int) $term_id : nizamiye_current_term_id();

	// Derslikler doğrudan JOIN ile daraltılır; böylece dinamik bir IN(...) placeholder
	// listesi kurmaya gerek kalmaz ve sorgu tamamen sabit %d yer tutucularıyla hazırlanır.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- özel eklenti tablosu, parametreler $wpdb->prepare() ile bağlanır.
	$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT cs.student_id
		 FROM {$wpdb->prefix}nizamiye_class_students cs
		 INNER JOIN {$wpdb->prefix}nizamiye_classes c ON c.id = cs.class_id
		 WHERE c.teacher_id = %d AND c.term_id = %d",
		$user_id,
		$term_id
	) ) );

	// Sınıf öğretmeni: sorumlu sınıf seviyelerindeki (veya tüm) aktif öğrenciler.
	if ( nizamiye_is_class_teacher( $user_id ) ) {
		$ids = array_merge( $ids, nizamiye_general_attendance_student_ids( $term_id, $user_id ) );
	}

	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/** Kullanıcı sınıf öğretmeni mi? */
function nizamiye_is_class_teacher( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return (bool) get_user_meta( $user_id, 'nizamiye_is_class_teacher', true );
}

/**
 * Sınıf öğretmeninin sorumluluk kapsamı: sınıf/şube çiftleri.
 *
 * Meta ('nizamiye_class_teacher_grades') iki biçimi de taşıyabilir; anahtar
 * değişmediği için eski kurulumlar migration'sız çalışır:
 *   eski → [6, 7]            (tamsayı: o sınıfın tamamı)
 *   yeni → ["6", "6-A"]      ("6" sınıfın tamamı, "6-A" yalnızca o şube)
 *
 * @return array<int,array{grade:int,section:string}> Boş dizi = kısıt yok (tüm öğrenciler).
 */
function nizamiye_class_teacher_scopes( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$raw     = get_user_meta( $user_id, 'nizamiye_class_teacher_grades', true );
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$scopes = array();
	foreach ( $raw as $entry ) {
		$parts   = explode( '-', (string) $entry, 2 );
		$grade   = (int) $parts[0];
		$section = isset( $parts[1] ) ? nizamiye_normalize_section( $parts[1] ) : '';
		if ( $grade > 0 ) {
			$scopes[] = array( 'grade' => $grade, 'section' => $section );
		}
	}
	return $scopes;
}

/**
 * Bir öğrenci, verilen sorumluluk kapsamlarından herhangi birine giriyor mu?
 * Kapsam listesi boşsa kısıt yoktur ve her öğrenci geçer. Şubesiz bir kapsam
 * ("6") o sınıfın tüm şubelerini kapsar; şubeli bir kapsam ("6-A") yalnızca
 * kendi şubesini.
 */
function nizamiye_scope_allows( array $scopes, $grade, $section ) {
	if ( ! $scopes ) {
		return true;
	}
	$grade   = (int) $grade;
	$section = nizamiye_normalize_section( $section );

	foreach ( $scopes as $scope ) {
		if ( $scope['grade'] !== $grade ) {
			continue;
		}
		if ( '' === $scope['section'] || $scope['section'] === $section ) {
			return true;
		}
	}
	return false;
}

/**
 * Sınıf öğretmeninin sorumlu olduğu sınıf seviyeleri (boş = tüm seviyeler).
 * Kapsam artık şube de içerebildiğinden yalnızca seviye boyutunu döndürür;
 * şube ayrımı gereken yerlerde nizamiye_class_teacher_scopes() kullanılmalıdır.
 */
function nizamiye_class_teacher_grades( $user_id = 0 ) {
	$grades = array();
	foreach ( nizamiye_class_teacher_scopes( $user_id ) as $scope ) {
		$grades[] = $scope['grade'];
	}
	return array_values( array_unique( $grades ) );
}

/** Geçerli kullanıcı genel (namaz/temizlik/telefon) yoklaması alabilir mi? */
function nizamiye_can_take_general_attendance() {
	if ( nizamiye_is_manager() ) {
		return true;
	}
	return current_user_can( 'nizamiye_teach' ) && nizamiye_is_class_teacher();
}

/**
 * Genel yoklama için görülebilecek öğrenci ID'leri.
 * Yönetici: dönemdeki tüm aktif öğrenciler. Sınıf öğretmeni: sorumlu sınıf/şubeler (boşsa tümü).
 * $category_id verilirse, kategorinin "hangi sınıflar bu yoklamada görünsün" kısıtlamasıyla
 * (Yoklama Türleri sayfasında ayarlanır) da kesişim alınır.
 */
function nizamiye_general_attendance_student_ids( $term_id = 0, $user_id = 0, $category_id = 0 ) {
	$term_id = $term_id ? (int) $term_id : nizamiye_current_term_id();
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	$args = array( 'term_id' => $term_id, 'status' => 'active' );
	$students = Nizamiye_Students::query( $args );

	$scopes     = user_can( $user_id, 'manage_options' ) ? array() : nizamiye_class_teacher_scopes( $user_id );
	$cat_grades = $category_id ? Nizamiye_Attendance_Types::get_grade_levels( $category_id ) : array();

	$ids = array();
	foreach ( $students as $s ) {
		$g   = (int) ( $s->grade_level ?? 0 );
		$sec = nizamiye_normalize_section( $s->section ?? '' );
		// Öğretmenin kapsamı şube düzeyinde olabilir; kategorinin kendi kısıtı
		// (Yoklama Türleri sayfası) seviye düzeyinde kalır ve ayrıca kesişir.
		if ( ! nizamiye_scope_allows( $scopes, $g, $sec ) ) {
			continue;
		}
		if ( $cat_grades && ! in_array( $g, $cat_grades, true ) ) {
			continue;
		}
		$ids[] = (int) $s->id;
	}
	return $ids;
}

/**
 * Geçerli kullanıcı bu dersliğin notlarını GÖRÜNTÜLEYEBİLİR mi?
 * Yönetici ve sınıf öğretmeni tüm branşları gezebilir (salt okunur);
 * branş öğretmeni yalnızca kendi dersliğini görür (ve yönetir).
 */
function nizamiye_can_view_grades( $class_id ) {
	if ( nizamiye_is_manager() ) {
		return true;
	}
	if ( ! current_user_can( 'nizamiye_teach' ) ) {
		return false;
	}
	$class = Nizamiye_Classes::get( (int) $class_id );
	if ( ! $class ) {
		return false;
	}
	if ( (int) $class->teacher_id === get_current_user_id() ) {
		return true;
	}
	return nizamiye_is_class_teacher();
}

/** Ders (derslik bazlı) yoklama kategorisinin kimliği. */
function nizamiye_ders_category_id() {
	$cat = Nizamiye_Attendance_Types::get_category_by_slug( 'ders' );
	return $cat ? (int) $cat->id : 0;
}

/**
 * Geçerli kullanıcı bu öğrencinin verilerini görebilir mi?
 * Yönetici: her zaman. Öğretmen: öğrencisi ise. Veli: çocuğu ise. Öğrenci: kendisi ise.
 */
function nizamiye_can_access_student( $student_id ) {
	$student_id = (int) $student_id;
	if ( nizamiye_is_manager() ) {
		return true;
	}
	$student = Nizamiye_Students::get( $student_id );
	if ( ! $student ) {
		return false;
	}
	$uid = get_current_user_id();
	if ( (int) $student->parent_user_id === $uid || (int) $student->user_id === $uid ) {
		return true;
	}
	if ( nizamiye_is_teacher() ) {
		return in_array( $student_id, nizamiye_teacher_student_ids(), true );
	}
	return false;
}

/** Geçerli kullanıcı bu dersliği yönetebilir mi? */
function nizamiye_can_manage_class( $class_id ) {
	if ( nizamiye_is_manager() ) {
		return true;
	}
	if ( ! current_user_can( 'nizamiye_teach' ) ) {
		return false;
	}
	$class = Nizamiye_Classes::get( (int) $class_id );
	return $class && (int) $class->teacher_id === get_current_user_id();
}

function nizamiye_grade_label( $grade ) {
	return (int) $grade . '. Sınıf';
}

/**
 * Şube etiketini tek harfe (A-Z) normalize eder.
 *
 * Kullanıcı "a", " b ", "6-C" gibi her şeyi yazabilir; içe aktarmada da serbest
 * metin gelir. Tek bir biçim dayatmak, aynı şubenin "A" ve "a" olarak ikiye
 * bölünmesini ve toplu derslik matrisinin kirlenmesini önler.
 *
 * @return string Tek büyük harf, ya da tanınmayan girdide boş dize.
 */
function nizamiye_normalize_section( $raw ) {
	$letters = preg_replace( '/[^A-Za-z]/', '', (string) $raw );
	if ( '' === $letters ) {
		return '';
	}
	return strtoupper( substr( $letters, 0, 1 ) );
}

/** Sınıf + şube etiketi: "6-A"; şube yoksa "6. Sınıf". */
function nizamiye_section_label( $grade, $section ) {
	$section = nizamiye_normalize_section( $section );
	if ( '' === $section ) {
		return nizamiye_grade_label( $grade );
	}
	return (int) $grade . '-' . $section;
}

/** Şube seçeneği listesi (A-Z). Select kutularını besler. */
function nizamiye_section_options() {
	return range( 'A', 'Z' );
}

function nizamiye_student_status_label( $status ) {
	$map = array(
		'active'    => 'Aktif',
		'graduated' => 'Mezun',
		'archived'  => 'Arşiv',
	);
	return $map[ $status ] ?? $status;
}

function nizamiye_attendance_statuses() {
	return array(
		'present' => 'Geldi',
		'absent'  => 'Gelmedi',
		'late'    => 'Geç Kaldı',
		'excused' => 'İzinli',
	);
}

/** Yoklama segment kontrolünde kullanılan kısa, tek kelimelik etiketler. */
function nizamiye_attendance_status_short() {
	return array(
		'present' => 'Var',
		'absent'  => 'Yok',
		'late'    => 'Geç',
		'excused' => 'İzin',
	);
}

/** Alışkanlık takip türü etiketi (liste/karne kartlarında kullanılır). */
function nizamiye_habit_track_type_label( $habit ) {
	if ( 'reading' === $habit->track_type ) {
		return 'Kitap / Sayfa Takibi';
	}
	if ( 'scale' === $habit->track_type ) {
		return 'Dereceli (1–' . (int) $habit->scale_max . ')';
	}
	return 'Yaptı / Yapmadı';
}

/** Rapor filtrelerinde kullanılan Türkçe ay adları. */
function nizamiye_month_names() {
	return array(
		1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
		7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
	);
}

/**
 * Raporlar sayfası ve CSV dışa aktarmada ortak tarih aralığı çözümü.
 * datemode=range → from/to (varsayılan davranış); datemode=month → rmonth/ryear
 * (ay=0 ise seçili yılın tamamı). Reports.php ile export handler'ının aynı
 * mantığı kullanmasını sağlar, ikisi arasında sürüklenmeyi önler.
 */
/**
 * $check_nonce=true (varsayılan, reports.php) 'nizamiye_view' nonce'unu arar.
 * handle_export_report() gibi zaten kendi (daha dar kapsamlı) nonce'uyla
 * doğrulanmış admin-post işleyicileri $check_nonce=false geçer; aksi halde
 * farklı bir action için üretilmiş nonce burada geçersiz sayılır ve tarih
 * filtresi sessizce sıfırlanırdı.
 */
function nizamiye_resolve_report_dates( $default_from = '', $check_nonce = true ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce yukarıda wp_verify_nonce() ile doğrulanır (ya da çağıran taraf zaten kendi nonce'unu doğrulamıştır); ham tarih değerleri yalnızca regex biçim kontrolü için okunur, kullanılan değer sanitize_text_field(wp_unslash()) ile temizlenir.
	$has_nonce = ! $check_nonce || ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) );
	$mode      = $has_nonce && isset( $_GET['datemode'] ) && 'month' === $_GET['datemode'] ? 'month' : 'range';
	$cur_year  = (int) current_time( 'Y' );

	if ( 'month' === $mode ) {
		$month = $has_nonce && isset( $_GET['rmonth'] ) ? max( 0, min( 12, (int) $_GET['rmonth'] ) ) : 0;
		$year  = $has_nonce && isset( $_GET['ryear'] ) ? max( 2000, min( 2100, (int) $_GET['ryear'] ) ) : $cur_year;
		if ( $month > 0 ) {
			$from = sprintf( '%04d-%02d-01', $year, $month );
			$to   = gmdate( 'Y-m-t', strtotime( $from ) );
		} else {
			$from = sprintf( '%04d-01-01', $year );
			$to   = sprintf( '%04d-12-31', $year );
		}
		return array( 'mode' => 'month', 'month' => $month, 'year' => $year, 'from' => $from, 'to' => $to );
	}

	$from = $has_nonce && isset( $_GET['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['from'] ) )
		? sanitize_text_field( wp_unslash( $_GET['from'] ) )
		: ( $default_from ?: gmdate( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) ) );
	$to   = $has_nonce && isset( $_GET['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['to'] ) )
		? sanitize_text_field( wp_unslash( $_GET['to'] ) )
		: current_time( 'Y-m-d' );

	return array( 'mode' => 'range', 'month' => 0, 'year' => $cur_year, 'from' => $from, 'to' => $to );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}

/** Rapor filtrelerinde kullanılan Türkçe gün adları ('N' biçimi: 1=Pazartesi … 7=Pazar). */
function nizamiye_day_names() {
	return array(
		1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe',
		5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar',
	);
}

/**
 * İki tarih arasını Türkçe olarak etiketler: "1 – 7 Eylül", ay değişiyorsa
 * "31 Ağustos – 6 Eylül", yıl da değişiyorsa "29 Aralık 2026 – 4 Ocak 2027".
 *
 * @param int $start_ts Aralığın ilk gününün zaman damgası.
 * @param int $end_ts   Aralığın son gününün zaman damgası.
 */
function nizamiye_date_span_label( $start_ts, $end_ts ) {
	$months = nizamiye_month_names();
	$sd     = (int) gmdate( 'j', $start_ts );
	$sm     = (int) gmdate( 'n', $start_ts );
	$sy     = (int) gmdate( 'Y', $start_ts );
	$ed     = (int) gmdate( 'j', $end_ts );
	$em     = (int) gmdate( 'n', $end_ts );
	$ey     = (int) gmdate( 'Y', $end_ts );

	if ( $sy !== $ey ) {
		return sprintf( '%d %s %d – %d %s %d', $sd, $months[ $sm ], $sy, $ed, $months[ $em ], $ey );
	}
	if ( $sm !== $em ) {
		return sprintf( '%d %s – %d %s', $sd, $months[ $sm ], $ed, $months[ $em ] );
	}
	return sprintf( '%d – %d %s', $sd, $ed, $months[ $sm ] );
}

/**
 * Bir ayla kesişen ISO haftalarını (Pazartesi–Pazar) döndürür. Rapor
 * filtresindeki "ay seç → o ayın haftaları gelsin → hafta seç" akışını besler.
 *
 * Ayın 1'ini içeren haftadan başlanır ve hafta ay sınırını aşsa bile gerçek
 * Pazartesi–Pazar aralığı korunur (kırpılmaz); etiket bu durumda iki ay adını
 * da yazar, böylece kullanıcı hangi günleri seçtiğini tam görür.
 *
 * @return array<int,array{start:string,end:string,label:string}>
 */
function nizamiye_month_weeks( $year, $month ) {
	$year  = max( 2000, min( 2100, (int) $year ) );
	$month = max( 1, min( 12, (int) $month ) );
	$first = strtotime( sprintf( '%04d-%02d-01', $year, $month ) );
	$last  = strtotime( gmdate( 'Y-m-t', $first ) );

	// Ayın ilk gününü içeren haftanın Pazartesi'si. Göreli ifade ayrıştırması
	// ("monday this week") yerine aritmetik kullanılır; 'N' her zaman ISO gün
	// numarasıdır (1=Pazartesi), dolayısıyla sonuç locale'den bağımsızdır.
	$cursor = $first - ( (int) gmdate( 'N', $first ) - 1 ) * DAY_IN_SECONDS;

	$weeks = array();
	while ( $cursor <= $last ) {
		$end     = $cursor + 6 * DAY_IN_SECONDS;
		$weeks[] = array(
			'start' => gmdate( 'Y-m-d', $cursor ),
			'end'   => gmdate( 'Y-m-d', $end ),
			'label' => nizamiye_date_span_label( $cursor, $end ),
		);
		$cursor += 7 * DAY_IN_SECONDS;
	}
	return $weeks;
}

/**
 * Veli raporlarının gün / hafta / ay dönem çözümü.
 *
 * nizamiye_resolve_report_dates() ile aynı deseni izler ama farklı GET
 * parametreleri kullanır (pmode/pdate/pweek/pmonth/pyear); ikisi ayrı tutulur
 * çünkü Raporlar sayfasının serbest tarih aralığı (datemode/from/to) ile bu
 * ekranın sabit dönem mantığı farklı şeylerdir ve birbirine karışmamalıdır.
 *
 * $check_nonce=false, kendi (daha dar kapsamlı) nonce'uyla zaten doğrulanmış
 * admin-post işleyicileri içindir — bkz. nizamiye_resolve_report_dates().
 *
 * @return array{mode:string,from:string,to:string,label:string,year:int,month:int,date:string,week_start:string}
 */
function nizamiye_resolve_period( $check_nonce = true ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce yukarıda wp_verify_nonce() ile doğrulanır (ya da çağıran taraf zaten kendi nonce'unu doğrulamıştır); ham değerler yalnızca regex biçim kontrolü için okunur, kullanılan değer sanitize_text_field(wp_unslash()) ile temizlenir.
	$has_nonce = ! $check_nonce || ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) );
	$raw_mode  = $has_nonce && isset( $_GET['pmode'] ) ? sanitize_text_field( wp_unslash( $_GET['pmode'] ) ) : '';
	$mode      = in_array( $raw_mode, array( 'week', 'month' ), true ) ? $raw_mode : 'day';
	$months    = nizamiye_month_names();
	$today     = current_time( 'Y-m-d' );

	if ( 'day' === $mode ) {
		$date = $has_nonce && isset( $_GET['pdate'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['pdate'] ) )
			? sanitize_text_field( wp_unslash( $_GET['pdate'] ) )
			: $today;
		$ts   = strtotime( $date );
		return array(
			'mode'       => 'day',
			'from'       => $date,
			'to'         => $date,
			'year'       => (int) gmdate( 'Y', $ts ),
			'month'      => (int) gmdate( 'n', $ts ),
			'date'       => $date,
			'week_start' => '',
			'label'      => sprintf(
				'%d %s %d, %s',
				(int) gmdate( 'j', $ts ),
				$months[ (int) gmdate( 'n', $ts ) ],
				(int) gmdate( 'Y', $ts ),
				nizamiye_day_names()[ (int) gmdate( 'N', $ts ) ]
			),
		);
	}

	$year  = $has_nonce && isset( $_GET['pyear'] ) ? max( 2000, min( 2100, (int) $_GET['pyear'] ) ) : (int) current_time( 'Y' );
	$month = $has_nonce && isset( $_GET['pmonth'] ) ? max( 1, min( 12, (int) $_GET['pmonth'] ) ) : (int) current_time( 'n' );

	if ( 'month' === $mode ) {
		$from = sprintf( '%04d-%02d-01', $year, $month );
		return array(
			'mode'       => 'month',
			'from'       => $from,
			'to'         => gmdate( 'Y-m-t', strtotime( $from ) ),
			'year'       => $year,
			'month'      => $month,
			'date'       => '',
			'week_start' => '',
			'label'      => $months[ $month ] . ' ' . $year,
		);
	}

	// Hafta modu: istenen hafta yalnızca seçili ayın hafta listesinde varsa
	// kabul edilir (uydurma bir 'pweek' değeri sessizce varsayılana düşer).
	$weeks   = nizamiye_month_weeks( $year, $month );
	$wanted  = $has_nonce && isset( $_GET['pweek'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['pweek'] ) )
		? sanitize_text_field( wp_unslash( $_GET['pweek'] ) )
		: '';
	$picked  = null;
	$fallback = null;
	foreach ( $weeks as $w ) {
		if ( $w['start'] === $wanted ) {
			$picked = $w;
			break;
		}
		// Varsayılan: bugünü içeren hafta (yoksa aşağıda ilk haftaya düşülür).
		if ( ! $fallback && $today >= $w['start'] && $today <= $w['end'] ) {
			$fallback = $w;
		}
	}
	if ( ! $picked ) {
		$picked = $fallback ? $fallback : $weeks[0];
	}

	return array(
		'mode'       => 'week',
		'from'       => $picked['start'],
		'to'         => $picked['end'],
		'year'       => $year,
		'month'      => $month,
		'date'       => '',
		'week_start' => $picked['start'],
		'label'      => $picked['label'] . ' ' . gmdate( 'Y', strtotime( $picked['end'] ) ),
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}

/**
 * Karne PDF'lerinin paylaştığı CSS. Dompdf (sunucu taraflı PDF motoru) tarafından
 * işlendiğinden bilinçli olarak flexbox kullanılmaz — tablo/blok tabanlı, dompdf'in
 * en güvenilir desteklediği düzen teknikleriyle yazılmıştır.
 */
function nizamiye_print_report_css() {
	return '
	* { box-sizing: border-box; }
	@page { margin: 12mm; }
	body {
		font-family: "DejaVu Sans", sans-serif;
		font-size: 11px;
		color: #1e293b;
		margin: 0;
	}
	.doc { width: 100%; }

	.head-table { width: 100%; border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 10px; }
	.head-table td { border: none; padding: 0; vertical-align: bottom; }
	.head h1 { font-size: 17px; margin: 0 0 2px; color: #4f46e5; }
	.head .school { font-size: 12px; font-weight: 700; }
	.head-meta { text-align: right; font-size: 10px; color: #64748b; }

	.identity { width: 100%; background: #f8fafc; border-radius: 10px; margin-bottom: 10px; }
	.identity td { border: none; padding: 10px 14px; vertical-align: middle; }
	.identity .avatar-cell { width: 46px; }
	.identity .avatar {
		width: 40px; height: 40px; border-radius: 50%; background: #eef2ff; color: #4f46e5;
		text-align: center; font-weight: 700; font-size: 15px; line-height: 40px;
	}
	.identity .name { font-size: 15px; font-weight: 700; margin: 0 0 2px; }
	.identity .sub { color: #64748b; font-size: 10.5px; }

	.tiles { width: 100%; margin-bottom: 10px; }
	.tiles td { border: none; padding: 0 4px; width: 25%; }
	.tile { background: #f8fafc; border-radius: 8px; padding: 8px 6px; text-align: center; }
	.tile .v { display: block; font-size: 16px; font-weight: 800; }
	.tile .l { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }

	h2.sec { font-size: 12px; margin: 12px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #e2e8f0; color: #334155; }
	table.data { width: 100%; border-collapse: collapse; font-size: 10.5px; margin-bottom: 4px; }
	table.data th, table.data td { text-align: left; padding: 3px 6px; border-bottom: 1px solid #f1f5f9; }
	table.data th { color: #64748b; font-weight: 700; font-size: 9.5px; text-transform: uppercase; }
	.center { text-align: center; }

	.cat-line { margin-bottom: 4px; font-size: 10.5px; }
	.cat-line b { display: inline-block; min-width: 80px; }
	.chip { background: #f1f5f9; border-radius: 999px; padding: 1px 7px; margin-left: 4px; font-size: 9.5px; }

	.foot { margin-top: 14px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
	';
}

/**
 * Rapor ekranlarındaki gün/hafta/ay filtre alanlarını basar (çağıranın kendi
 * <form method="get"> öğesinin içine). İki rapor ekranı da aynı işaretlemeyi
 * kullansın diye helper'a alınmıştır.
 *
 * Her seçim formu anında gönderir (eklentinin her yerindeki kalıp): ay
 * değiştiğinde hafta listesi sunucuda yeniden üretilir, AJAX'a gerek kalmaz.
 * Eski aya ait bir 'pweek' değeri yeni ayın listesinde bulunamayacağı için
 * nizamiye_resolve_period() sessizce o ayın varsayılan haftasına düşer.
 *
 * @param array $period nizamiye_resolve_period() çıktısı.
 */
function nizamiye_period_filter_fields( array $period ) {
	$months  = nizamiye_month_names();
	$cur_y   = (int) current_time( 'Y' );
	$modes   = array( 'day' => 'Günlük', 'week' => 'Haftalık', 'month' => 'Aylık' );

	echo '<label class="sms-muted">Dönem</label>';
	echo '<select name="pmode" onchange="this.form.submit()">';
	foreach ( $modes as $value => $label ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $value ),
			selected( $period['mode'], $value, false ),
			esc_html( $label )
		);
	}
	echo '</select>';

	if ( 'day' === $period['mode'] ) {
		echo '<input type="date" name="pdate" value="' . esc_attr( $period['date'] ) . '" onchange="this.form.submit()">';
		return;
	}

	echo '<select name="pmonth" onchange="this.form.submit()">';
	foreach ( $months as $num => $name ) {
		printf(
			'<option value="%d" %s>%s</option>',
			(int) $num,
			selected( $period['month'], (int) $num, false ),
			esc_html( $name )
		);
	}
	echo '</select>';

	echo '<select name="pyear" onchange="this.form.submit()">';
	for ( $y = $cur_y - 3; $y <= $cur_y + 1; $y++ ) {
		printf(
			'<option value="%d" %s>%s</option>',
			(int) $y,
			selected( $period['year'], $y, false ),
			esc_html( $y )
		);
	}
	echo '</select>';

	if ( 'week' !== $period['mode'] ) {
		return;
	}

	echo '<label class="sms-muted">Hafta</label>';
	echo '<select name="pweek" onchange="this.form.submit()">';
	foreach ( nizamiye_month_weeks( $period['year'], $period['month'] ) as $week ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $week['start'] ),
			selected( $period['week_start'], $week['start'], false ),
			esc_html( $week['label'] )
		);
	}
	echo '</select>';
}

/**
 * Rapor ekranlarındaki indirme çubuğu: PDF (sunucu taraflı dompdf) ve
 * PNG/JPG (tarayıcıda html2canvas ile önizlemenin görüntüsü).
 *
 * @param string $pdf_url    Nonce'lu admin-post adresi.
 * @param string $file_base  İndirilen görsel için dosya adı çekirdeği.
 */
function nizamiye_sheet_download_bar( $pdf_url, $file_base ) {
	echo '<div class="sms-toolbar">';
	echo '<span class="sms-muted">Velilere göndermek için indirin — PNG/JPG tek parça görüntüdür.</span>';
	echo '<span>';
	// data-sms-pdf-link: afişteki elle işaretler sunucuda üretilen PDF'e de
	// geçsin diye JS bu adrese 'marks' parametresi ekler (assets/js/sheet-marks.js).
	printf(
		'<a class="sms-btn sms-btn-primary sms-btn-sm" href="%s" data-sms-pdf-link="%s" target="_blank" rel="noopener"><span class="dashicons dashicons-pdf"></span> PDF İndir</a> ',
		esc_url( $pdf_url ),
		esc_attr( $pdf_url )
	);
	printf(
		'<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-sheet-export="png" data-sms-sheet-name="%s"><span class="dashicons dashicons-format-image"></span> PNG İndir</button> ',
		esc_attr( $file_base )
	);
	printf(
		'<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-sheet-export="jpeg" data-sms-sheet-name="%s"><span class="dashicons dashicons-format-image"></span> JPG İndir</button>',
		esc_attr( $file_base )
	);
	echo '</span>';
	echo '</div>';
}

/**
 * Toplu liste raporlarının (alışkanlık / yoklama) paylaştığı CSS.
 *
 * nizamiye_print_report_css()'ten iki farkı var:
 *  1. Tüm kurallar `.sheet` altında kapsanmıştır — böylece aynı stil hem dompdf
 *     belgesinde hem de WP admin ekranındaki önizlemede, admin.css ile
 *     çakışmadan çalışır. Ekrandaki önizleme ile PDF'in birebir aynı görünmesi
 *     (ve html2canvas'ın ürettiği PNG'nin ikisiyle eşleşmesi) buna dayanır.
 *  2. Yoğunluk `.sheet` üzerindeki `is-compact` / `is-dense` sınıflarıyla
 *     ayarlanır; uzun isim listelerinin tek sayfaya sığması içindir.
 *
 * Karne CSS'i gibi burada da bilinçli olarak flexbox/grid kullanılmaz —
 * dompdf yalnızca tablo/blok düzenini güvenilir biçimde işler.
 */
function nizamiye_print_sheet_css() {
	return '
	.sheet, .sheet * { box-sizing: border-box; }
	.sheet {
		font-family: "DejaVu Sans", sans-serif;
		font-size: 11px;
		line-height: 1.35;
		color: #1e293b;
		background: #ffffff;
		padding: 4px;
	}

	.sheet .sheet-head { width: 100%; border-bottom: 2px solid #4f46e5; padding-bottom: 7px; margin-bottom: 9px; border-collapse: collapse; }
	.sheet .sheet-head td { border: none; padding: 0; vertical-align: bottom; }
	.sheet .sheet-head h1 { font-size: 17px; margin: 0 0 2px; color: #4f46e5; }
	.sheet .sheet-head .sub { font-size: 11.5px; color: #334155; font-weight: 700; }
	.sheet .sheet-head .meta { text-align: right; font-size: 10px; color: #64748b; }
	.sheet .sheet-head .meta .school { font-size: 11.5px; font-weight: 700; color: #1e293b; }

	.sheet .sheet-tiles { width: 100%; margin-bottom: 9px; border-collapse: separate; border-spacing: 4px 0; }
	.sheet .sheet-tiles td { border: none; padding: 0; }
	.sheet .sheet-tiles .tile { background: #f8fafc; border-radius: 8px; padding: 7px 6px; text-align: center; }
	.sheet .sheet-tiles .tile .v { display: block; font-size: 15px; font-weight: 800; }
	.sheet .sheet-tiles .tile .l { font-size: 8.5px; color: #64748b; letter-spacing: .03em; }

	.sheet .sheet-data { width: 100%; border-collapse: collapse; }
	.sheet .sheet-data th, .sheet .sheet-data td { text-align: left; padding: 5px 7px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
	.sheet .sheet-data th {
		color: #475569; font-weight: 700; font-size: 9px;
		letter-spacing: .03em; background: #f1f5f9; border-bottom: 1px solid #cbd5e1;
	}
	.sheet .sheet-data tr.alt td { background: #f8fafc; }
	.sheet .sheet-data .num { width: 26px; color: #94a3b8; text-align: right; }
	.sheet .sheet-data .name { font-weight: 700; }
	.sheet .sheet-data .name .grade { font-weight: 400; color: #94a3b8; font-size: 9px; }
	.sheet .sheet-data .c { text-align: center; }
	.sheet .sheet-data .r { text-align: right; }
	.sheet .sheet-data .empty { color: #cbd5e1; }
	.sheet .sheet-data .books { color: #334155; }
	.sheet .sheet-data .books .pg { color: #94a3b8; }

	.sheet .good { color: #16a34a; font-weight: 700; }
	.sheet .mid  { color: #d97706; font-weight: 700; }
	.sheet .low  { color: #dc2626; font-weight: 700; }

	.sheet .sheet-note { margin-top: 8px; font-size: 9.5px; color: #64748b; }
	.sheet .sheet-foot { margin-top: 10px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }

	.sheet.is-compact { font-size: 9.5px; }
	.sheet.is-compact .sheet-head h1 { font-size: 15px; }
	.sheet.is-compact .sheet-data th, .sheet.is-compact .sheet-data td { padding: 3px 6px; }
	.sheet.is-compact .sheet-tiles .tile { padding: 5px 4px; }
	.sheet.is-compact .sheet-tiles .tile .v { font-size: 13px; }

	.sheet.is-dense { font-size: 8.5px; line-height: 1.25; }
	.sheet.is-dense .sheet-head h1 { font-size: 14px; }
	.sheet.is-dense .sheet-head { padding-bottom: 5px; margin-bottom: 6px; }
	.sheet.is-dense .sheet-data th, .sheet.is-dense .sheet-data td { padding: 2px 5px; }
	.sheet.is-dense .sheet-data .num { width: 20px; }
	.sheet.is-dense .sheet-tiles { margin-bottom: 6px; }
	.sheet.is-dense .sheet-tiles .tile { padding: 4px 3px; }
	.sheet.is-dense .sheet-tiles .tile .v { font-size: 12px; }
	';
}

/* ---------- Çıktı düzeni (klasik / afiş) ---------- */

/** Seçilebilir çıktı düzenleri: değer => ekranda görünen ad. */
function nizamiye_sheet_layouts() {
	return array(
		'classic' => 'Klasik — veri odaklı',
		'poster'  => 'Afiş — renkli, iri punto',
	);
}

/** Bilinmeyen değerleri sessizce 'classic'e düşürür. */
function nizamiye_normalize_sheet_layout( $raw ) {
	$raw = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';
	return array_key_exists( $raw, nizamiye_sheet_layouts() ) ? $raw : 'classic';
}

/** Ayarlardaki varsayılan düzen; rapor ekranları seçim yoksa buna açılır. */
function nizamiye_default_sheet_layout() {
	$settings = nizamiye_get_settings();
	return nizamiye_normalize_sheet_layout( isset( $settings['sheet_layout'] ) ? $settings['sheet_layout'] : '' );
}

/**
 * Rapor ekranındaki düzen seçicisi. Form GET ile gönderildiği için seçim
 * hem önizlemeyi hem de altındaki PDF/PNG/JPG bağlantılarını birlikte değiştirir.
 */
function nizamiye_sheet_layout_field( $layout ) {
	echo '<label class="sms-muted">Çıktı tipi</label>';
	echo '<select name="layout" onchange="this.form.submit()">';
	foreach ( nizamiye_sheet_layouts() as $nizamiye_value => $nizamiye_label ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $nizamiye_value ),
			selected( $layout, $nizamiye_value, false ),
			esc_html( $nizamiye_label )
		);
	}
	echo '</select>';
}

/* ---------- Afiş işaretleri (yalnızca çıktıda görünen, kaydedilmeyen durumlar) ---------- */

/**
 * Afiş çıktısında bir öğrencinin satırına elle konulabilen durumlar.
 *
 * Bunlar BİLEREK veritabanına yazılmaz: okuma kaydı "kitap adı + sayfa"dır ve
 * sayfa boş bırakılınca kayıt hiç oluşmaz (bkz. Nizamiye_Actions::handle_save_habit_logs).
 * "Ödeve devam etti" için sayfaya 0 yazmak kaydı oluştururdu ama veriyi kirletirdi:
 * okuma kaydı sayılır, kitap adı otomatik tamamlamaya girer, "farklı kitap"
 * sayısını şişirirdi. Bu yüzden işaretler yalnızca yazdırma anında yaşar —
 * önizlemeye JS ile uygulanır, PDF'e de adres satırındaki 'marks' ile taşınır.
 */
function nizamiye_sheet_mark_types() {
	return array(
		'h' => array( 'label' => 'Ödeve devam etti', 'class' => 'mid' ),
		'a' => array( 'label' => 'Gelmedi',          'class' => 'low' ),
	);
}

/**
 * "12h,15a" biçimindeki işaret dizgesini [öğrenci id => tür] dizisine çevirir.
 * Tanınmayan tür ve id'ler sessizce atılır — değer kullanıcıdan gelir.
 */
function nizamiye_parse_sheet_marks( $raw ) {
	$marks = array();
	if ( ! is_string( $raw ) || '' === $raw ) {
		return $marks;
	}
	$types = nizamiye_sheet_mark_types();
	if ( ! preg_match_all( '/(\d+)([a-z])/', strtolower( $raw ), $found, PREG_SET_ORDER ) ) {
		return $marks;
	}
	foreach ( $found as $one ) {
		$id = (int) $one[1];
		if ( $id > 0 && isset( $types[ $one[2] ] ) ) {
			$marks[ $id ] = $one[2];
		}
	}
	return $marks;
}

/**
 * Türkçe kurallarıyla büyük harfe çevirir: i → İ, ı → I.
 *
 * CSS'in `text-transform: uppercase`'i bu ayrımı bilmez ve "KITAP OKUMA
 * ÇIZELGESI", "ÖĞRENCI", "ÖDEVE DEVAM ETTI" gibi bozuk başlıklar üretir —
 * ne dompdf ne de tarayıcı, sayfanın dili Türkçe olsa bile bunu düzeltir.
 * Bu yüzden afiş düzeninde büyük harfe çevirme CSS'te değil burada yapılır;
 * böylece PDF, ekran önizlemesi ve PNG/JPG aynı metni gösterir.
 */
function nizamiye_upper_tr( $text ) {
	$text = (string) $text;
	if ( '' === $text ) {
		return '';
	}
	// Önce Türkçeye özgü iki harf sabitlenir, sonra gerisi normal yoldan çevrilir.
	$text = str_replace( array( 'i', 'ı' ), array( 'İ', 'I' ), $text );
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
}

/**
 * Afiş künyesindeki çizimler: dompdf bunları php-svg-lib ile basar, bu yüzden
 * yalnızca kütüphanenin kesin desteklediği <rect> ve <circle> kullanılır.
 *
 * Çizim bozuk çıkan bir kurulumda tek satırla kapatılabilsin diye filtreye
 * bağlı — kapatıldığında künye çizimsiz, ama bozulmadan basılır.
 */
function nizamiye_poster_ornament( $name ) {
	if ( ! apply_filters( 'nizamiye_poster_ornaments', true ) ) {
		return '';
	}

	$shapes = array(
		// Üst üste binmiş üç kitap — okuma çizelgesi için.
		'books' => "%3Crect x='5' y='41' width='54' height='13' rx='3' fill='%23f26b3a'/%3E"
			. "%3Crect x='9' y='27' width='46' height='13' rx='3' fill='%230f9b8e'/%3E"
			. "%3Crect x='13' y='13' width='38' height='13' rx='3' fill='%23ffc13c'/%3E"
			. "%3Crect x='11' y='44.5' width='6' height='6' rx='1.5' fill='%23fffbf2'/%3E"
			. "%3Crect x='15' y='30.5' width='6' height='6' rx='1.5' fill='%23fffbf2'/%3E"
			. "%3Crect x='19' y='16.5' width='6' height='6' rx='1.5' fill='%23fffbf2'/%3E",
		// Yoklama panosu — işaretli/işaretsiz satırlarla.
		'board' => "%3Crect x='8' y='9' width='48' height='50' rx='6' fill='%23ffffff'/%3E"
			. "%3Crect x='24' y='4' width='16' height='10' rx='4' fill='%23f26b3a'/%3E"
			. "%3Crect x='22' y='26' width='26' height='4' rx='2' fill='%23c9d6e4'/%3E"
			. "%3Crect x='22' y='37' width='26' height='4' rx='2' fill='%23c9d6e4'/%3E"
			. "%3Crect x='22' y='48' width='17' height='4' rx='2' fill='%23c9d6e4'/%3E"
			. "%3Ccircle cx='16' cy='28' r='3.5' fill='%232e9e4f'/%3E"
			. "%3Ccircle cx='16' cy='39' r='3.5' fill='%232e9e4f'/%3E"
			. "%3Ccircle cx='16' cy='50' r='3.5' fill='%23d64545'/%3E",
	);

	if ( ! isset( $shapes[ $name ] ) ) {
		return '';
	}

	// Yüzde kodlaması bilinçli: dompdf data URI'yi base64'ten önce rawurldecode()
	// ettiği için okunur kalabiliyor (bkz. Dompdf\Helpers::parse_data_uri).
	return '<img alt="" src="data:image/svg+xml,'
		. "%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E"
		. $shapes[ $name ]
		. '%3C/svg%3E">';
}

/**
 * Afiş çıktısındaki elle işaretleme paneli.
 *
 * Her öğrenci için üç durumlu bir seçici verir; seçim anında önizlemeye uygulanır
 * ve PDF bağlantısına 'marks' olarak eklenir. Hiçbir şey kaydedilmez — gerekçe
 * için bkz. nizamiye_sheet_mark_types().
 *
 * @param array  $sheet Nizamiye_Sheet çıktısı (satırlar öğrenci kaydını taşır).
 * @param string $scope Tarayıcıda hatırlama anahtarı — alışkanlık + tarih.
 */
function nizamiye_sheet_marks_panel( array $sheet, $scope ) {
	$students = array();
	foreach ( $sheet['rows'] as $row ) {
		if ( ! empty( $row['student'] ) ) {
			$students[] = $row['student'];
		}
	}
	if ( ! $students ) {
		return;
	}

	// JS'in etiket/renk için kullandığı tür tablosu.
	$types = array();
	foreach ( nizamiye_sheet_mark_types() as $key => $type ) {
		$types[ $key ] = array( 'label' => $type['label'], 'klass' => $type['class'] );
	}

	echo '<details class="sms-card sms-mt" data-sms-marks="' . esc_attr( $scope ) . '" data-sms-mark-types="' . esc_attr( wp_json_encode( $types ) ) . '">';
	echo '<summary class="sms-pad"><strong>Elle işaretle</strong> <span class="sms-muted">— "Ödeve devam etti" / "Gelmedi". Yalnızca çıktıda görünür, kaydedilmez.</span></summary>';
	echo '<div class="sms-pad">';
	echo '<table class="sms-table"><tbody>';
	foreach ( $students as $student ) {
		$id = (int) $student->id;
		echo '<tr><td class="sms-name-cell"><strong>' . esc_html( nizamiye_student_name( $student ) ) . '</strong></td><td>';
		echo '<select data-sms-mark-for="' . esc_attr( (string) $id ) . '">';
		echo '<option value="">— işaret yok —</option>';
		foreach ( nizamiye_sheet_mark_types() as $key => $type ) {
			printf( '<option value="%s">%s</option>', esc_attr( $key ), esc_html( $type['label'] ) );
		}
		echo '</select></td></tr>';
	}
	echo '</tbody></table>';
	echo '<p><button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-marks-clear>Tüm işaretleri temizle</button></p>';
	echo '</div></details>';
}

/**
 * Afiş düzeninin CSS'i. nizamiye_print_sheet_css() ile aynı gerekçelerle
 * `.sheet-poster` altında kapsanır: tek stil hem dompdf belgesinde hem WP admin
 * önizlemesinde çalışsın, PNG/JPG de ikisiyle örtüşsün.
 *
 * dompdf kısıtları (kaynaktan doğrulandı):
 *  - flex/grid yok; yalnızca tablo + blok + inline-block.
 *  - SVG yalnızca <img> ile basılır; background-image yolu GD üzerinden gittiği
 *    ve imagecreatefromsvg() olmadığı için SVG arka planlar sessizce çizilmez.
 *  - Gömülü tek Türkçe uyumlu font DejaVu Sans; ✓ ✗ ○ ★ onda mevcut, emoji değil.
 */
function nizamiye_print_poster_css() {
	return '
	.sheet-poster, .sheet-poster * { box-sizing: border-box; }
	.sheet-poster {
		font-family: "DejaVu Sans", Verdana, sans-serif;
		font-size: 12.5px;
		line-height: 1.3;
		color: #12355b;
		background: #fffbf2;
		padding: 3px;
	}

	.sheet-poster .pp-frame { border: 2px solid #12355b; border-radius: 14px; overflow: hidden; background: #fffbf2; }

	.sheet-poster .pp-mast { background: #12355b; padding: 10px 15px 9px; }
	.sheet-poster .pp-mast-row { width: 100%; border-collapse: collapse; }
	.sheet-poster .pp-mast-row td { border: none; padding: 0; vertical-align: middle; }
	.sheet-poster .pp-mast-icon { width: 50px; }
	.sheet-poster .pp-mast-icon img { width: 40px; height: 40px; }
	.sheet-poster .pp-school { color: #ffc13c; font-size: 11.5px; font-weight: bold; letter-spacing: .16em; }
	.sheet-poster .pp-hair { height: 2px; background: #2c5a86; margin: 6px 0 7px; width: 46px; border-radius: 1px; }
	.sheet-poster .pp-title { color: #ffffff; font-size: 21px; font-weight: bold; letter-spacing: .035em; line-height: 1.15; }

	.sheet-poster .pp-pills { width: 100%; margin-top: 9px; border-collapse: separate; border-spacing: 7px 0; }
	.sheet-poster .pp-pills td { padding: 0; }
	.sheet-poster .pp-pill {
		background: #1e4e7a; color: #ffffff; border-radius: 16px; text-align: center;
		padding: 5px 9px; font-size: 11px; font-weight: bold; letter-spacing: .06em;
	}
	.sheet-poster .pp-pill .l { color: #93bde0; font-weight: normal; letter-spacing: .1em; }

	.sheet-poster .pp-data { width: 100%; border-collapse: collapse; }
	.sheet-poster .pp-data th {
		background: #ffc13c; color: #12355b; font-size: 10.5px; font-weight: bold;
		letter-spacing: .1em; padding: 7px 11px; text-align: left; border: none;
	}
	.sheet-poster .pp-data td { padding: 4px 11px; border-bottom: 1px solid #ece3cf; vertical-align: middle; background: #ffffff; }
	.sheet-poster .pp-data tr.alt td { background: #fdf8ec; }

	.sheet-poster .pp-data td.grp {
		background: #e7eef7; color: #12355b; font-size: 10.5px; font-weight: bold;
		letter-spacing: .12em; padding: 4px 11px; border-bottom: 1px solid #c6d5e6;
	}
	.sheet-poster .pp-data td.grp .n { color: #6d88a6; font-weight: normal; letter-spacing: .06em; }

	.sheet-poster .pp-data .num { width: 46px; text-align: center; padding-left: 8px; padding-right: 8px; }
	.sheet-poster .pp-data .num .badge {
		display: inline-block; background: #eef3f9; color: #12355b; font-size: 11.5px;
		font-weight: bold; border-radius: 7px; padding: 2px 0; width: 26px; text-align: center;
	}
	.sheet-poster .pp-data .name { font-size: 13.5px; font-weight: bold; letter-spacing: .02em; }
	.sheet-poster .pp-data .name .grade {
		display: block; font-size: 9.5px; font-weight: bold; letter-spacing: .09em; color: #8ca0b5; padding-top: 1px;
	}
	.sheet-poster .pp-data .books { font-size: 12.5px; letter-spacing: .01em; color: #294662; }
	.sheet-poster .pp-data .books div { padding: 1px 0; }
	.sheet-poster .pp-data .c { text-align: center; }
	.sheet-poster .pp-data .r { text-align: right; }

	.sheet-poster .pp-num {
		display: inline-block; min-width: 42px; background: #fff0cc; color: #12355b;
		font-size: 13.5px; font-weight: bold; border-radius: 8px; padding: 2px 8px; text-align: center;
	}
	.sheet-poster .pp-num .u { font-size: 9px; font-weight: normal; color: #9b8656; letter-spacing: .04em; }
	.sheet-poster .chip {
		display: inline-block; border-radius: 13px; padding: 2px 10px; font-size: 10.5px;
		font-weight: bold; letter-spacing: .07em;
	}
	.sheet-poster .chip-good  { background: #e6f5ea; color: #1d7a37; }
	.sheet-poster .chip-mid   { background: #fff1de; color: #c2650b; }
	.sheet-poster .chip-low   { background: #fdeaea; color: #c22f2f; }
	.sheet-poster .chip-empty { background: #f2ede0; color: #948d78; }

	.sheet-poster .pp-data .tick { width: 76px; text-align: center; }
	/* İri punto yalnızca ✓/✗ hücrelerine. Başlık hücrelerine de uygulanırsa
	   sütun başlıkları diğerlerinden kat kat büyük çıkıyor. */
	.sheet-poster .pp-data td.tick { font-size: 17px; line-height: 1; }
	.sheet-poster .tick-yes { color: #2e9e4f; font-weight: bold; }
	.sheet-poster .tick-no  { color: #d64545; font-weight: bold; }
	.sheet-poster .tick-off { color: #d5cdb8; }
	.sheet-poster .pp-field {
		border: 1px solid #cfdae7; border-radius: 13px; min-height: 17px; padding: 2px 12px;
		font-size: 10.5px; font-weight: bold; letter-spacing: .07em;
		color: #294662; text-align: center; background: #fbfcfe;
	}

	.sheet-poster .pp-summary {
		background: #12355b; color: #ffffff; text-align: center; font-size: 11.5px;
		font-weight: bold; letter-spacing: .1em; padding: 7px 12px;
	}
	.sheet-poster .pp-summary .s { color: #93bde0; font-weight: normal; }
	.sheet-poster .pp-motto {
		background: #ffc13c; color: #12355b; text-align: center; font-size: 12.5px;
		font-weight: bold; letter-spacing: .05em; padding: 8px 12px;
	}
	.sheet-poster .pp-note { margin-top: 8px; font-size: 9.5px; color: #948d78; text-align: center; }
	.sheet-poster .pp-foot { margin-top: 3px; font-size: 9px; color: #b0a993; text-align: center; }

	/* Yoğunluk kademeleri. Her kademe bir öncekinden sıkı olmalı — taban ölçüler
	   değişirse bu blokların da birlikte güncellenmesi gerekir. Değerler gerçek
	   A4 çıktısı ölçülerek bulundu: taban 22, compact 44 satıra kadar tek sayfa. */
	.sheet-poster.is-compact { font-size: 11px; }
	.sheet-poster.is-compact .pp-mast { padding: 7px 12px 6px; }
	.sheet-poster.is-compact .pp-school { font-size: 10px; }
	.sheet-poster.is-compact .pp-hair { margin: 4px 0 5px; }
	.sheet-poster.is-compact .pp-title { font-size: 16px; }
	.sheet-poster.is-compact .pp-mast-icon { width: 40px; }
	.sheet-poster.is-compact .pp-mast-icon img { width: 32px; height: 32px; }
	.sheet-poster.is-compact .pp-pills { margin-top: 6px; }
	.sheet-poster.is-compact .pp-pill { font-size: 9.5px; padding: 4px 8px; }
	.sheet-poster.is-compact .pp-data th { padding: 5px 9px; font-size: 9.5px; }
	.sheet-poster.is-compact .pp-data td { padding: 2px 9px; }
	.sheet-poster.is-compact .pp-data td.grp { padding: 3px 9px; font-size: 9.5px; }
	.sheet-poster.is-compact .pp-data .name { font-size: 11.5px; }
	.sheet-poster.is-compact .pp-data .name .grade { font-size: 8.5px; }
	.sheet-poster.is-compact .pp-data .books { font-size: 11px; }
	.sheet-poster.is-compact .pp-data .num .badge { width: 23px; font-size: 10.5px; padding: 1px 0; }
	.sheet-poster.is-compact .pp-num { font-size: 12px; min-width: 34px; padding: 1px 7px; }
	.sheet-poster.is-compact .chip { font-size: 9.5px; padding: 1px 8px; }
	.sheet-poster.is-compact .pp-data td.tick { font-size: 14px; }
	.sheet-poster.is-compact .pp-field { min-height: 14px; padding: 1px 10px; font-size: 9.5px; }
	.sheet-poster.is-compact .pp-summary { padding: 5px 12px; font-size: 10.5px; }
	.sheet-poster.is-compact .pp-motto { padding: 6px 12px; font-size: 11.5px; }

	.sheet-poster.is-dense { font-size: 9.5px; line-height: 1.2; }
	.sheet-poster.is-dense .pp-mast { padding: 5px 10px 5px; }
	.sheet-poster.is-dense .pp-school { font-size: 9px; }
	.sheet-poster.is-dense .pp-hair { margin: 3px 0 4px; }
	.sheet-poster.is-dense .pp-title { font-size: 13px; }
	.sheet-poster.is-dense .pp-mast-icon { width: 30px; }
	.sheet-poster.is-dense .pp-mast-icon img { width: 24px; height: 24px; }
	.sheet-poster.is-dense .pp-pills { margin-top: 5px; }
	.sheet-poster.is-dense .pp-pill { font-size: 8.5px; padding: 3px 6px; }
	.sheet-poster.is-dense .pp-data th { padding: 4px 7px; font-size: 8.5px; }
	.sheet-poster.is-dense .pp-data td { padding: 1px 7px; }
	.sheet-poster.is-dense .pp-data td.grp { padding: 2px 7px; font-size: 8.5px; }
	.sheet-poster.is-dense .pp-data .name { font-size: 10px; }
	.sheet-poster.is-dense .pp-data .name .grade { font-size: 8px; }
	.sheet-poster.is-dense .pp-data .books { font-size: 9.5px; }
	.sheet-poster.is-dense .pp-data .num .badge { width: 20px; font-size: 9px; padding: 0 0; }
	.sheet-poster.is-dense .pp-num { font-size: 10.5px; min-width: 28px; padding: 0 6px; }
	.sheet-poster.is-dense .chip { font-size: 8.5px; padding: 0 7px; }
	.sheet-poster.is-dense .pp-data td.tick { font-size: 12px; }
	.sheet-poster.is-dense .pp-field { min-height: 11px; padding: 0 8px; font-size: 8.5px; }
	.sheet-poster.is-dense .pp-summary { padding: 4px 10px; font-size: 9.5px; }
	.sheet-poster.is-dense .pp-motto { padding: 5px 10px; font-size: 10px; }
	';
}

function nizamiye_format_date( $date ) {
	if ( ! $date || '0000-00-00' === $date ) {
		return '—';
	}
	return date_i18n( 'j F Y', strtotime( $date ) );
}

/** Ad Soyad döndürür. */
function nizamiye_student_name( $student ) {
	return trim( $student->first_name . ' ' . $student->last_name );
}

/** Rol bazlı kullanıcı listesi (id => görünen ad). */
function nizamiye_users_by_role( $role ) {
	$users = get_users( array( 'role' => $role, 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
	return $users;
}

/** Sayfa içi başarı/hata bildirimini yazdırır (?nizamiye_msg & ?nizamiye_err). */
function nizamiye_render_notices() {
	if ( ! ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yukarıda wp_verify_nonce() ile zaten doğrulandı.
	if ( ! empty( $_GET['nizamiye_msg'] ) ) {
		echo '<div class="sms-notice sms-notice-success"><span class="dashicons dashicons-yes-alt"></span>' . esc_html( sanitize_text_field( wp_unslash( $_GET['nizamiye_msg'] ) ) ) . '</div>';
	}
	if ( ! empty( $_GET['nizamiye_err'] ) ) {
		echo '<div class="sms-notice sms-notice-error"><span class="dashicons dashicons-warning"></span>' . esc_html( sanitize_text_field( wp_unslash( $_GET['nizamiye_err'] ) ) ) . '</div>';
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}

/** Ortak sayfa başlığı + dönem seçici (+ sınırlı kullanıcılar için hesap çipi). */
function nizamiye_view_header( $title, $subtitle = '', $show_term_picker = true ) {
	$terms   = Nizamiye_Terms::all();
	$current = nizamiye_current_term_id();
	echo '<div class="sms-page-head">';
	echo '<div><h1 class="sms-title">' . esc_html( $title ) . '</h1>';
	if ( $subtitle ) {
		echo '<p class="sms-subtitle">' . esc_html( $subtitle ) . '</p>';
	}
	echo '</div>';
	echo '<div class="sms-head-tools">';
	if ( $show_term_picker && $terms ) {
		echo '<form method="get" class="sms-term-picker">';
		nizamiye_view_nonce_field();
		// 'page' WordPress'in kendi menü yönlendirmesidir (nonce taşımaz), her zaman korunur.
		// Değeri $_GET yerine WordPress'in bu ekran için hazırladığı $plugin_page
		// global'inden alıyoruz; böylece hiçbir form verisi doğrudan okunmuyor.
		global $plugin_page;
		if ( $plugin_page ) {
			echo '<input type="hidden" name="page" value="' . esc_attr( $plugin_page ) . '">';
		}
		// Diğer parametreler yalnızca geçerli bir görüntüleme nonce'u varsa korunur
		// (aksi halde bu değerler zaten sayfanın kendisinde de yok sayılmış demektir).
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yukarıda wp_verify_nonce() ile zaten doğrulandı.
			foreach ( array( 'view', 'class_id', 'habit_id', 'student', 'cat', 'session', 'rsession', 'tab', 'rtype', 'group', 'grade', 'metric', 'from', 'to', 'datemode', 'rmonth', 'ryear', 'gview', 'subject', 'title', 'exam_date', 'exam_type', 'pmode', 'pdate', 'pweek', 'pmonth', 'pyear', 'orient', 'section' ) as $keep ) {
				if ( isset( $_GET[ $keep ] ) ) {
					echo '<input type="hidden" name="' . esc_attr( $keep ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET[ $keep ] ) ) ) . '">';
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}
		echo '<label>Dönem</label><select name="nizamiye_term" onchange="this.form.submit()">';
		foreach ( $terms as $t ) {
			printf(
				'<option value="%d" %s>%s%s</option>',
				(int) $t->id,
				selected( $current, (int) $t->id, false ),
				esc_html( $t->name ),
				$t->is_active ? ' • aktif' : ''
			);
		}
		echo '</select></form>';
	}
	// Not: sınırlı kullanıcılar için profil + çıkış artık üst çubukta gösterilir (bkz. admin_bar_menu kancası).
	echo '</div>';
	echo '</div>';
	nizamiye_render_notices();

	if ( ! $terms && nizamiye_is_manager() ) {
		echo '<div class="sms-notice sms-notice-info"><span class="dashicons dashicons-info"></span>Henüz dönem oluşturulmadı. Başlamak için <a href="' . esc_url( admin_url( 'admin.php?page=nizamiye-terms' ) ) . '">Dönemler</a> sayfasından bir dönem açın (örn. 2025-2026).</div>';
	}
}

/** admin-post form açılışı: action + nonce. */
function nizamiye_form_open( $action, $extra_class = '' ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sms-form ' . esc_attr( $extra_class ) . '">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	wp_nonce_field( $action, '_nizamiye_nonce' );
}

/** İşlem sonrası geri dönüş adresi (form içinden gönderilir). */
function nizamiye_back_url_field( $url = '' ) {
	if ( ! $url ) {
		$url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : admin_url( 'admin.php?page=nizamiye-dashboard' );
	}
	echo '<input type="hidden" name="_nizamiye_back" value="' . esc_attr( $url ) . '">';
}

/** Yüzdeye göre renk sınıfı. */
function nizamiye_rate_class( $rate ) {
	if ( null === $rate ) {
		return '';
	}
	if ( $rate >= 75 ) {
		return 'sms-rate-good';
	}
	if ( $rate >= 50 ) {
		return 'sms-rate-mid';
	}
	return 'sms-rate-low';
}

/** Baş harflerden avatar rozeti. */
function nizamiye_avatar( $name, $size = '' ) {
	$parts    = preg_split( '/\s+/', trim( $name ) );
	$initials = mb_strtoupper( mb_substr( $parts[0] ?? '', 0, 1 ) . mb_substr( $parts[ count( $parts ) - 1 ] ?? '', 0, 1 ) );
	$hue      = crc32( $name ) % 360;
	return '<span class="sms-avatar ' . esc_attr( $size ) . '" style="--h:' . (int) $hue . '">' . esc_html( $initials ) . '</span>';
}
