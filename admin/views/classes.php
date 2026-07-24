<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_term_id = nizamiye_current_term_id();
$nizamiye_teacher = nizamiye_is_teacher();
$nizamiye_classes = $nizamiye_term_id ? Nizamiye_Classes::for_term( $nizamiye_term_id, $nizamiye_teacher ? get_current_user_id() : 0 ) : array();

$nizamiye_teacher_names = array();
foreach ( nizamiye_users_by_role( 'nizamiye_teacher' ) as $nizamiye_t ) {
	$nizamiye_teacher_names[ (int) $nizamiye_t->ID ] = $nizamiye_t->display_name;
}
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Derslikler', 'Şube mantığıyla çalışır: aynı branştan birden fazla derslik açabilirsiniz (örn. Türkçe 6-A, Türkçe 6-B).' ); ?>

	<?php if ( nizamiye_is_manager() && $nizamiye_term_id ) : ?>
		<div class="sms-toolbar">
			<span></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-classes&view=edit&nizamiye_term=' . $nizamiye_term_id ) ); ?>" class="sms-btn sms-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> Yeni Derslik</a>
		</div>
	<?php endif; ?>

	<?php if ( $nizamiye_classes ) : ?>
		<div class="sms-class-grid">
			<?php foreach ( $nizamiye_classes as $nizamiye_c ) : ?>
				<div class="sms-card sms-class-card">
					<div class="sms-class-card-top">
						<span class="sms-class-emblem"><?php echo esc_html( mb_strtoupper( mb_substr( $nizamiye_c->subject ?: $nizamiye_c->name, 0, 2 ) ) ); ?></span>
						<div>
							<h3><?php echo esc_html( $nizamiye_c->name ); ?></h3>
							<span class="sms-muted"><?php echo esc_html( $nizamiye_c->subject ?: 'Branş belirtilmedi' ); ?><?php echo $nizamiye_c->grade_level ? ' • ' . esc_html( nizamiye_grade_label( $nizamiye_c->grade_level ) ) : ''; ?></span>
						</div>
					</div>
					<div class="sms-class-card-meta">
						<span><span class="dashicons dashicons-businessperson"></span> <?php echo $nizamiye_c->teacher_id && isset( $nizamiye_teacher_names[ (int) $nizamiye_c->teacher_id ] ) ? esc_html( $nizamiye_teacher_names[ (int) $nizamiye_c->teacher_id ] ) : 'Öğretmen atanmadı'; ?></span>
						<span><span class="dashicons dashicons-groups"></span> <?php echo (int) $nizamiye_c->student_count; ?> öğrenci</span>
					</div>
					<div class="sms-class-card-actions">
						<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-classes&view=edit&class_id=' . (int) $nizamiye_c->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Yönet</a>
						<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-attendance&class_id=' . (int) $nizamiye_c->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Yoklama</a>
						<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-grades&gview=class&class_id=' . (int) $nizamiye_c->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Notlar</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="sms-card">
			<div class="sms-empty">
				<span class="dashicons dashicons-book-alt"></span>
				<h2>Henüz derslik yok</h2>
				<p><?php echo $nizamiye_teacher ? 'Size atanmış derslik bulunmuyor.' : 'Bu dönem için henüz derslik oluşturulmadı.'; ?></p>
			</div>
		</div>
	<?php endif; ?>
</div>
