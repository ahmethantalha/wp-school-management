<?php
defined( 'ABSPATH' ) || exit;

if ( ! nizamiye_is_manager() ) {
	wp_die( 'Öğrenci düzenleme yetkiniz yok.' );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$student_id = ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) ) && isset( $_GET['student'] ) ? (int) $_GET['student'] : 0;
$student    = $student_id ? Nizamiye_Students::get( $student_id ) : null;
$term_id    = nizamiye_current_term_id();
$enrollment = $student ? Nizamiye_Students::enrollment( $student_id, $term_id ) : null;
$settings   = nizamiye_get_settings();
$parents    = nizamiye_users_by_role( 'nizamiye_parent' );
$classes    = $student ? Nizamiye_Classes::for_student( $student_id, $term_id ) : array();
$linked_user = $student && $student->user_id ? get_userdata( (int) $student->user_id ) : null;
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( $student ? 'Öğrenciyi Düzenle' : 'Yeni Öğrenci', $student ? nizamiye_student_name( $student ) : 'Yeni öğrenci kaydı oluşturun' ); ?>

	<p><a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-students&nizamiye_term=' . $term_id ) ); ?>">← Öğrenci listesine dön</a></p>

	<div class="sms-grid-2 sms-grid-uneven">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Öğrenci Bilgileri</h2></div>
			<div class="sms-pad">
				<?php nizamiye_form_open( 'nizamiye_save_student' ); nizamiye_back_url_field(); ?>
					<input type="hidden" name="student_id" value="<?php echo (int) $student_id; ?>">
					<input type="hidden" name="term_id" value="<?php echo (int) $term_id; ?>">

					<div class="sms-field-row">
						<div class="sms-field"><label>Ad *</label><input type="text" name="first_name" value="<?php echo esc_attr( $student->first_name ?? '' ); ?>" required></div>
						<div class="sms-field"><label>Soyad *</label><input type="text" name="last_name" value="<?php echo esc_attr( $student->last_name ?? '' ); ?>" required></div>
					</div>
					<div class="sms-field-row">
						<div class="sms-field"><label>Doğum Tarihi</label><input type="date" name="birth_date" value="<?php echo esc_attr( $student->birth_date ?? '' ); ?>"></div>
						<div class="sms-field"><label>Öğrenci No</label><input type="text" name="student_no" value="<?php echo esc_attr( $student->student_no ?? '' ); ?>"></div>
					</div>
					<div class="sms-field-row">
						<div class="sms-field"><label>Okulu</label><input type="text" name="school" value="<?php echo esc_attr( $student->school ?? '' ); ?>" placeholder="Örn. Atatürk Ortaokulu"></div>
						<div class="sms-field">
							<label>Sınıf Seviyesi (<?php echo $term_id ? 'bu dönem' : 'dönem seçilmedi'; ?>)</label>
							<select name="grade_level">
								<option value="0">— Seçin —</option>
								<?php for ( $g = (int) $settings['min_grade']; $g <= (int) $settings['max_grade']; $g++ ) : ?>
									<option value="<?php echo (int) $g; ?>" <?php selected( $enrollment ? (int) $enrollment->grade_level : 0, $g ); ?>><?php echo esc_html( nizamiye_grade_label( $g ) ); ?></option>
								<?php endfor; ?>
							</select>
						</div>
					</div>
					<div class="sms-field-row">
						<div class="sms-field">
							<label>Velisi</label>
							<select name="parent_user_id">
								<option value="0">— Veli yok —</option>
								<?php foreach ( $parents as $p ) : ?>
									<option value="<?php echo (int) $p->ID; ?>" <?php selected( (int) ( $student->parent_user_id ?? 0 ), (int) $p->ID ); ?>><?php echo esc_html( $p->display_name . ' (' . $p->user_email . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="sms-field">
							<label>Durum</label>
							<select name="status">
								<option value="active" <?php selected( $student->status ?? 'active', 'active' ); ?>>Aktif</option>
								<option value="graduated" <?php selected( $student->status ?? '', 'graduated' ); ?>>Mezun</option>
								<option value="archived" <?php selected( $student->status ?? '', 'archived' ); ?>>Arşiv</option>
							</select>
						</div>
					</div>
					<div class="sms-field"><label>Notlar</label><textarea name="notes" rows="3"><?php echo esc_textarea( $student->notes ?? '' ); ?></textarea></div>

					<?php if ( ! $linked_user ) : ?>
						<details class="sms-details">
							<summary>Öğrenci giriş hesabı oluştur (isteğe bağlı)</summary>
							<div class="sms-field-row">
								<div class="sms-field"><label>Kullanıcı Adı</label><input type="text" name="account_username" autocomplete="off"></div>
								<div class="sms-field"><label>E-posta</label><input type="email" name="account_email" autocomplete="off"></div>
							</div>
							<div class="sms-field"><label>Şifre (boşsa otomatik üretilir)</label><input type="text" name="account_password" autocomplete="new-password"></div>
						</details>
					<?php else : ?>
						<p class="sms-muted"><span class="dashicons dashicons-admin-users"></span> Bağlı giriş hesabı: <strong><?php echo esc_html( $linked_user->user_login ); ?></strong> (<?php echo esc_html( $linked_user->user_email ); ?>)</p>
					<?php endif; ?>

					<button type="submit" class="sms-btn sms-btn-primary">Kaydet</button>
				</form>
			</div>
		</div>

		<div>
			<?php if ( $student ) : ?>
				<div class="sms-card">
					<div class="sms-card-head"><h2>Bu Dönemdeki Derslikleri</h2></div>
					<div class="sms-pad">
						<?php if ( $classes ) : ?>
							<ul class="sms-mini-list">
								<?php foreach ( $classes as $c ) : ?>
									<li><a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-classes&view=edit&class_id=' . (int) $c->id . '&nizamiye_term=' . $term_id ) ) ); ?>"><?php echo esc_html( $c->name ); ?></a></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="sms-muted">Henüz bir dersliğe eklenmemiş. Atama <a href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-classes&nizamiye_term=' . $term_id ) ); ?>">Derslikler</a> sayfasındaki kadro yönetiminden yapılır.</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="sms-card sms-mt">
					<div class="sms-card-head"><h2>Dönem Geçmişi</h2></div>
					<div class="sms-pad">
						<ul class="sms-mini-list">
							<?php foreach ( Nizamiye_Students::enrollment_history( $student_id ) as $h ) : ?>
								<li><?php echo esc_html( $h->term_name . ' — ' . nizamiye_grade_label( $h->grade_level ) ); ?> <?php echo 'graduated' === $h->status ? '🎓' : ''; ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>

				<div class="sms-card sms-mt sms-danger-zone">
					<div class="sms-pad">
						<?php nizamiye_form_open( 'nizamiye_delete_student', 'sms-confirm' ); ?>
							<input type="hidden" name="student_id" value="<?php echo (int) $student_id; ?>">
							<button type="submit" class="sms-btn sms-btn-danger-ghost" data-confirm="Öğrenci ve TÜM kayıtları (yoklama, not, alışkanlık) kalıcı olarak silinecek. Emin misiniz?">Öğrenciyi Kalıcı Olarak Sil</button>
						</form>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
