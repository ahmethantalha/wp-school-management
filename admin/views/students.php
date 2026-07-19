<?php
defined( 'ABSPATH' ) || exit;

$term_id  = sms_current_term_id();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- salt görüntüleme filtreleri (GET), durum değişikliği yok.
$grade_f  = isset( $_GET['grade'] ) ? (int) $_GET['grade'] : 0;
$status_f = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'active';
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$teacher  = sms_is_teacher();

$args = array(
	'term_id' => 'graduated' === $status_f ? 0 : $term_id,
	'grade'   => $grade_f,
	'status'  => $status_f,
	'search'  => $search,
);
if ( $teacher ) {
	$args['ids'] = sms_teacher_student_ids();
}
$students = SMS_Students::query( $args );
$grades   = SMS_Students::grades_in_term( $term_id );
$parents  = sms_users_by_role( 'sms_parent' );
$parent_names = array();
foreach ( $parents as $p ) {
	$parent_names[ (int) $p->ID ] = $p->display_name;
}
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Öğrenciler', $teacher ? 'Dersliklerinizdeki öğrenciler' : 'Öğrenci kayıtlarını yönetin' ); ?>

	<div class="sms-toolbar">
		<form method="get" class="sms-filters">
			<input type="hidden" name="page" value="sms-students">
			<?php if ( $term_id ) : ?><input type="hidden" name="sms_term" value="<?php echo (int) $term_id; ?>"><?php endif; ?>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Ad, numara veya okul ara…">
			<select name="grade">
				<option value="0">Tüm sınıflar</option>
				<?php foreach ( $grades as $g ) : ?>
					<option value="<?php echo (int) $g; ?>" <?php selected( $grade_f, $g ); ?>><?php echo esc_html( sms_grade_label( $g ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<option value="active" <?php selected( $status_f, 'active' ); ?>>Aktif</option>
				<option value="graduated" <?php selected( $status_f, 'graduated' ); ?>>🎓 Mezunlar (arşiv)</option>
			</select>
			<button type="submit" class="sms-btn sms-btn-ghost">Filtrele</button>
		</form>
		<?php if ( sms_is_manager() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-students&view=edit&sms_term=' . $term_id ) ); ?>" class="sms-btn sms-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> Yeni Öğrenci</a>
		<?php endif; ?>
	</div>

	<div class="sms-card">
		<?php if ( $students ) : ?>
			<table class="sms-table">
				<thead><tr><th>Öğrenci</th><th>Sınıf</th><th>Okul</th><th>Veli</th><th>Doğum Tarihi</th><th>Durum</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $students as $s ) : ?>
					<tr>
						<td class="sms-name-cell">
							<?php echo sms_avatar( sms_student_name( $s ) ); // phpcs:ignore ?>
							<div>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $s->id . '&sms_term=' . $term_id ) ); ?>"><strong><?php echo esc_html( sms_student_name( $s ) ); ?></strong></a>
								<?php if ( $s->student_no ) : ?><span class="sms-muted">No: <?php echo esc_html( $s->student_no ); ?></span><?php endif; ?>
							</div>
						</td>
						<td><?php echo isset( $s->grade_level ) ? '<span class="sms-badge sms-badge-indigo">' . esc_html( sms_grade_label( $s->grade_level ) ) . '</span>' : '—'; ?></td>
						<td class="sms-muted"><?php echo $s->school ? esc_html( $s->school ) : '—'; ?></td>
						<td class="sms-muted"><?php echo $s->parent_user_id && isset( $parent_names[ (int) $s->parent_user_id ] ) ? esc_html( $parent_names[ (int) $s->parent_user_id ] ) : '—'; ?></td>
						<td class="sms-muted"><?php echo esc_html( sms_format_date( $s->birth_date ) ); ?></td>
						<td>
							<?php $badge = 'active' === $s->status ? 'sms-badge-green' : ( 'graduated' === $s->status ? 'sms-badge-amber' : '' ); ?>
							<span class="sms-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( sms_student_status_label( $s->status ) ); ?></span>
						</td>
						<td class="sms-actions-cell">
							<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $s->id . '&sms_term=' . $term_id ) ); ?>">Karne</a>
							<?php if ( sms_is_manager() ) : ?>
								<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-students&view=edit&student=' . (int) $s->id . '&sms_term=' . $term_id ) ); ?>">Düzenle</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="sms-empty">
				<span class="dashicons dashicons-groups"></span>
				<h2>Öğrenci bulunamadı</h2>
				<p><?php echo 'graduated' === $status_f ? 'Arşivde mezun öğrenci yok.' : 'Bu filtrelerle eşleşen öğrenci yok. Yeni öğrenci ekleyebilirsiniz.'; ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
