<?php
defined( 'ABSPATH' ) || exit;

$habit_id = isset( $_GET['habit_id'] ) ? (int) $_GET['habit_id'] : 0;
$habit    = $habit_id ? SMS_Habits::get( $habit_id ) : null;
if ( ! $habit ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>Alışkanlık bulunamadı</h2></div></div>';
	return;
}
$term_id = (int) $habit->term_id;
$date    = isset( $_GET['log_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['log_date'] ) ? sanitize_text_field( wp_unslash( $_GET['log_date'] ) ) : current_time( 'Y-m-d' );

$students = SMS_Habits::students( $habit_id );
// Öğretmen (oluşturan değilse) yalnızca kendi öğrencilerini doldurur.
if ( sms_is_teacher() && (int) $habit->created_by !== get_current_user_id() ) {
	$my_ids   = sms_teacher_student_ids( 0, $term_id );
	$students = array_values( array_filter( $students, function ( $s ) use ( $my_ids ) {
		return in_array( (int) $s->id, $my_ids, true );
	} ) );
}
$logs      = SMS_Habits::logs_for_date( $habit_id, $date );
$is_scale  = 'scale' === $habit->track_type;
$is_reading = 'reading' === $habit->track_type;
$max       = $is_scale ? (int) $habit->scale_max : 1;

$subtitle = 'Yaptı / yapmadı takibi';
if ( $is_scale ) {
	$subtitle = 'Dereceli takip (1–' . $max . '); boş bırakılan öğrenci için kayıt girilmez.';
} elseif ( $is_reading ) {
	$subtitle = 'Kitap adı ve o gün okunan sayfa sayısını girin (daha önce girilen kitaplar öneri olarak çıkar); sayfa boş bırakılan öğrenci için kayıt girilmez.';
}
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Takip: ' . $habit->name, $subtitle ); ?>

	<p><a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-habits&sms_term=' . $term_id ) ); ?>">← Alışkanlık listesine dön</a></p>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<input type="hidden" name="page" value="sms-habits">
				<input type="hidden" name="view" value="track">
				<input type="hidden" name="habit_id" value="<?php echo (int) $habit_id; ?>">
				<input type="hidden" name="sms_term" value="<?php echo (int) $term_id; ?>">
				<label class="sms-muted">Tarih</label>
				<input type="date" name="log_date" value="<?php echo esc_attr( $date ); ?>" onchange="this.form.submit()">
				<button type="submit" class="sms-btn sms-btn-ghost">Getir</button>
			</form>
		</div>
	</div>

	<div class="sms-card sms-mt">
		<div class="sms-card-head">
			<h2><?php echo esc_html( sms_format_date( $date ) ); ?></h2>
			<?php if ( ! $is_scale && ! $is_reading ) : ?>
				<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-all-done>Tümünü "Yaptı" işaretle</button>
			<?php endif; ?>
		</div>
		<?php if ( $students ) : ?>
			<?php sms_form_open( 'sms_save_habit_logs' ); sms_back_url_field(); ?>
				<input type="hidden" name="habit_id" value="<?php echo (int) $habit_id; ?>">
				<input type="hidden" name="log_date" value="<?php echo esc_attr( $date ); ?>">
				<table class="sms-table">
					<thead><tr><th>Öğrenci</th><th><?php
						if ( $is_scale ) {
							echo 'Derece (1–' . esc_html( $max ) . ')';
						} elseif ( $is_reading ) {
							echo 'Kitap Adı';
						} else {
							echo 'Durum';
						}
					?></th><th><?php echo $is_reading ? 'Sayfa Sayısı' : 'Not'; ?></th></tr></thead>
					<tbody>
					<?php foreach ( $students as $s ) :
						$log = $logs[ (int) $s->id ] ?? null;
						?>
						<tr>
							<td class="sms-name-cell"><?php echo sms_avatar( sms_student_name( $s ) ); // phpcs:ignore ?><strong><?php echo esc_html( sms_student_name( $s ) ); ?></strong></td>
							<?php if ( $is_reading ) :
								$book_options = SMS_Habits::book_titles_for_student( $habit_id, (int) $s->id );
								?>
								<td>
									<input type="text" class="sms-input-sm" name="log_note[<?php echo (int) $s->id; ?>]" value="<?php echo esc_attr( $log->note ?? '' ); ?>" placeholder="Kitap adı…" list="sms-books-<?php echo (int) $s->id; ?>" autocomplete="off">
									<?php if ( $book_options ) : ?>
										<datalist id="sms-books-<?php echo (int) $s->id; ?>">
											<?php foreach ( $book_options as $b ) : ?><option value="<?php echo esc_attr( $b ); ?>"><?php endforeach; ?>
										</datalist>
									<?php endif; ?>
								</td>
								<td><input type="number" class="sms-input-sm" name="log_value[<?php echo (int) $s->id; ?>]" min="0" max="3000" value="<?php echo esc_attr( $log->value ?? '' ); ?>" placeholder="Sayfa"></td>
							<?php else : ?>
							<td>
								<?php if ( $is_scale ) : ?>
									<div class="sms-seg sms-seg-scale">
										<label class="sms-seg-item">
											<input type="radio" name="log_value[<?php echo (int) $s->id; ?>]" value="" <?php checked( null === $log ); ?>>
											<span>—</span>
										</label>
										<?php for ( $v = 1; $v <= $max; $v++ ) : ?>
											<label class="sms-seg-item sms-seg-num">
												<input type="radio" name="log_value[<?php echo (int) $s->id; ?>]" value="<?php echo (int) $v; ?>" <?php checked( $log && (int) $log->value === $v ); ?>>
												<span><?php echo (int) $v; ?></span>
											</label>
										<?php endfor; ?>
									</div>
								<?php else : ?>
									<div class="sms-seg">
										<label class="sms-seg-item">
											<input type="radio" name="log_value[<?php echo (int) $s->id; ?>]" value="" <?php checked( null === $log ); ?>>
											<span>—</span>
										</label>
										<label class="sms-seg-item sms-seg-present">
											<input type="radio" name="log_value[<?php echo (int) $s->id; ?>]" value="1" <?php checked( $log && (int) $log->value >= 1 ); ?>>
											<span>✓ Yaptı</span>
										</label>
										<label class="sms-seg-item sms-seg-absent">
											<input type="radio" name="log_value[<?php echo (int) $s->id; ?>]" value="0" <?php checked( $log && 0 === (int) $log->value ); ?>>
											<span>✗ Yapmadı</span>
										</label>
									</div>
								<?php endif; ?>
							</td>
							<td><input type="text" class="sms-input-sm" name="log_note[<?php echo (int) $s->id; ?>]" value="<?php echo esc_attr( $log->note ?? '' ); ?>" placeholder="Not…"></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="sms-pad">
					<button type="submit" class="sms-btn sms-btn-primary">Takibi Kaydet</button>
					<span class="sms-muted sms-ml"><?php echo $is_reading ? 'Sayfa sayısı boş bırakılan öğrenci için kayıt girilmez.' : '"—" seçili öğrenciler için kayıt girilmez / mevcut kayıt silinir.'; ?></span>
				</div>
			</form>
		<?php else : ?>
			<div class="sms-empty"><span class="dashicons dashicons-groups"></span><h2>Atanmış öğrenci yok</h2><p>Önce alışkanlığa öğrenci atayın.</p></div>
		<?php endif; ?>
	</div>
</div>
