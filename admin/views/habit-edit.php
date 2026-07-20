<?php
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- salt görüntüleme (GET), durum değişikliği yok.
$habit_id = isset( $_GET['habit_id'] ) ? (int) $_GET['habit_id'] : 0;
$habit    = $habit_id ? Nizamiye_Habits::get( $habit_id ) : null;
$term_id  = $habit ? (int) $habit->term_id : nizamiye_current_term_id();
$teacher  = nizamiye_is_teacher();

// Öğretmen başkasının alışkanlığını düzenleyemez, ama öğrenci ekleyebilsin diye görüntüleyebilir.
$can_edit_meta = nizamiye_is_manager() || ! $habit || (int) $habit->created_by === get_current_user_id();

$assigned_ids = $habit ? Nizamiye_Habits::student_ids( $habit_id ) : array();
$all_students = Nizamiye_Students::query( array( 'term_id' => $term_id, 'status' => 'active' ) );
if ( $teacher ) {
	$my_ids       = nizamiye_teacher_student_ids();
	$all_students = array_values( array_filter( $all_students, function ( $s ) use ( $my_ids, $assigned_ids ) {
		return in_array( (int) $s->id, $my_ids, true ) || in_array( (int) $s->id, $assigned_ids, true );
	} ) );
}
$grades = Nizamiye_Students::grades_in_term( $term_id );
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( $habit ? 'Alışkanlık: ' . $habit->name : 'Yeni Alışkanlık', 'Takip tipini seçin ve öğrencileri atayın.' ); ?>

	<p><a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-habits&nizamiye_term=' . $term_id ) ); ?>">← Alışkanlık listesine dön</a></p>

	<?php nizamiye_form_open( 'nizamiye_save_habit' ); nizamiye_back_url_field(); ?>
		<input type="hidden" name="habit_id" value="<?php echo (int) $habit_id; ?>">
		<input type="hidden" name="term_id" value="<?php echo (int) $term_id; ?>">

		<div class="sms-grid-2 sms-grid-uneven">
			<div class="sms-card">
				<div class="sms-card-head">
					<h2>Öğrenci Atama</h2>
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
					<div class="sms-roster-list" data-sms-roster>
						<?php if ( $all_students ) : foreach ( $all_students as $s ) : ?>
							<label class="sms-roster-item" data-grade="<?php echo (int) ( $s->grade_level ?? 0 ); ?>" data-name="<?php echo esc_attr( mb_strtolower( nizamiye_student_name( $s ) ) ); ?>">
								<input type="checkbox" name="student_ids[]" value="<?php echo (int) $s->id; ?>" <?php checked( in_array( (int) $s->id, $assigned_ids, true ) ); ?>>
								<?php echo nizamiye_avatar( nizamiye_student_name( $s ) ); // phpcs:ignore ?>
								<span class="sms-roster-name"><?php echo esc_html( nizamiye_student_name( $s ) ); ?></span>
								<span class="sms-badge sms-badge-indigo"><?php echo esc_html( nizamiye_grade_label( $s->grade_level ?? 0 ) ); ?></span>
							</label>
						<?php endforeach; else : ?>
							<p class="sms-muted">Bu dönemde atanabilir öğrenci yok.</p>
						<?php endif; ?>
					</div>
					<?php if ( $teacher ) : ?>
						<p class="sms-muted sms-mt-sm"><span class="dashicons dashicons-info"></span> Yalnızca kendi dersliklerinizdeki öğrencileri ekleyip çıkarabilirsiniz; diğer atamalar korunur.</p>
					<?php endif; ?>
				</div>
			</div>

			<div>
				<div class="sms-card">
					<div class="sms-card-head"><h2>Alışkanlık Bilgileri</h2></div>
					<div class="sms-pad">
						<div class="sms-field"><label>Alışkanlık Adı *</label><input type="text" name="name" value="<?php echo esc_attr( $habit->name ?? '' ); ?>" placeholder="Örn. Kitap Okuma" <?php echo $can_edit_meta ? 'required' : 'readonly'; ?>></div>
						<div class="sms-field"><label>Açıklama</label><textarea name="description" rows="3" <?php echo $can_edit_meta ? '' : 'readonly'; ?> placeholder="Örn. Her gün en az 30 dakika kitap okuma"><?php echo esc_textarea( $habit->description ?? '' ); ?></textarea></div>

						<div class="sms-field">
							<label>Takip Metodu *</label>
							<div class="sms-choice-cards sms-choice-cards-3">
								<label class="sms-choice-card">
									<input type="radio" name="track_type" value="binary" <?php checked( ( $habit->track_type ?? 'binary' ), 'binary' ); ?> <?php disabled( ! $can_edit_meta ); ?>>
									<div><strong>Yaptı / Yapmadı</strong><span class="sms-muted">İki seçenekli basit takip</span></div>
								</label>
								<label class="sms-choice-card">
									<input type="radio" name="track_type" value="scale" <?php checked( ( $habit->track_type ?? '' ), 'scale' ); ?> <?php disabled( ! $can_edit_meta ); ?>>
									<div><strong>Dereceli</strong><span class="sms-muted">Yapma derecesine göre puanlı takip</span></div>
								</label>
								<label class="sms-choice-card">
									<input type="radio" name="track_type" value="reading" <?php checked( ( $habit->track_type ?? '' ), 'reading' ); ?> <?php disabled( ! $can_edit_meta ); ?>>
									<div><strong>Kitap / Sayfa Takibi</strong><span class="sms-muted">Günlük kitap adı + sayfa sayısı; karnede toplam sayfa ve kitap listesi</span></div>
								</label>
							</div>
						</div>

						<div class="sms-field" data-sms-scale-field <?php echo ( $habit && 'scale' === $habit->track_type ) ? '' : 'style="display:none"'; ?>>
							<label>Derece Üst Sınırı (2–10)</label>
							<input type="number" name="scale_max" min="2" max="10" value="<?php echo (int) ( $habit->scale_max ?? 5 ); ?>" <?php echo $can_edit_meta ? '' : 'readonly'; ?>>
						</div>

						<?php if ( $habit && ! $can_edit_meta ) : ?>
							<p class="sms-muted"><span class="dashicons dashicons-lock"></span> Alışkanlık bilgilerini yalnızca oluşturan öğretmen veya yönetici değiştirebilir.</p>
						<?php endif; ?>

						<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $habit ? 'Kaydet' : 'Alışkanlığı Oluştur'; ?></button>
					</div>
				</div>

				<?php if ( $habit ) : ?>
					<div class="sms-card sms-mt">
						<div class="sms-pad">
							<a class="sms-btn sms-btn-ghost sms-btn-block" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-habits&view=track&habit_id=' . (int) $habit_id . '&nizamiye_term=' . $term_id ) ); ?>"><span class="dashicons dashicons-edit"></span> Günlük Takip Doldur</a>
						</div>
					</div>
					<?php if ( $can_edit_meta ) : ?>
						<div class="sms-card sms-mt sms-danger-zone">
							<div class="sms-pad">
								<?php // Ayrı form: iç içe form olmaması için butonla yönlendirilir. ?>
								<button type="submit" form="sms-delete-habit-form" class="sms-btn sms-btn-danger-ghost" data-confirm="Alışkanlık ve tüm takip kayıtları silinecek. Emin misiniz?">Alışkanlığı Sil</button>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</form>

	<?php if ( $habit && $can_edit_meta ) : ?>
		<form id="sms-delete-habit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sms-confirm">
			<input type="hidden" name="action" value="nizamiye_delete_habit">
			<?php wp_nonce_field( 'nizamiye_delete_habit', '_nizamiye_nonce' ); ?>
			<input type="hidden" name="habit_id" value="<?php echo (int) $habit_id; ?>">
		</form>
	<?php endif; ?>
</div>
