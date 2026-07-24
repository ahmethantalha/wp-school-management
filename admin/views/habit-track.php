<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_has_nonce = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_habit_id = $nizamiye_has_nonce && isset( $_GET['habit_id'] ) ? (int) $_GET['habit_id'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$nizamiye_habit    = $nizamiye_habit_id ? Nizamiye_Habits::get( $nizamiye_habit_id ) : null;
if ( ! $nizamiye_habit ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>Alışkanlık bulunamadı</h2></div></div>';
	return;
}
$nizamiye_term_id = (int) $nizamiye_habit->term_id;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- yukarıdaki wp_verify_nonce() ile doğrulanır; ham değer yalnızca regex biçim kontrolü için okunur, kullanılan değer sanitize_text_field(wp_unslash()) ile temizlenir.
$nizamiye_date    = $nizamiye_has_nonce && isset( $_GET['log_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['log_date'] ) ) ? sanitize_text_field( wp_unslash( $_GET['log_date'] ) ) : current_time( 'Y-m-d' );

$nizamiye_students = Nizamiye_Habits::students( $nizamiye_habit_id );
// Öğretmen (oluşturan değilse) yalnızca kendi öğrencilerini doldurur.
if ( nizamiye_is_teacher() && (int) $nizamiye_habit->created_by !== get_current_user_id() ) {
	$nizamiye_my_ids   = nizamiye_teacher_student_ids( 0, $nizamiye_term_id );
	$nizamiye_students = array_values( array_filter( $nizamiye_students, function ( $nizamiye_s ) use ( $nizamiye_my_ids ) {
		return in_array( (int) $nizamiye_s->id, $nizamiye_my_ids, true );
	} ) );
}
$nizamiye_logs      = Nizamiye_Habits::logs_for_date( $nizamiye_habit_id, $nizamiye_date );
$nizamiye_is_scale  = 'scale' === $nizamiye_habit->track_type;
$nizamiye_is_reading = 'reading' === $nizamiye_habit->track_type;
$nizamiye_max       = $nizamiye_is_scale ? (int) $nizamiye_habit->scale_max : 1;

$nizamiye_subtitle = 'Yaptı / yapmadı takibi';
if ( $nizamiye_is_scale ) {
	$nizamiye_subtitle = 'Dereceli takip (1–' . $nizamiye_max . '); boş bırakılan öğrenci için kayıt girilmez.';
} elseif ( $nizamiye_is_reading ) {
	$nizamiye_subtitle = 'Kitap adı ve o gün okunan sayfa sayısını girin (daha önce girilen kitaplar öneri olarak çıkar); sayfa boş bırakılan öğrenci için kayıt girilmez.';
}
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Takip: ' . $nizamiye_habit->name, $nizamiye_subtitle ); ?>

	<p><a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-habits&nizamiye_term=' . $nizamiye_term_id ) ); ?>">← Alışkanlık listesine dön</a></p>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<?php nizamiye_view_nonce_field(); ?>
				<input type="hidden" name="page" value="nizamiye-habits">
				<input type="hidden" name="view" value="track">
				<input type="hidden" name="habit_id" value="<?php echo (int) $nizamiye_habit_id; ?>">
				<input type="hidden" name="nizamiye_term" value="<?php echo (int) $nizamiye_term_id; ?>">
				<label class="sms-muted">Tarih</label>
				<input type="date" name="log_date" value="<?php echo esc_attr( $nizamiye_date ); ?>" onchange="this.form.submit()">
				<button type="submit" class="sms-btn sms-btn-ghost">Getir</button>
			</form>
		</div>
	</div>

	<div class="sms-card sms-mt">
		<div class="sms-card-head">
			<h2><?php echo esc_html( nizamiye_format_date( $nizamiye_date ) ); ?></h2>
			<?php if ( ! $nizamiye_is_scale && ! $nizamiye_is_reading ) : ?>
				<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-all-done>Tümünü "Yaptı" işaretle</button>
			<?php endif; ?>
		</div>
		<?php if ( $nizamiye_students ) : ?>
			<?php nizamiye_form_open( 'nizamiye_save_habit_logs' ); nizamiye_back_url_field(); ?>
				<input type="hidden" name="habit_id" value="<?php echo (int) $nizamiye_habit_id; ?>">
				<input type="hidden" name="log_date" value="<?php echo esc_attr( $nizamiye_date ); ?>">
				<table class="sms-table">
					<thead><tr><th>Öğrenci</th><th><?php
						if ( $nizamiye_is_scale ) {
							echo 'Derece (1–' . esc_html( $nizamiye_max ) . ')';
						} elseif ( $nizamiye_is_reading ) {
							echo 'Kitap Adı';
						} else {
							echo 'Durum';
						}
					?></th><th><?php echo $nizamiye_is_reading ? 'Sayfa Sayısı' : 'Not'; ?></th></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_students as $nizamiye_s ) :
						$nizamiye_log = $nizamiye_logs[ (int) $nizamiye_s->id ] ?? null;
						?>
						<tr>
							<td class="sms-name-cell"><?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_s ) ) ); ?><strong><?php echo esc_html( nizamiye_student_name( $nizamiye_s ) ); ?></strong></td>
							<?php if ( $nizamiye_is_reading ) :
								$nizamiye_book_options = Nizamiye_Habits::book_titles_for_student( $nizamiye_habit_id, (int) $nizamiye_s->id );
								?>
								<td>
									<input type="text" class="sms-input-sm" name="log_note[<?php echo (int) $nizamiye_s->id; ?>]" value="<?php echo esc_attr( $nizamiye_log->note ?? '' ); ?>" placeholder="Kitap adı…" list="sms-books-<?php echo (int) $nizamiye_s->id; ?>" autocomplete="off">
									<?php if ( $nizamiye_book_options ) : ?>
										<datalist id="sms-books-<?php echo (int) $nizamiye_s->id; ?>">
											<?php foreach ( $nizamiye_book_options as $nizamiye_b ) : ?><option value="<?php echo esc_attr( $nizamiye_b ); ?>"><?php endforeach; ?>
										</datalist>
									<?php endif; ?>
								</td>
								<td><input type="number" class="sms-input-sm" name="log_value[<?php echo (int) $nizamiye_s->id; ?>]" min="0" max="3000" value="<?php echo esc_attr( $nizamiye_log->value ?? '' ); ?>" placeholder="Sayfa"></td>
							<?php else : ?>
							<td>
								<?php if ( $nizamiye_is_scale ) : ?>
									<div class="sms-seg sms-seg-scale">
										<label class="sms-seg-item">
											<input type="radio" name="log_value[<?php echo (int) $nizamiye_s->id; ?>]" value="" <?php checked( null === $nizamiye_log ); ?>>
											<span>—</span>
										</label>
										<?php for ( $nizamiye_v = 1; $nizamiye_v <= $nizamiye_max; $nizamiye_v++ ) : ?>
											<label class="sms-seg-item sms-seg-num">
												<input type="radio" name="log_value[<?php echo (int) $nizamiye_s->id; ?>]" value="<?php echo (int) $nizamiye_v; ?>" <?php checked( $nizamiye_log && (int) $nizamiye_log->value === $nizamiye_v ); ?>>
												<span><?php echo (int) $nizamiye_v; ?></span>
											</label>
										<?php endfor; ?>
									</div>
								<?php else : ?>
									<div class="sms-seg">
										<label class="sms-seg-item">
											<input type="radio" name="log_value[<?php echo (int) $nizamiye_s->id; ?>]" value="" <?php checked( null === $nizamiye_log ); ?>>
											<span>—</span>
										</label>
										<label class="sms-seg-item sms-seg-present">
											<input type="radio" name="log_value[<?php echo (int) $nizamiye_s->id; ?>]" value="1" <?php checked( $nizamiye_log && (int) $nizamiye_log->value >= 1 ); ?>>
											<span>✓ Yaptı</span>
										</label>
										<label class="sms-seg-item sms-seg-absent">
											<input type="radio" name="log_value[<?php echo (int) $nizamiye_s->id; ?>]" value="0" <?php checked( $nizamiye_log && 0 === (int) $nizamiye_log->value ); ?>>
											<span>✗ Yapmadı</span>
										</label>
									</div>
								<?php endif; ?>
							</td>
							<td><input type="text" class="sms-input-sm" name="log_note[<?php echo (int) $nizamiye_s->id; ?>]" value="<?php echo esc_attr( $nizamiye_log->note ?? '' ); ?>" placeholder="Not…"></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="sms-pad">
					<button type="submit" class="sms-btn sms-btn-primary">Takibi Kaydet</button>
					<span class="sms-muted sms-ml"><?php echo $nizamiye_is_reading ? 'Sayfa sayısı boş bırakılan öğrenci için kayıt girilmez.' : '"—" seçili öğrenciler için kayıt girilmez / mevcut kayıt silinir.'; ?></span>
				</div>
			</form>
		<?php else : ?>
			<div class="sms-empty"><span class="dashicons dashicons-groups"></span><h2>Atanmış öğrenci yok</h2><p>Önce alışkanlığa öğrenci atayın.</p></div>
		<?php endif; ?>
	</div>
</div>
