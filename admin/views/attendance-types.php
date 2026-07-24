<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_categories = Nizamiye_Attendance_Types::categories( false );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_edit_id    = ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) ) && isset( $_GET['cat'] ) ? (int) $_GET['cat'] : 0;
$nizamiye_edit       = $nizamiye_edit_id ? Nizamiye_Attendance_Types::get_category( $nizamiye_edit_id ) : null;

// Kullanılabilir dashicon seçenekleri.
$nizamiye_icons = array(
	'dashicons-store', 'dashicons-clock', 'dashicons-image-filter', 'dashicons-smartphone',
	'dashicons-welcome-learn-more', 'dashicons-clipboard', 'dashicons-yes-alt', 'dashicons-heart',
	'dashicons-book', 'dashicons-coffee', 'dashicons-buddicons-activity', 'dashicons-groups',
);
$nizamiye_settings     = nizamiye_get_settings();
$nizamiye_edit_grades  = $nizamiye_edit ? Nizamiye_Attendance_Types::get_grade_levels( (int) $nizamiye_edit->id ) : array();
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Yoklama Türleri', 'Yoklama kategorilerini ve oturumlarını yönetin. Namaz gibi bir kategori altında birden çok oturum (vakit) olabilir.', false ); ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div>
			<?php foreach ( $nizamiye_categories as $nizamiye_cat ) : $nizamiye_sessions = Nizamiye_Attendance_Types::sessions( (int) $nizamiye_cat->id ); $nizamiye_cat_grades = 'general' === $nizamiye_cat->scope ? Nizamiye_Attendance_Types::get_grade_levels( (int) $nizamiye_cat->id ) : array(); ?>
				<div class="sms-card sms-mb">
					<div class="sms-card-head">
						<h2>
							<span class="dashicons <?php echo esc_attr( $nizamiye_cat->icon ?: 'dashicons-clipboard' ); ?>"></span>
							<?php echo esc_html( $nizamiye_cat->name ); ?>
							<span class="sms-badge <?php echo 'class' === $nizamiye_cat->scope ? 'sms-badge-indigo' : 'sms-badge-green'; ?>"><?php echo 'class' === $nizamiye_cat->scope ? 'Derslik bazlı' : 'Genel'; ?></span>
							<?php echo $nizamiye_cat->is_system ? '<span class="sms-badge">sistem</span>' : ''; ?>
							<?php if ( 'general' === $nizamiye_cat->scope ) : ?>
								<span class="sms-badge sms-badge-amber"><?php echo $nizamiye_cat_grades ? esc_html( implode( ', ', $nizamiye_cat_grades ) ) . '. sınıflar' : 'Tüm sınıflar'; ?></span>
							<?php endif; ?>
						</h2>
						<div class="sms-actions-cell">
							<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-att-types&cat=' . (int) $nizamiye_cat->id ) ) ); ?>">Adı Düzenle</a>
							<?php if ( ! $nizamiye_cat->is_system ) : ?>
								<?php nizamiye_form_open( 'nizamiye_delete_category', 'sms-inline sms-confirm' ); nizamiye_back_url_field( admin_url( 'admin.php?page=nizamiye-att-types' ) ); ?>
									<input type="hidden" name="category_id" value="<?php echo (int) $nizamiye_cat->id; ?>">
									<button type="submit" class="sms-btn sms-btn-danger-ghost sms-btn-sm" data-confirm="Bu kategori ve TÜM ilgili yoklama kayıtları silinecek. Emin misiniz?">Sil</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
					<div class="sms-pad">
						<div class="sms-session-chips">
							<?php foreach ( $nizamiye_sessions as $nizamiye_s ) : ?>
								<span class="sms-session-chip">
									<?php echo esc_html( $nizamiye_s->name ); ?>
									<?php nizamiye_form_open( 'nizamiye_delete_session', 'sms-inline sms-confirm' ); nizamiye_back_url_field( admin_url( 'admin.php?page=nizamiye-att-types' ) ); ?>
										<input type="hidden" name="session_id" value="<?php echo (int) $nizamiye_s->id; ?>">
										<button type="submit" class="sms-chip-x" data-confirm="'<?php echo esc_attr( $nizamiye_s->name ); ?>' oturumu ve kayıtları silinsin mi?" title="Sil">×</button>
									</form>
								</span>
							<?php endforeach; ?>
						</div>
						<?php nizamiye_form_open( 'nizamiye_add_session', 'sms-inline-form' ); nizamiye_back_url_field( admin_url( 'admin.php?page=nizamiye-att-types' ) ); ?>
							<input type="hidden" name="category_id" value="<?php echo (int) $nizamiye_cat->id; ?>">
							<input type="text" name="name" placeholder="Yeni oturum adı (örn. Sabah)" required>
							<button type="submit" class="sms-btn sms-btn-ghost sms-btn-sm">+ Oturum Ekle</button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2><?php echo $nizamiye_edit ? 'Kategoriyi Düzenle' : 'Yeni Kategori'; ?></h2></div>
			<div class="sms-pad">
				<?php nizamiye_form_open( 'nizamiye_save_category' ); nizamiye_back_url_field( admin_url( 'admin.php?page=nizamiye-att-types' ) ); ?>
					<input type="hidden" name="category_id" value="<?php echo $nizamiye_edit ? (int) $nizamiye_edit->id : 0; ?>">
					<div class="sms-field"><label>Kategori Adı *</label><input type="text" name="name" value="<?php echo esc_attr( $nizamiye_edit->name ?? '' ); ?>" placeholder="Örn. Etüt" required></div>

					<div class="sms-field">
						<label>Simge</label>
						<div class="sms-icon-picker">
							<?php foreach ( $nizamiye_icons as $nizamiye_ic ) : ?>
								<label class="sms-icon-opt">
									<input type="radio" name="icon" value="<?php echo esc_attr( $nizamiye_ic ); ?>" <?php checked( ( $nizamiye_edit->icon ?? 'dashicons-clipboard' ), $nizamiye_ic ); ?>>
									<span class="dashicons <?php echo esc_attr( $nizamiye_ic ); ?>"></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<?php if ( ! $nizamiye_edit ) : ?>
						<div class="sms-field">
							<label>Yoklama Türü *</label>
							<div class="sms-choice-cards">
								<label class="sms-choice-card">
									<input type="radio" name="scope" value="general" checked>
									<div><strong>Genel</strong><span class="sms-muted">Sınıf öğretmeni + yönetici alır (namaz/temizlik gibi)</span></div>
								</label>
								<label class="sms-choice-card">
									<input type="radio" name="scope" value="class">
									<div><strong>Derslik bazlı</strong><span class="sms-muted">Branş öğretmeni kendi dersliğinde alır</span></div>
								</label>
							</div>
						</div>
						<div class="sms-field"><label>İlk Oturum Adı</label><input type="text" name="first_session" placeholder="Boşsa kategori adı kullanılır"></div>
					<?php elseif ( 'general' === $nizamiye_edit->scope ) : ?>
						<div class="sms-field sms-ct-block">
							<label>Bu yoklamada hangi sınıflar görünsün?</label>
							<p class="sms-muted" style="margin:2px 0 0">Hiçbiri işaretlenmezse tüm sınıflar bu yoklamada yer alır.</p>
							<div class="sms-grade-checks">
								<?php for ( $nizamiye_g = (int) $nizamiye_settings['min_grade']; $nizamiye_g <= (int) $nizamiye_settings['max_grade']; $nizamiye_g++ ) : ?>
									<label class="sms-grade-check">
										<input type="checkbox" name="cat_grades[]" value="<?php echo (int) $nizamiye_g; ?>" <?php checked( in_array( $nizamiye_g, $nizamiye_edit_grades, true ) ); ?>>
										<span><?php echo (int) $nizamiye_g; ?></span>
									</label>
								<?php endfor; ?>
							</div>
						</div>
					<?php endif; ?>

					<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $nizamiye_edit ? 'Kaydet' : 'Kategori Oluştur'; ?></button>
					<?php if ( $nizamiye_edit ) : ?>
						<a class="sms-btn sms-btn-ghost sms-btn-block sms-mt-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-att-types' ) ); ?>">Vazgeç</a>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
</div>
