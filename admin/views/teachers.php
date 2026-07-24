<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_teachers = nizamiye_users_by_role( 'nizamiye_teacher' );
$nizamiye_term_id  = nizamiye_current_term_id();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_edit_id  = ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) ) && isset( $_GET['user'] ) ? (int) $_GET['user'] : 0;
$nizamiye_edit     = $nizamiye_edit_id ? get_userdata( $nizamiye_edit_id ) : null;
if ( $nizamiye_edit && ! in_array( 'nizamiye_teacher', (array) $nizamiye_edit->roles, true ) ) {
	$nizamiye_edit = null;
}
$nizamiye_edit_ct       = $nizamiye_edit ? nizamiye_is_class_teacher( (int) $nizamiye_edit->ID ) : false;
$nizamiye_edit_ct_grade = $nizamiye_edit ? nizamiye_class_teacher_grades( (int) $nizamiye_edit->ID ) : array();
$nizamiye_settings      = nizamiye_get_settings();
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Öğretmenler', 'Öğretmen hesaplarını yönetin; derslik atamaları Derslikler sayfasından yapılır.' ); ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Öğretmen Listesi</h2></div>
			<?php if ( $nizamiye_teachers ) : ?>
				<table class="sms-table">
					<thead><tr><th>Öğretmen</th><th>E-posta</th><th>Derslik</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_teachers as $nizamiye_t ) : ?>
						<tr>
							<td class="sms-name-cell"><?php echo wp_kses_post( nizamiye_avatar( $nizamiye_t->display_name ) ); ?><strong><?php echo esc_html( $nizamiye_t->display_name ); ?></strong><?php echo nizamiye_is_class_teacher( (int) $nizamiye_t->ID ) ? ' <span class="sms-badge sms-badge-green">Sınıf Öğretmeni</span>' : ''; ?></td>
							<td class="sms-muted"><?php echo esc_html( $nizamiye_t->user_email ); ?></td>
							<td><?php echo (int) Nizamiye_Classes::count_for_term( $nizamiye_term_id, (int) $nizamiye_t->ID ); ?></td>
							<td class="sms-actions-cell">
								<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-teachers&user=' . (int) $nizamiye_t->ID ) ) ); ?>">Düzenle</a>
								<?php nizamiye_form_open( 'nizamiye_delete_user', 'sms-inline sms-confirm' ); nizamiye_back_url_field( admin_url( 'admin.php?page=nizamiye-teachers' ) ); ?>
									<input type="hidden" name="user_id" value="<?php echo (int) $nizamiye_t->ID; ?>">
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
			<div class="sms-card-head"><h2><?php echo $nizamiye_edit ? 'Öğretmeni Düzenle' : 'Yeni Öğretmen'; ?></h2></div>
			<div class="sms-pad">
				<?php nizamiye_form_open( 'nizamiye_save_user' ); nizamiye_back_url_field( admin_url( 'admin.php?page=nizamiye-teachers' ) ); ?>
					<input type="hidden" name="nizamiye_role" value="nizamiye_teacher">
					<input type="hidden" name="user_id" value="<?php echo $nizamiye_edit ? (int) $nizamiye_edit->ID : 0; ?>">
					<div class="sms-field"><label>Ad Soyad *</label><input type="text" name="display_name" value="<?php echo esc_attr( $nizamiye_edit->display_name ?? '' ); ?>" required></div>
					<?php if ( ! $nizamiye_edit ) : ?>
						<div class="sms-field"><label>Kullanıcı Adı *</label><input type="text" name="username" required autocomplete="off"></div>
					<?php endif; ?>
					<div class="sms-field"><label>E-posta *</label><input type="email" name="email" value="<?php echo esc_attr( $nizamiye_edit->user_email ?? '' ); ?>" required autocomplete="off"></div>
					<div class="sms-field"><label>Şifre <?php echo $nizamiye_edit ? '(değiştirmek için doldurun)' : '(boşsa otomatik üretilir)'; ?></label><input type="text" name="password" autocomplete="new-password"></div>

					<div class="sms-field sms-ct-block">
						<label class="sms-check">
							<input type="checkbox" name="is_class_teacher" value="1" data-sms-ct-toggle <?php checked( $nizamiye_edit_ct ); ?>>
							<span><strong>Sınıf öğretmeni</strong> — genel yoklama (namaz, temizlik, telefon) alabilir</span>
						</label>
						<div class="sms-ct-grades" data-sms-ct-grades <?php echo $nizamiye_edit_ct ? '' : 'style="display:none"'; ?>>
							<label class="sms-muted">Sorumlu sınıf seviyeleri (boş = tümü):</label>
							<div class="sms-grade-checks">
								<?php for ( $nizamiye_g = (int) $nizamiye_settings['min_grade']; $nizamiye_g <= (int) $nizamiye_settings['max_grade']; $nizamiye_g++ ) : ?>
									<label class="sms-grade-check">
										<input type="checkbox" name="ct_grades[]" value="<?php echo (int) $nizamiye_g; ?>" <?php checked( in_array( $nizamiye_g, $nizamiye_edit_ct_grade, true ) ); ?>>
										<span><?php echo (int) $nizamiye_g; ?></span>
									</label>
								<?php endfor; ?>
							</div>
						</div>
					</div>

					<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $nizamiye_edit ? 'Güncelle' : 'Öğretmen Ekle'; ?></button>
					<?php if ( $nizamiye_edit ) : ?>
						<a class="sms-btn sms-btn-ghost sms-btn-block sms-mt-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-teachers' ) ); ?>">Vazgeç</a>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
</div>
