<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bir yoklama türünün gün/hafta/ay raporu — velilere gönderilecek toplu isim
 * listesi. Ekrandaki önizleme ile PDF aynı şablonu (print/_roster-report-body.php)
 * ve aynı CSS'i kullanır; PNG/JPG de bu önizlemeden üretilir.
 */

$nizamiye_has_nonce = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_cat_id   = $nizamiye_has_nonce && isset( $_GET['cat'] ) ? (int) $_GET['cat'] : 0;
$nizamiye_sess_id  = $nizamiye_has_nonce && isset( $_GET['session'] ) ? (int) $_GET['session'] : 0;
$nizamiye_class_id = $nizamiye_has_nonce && isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
$nizamiye_grade    = $nizamiye_has_nonce && isset( $_GET['grade'] ) ? (int) $_GET['grade'] : 0;
$nizamiye_sec_f    = $nizamiye_has_nonce && isset( $_GET['section'] ) ? nizamiye_normalize_section( wp_unslash( $_GET['section'] ) ) : '';
$nizamiye_orient   = $nizamiye_has_nonce && isset( $_GET['orient'] ) && 'landscape' === $_GET['orient'] ? 'landscape' : 'portrait';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$nizamiye_term_id  = nizamiye_current_term_id();
$nizamiye_category = $nizamiye_cat_id ? Nizamiye_Attendance_Types::get_category( $nizamiye_cat_id ) : null;

if ( ! $nizamiye_category ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>Yoklama türü bulunamadı</h2></div></div>';
	return;
}

$nizamiye_sessions = Nizamiye_Attendance_Types::sessions( $nizamiye_cat_id );
$nizamiye_period   = nizamiye_resolve_period();
$nizamiye_sheet    = Nizamiye_Sheet::attendance_sheet(
	$nizamiye_term_id,
	$nizamiye_cat_id,
	$nizamiye_sess_id,
	$nizamiye_class_id,
	$nizamiye_period,
	$nizamiye_grade,
	$nizamiye_sec_f
);

if ( is_wp_error( $nizamiye_sheet ) ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>' . esc_html( $nizamiye_sheet->get_error_message() ) . '</h2></div></div>';
	return;
}

$nizamiye_back_url = nizamiye_view_nonce_url( add_query_arg(
	array(
		'page'          => 'nizamiye-attendance',
		'cat'           => $nizamiye_cat_id,
		'session'       => $nizamiye_sess_id,
		'class_id'      => $nizamiye_class_id,
		'nizamiye_term' => $nizamiye_term_id,
	),
	admin_url( 'admin.php' )
) );

$nizamiye_pdf_url = wp_nonce_url(
	add_query_arg(
		array(
			'action'        => 'nizamiye_print_roster',
			'kind'          => 'attendance',
			'cat'           => $nizamiye_cat_id,
			'session'       => $nizamiye_sess_id,
			'class_id'      => $nizamiye_class_id,
			'pmode'         => $nizamiye_period['mode'],
			'pdate'         => $nizamiye_period['date'],
			'pweek'         => $nizamiye_period['week_start'],
			'pmonth'        => $nizamiye_period['month'],
			'pyear'         => $nizamiye_period['year'],
			'grade'         => $nizamiye_grade,
			'section'       => $nizamiye_sec_f,
			'orient'        => $nizamiye_orient,
			'nizamiye_term' => $nizamiye_term_id,
		),
		admin_url( 'admin-post.php' )
	),
	'nizamiye_print_roster'
);
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Rapor: ' . $nizamiye_category->name, 'Velilere gönderilecek toplu liste. Dönemi seçip PDF, PNG veya JPG olarak indirin.' ); ?>

	<p><a class="sms-back-link" href="<?php echo esc_url( $nizamiye_back_url ); ?>">← Yoklama ekranına dön</a></p>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<?php nizamiye_view_nonce_field(); ?>
				<input type="hidden" name="page" value="nizamiye-attendance">
				<input type="hidden" name="view" value="report">
				<input type="hidden" name="cat" value="<?php echo (int) $nizamiye_cat_id; ?>">
				<input type="hidden" name="class_id" value="<?php echo (int) $nizamiye_class_id; ?>">
				<input type="hidden" name="nizamiye_term" value="<?php echo (int) $nizamiye_term_id; ?>">

				<?php if ( count( $nizamiye_sessions ) > 1 ) : ?>
					<label class="sms-muted">Oturum</label>
					<select name="session" onchange="this.form.submit()">
						<option value="0" <?php selected( $nizamiye_sess_id, 0 ); ?>>Tüm oturumlar</option>
						<?php foreach ( $nizamiye_sessions as $nizamiye_sess ) : ?>
							<option value="<?php echo (int) $nizamiye_sess->id; ?>" <?php selected( $nizamiye_sess_id, (int) $nizamiye_sess->id ); ?>>
								<?php echo esc_html( $nizamiye_sess->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="hidden" name="session" value="<?php echo (int) $nizamiye_sess_id; ?>">
				<?php endif; ?>

				<?php nizamiye_period_filter_fields( $nizamiye_period ); ?>

				<?php if ( 'general' === $nizamiye_category->scope ) : ?>
					<label class="sms-muted">Sınıf</label>
					<select name="grade" onchange="this.form.submit()">
						<option value="0">Tüm sınıflar</option>
						<?php foreach ( Nizamiye_Students::grades_in_term( $nizamiye_term_id ) as $nizamiye_g ) : ?>
							<option value="<?php echo (int) $nizamiye_g; ?>" <?php selected( $nizamiye_grade, (int) $nizamiye_g ); ?>>
								<?php echo esc_html( nizamiye_grade_label( (int) $nizamiye_g ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>

				<label class="sms-muted">Şube</label>
				<select name="section" onchange="this.form.submit()">
					<option value="">Tüm şubeler</option>
					<?php foreach ( Nizamiye_Students::sections_in_term( $nizamiye_term_id ) as $nizamiye_sec ) : ?>
						<option value="<?php echo esc_attr( $nizamiye_sec ); ?>" <?php selected( $nizamiye_sec_f, $nizamiye_sec ); ?>><?php echo esc_html( $nizamiye_sec ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="sms-muted">PDF yönü</label>
				<select name="orient" onchange="this.form.submit()">
					<option value="portrait" <?php selected( $nizamiye_orient, 'portrait' ); ?>>Dikey</option>
					<option value="landscape" <?php selected( $nizamiye_orient, 'landscape' ); ?>>Yatay</option>
				</select>

				<button type="submit" class="sms-btn sms-btn-ghost">Getir</button>
			</form>
		</div>
	</div>

	<div class="sms-card sms-mt">
		<div class="sms-pad">
			<?php nizamiye_sheet_download_bar( $nizamiye_pdf_url, Nizamiye_Sheet::filename_base( $nizamiye_sheet ) ); ?>
			<div class="sms-sheet-wrap">
				<div class="sheet <?php echo esc_attr( $nizamiye_sheet['density'] ); ?>" data-sms-sheet>
					<?php include NIZAMIYE_DIR . 'admin/views/print/_roster-report-body.php'; ?>
				</div>
			</div>
		</div>
	</div>
</div>
