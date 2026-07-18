<?php
defined( 'ABSPATH' ) || exit;

$categories = SMS_Attendance_Types::categories( false );
$edit_id    = isset( $_GET['cat'] ) ? (int) $_GET['cat'] : 0;
$edit       = $edit_id ? SMS_Attendance_Types::get_category( $edit_id ) : null;

// Kullanılabilir dashicon seçenekleri.
$icons = array(
	'dashicons-store', 'dashicons-clock', 'dashicons-image-filter', 'dashicons-smartphone',
	'dashicons-welcome-learn-more', 'dashicons-clipboard', 'dashicons-yes-alt', 'dashicons-heart',
	'dashicons-book', 'dashicons-coffee', 'dashicons-buddicons-activity', 'dashicons-groups',
);
$settings     = sms_get_settings();
$edit_grades  = $edit ? SMS_Attendance_Types::get_grade_levels( (int) $edit->id ) : array();
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Yoklama Türleri', 'Yoklama kategorilerini ve oturumlarını yönetin. Namaz gibi bir kategori altında birden çok oturum (vakit) olabilir.', false ); ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div>
			<?php foreach ( $categories as $cat ) : $sessions = SMS_Attendance_Types::sessions( (int) $cat->id ); $cat_grades = 'general' === $cat->scope ? SMS_Attendance_Types::get_grade_levels( (int) $cat->id ) : array(); ?>
				<div class="sms-card sms-mb">
					<div class="sms-card-head">
						<h2>
							<span class="dashicons <?php echo esc_attr( $cat->icon ?: 'dashicons-clipboard' ); ?>"></span>
							<?php echo esc_html( $cat->name ); ?>
							<span class="sms-badge <?php echo 'class' === $cat->scope ? 'sms-badge-indigo' : 'sms-badge-green'; ?>"><?php echo 'class' === $cat->scope ? 'Derslik bazlı' : 'Genel'; ?></span>
							<?php echo $cat->is_system ? '<span class="sms-badge">sistem</span>' : ''; ?>
							<?php if ( 'general' === $cat->scope ) : ?>
								<span class="sms-badge sms-badge-amber"><?php echo $cat_grades ? esc_html( implode( ', ', $cat_grades ) ) . '. sınıflar' : 'Tüm sınıflar'; ?></span>
							<?php endif; ?>
						</h2>
						<div class="sms-actions-cell">
							<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-att-types&cat=' . (int) $cat->id ) ); ?>">Adı Düzenle</a>
							<?php if ( ! $cat->is_system ) : ?>
								<?php sms_form_open( 'sms_delete_category', 'sms-inline sms-confirm' ); sms_back_url_field( admin_url( 'admin.php?page=sms-att-types' ) ); ?>
									<input type="hidden" name="category_id" value="<?php echo (int) $cat->id; ?>">
									<button type="submit" class="sms-btn sms-btn-danger-ghost sms-btn-sm" data-confirm="Bu kategori ve TÜM ilgili yoklama kayıtları silinecek. Emin misiniz?">Sil</button>
								</form>
							<?php endif; ?>
						</div>
					</div>
					<div class="sms-pad">
						<div class="sms-session-chips">
							<?php foreach ( $sessions as $s ) : ?>
								<span class="sms-session-chip">
									<?php echo esc_html( $s->name ); ?>
									<?php sms_form_open( 'sms_delete_session', 'sms-inline sms-confirm' ); sms_back_url_field( admin_url( 'admin.php?page=sms-att-types' ) ); ?>
										<input type="hidden" name="session_id" value="<?php echo (int) $s->id; ?>">
										<button type="submit" class="sms-chip-x" data-confirm="'<?php echo esc_attr( $s->name ); ?>' oturumu ve kayıtları silinsin mi?" title="Sil">×</button>
									</form>
								</span>
							<?php endforeach; ?>
						</div>
						<?php sms_form_open( 'sms_add_session', 'sms-inline-form' ); sms_back_url_field( admin_url( 'admin.php?page=sms-att-types' ) ); ?>
							<input type="hidden" name="category_id" value="<?php echo (int) $cat->id; ?>">
							<input type="text" name="name" placeholder="Yeni oturum adı (örn. Sabah)" required>
							<button type="submit" class="sms-btn sms-btn-ghost sms-btn-sm">+ Oturum Ekle</button>
						</form>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2><?php echo $edit ? 'Kategoriyi Düzenle' : 'Yeni Kategori'; ?></h2></div>
			<div class="sms-pad">
				<?php sms_form_open( 'sms_save_category' ); sms_back_url_field( admin_url( 'admin.php?page=sms-att-types' ) ); ?>
					<input type="hidden" name="category_id" value="<?php echo $edit ? (int) $edit->id : 0; ?>">
					<div class="sms-field"><label>Kategori Adı *</label><input type="text" name="name" value="<?php echo esc_attr( $edit->name ?? '' ); ?>" placeholder="Örn. Etüt" required></div>

					<div class="sms-field">
						<label>Simge</label>
						<div class="sms-icon-picker">
							<?php foreach ( $icons as $ic ) : ?>
								<label class="sms-icon-opt">
									<input type="radio" name="icon" value="<?php echo esc_attr( $ic ); ?>" <?php checked( ( $edit->icon ?? 'dashicons-clipboard' ), $ic ); ?>>
									<span class="dashicons <?php echo esc_attr( $ic ); ?>"></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<?php if ( ! $edit ) : ?>
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
					<?php elseif ( 'general' === $edit->scope ) : ?>
						<div class="sms-field sms-ct-block">
							<label>Bu yoklamada hangi sınıflar görünsün?</label>
							<p class="sms-muted" style="margin:2px 0 0">Hiçbiri işaretlenmezse tüm sınıflar bu yoklamada yer alır.</p>
							<div class="sms-grade-checks">
								<?php for ( $g = (int) $settings['min_grade']; $g <= (int) $settings['max_grade']; $g++ ) : ?>
									<label class="sms-grade-check">
										<input type="checkbox" name="cat_grades[]" value="<?php echo (int) $g; ?>" <?php checked( in_array( $g, $edit_grades, true ) ); ?>>
										<span><?php echo (int) $g; ?></span>
									</label>
								<?php endfor; ?>
							</div>
						</div>
					<?php endif; ?>

					<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $edit ? 'Kaydet' : 'Kategori Oluştur'; ?></button>
					<?php if ( $edit ) : ?>
						<a class="sms-btn sms-btn-ghost sms-btn-block sms-mt-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-att-types' ) ); ?>">Vazgeç</a>
					<?php endif; ?>
				</form>
			</div>
		</div>
	</div>
</div>
