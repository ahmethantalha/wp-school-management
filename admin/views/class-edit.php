<?php
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nizamiye_verify_view_nonce() dahilinde wp_verify_nonce() ile gerçek doğrulama yapılır.
$class_id = nizamiye_verify_view_nonce() && isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
$class    = $class_id ? Nizamiye_Classes::get( $class_id ) : null;
$term_id  = $class ? (int) $class->term_id : nizamiye_current_term_id();
$settings = nizamiye_get_settings();

if ( $class && ! nizamiye_can_manage_class( $class_id ) ) {
	wp_die( 'Bu dersliği görüntüleme yetkiniz yok.' );
}
if ( ! $class && ! nizamiye_is_manager() ) {
	wp_die( 'Derslik oluşturma yetkisi yalnızca yöneticidedir.' );
}

$teachers    = nizamiye_users_by_role( 'nizamiye_teacher' );
$roster_ids  = $class ? Nizamiye_Classes::student_ids( $class_id ) : array();
$all_students = Nizamiye_Students::query( array( 'term_id' => $term_id, 'status' => 'active' ) );
$grades      = Nizamiye_Students::grades_in_term( $term_id );
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( $class ? 'Derslik: ' . $class->name : 'Yeni Derslik', 'Derslik bilgileri ve öğrenci kadrosu' ); ?>

	<p><a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-classes&nizamiye_term=' . $term_id ) ); ?>">← Derslik listesine dön</a></p>

	<div class="sms-grid-2 sms-grid-uneven">
		<div>
			<?php if ( $class ) : ?>
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
								<?php foreach ( $grades as $g ) : ?>
									<option value="<?php echo (int) $g; ?>"><?php echo esc_html( nizamiye_grade_label( $g ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-select-visible>Görünenleri Seç</button>
							<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-clear-visible>Görünenleri Kaldır</button>
						</div>

						<?php nizamiye_form_open( 'nizamiye_class_roster' ); nizamiye_back_url_field(); ?>
							<input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
							<div class="sms-roster-list" data-sms-roster>
								<?php if ( $all_students ) : foreach ( $all_students as $s ) : ?>
									<label class="sms-roster-item" data-grade="<?php echo (int) ( $s->grade_level ?? 0 ); ?>" data-name="<?php echo esc_attr( mb_strtolower( nizamiye_student_name( $s ) ) ); ?>">
										<input type="checkbox" name="student_ids[]" value="<?php echo (int) $s->id; ?>" <?php checked( in_array( (int) $s->id, $roster_ids, true ) ); ?>>
										<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $s ) ) ); ?>
										<span class="sms-roster-name"><?php echo esc_html( nizamiye_student_name( $s ) ); ?></span>
										<span class="sms-badge sms-badge-indigo"><?php echo esc_html( nizamiye_grade_label( $s->grade_level ?? 0 ) ); ?></span>
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
				<div class="sms-card-head"><h2><?php echo $class ? 'Derslik Bilgileri' : 'Derslik Oluştur'; ?></h2></div>
				<div class="sms-pad">
					<?php if ( nizamiye_is_manager() ) : ?>
						<?php nizamiye_form_open( 'nizamiye_save_class' ); nizamiye_back_url_field(); ?>
							<input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
							<input type="hidden" name="term_id" value="<?php echo (int) $term_id; ?>">
							<div class="sms-field"><label>Derslik Adı *</label><input type="text" name="name" value="<?php echo esc_attr( $class->name ?? '' ); ?>" placeholder="Örn. Türkçe 6-A" required></div>
							<div class="sms-field-row">
								<div class="sms-field"><label>Branş</label><input type="text" name="subject" value="<?php echo esc_attr( $class->subject ?? '' ); ?>" placeholder="Örn. Türkçe"></div>
								<div class="sms-field">
									<label>Sınıf Seviyesi</label>
									<select name="grade_level">
										<option value="0">— Karma —</option>
										<?php for ( $g = (int) $settings['min_grade']; $g <= (int) $settings['max_grade']; $g++ ) : ?>
											<option value="<?php echo (int) $g; ?>" <?php selected( (int) ( $class->grade_level ?? 0 ), $g ); ?>><?php echo esc_html( nizamiye_grade_label( $g ) ); ?></option>
										<?php endfor; ?>
									</select>
								</div>
							</div>
							<div class="sms-field">
								<label>Öğretmen</label>
								<select name="teacher_id">
									<option value="0">— Atanmadı —</option>
									<?php foreach ( $teachers as $t ) : ?>
										<option value="<?php echo (int) $t->ID; ?>" <?php selected( (int) ( $class->teacher_id ?? 0 ), (int) $t->ID ); ?>><?php echo esc_html( $t->display_name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $class ? 'Güncelle' : 'Derslik Oluştur'; ?></button>
						</form>
					<?php else : ?>
						<p><strong><?php echo esc_html( $class->name ); ?></strong></p>
						<p class="sms-muted"><?php echo esc_html( $class->subject ?: '—' ); ?><?php echo $class->grade_level ? ' • ' . esc_html( nizamiye_grade_label( $class->grade_level ) ) : ''; ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $class ) : ?>
				<div class="sms-card sms-mt">
					<div class="sms-pad">
						<a class="sms-btn sms-btn-ghost sms-btn-block" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-attendance&class_id=' . (int) $class_id . '&nizamiye_term=' . $term_id ) ) ); ?>"><span class="dashicons dashicons-clipboard"></span> Yoklama Al</a>
						<a class="sms-btn sms-btn-ghost sms-btn-block sms-mt-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-grades&gview=class&class_id=' . (int) $class_id . '&nizamiye_term=' . $term_id ) ) ); ?>"><span class="dashicons dashicons-welcome-write-blog"></span> Notlar</a>
					</div>
				</div>

				<?php if ( nizamiye_is_manager() ) : ?>
					<div class="sms-card sms-mt sms-danger-zone">
						<div class="sms-pad">
							<?php nizamiye_form_open( 'nizamiye_delete_class', 'sms-confirm' ); ?>
								<input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
								<button type="submit" class="sms-btn sms-btn-danger-ghost" data-confirm="Derslik ve bağlı yoklama/not kayıtları silinecek. Emin misiniz?">Dersliği Sil</button>
							</form>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
