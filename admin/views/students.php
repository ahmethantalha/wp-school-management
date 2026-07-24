<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_term_id  = nizamiye_current_term_id();
$nizamiye_has_nonce = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_grade_f  = $nizamiye_has_nonce && isset( $_GET['grade'] ) ? (int) $_GET['grade'] : 0;
$nizamiye_status_f = $nizamiye_has_nonce && isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'active';
$nizamiye_search   = $nizamiye_has_nonce && isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$nizamiye_teacher  = nizamiye_is_teacher();

$nizamiye_args = array(
	'term_id' => 'graduated' === $nizamiye_status_f ? 0 : $nizamiye_term_id,
	'grade'   => $nizamiye_grade_f,
	'status'  => $nizamiye_status_f,
	'search'  => $nizamiye_search,
);
if ( $nizamiye_teacher ) {
	$nizamiye_args['ids'] = nizamiye_teacher_student_ids();
}
$nizamiye_students = Nizamiye_Students::query( $nizamiye_args );
$nizamiye_grades   = Nizamiye_Students::grades_in_term( $nizamiye_term_id );
$nizamiye_parents  = nizamiye_users_by_role( 'nizamiye_parent' );
$nizamiye_parent_names = array();
foreach ( $nizamiye_parents as $nizamiye_p ) {
	$nizamiye_parent_names[ (int) $nizamiye_p->ID ] = $nizamiye_p->display_name;
}
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Öğrenciler', $nizamiye_teacher ? 'Dersliklerinizdeki öğrenciler' : 'Öğrenci kayıtlarını yönetin' ); ?>

	<div class="sms-toolbar">
		<form method="get" class="sms-filters">
			<?php nizamiye_view_nonce_field(); ?>
			<input type="hidden" name="page" value="nizamiye-students">
			<?php if ( $nizamiye_term_id ) : ?><input type="hidden" name="nizamiye_term" value="<?php echo (int) $nizamiye_term_id; ?>"><?php endif; ?>
			<input type="search" name="s" value="<?php echo esc_attr( $nizamiye_search ); ?>" placeholder="Ad, numara veya okul ara…">
			<select name="grade">
				<option value="0">Tüm sınıflar</option>
				<?php foreach ( $nizamiye_grades as $nizamiye_g ) : ?>
					<option value="<?php echo (int) $nizamiye_g; ?>" <?php selected( $nizamiye_grade_f, $nizamiye_g ); ?>><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<option value="active" <?php selected( $nizamiye_status_f, 'active' ); ?>>Aktif</option>
				<option value="graduated" <?php selected( $nizamiye_status_f, 'graduated' ); ?>>🎓 Mezunlar (arşiv)</option>
			</select>
			<button type="submit" class="sms-btn sms-btn-ghost">Filtrele</button>
		</form>
		<?php if ( nizamiye_is_manager() ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-students&view=edit&nizamiye_term=' . $nizamiye_term_id ) ); ?>" class="sms-btn sms-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> Yeni Öğrenci</a>
		<?php endif; ?>
	</div>

	<div class="sms-card">
		<?php if ( $nizamiye_students ) : ?>
			<table class="sms-table">
				<thead><tr><th>Öğrenci</th><th>Sınıf</th><th>Okul</th><th>Veli</th><th>Doğum Tarihi</th><th>Durum</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $nizamiye_students as $nizamiye_s ) : ?>
					<tr>
						<td class="sms-name-cell">
							<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_s ) ) ); ?>
							<div>
								<a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_s->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><strong><?php echo esc_html( nizamiye_student_name( $nizamiye_s ) ); ?></strong></a>
								<?php if ( $nizamiye_s->student_no ) : ?><span class="sms-muted">No: <?php echo esc_html( $nizamiye_s->student_no ); ?></span><?php endif; ?>
							</div>
						</td>
						<td><?php echo isset( $nizamiye_s->grade_level ) ? '<span class="sms-badge sms-badge-indigo">' . esc_html( nizamiye_grade_label( $nizamiye_s->grade_level ) ) . '</span>' : '—'; ?></td>
						<td class="sms-muted"><?php echo $nizamiye_s->school ? esc_html( $nizamiye_s->school ) : '—'; ?></td>
						<td class="sms-muted"><?php echo $nizamiye_s->parent_user_id && isset( $nizamiye_parent_names[ (int) $nizamiye_s->parent_user_id ] ) ? esc_html( $nizamiye_parent_names[ (int) $nizamiye_s->parent_user_id ] ) : '—'; ?></td>
						<td class="sms-muted"><?php echo esc_html( nizamiye_format_date( $nizamiye_s->birth_date ) ); ?></td>
						<td>
							<?php $nizamiye_badge = 'active' === $nizamiye_s->status ? 'sms-badge-green' : ( 'graduated' === $nizamiye_s->status ? 'sms-badge-amber' : '' ); ?>
							<span class="sms-badge <?php echo esc_attr( $nizamiye_badge ); ?>"><?php echo esc_html( nizamiye_student_status_label( $nizamiye_s->status ) ); ?></span>
						</td>
						<td class="sms-actions-cell">
							<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_s->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Karne</a>
							<?php if ( nizamiye_is_manager() ) : ?>
								<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-students&view=edit&student=' . (int) $nizamiye_s->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Düzenle</a>
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
				<p><?php echo 'graduated' === $nizamiye_status_f ? 'Arşivde mezun öğrenci yok.' : 'Bu filtrelerle eşleşen öğrenci yok. Yeni öğrenci ekleyebilirsiniz.'; ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
