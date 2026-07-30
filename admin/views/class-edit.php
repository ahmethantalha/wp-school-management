<?php
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_class_id = ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) ) && isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
$nizamiye_class    = $nizamiye_class_id ? Nizamiye_Classes::get( $nizamiye_class_id ) : null;
$nizamiye_term_id  = $nizamiye_class ? (int) $nizamiye_class->term_id : nizamiye_current_term_id();
$nizamiye_settings = nizamiye_get_settings();

if ( $nizamiye_class && ! nizamiye_can_manage_class( $nizamiye_class_id ) ) {
	wp_die( 'Bu dersliği görüntüleme yetkiniz yok.' );
}
if ( ! $nizamiye_class && ! nizamiye_is_manager() ) {
	wp_die( 'Derslik oluşturma yetkisi yalnızca yöneticidedir.' );
}

$nizamiye_teachers    = nizamiye_users_by_role( 'nizamiye_teacher' );
$nizamiye_roster_ids  = $nizamiye_class ? Nizamiye_Classes::student_ids( $nizamiye_class_id ) : array();
$nizamiye_all_students = Nizamiye_Students::query( array( 'term_id' => $nizamiye_term_id, 'status' => 'active' ) );
$nizamiye_grades      = Nizamiye_Students::grades_in_term( $nizamiye_term_id );
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( $nizamiye_class ? 'Derslik: ' . $nizamiye_class->name : 'Yeni Derslik', 'Derslik bilgileri ve öğrenci kadrosu' ); ?>

	<p><a class="sms-back-link" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-classes&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">← Derslik listesine dön</a></p>

	<div class="sms-grid-2 sms-grid-uneven">
		<div>
			<?php if ( $nizamiye_class ) : ?>
				<div class="sms-card">
					<div class="sms-card-head">
						<h2>Öğrenci Kadrosu</h2>
						<span class="sms-muted"><span data-sms-count-checked>?</span> öğrenci seçili</span>
					</div>
					<div class="sms-pad">
						<div class="sms-roster-tools">
							<input type="search" placeholder="Öğrenci ara…" data-sms-filter-search>
							<select data-sms-filter-grade>
								<option value="">Tüm sınıflar</option>
								<?php foreach ( $nizamiye_grades as $nizamiye_g ) : ?>
									<option value="<?php echo (int) $nizamiye_g; ?>"><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-select-visible>Görünenleri Seç</button>
							<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-clear-visible>Görünenleri Kaldır</button>
						</div>

						<?php nizamiye_form_open( 'nizamiye_class_roster' ); nizamiye_back_url_field(); ?>
							<input type="hidden" name="class_id" value="<?php echo (int) $nizamiye_class_id; ?>">
							<div class="sms-roster-list" data-sms-roster>
								<?php if ( $nizamiye_all_students ) : foreach ( $nizamiye_all_students as $nizamiye_s ) : ?>
									<label class="sms-roster-item" data-grade="<?php echo (int) ( $nizamiye_s->grade_level ?? 0 ); ?>" data-name="<?php echo esc_attr( mb_strtolower( nizamiye_student_name( $nizamiye_s ) ) ); ?>">
										<input type="checkbox" name="student_ids[]" value="<?php echo (int) $nizamiye_s->id; ?>" <?php checked( in_array( (int) $nizamiye_s->id, $nizamiye_roster_ids, true ) ); ?>>
										<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_s ) ) ); ?>
										<span class="sms-roster-name"><?php echo esc_html( nizamiye_student_name( $nizamiye_s ) ); ?></span>
										<span class="sms-badge sms-badge-indigo"><?php echo esc_html( nizamiye_grade_label( $nizamiye_s->grade_level ?? 0 ) ); ?></span>
									</label>
								<?php endforeach; else : ?>
									<p class="sms-muted">Bu dönemde kayıtlı aktif öğrenci yok.</p>
								<?php endif; ?>
							</div>
							<button type="submit" class="sms-btn sms-btn-primary sms-mt">Kadroyu Kaydet</button>
						</form>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div>
			<div class="sms-card">
				<div class="sms-card-head"><h2><?php echo $nizamiye_class ? 'Derslik Bilgileri' : 'Derslik Oluştur'; ?></h2></div>
				<div class="sms-pad">
					<?php if ( nizamiye_is_manager() ) : ?>
						<?php nizamiye_form_open( 'nizamiye_save_class' ); nizamiye_back_url_field(); ?>
							<input type="hidden" name="class_id" value="<?php echo (int) $nizamiye_class_id; ?>">
							<input type="hidden" name="term_id" value="<?php echo (int) $nizamiye_term_id; ?>">
							<div class="sms-field"><label>Derslik Adı *</label><input type="text" name="name" value="<?php echo esc_attr( $nizamiye_class->name ?? '' ); ?>" placeholder="Örn. Türkçe 6-A" required></div>
							<div class="sms-field-row">
								<div class="sms-field"><label>Branş</label><input type="text" name="subject" value="<?php echo esc_attr( $nizamiye_class->subject ?? '' ); ?>" placeholder="Örn. Türkçe"></div>
								<div class="sms-field">
									<label>Sınıf Seviyesi</label>
									<select name="grade_level">
										<option value="0">— Karma —</option>
										<?php for ( $nizamiye_g = (int) $nizamiye_settings['min_grade']; $nizamiye_g <= (int) $nizamiye_settings['max_grade']; $nizamiye_g++ ) : ?>
											<option value="<?php echo (int) $nizamiye_g; ?>" <?php selected( (int) ( $nizamiye_class->grade_level ?? 0 ), $nizamiye_g ); ?>><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></option>
										<?php endfor; ?>
									</select>
								</div>
							</div>
							<div class="sms-field">
								<label>Öğretmen</label>
								<select name="teacher_id">
									<option value="0">— Atanmadı —</option>
									<?php foreach ( $nizamiye_teachers as $nizamiye_t ) : ?>
										<option value="<?php echo (int) $nizamiye_t->ID; ?>" <?php selected( (int) ( $nizamiye_class->teacher_id ?? 0 ), (int) $nizamiye_t->ID ); ?>><?php echo esc_html( $nizamiye_t->display_name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $nizamiye_class ? 'Güncelle' : 'Derslik Oluştur'; ?></button>
						</form>
					<?php else : ?>
						<p><strong><?php echo esc_html( $nizamiye_class->name ); ?></strong></p>
						<p class="sms-muted"><?php echo esc_html( $nizamiye_class->subject ?: '—' ); ?><?php echo $nizamiye_class->grade_level ? ' • ' . esc_html( nizamiye_grade_label( $nizamiye_class->grade_level ) ) : ''; ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $nizamiye_class ) : ?>
				<div class="sms-card sms-mt">
					<div class="sms-pad">
						<a class="sms-btn sms-btn-ghost sms-btn-block" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-attendance&class_id=' . (int) $nizamiye_class_id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><span class="dashicons dashicons-clipboard"></span> Yoklama Al</a>
						<a class="sms-btn sms-btn-ghost sms-btn-block sms-mt-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-grades&gview=class&class_id=' . (int) $nizamiye_class_id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><span class="dashicons dashicons-welcome-write-blog"></span> Notlar</a>
					</div>
				</div>

				<?php if ( nizamiye_is_manager() ) : ?>
					<div class="sms-card sms-mt sms-danger-zone">
						<div class="sms-pad">
							<?php nizamiye_form_open( 'nizamiye_delete_class', 'sms-confirm' ); ?>
								<input type="hidden" name="class_id" value="<?php echo (int) $nizamiye_class_id; ?>">
								<button type="submit" class="sms-btn sms-btn-danger-ghost" data-confirm="Derslik ve bağlı yoklama/not kayıtları silinecek. Emin misiniz?">Dersliği Sil</button>
							</form>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
