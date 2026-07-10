<?php
defined( 'ABSPATH' ) || exit;

$teachers = sms_users_by_role( 'sms_teacher' );
$term_id  = sms_current_term_id();
$edit_id  = isset( $_GET['user'] ) ? (int) $_GET['user'] : 0;
$edit     = $edit_id ? get_userdata( $edit_id ) : null;
if ( $edit && ! in_array( 'sms_teacher', (array) $edit->roles, true ) ) {
	$edit = null;
}
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Öğretmenler', 'Öğretmen hesaplarını yönetin; derslik atamaları Derslikler sayfasından yapılır.' ); ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Öğretmen Listesi</h2></div>
			<?php if ( $teachers ) : ?>
				<table class="sms-table">
					<thead><tr><th>Öğretmen</th><th>E-posta</th><th>Derslik</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $teachers as $t ) : ?>
						<tr>
							<td class="sms-name-cell"><?php echo sms_avatar( $t->display_name ); // phpcs:ignore ?><strong><?php echo esc_html( $t->display_name ); ?></strong></td>
							<td class="sms-muted"><?php echo esc_html( $t->user_email ); ?></td>
							<td><?php echo (int) SMS_Classes::count_for_term( $term_id, (int) $t->ID ); ?></td>
							<td class="sms-actions-cell">
								<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-teachers&user=' . (int) $t->ID ) ); ?>">Düzenle</a>
								<?php sms_form_open( 'sms_delete_user', 'sms-inline sms-confirm' ); sms_back_url_field( admin_url( 'admin.php?page=sms-teachers' ) ); ?>
									<input type="hidden" name="user_id" value="<?php echo (int) $t->ID; ?>">
									<button type="submit" class="sms-btn sms-btn-danger-ghost sms-btn-sm" data-confirm="Öğretmen hesabı silinecek; derslikleri öğretmensiz kalır. Emin misiniz?">Sil</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="sms-empty"><span class="dashicons dashicons-businessperson"></span><h2>Henüz öğretmen yok</h2><p>Sağdaki formdan ilk öğretmeni ekleyin.</p></div>
			<?php endif; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2><?php echo $edit ? 'Öğretmeni Düzenle' : 'Yeni Öğretmen'; ?></h2></div>
			<div class="sms-pad">
				<?php sms_form_open( 'sms_save_user' ); sms_back_url_field( admin_url( 'admin.php?page=sms-teachers' ) ); ?>
					<input type="hidden" name="sms_role" value="sms_teacher">
					<input type="hidden" name="user_id" value="<?php echo $edit ? (int) $edit->ID : 0; ?>">
					<div class="sms-field"><label>Ad Soyad *</label><input type="text" name="display_name" value="<?php echo esc_attr( $edit->display_name ?? '' ); ?>" required></div>
					<?php if ( ! $edit ) : ?>
						<div class="sms-field"><label>Kullanıcı Adı *</label><input type="text" name="username" required autocomplete="off"></div>
					<?php endif; ?>
					<div class="sms-field"><label>E-posta *</label><input type="email" name="email" value="<?php echo esc_attr( $edit->user_email ?? '' ); ?>" required autocomplete="off"></div>
					<div class="sms-field"><label>Şifre <?php echo $edit ? '(değiştirmek için doldurun)' : '(boşsa otomatik üretilir)'; ?></label><input type="text" name="password" autocomplete="new-password"></div>
					<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $edit ? 'Güncelle' : 'Öğretmen Ekle'; ?></button>
					<?php if ( $edit ) : ?>
						<a class="sms-btn sms-btn-ghost sms-btn-block sms-mt-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-teachers' ) ); ?>">Vazgeç</a>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
</div>
