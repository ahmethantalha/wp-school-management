<?php
defined( 'ABSPATH' ) || exit;

/** Eklenti ayarlarını varsayılanlarla birlikte döndürür. */
function nizamiye_get_settings() {
	$defaults = array(
		'school_name' => get_bloginfo( 'name' ),
		'final_grade' => 8,
		'min_grade'   => 1,
		'max_grade'   => 12,
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
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- salt görüntüleme parametresi, durum değişikliği yok.
	if ( isset( $_GET['nizamiye_term'] ) && (int) $_GET['nizamiye_term'] > 0 ) {
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

	$ids       = array();
	$class_ids = nizamiye_teacher_class_ids( $user_id, $term_id );
	if ( $class_ids ) {
		$in  = implode( ',', array_map( 'intval', $class_ids ) ); // intval ile temizlenmiş, sorguya güvenle gömülür.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- özel eklenti tablosu; $in yalnızca intval edilmiş id'lerden oluşur.
		$ids = array_map( 'intval', $wpdb->get_col(
			"SELECT DISTINCT student_id FROM {$wpdb->prefix}nizamiye_class_students WHERE class_id IN ($in)"
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

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

/** Sınıf öğretmeninin sorumlu olduğu sınıf seviyeleri (boş = tüm seviyeler). */
function nizamiye_class_teacher_grades( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$grades  = get_user_meta( $user_id, 'nizamiye_class_teacher_grades', true );
	return is_array( $grades ) ? array_map( 'intval', $grades ) : array();
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
 * Yönetici: dönemdeki tüm aktif öğrenciler. Sınıf öğretmeni: sorumlu seviyeler (boşsa tümü).
 * $category_id verilirse, kategorinin "hangi sınıflar bu yoklamada görünsün" kısıtlamasıyla
 * (Yoklama Türleri sayfasında ayarlanır) da kesişim alınır.
 */
function nizamiye_general_attendance_student_ids( $term_id = 0, $user_id = 0, $category_id = 0 ) {
	$term_id = $term_id ? (int) $term_id : nizamiye_current_term_id();
	$user_id = $user_id ? (int) $user_id : get_current_user_id();

	$args = array( 'term_id' => $term_id, 'status' => 'active' );
	$students = Nizamiye_Students::query( $args );

	$teacher_grades = user_can( $user_id, 'manage_options' ) ? array() : nizamiye_class_teacher_grades( $user_id );
	$cat_grades     = $category_id ? Nizamiye_Attendance_Types::get_grade_levels( $category_id ) : array();

	$ids = array();
	foreach ( $students as $s ) {
		$g = (int) ( $s->grade_level ?? 0 );
		if ( $teacher_grades && ! in_array( $g, $teacher_grades, true ) ) {
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
// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- salt görüntüleme/dışa aktarma filtreleri (GET), durum değişikliği yok; ham değer yalnızca regex biçim doğrulaması için okunur, kullanılan değer sanitize_text_field(wp_unslash()) ile temizlenir.
function nizamiye_resolve_report_dates( $default_from = '' ) {
	$mode     = isset( $_GET['datemode'] ) && 'month' === $_GET['datemode'] ? 'month' : 'range';
	$cur_year = (int) current_time( 'Y' );

	if ( 'month' === $mode ) {
		$month = isset( $_GET['rmonth'] ) ? max( 0, min( 12, (int) $_GET['rmonth'] ) ) : 0;
		$year  = isset( $_GET['ryear'] ) ? max( 2000, min( 2100, (int) $_GET['ryear'] ) ) : $cur_year;
		if ( $month > 0 ) {
			$from = sprintf( '%04d-%02d-01', $year, $month );
			$to   = gmdate( 'Y-m-t', strtotime( $from ) );
		} else {
			$from = sprintf( '%04d-01-01', $year );
			$to   = sprintf( '%04d-12-31', $year );
		}
		return array( 'mode' => 'month', 'month' => $month, 'year' => $year, 'from' => $from, 'to' => $to );
	}

	$from = isset( $_GET['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['from'] ) )
		? sanitize_text_field( wp_unslash( $_GET['from'] ) )
		: ( $default_from ?: gmdate( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) ) );
	$to   = isset( $_GET['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['to'] ) )
		? sanitize_text_field( wp_unslash( $_GET['to'] ) )
		: current_time( 'Y-m-d' );

	return array( 'mode' => 'range', 'month' => 0, 'year' => $cur_year, 'from' => $from, 'to' => $to );
}
// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

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
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- salt görüntüleme (GET), durum değişikliği yok; çıktı esc_html ile kaçışlanır.
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
		// Mevcut sayfa parametrelerini koru.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- salt görüntüleme (GET) parametrelerini forma taşır, durum değişikliği yok; çıktı esc_attr ile kaçışlanır.
		foreach ( array( 'page', 'view', 'class_id', 'habit_id', 'student', 'cat', 'session', 'rsession', 'tab', 'rtype', 'group', 'grade', 'metric', 'from', 'to', 'datemode', 'rmonth', 'ryear', 'gview', 'subject', 'title', 'exam_date', 'exam_type' ) as $keep ) {
			if ( isset( $_GET[ $keep ] ) ) {
				echo '<input type="hidden" name="' . esc_attr( $keep ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET[ $keep ] ) ) ) . '">';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
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
		echo '<div class="sms-notice sms-notice-info"><span class="dashicons dashicons-info"></span>Henüz dönem oluşturulmadı. Başlamak için <a href="' . esc_url( admin_url( 'admin.php?page=sms-terms' ) ) . '">Dönemler</a> sayfasından bir dönem açın (örn. 2025-2026).</div>';
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
		$url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : admin_url( 'admin.php?page=sms-dashboard' );
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
