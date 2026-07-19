<?php
defined( 'ABSPATH' ) || exit;

// Veli/öğrenci bu sayfayı açarsa kendi görünümüne yönlendirilir.
if ( ! current_user_can( 'sms_teach' ) ) {
	include __DIR__ . '/my-children.php';
	return;
}

$term_id = sms_current_term_id();
$teacher = sms_is_teacher();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- salt görüntüleme filtreleri (GET), durum değişikliği yok.
$grade_f = isset( $_GET['grade'] ) ? (int) $_GET['grade'] : 0;
$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$scores  = $term_id ? SMS_Reports::student_scores( $term_id, $teacher ? sms_teacher_student_ids() : null ) : array();

// Sınıf ve arama filtreleri.
if ( $grade_f || $search ) {
	$needle = $search ? SMS_Import::normalize_name( $search ) : '';
	$scores = array_values( array_filter( $scores, function ( $row ) use ( $grade_f, $needle ) {
		$s = $row['student'];
		if ( $grade_f && (int) ( $s->grade_level ?? 0 ) !== $grade_f ) {
			return false;
		}
		if ( $needle && false === strpos( SMS_Import::normalize_name( sms_student_name( $s ) ), $needle ) ) {
			return false;
		}
		return true;
	} ) );
}
$grades = $term_id ? SMS_Students::grades_in_term( $term_id ) : array();
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Karneler', 'Öğrenci seçerek detaylı karnesini görüntüleyin.' ); ?>

	<div class="sms-toolbar">
		<form method="get" class="sms-filters">
			<input type="hidden" name="page" value="sms-cards">
			<?php if ( $term_id ) : ?><input type="hidden" name="sms_term" value="<?php echo (int) $term_id; ?>"><?php endif; ?>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Öğrenci ara…">
			<select name="grade">
				<option value="0">Tüm sınıflar</option>
				<?php foreach ( $grades as $g ) : ?>
					<option value="<?php echo (int) $g; ?>" <?php selected( $grade_f, $g ); ?>><?php echo esc_html( sms_grade_label( $g ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="sms-btn sms-btn-ghost">Filtrele</button>
		</form>
	</div>

	<div class="sms-card">
		<?php if ( $scores ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank" rel="noopener" data-sms-bulk-print-form>
				<input type="hidden" name="action" value="sms_print_report_bulk">
				<?php wp_nonce_field( 'sms_print_report_bulk', '_sms_nonce' ); ?>
				<input type="hidden" name="sms_term" value="<?php echo (int) $term_id; ?>">

				<div class="sms-toolbar">
					<span class="sms-muted"><span data-sms-bulk-count>0</span> öğrenci seçili</span>
					<button type="submit" class="sms-btn sms-btn-primary sms-btn-sm" data-sms-bulk-submit disabled>
						<span class="dashicons dashicons-download"></span> Seçilenleri ZIP Olarak İndir (Her Öğrenci Ayrı PDF)
					</button>
				</div>

				<div class="sms-table-scroll">
				<table class="sms-table">
					<thead><tr>
						<th class="sms-check-col"><input type="checkbox" data-sms-bulk-all title="Tümünü seç"></th>
						<th>#</th><th>Öğrenci</th><th>Sınıf</th><th>Devam</th><th>Alışkanlık</th><th>Not Ort.</th><th>Bileşik Skor</th><th></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $scores as $i => $row ) : $s = $row['student']; ?>
						<tr>
							<td class="sms-check-col"><input type="checkbox" name="student_ids[]" value="<?php echo (int) $s->id; ?>" data-sms-bulk-item></td>
							<td class="sms-muted">#<?php echo (int) $i + 1; ?></td>
							<td class="sms-name-cell"><?php echo sms_avatar( sms_student_name( $s ) ); // phpcs:ignore ?><strong><?php echo esc_html( sms_student_name( $s ) ); ?></strong></td>
							<td><?php echo isset( $s->grade_level ) ? esc_html( sms_grade_label( $s->grade_level ) ) : '—'; ?></td>
							<td><span class="sms-score <?php echo esc_attr( sms_rate_class( $row['attendance'] ) ); ?>"><?php echo null !== $row['attendance'] ? (int) $row['attendance'] . '%' : '—'; ?></span></td>
							<td><span class="sms-score <?php echo esc_attr( sms_rate_class( $row['habit'] ) ); ?>"><?php echo null !== $row['habit'] ? (int) $row['habit'] . '%' : '—'; ?></span></td>
							<td><span class="sms-score <?php echo esc_attr( sms_rate_class( $row['grade'] ) ); ?>"><?php echo null !== $row['grade'] ? (int) $row['grade'] . '%' : '—'; ?></span></td>
							<td><span class="sms-score sms-score-big <?php echo esc_attr( sms_rate_class( $row['score'] ) ); ?>"><?php echo (int) $row['score']; ?></span></td>
							<td class="sms-actions-cell"><a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $s->id . '&sms_term=' . $term_id ) ); ?>">Karne</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			</form>
		<?php else : ?>
			<div class="sms-empty">
				<span class="dashicons dashicons-id-alt"></span>
				<h2>Karne verisi yok</h2>
				<p>Skorların oluşması için yoklama, not veya alışkanlık kaydı girilmesi gerekir.</p>
			</div>
		<?php endif; ?>
	</div>
</div>
