<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_parents = nizamiye_users_by_role( 'nizamiye_parent' );
$nizamiye_term_id = nizamiye_current_term_id();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_edit_id = ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) ) && isset( $_GET['user'] ) ? (int) $_GET['user'] : 0;
$nizamiye_edit    = $nizamiye_edit_id ? get_userdata( $nizamiye_edit_id ) : null;
if ( $nizamiye_edit && ! in_array( 'nizamiye_parent', (array) $nizamiye_edit->roles, true ) ) {
	$nizamiye_edit = null;
}
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Veliler', 'Veli hesaplarını yönetin; öğrenci-veli eşleştirmesi öğrenci kartından yapılır. Bir velinin birden çok öğrencisi olabilir.' ); ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Veli Listesi</h2></div>
			<?php if ( $nizamiye_parents ) : ?>
				<table class="sms-table">
					<thead><tr><th>Veli</th><th>E-posta</th><th>Öğrencileri</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_parents as $nizamiye_p ) : $nizamiye_children = Nizamiye_Students::children_of( (int) $nizamiye_p->ID ); ?>
						<tr>
							<td class="sms-name-cell"><?php echo wp_kses_post( nizamiye_avatar( $nizamiye_p->display_name ) ); ?><strong><?php echo esc_html( $nizamiye_p->display_name ); ?></strong></td>
							<td class="sms-muted"><?php echo esc_html( $nizamiye_p->user_email ); ?></td>
							<td>
								<?php if ( $nizamiye_children ) : ?>
									<?php foreach ( $nizamiye_children as $nizamiye_c ) : ?>
										<a class="sms-badge sms-badge-indigo" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_c->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><?php echo esc_html( nizamiye_student_name( $nizamiye_c ) ); ?></a>
									<?php endforeach; ?>
								<?php else : ?>
									<span class="sms-muted">—</span>
								<?php endif; ?>
							</td>
							<td class="sms-actions-cell">
								<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-parents&user=' . (int) $nizamiye_p->ID ) ) ); ?>">Düzenle</a>
								<?php nizamiye_form_open( 'nizamiye_delete_user', 'sms-inline sms-confirm' ); nizamiye_back_url_field( admin_url( 'admin.php?page=nizamiye-parents' ) ); ?>
									<input type="hidden" name="user_id" value="<?php echo (int) $nizamiye_p->ID; ?>">
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
			<div class="sms-card-head"><h2><?php echo $nizamiye_edit ? 'Veliyi Düzenle' : 'Yeni Veli'; ?></h2></div>
			<div class="sms-pad">
				<?php nizamiye_form_open( 'nizamiye_save_user' ); nizamiye_back_url_field( admin_url( 'admin.php?page=nizamiye-parents' ) ); ?>
					<input type="hidden" name="nizamiye_role" value="nizamiye_parent">
					<input type="hidden" name="user_id" value="<?php echo $nizamiye_edit ? (int) $nizamiye_edit->ID : 0; ?>">
					<div class="sms-field"><label>Ad Soyad *</label><input type="text" name="display_name" value="<?php echo esc_attr( $nizamiye_edit->display_name ?? '' ); ?>" required></div>
					<?php if ( ! $nizamiye_edit ) : ?>
						<div class="sms-field"><label>Kullanıcı Adı *</label><input type="text" name="username" required autocomplete="off"></div>
					<?php endif; ?>
					<div class="sms-field"><label>E-posta *</label><input type="email" name="email" value="<?php echo esc_attr( $nizamiye_edit->user_email ?? '' ); ?>" required autocomplete="off"></div>
					<div class="sms-field"><label>Şifre <?php echo $nizamiye_edit ? '(değiştirmek için doldurun)' : '(boşsa otomatik üretilir)'; ?></label><input type="text" name="password" autocomplete="new-password"></div>
					<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $nizamiye_edit ? 'Güncelle' : 'Veli Ekle'; ?></button>
					<?php if ( $nizamiye_edit ) : ?>
						<a class="sms-btn sms-btn-ghost sms-btn-block sms-mt-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-parents' ) ); ?>">Vazgeç</a>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
</div>
