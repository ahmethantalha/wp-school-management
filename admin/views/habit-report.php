<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tek bir alışkanlığın gün/hafta/ay raporu — velilere gönderilecek toplu isim
 * listesi. Ekrandaki önizleme ile PDF aynı şablonu (print/_roster-report-body.php)
 * ve aynı CSS'i kullanır; PNG/JPG de bu önizlemeden üretilir.
 */

$nizamiye_has_nonce = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_habit_id = $nizamiye_has_nonce && isset( $_GET['habit_id'] ) ? (int) $_GET['habit_id'] : 0;
$nizamiye_grade    = $nizamiye_has_nonce && isset( $_GET['grade'] ) ? (int) $_GET['grade'] : 0;
$nizamiye_orient   = $nizamiye_has_nonce && isset( $_GET['orient'] ) && 'landscape' === $_GET['orient'] ? 'landscape' : 'portrait';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$nizamiye_habit = $nizamiye_habit_id ? Nizamiye_Habits::get( $nizamiye_habit_id ) : null;
if ( ! $nizamiye_habit ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>Alışkanlık bulunamadı</h2></div></div>';
	return;
}

$nizamiye_term_id = (int) $nizamiye_habit->term_id;
$nizamiye_period  = nizamiye_resolve_period();
$nizamiye_sheet   = Nizamiye_Sheet::habit_sheet( $nizamiye_habit_id, $nizamiye_period, $nizamiye_grade, $nizamiye_term_id );

if ( is_wp_error( $nizamiye_sheet ) ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>' . esc_html( $nizamiye_sheet->get_error_message() ) . '</h2></div></div>';
	return;
}

$nizamiye_pdf_url = wp_nonce_url(
	add_query_arg(
		array(
			'action'        => 'nizamiye_print_roster',
			'kind'          => 'habit',
			'habit_id'      => $nizamiye_habit_id,
			'pmode'         => $nizamiye_period['mode'],
			'pdate'         => $nizamiye_period['date'],
			'pweek'         => $nizamiye_period['week_start'],
			'pmonth'        => $nizamiye_period['month'],
			'pyear'         => $nizamiye_period['year'],
			'grade'         => $nizamiye_grade,
			'orient'        => $nizamiye_orient,
			'nizamiye_term' => $nizamiye_term_id,
		),
		admin_url( 'admin-post.php' )
	),
	'nizamiye_print_roster'
);
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Rapor: ' . $nizamiye_habit->name, 'Velilere gönderilecek toplu liste. Dönemi seçip PDF, PNG veya JPG olarak indirin.' ); ?>

	<p>
		<a class="sms-back-link" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-habits&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">← Alışkanlık listesine dön</a>
	</p>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<?php nizamiye_view_nonce_field(); ?>
				<input type="hidden" name="page" value="nizamiye-habits">
				<input type="hidden" name="view" value="report">
				<input type="hidden" name="habit_id" value="<?php echo (int) $nizamiye_habit_id; ?>">
				<input type="hidden" name="nizamiye_term" value="<?php echo (int) $nizamiye_term_id; ?>">

				<?php nizamiye_period_filter_fields( $nizamiye_period ); ?>

				<label class="sms-muted">Sınıf</label>
				<select name="grade" onchange="this.form.submit()">
					<option value="0">Tüm sınıflar</option>
					<?php foreach ( Nizamiye_Students::grades_in_term( $nizamiye_term_id ) as $nizamiye_g ) : ?>
						<option value="<?php echo (int) $nizamiye_g; ?>" <?php selected( $nizamiye_grade, (int) $nizamiye_g ); ?>>
							<?php echo esc_html( nizamiye_grade_label( (int) $nizamiye_g ) ); ?>
						</option>
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
