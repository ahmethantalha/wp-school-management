<?php
defined( 'ABSPATH' ) || exit;

$parents = sms_users_by_role( 'sms_parent' );
$term_id = sms_current_term_id();
$edit_id = isset( $_GET['user'] ) ? (int) $_GET['user'] : 0;
$edit    = $edit_id ? get_userdata( $edit_id ) : null;
if ( $edit && ! in_array( 'sms_parent', (array) $edit->roles, true ) ) {
	$edit = null;
}
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Veliler', 'Veli hesaplarını yönetin; öğrenci-veli eşleştirmesi öğrenci kartından yapılır. Bir velinin birden çok öğrencisi olabilir.' ); ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Veli Listesi</h2></div>
			<?php if ( $parents ) : ?>
				<table class="sms-table">
					<thead><tr><th>Veli</th><th>E-posta</th><th>Öğrencileri</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $parents as $p ) : $children = SMS_Students::children_of( (int) $p->ID ); ?>
						<tr>
							<td class="sms-name-cell"><?php echo sms_avatar( $p->display_name ); // phpcs:ignore ?><strong><?php echo esc_html( $p->display_name ); ?></strong></td>
							<td class="sms-muted"><?php echo esc_html( $p->user_email ); ?></td>
							<td>
								<?php if ( $children ) : ?>
									<?php foreach ( $children as $c ) : ?>
										<a class="sms-badge sms-badge-indigo" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $c->id . '&sms_term=' . $term_id ) ); ?>"><?php echo esc_html( sms_student_name( $c ) ); ?></a>
									<?php endforeach; ?>
								<?php else : ?>
									<span class="sms-muted">—</span>
								<?php endif; ?>
							</td>
							<td class="sms-actions-cell">
								<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-parents&user=' . (int) $p->ID ) ); ?>">Düzenle</a>
								<?php sms_form_open( 'sms_delete_user', 'sms-inline sms-confirm' ); sms_back_url_field( admin_url( 'admin.php?page=sms-parents' ) ); ?>
									<input type="hidden" name="user_id" value="<?php echo (int) $p->ID; ?>">
									<button type="submit" class="sms-btn sms-btn-danger-ghost sms-btn-sm" data-confirm="Veli hesabı silinecek; öğrencilerin veli bağı kaldırılır. Emin misiniz?">Sil</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="sms-empty"><span class="dashicons dashicons-admin-users"></span><h2>Henüz veli yok</h2><p>Sağdaki formdan ilk veliyi ekleyin, ardından öğrenci kartından eşleştirin.</p></div>
			<?php endif; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2><?php echo $edit ? 'Veliyi Düzenle' : 'Yeni Veli'; ?></h2></div>
			<div class="sms-pad">
				<?php sms_form_open( 'sms_save_user' ); sms_back_url_field( admin_url( 'admin.php?page=sms-parents' ) ); ?>
					<input type="hidden" name="sms_role" value="sms_parent">
					<input type="hidden" name="user_id" value="<?php echo $edit ? (int) $edit->ID : 0; ?>">
					<div class="sms-field"><label>Ad Soyad *</label><input type="text" name="display_name" value="<?php echo esc_attr( $edit->display_name ?? '' ); ?>" required></div>
					<?php if ( ! $edit ) : ?>
						<div class="sms-field"><label>Kullanıcı Adı *</label><input type="text" name="username" required autocomplete="off"></div>
					<?php endif; ?>
					<div class="sms-field"><label>E-posta *</label><input type="email" name="email" value="<?php echo esc_attr( $edit->user_email ?? '' ); ?>" required autocomplete="off"></div>
					<div class="sms-field"><label>Şifre <?php echo $edit ? '(değiştirmek için doldurun)' : '(boşsa otomatik üretilir)'; ?></label><input type="text" name="password" autocomplete="new-password"></div>
					<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $edit ? 'Güncelle' : 'Veli Ekle'; ?></button>
					<?php if ( $edit ) : ?>
						<a class="sms-btn sms-btn-ghost sms-btn-block sms-mt-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-parents' ) ); ?>">Vazgeç</a>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
</div>
