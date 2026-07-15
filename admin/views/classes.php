<?php
defined( 'ABSPATH' ) || exit;

$term_id = sms_current_term_id();
$teacher = sms_is_teacher();
$classes = $term_id ? SMS_Classes::for_term( $term_id, $teacher ? get_current_user_id() : 0 ) : array();

$teacher_names = array();
foreach ( sms_users_by_role( 'sms_teacher' ) as $t ) {
	$teacher_names[ (int) $t->ID ] = $t->display_name;
}
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Derslikler', 'Şube mantığıyla çalışır: aynı branştan birden fazla derslik açabilirsiniz (örn. Türkçe 6-A, Türkçe 6-B).' ); ?>

	<?php if ( sms_is_manager() && $term_id ) : ?>
		<div class="sms-toolbar">
			<span></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-classes&view=edit&sms_term=' . $term_id ) ); ?>" class="sms-btn sms-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> Yeni Derslik</a>
		</div>
	<?php endif; ?>

	<?php if ( $classes ) : ?>
		<div class="sms-class-grid">
			<?php foreach ( $classes as $c ) : ?>
				<div class="sms-card sms-class-card">
					<div class="sms-class-card-top">
						<span class="sms-class-emblem"><?php echo esc_html( mb_strtoupper( mb_substr( $c->subject ?: $c->name, 0, 2 ) ) ); ?></span>
						<div>
							<h3><?php echo esc_html( $c->name ); ?></h3>
							<span class="sms-muted"><?php echo esc_html( $c->subject ?: 'Branş belirtilmedi' ); ?><?php echo $c->grade_level ? ' • ' . esc_html( sms_grade_label( $c->grade_level ) ) : ''; ?></span>
						</div>
					</div>
					<div class="sms-class-card-meta">
						<span><span class="dashicons dashicons-businessperson"></span> <?php echo $c->teacher_id && isset( $teacher_names[ (int) $c->teacher_id ] ) ? esc_html( $teacher_names[ (int) $c->teacher_id ] ) : 'Öğretmen atanmadı'; ?></span>
						<span><span class="dashicons dashicons-groups"></span> <?php echo (int) $c->student_count; ?> öğrenci</span>
					</div>
					<div class="sms-class-card-actions">
						<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-classes&view=edit&class_id=' . (int) $c->id . '&sms_term=' . $term_id ) ); ?>">Yönet</a>
						<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-attendance&class_id=' . (int) $c->id . '&sms_term=' . $term_id ) ); ?>">Yoklama</a>
						<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-grades&gview=class&class_id=' . (int) $c->id . '&sms_term=' . $term_id ) ); ?>">Notlar</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="sms-card">
			<div class="sms-empty">
				<span class="dashicons dashicons-book-alt"></span>
				<h2>Henüz derslik yok</h2>
				<p><?php echo $teacher ? 'Size atanmış derslik bulunmuyor.' : 'Bu dönem için henüz derslik oluşturulmadı.'; ?></p>
			</div>
		</div>
	<?php endif; ?>
</div>
