<?php
defined( 'ABSPATH' ) || exit;

// Veli/öğrenci bu sayfayı açarsa kendi görünümüne yönlendirilir.
if ( ! current_user_can( 'sms_teach' ) ) {
	include __DIR__ . '/my-children.php';
	return;
}

$term_id = sms_current_term_id();
$teacher = sms_is_teacher();
$scores  = $term_id ? SMS_Reports::student_scores( $term_id, $teacher ? sms_teacher_student_ids() : null ) : array();
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Raporlar', 'Öğrencilerin dönem geneli başarı özeti; detaylı karne için öğrenciye tıklayın.' ); ?>

	<div class="sms-card">
		<?php if ( $scores ) : ?>
			<table class="sms-table">
				<thead><tr><th>#</th><th>Öğrenci</th><th>Sınıf</th><th>Devam</th><th>Alışkanlık</th><th>Not Ort.</th><th>Bileşik Skor</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $scores as $i => $row ) : $s = $row['student']; ?>
					<tr>
						<td class="sms-muted">#<?php echo $i + 1; ?></td>
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
		<?php else : ?>
			<div class="sms-empty">
				<span class="dashicons dashicons-chart-bar"></span>
				<h2>Henüz rapor verisi yok</h2>
				<p>Skorların oluşması için yoklama, not veya alışkanlık kaydı girilmesi gerekir.</p>
			</div>
		<?php endif; ?>
	</div>
</div>
