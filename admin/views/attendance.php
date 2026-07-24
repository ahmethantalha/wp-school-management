<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_term_id = nizamiye_current_term_id();
$nizamiye_has_nonce = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_cat_id  = $nizamiye_has_nonce && isset( $_GET['cat'] ) ? (int) $_GET['cat'] : 0;
$nizamiye_sess_id = $nizamiye_has_nonce && isset( $_GET['session'] ) ? (int) $_GET['session'] : 0;
$nizamiye_class_id = $nizamiye_has_nonce && isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Eski bağlantı uyumu: class_id var ama kategori yoksa Ders kategorisini varsay.
if ( $nizamiye_class_id && ! $nizamiye_cat_id ) {
	$nizamiye_cat_id = nizamiye_ders_category_id();
}

$nizamiye_category = $nizamiye_cat_id ? Nizamiye_Attendance_Types::get_category( $nizamiye_cat_id ) : null;

/* ============================ 1) KATEGORİ SEÇİMİ ============================ */
if ( ! $nizamiye_category ) {
	$nizamiye_cats = $nizamiye_term_id ? Nizamiye_Attendance_Types::accessible_categories( $nizamiye_term_id ) : array();
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( 'Yoklama Al', 'Almak istediğiniz yoklama türünü seçin.' ); ?>

		<?php if ( ! $nizamiye_term_id ) : ?>
			<div class="sms-card sms-empty"><span class="dashicons dashicons-calendar-alt"></span><h2>Aktif dönem yok</h2><p>Önce bir dönem oluşturun.</p></div>
		<?php elseif ( $nizamiye_cats ) : ?>
			<div class="sms-cat-grid">
				<?php foreach ( $nizamiye_cats as $nizamiye_cat ) :
					$nizamiye_scount = Nizamiye_Attendance_Types::session_count( (int) $nizamiye_cat->id );
					?>
					<a class="sms-cat-card sms-scope-<?php echo esc_attr( $nizamiye_cat->scope ); ?>" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-attendance&cat=' . (int) $nizamiye_cat->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">
						<span class="sms-cat-icon"><span class="dashicons <?php echo esc_attr( $nizamiye_cat->icon ?: 'dashicons-clipboard' ); ?>"></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $nizamiye_cat->name ); ?></span>
						<span class="sms-cat-meta">
							<?php echo 'class' === $nizamiye_cat->scope ? 'Derslik bazlı' : ( $nizamiye_scount > 1 ? esc_html( $nizamiye_scount . ' oturum' ) : 'Genel yoklama' ); ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
			<p class="sms-muted sms-mt"><span class="dashicons dashicons-info"></span> Yeni yoklama türü mü lazım? Yönetici olarak <a href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-att-types' ) ); ?>">Yoklama Türleri</a> sayfasından ekleyebilirsiniz.</p>
		<?php else : ?>
			<div class="sms-card sms-empty"><span class="dashicons dashicons-clipboard"></span><h2>Yetkili olduğunuz yoklama türü yok</h2><p>Branş öğretmenleri kendi dersliklerinin, sınıf öğretmenleri genel yoklamaların (namaz, temizlik, telefon) sorumlusudur.</p></div>
		<?php endif; ?>
	</div>
	<?php
	return;
}

/* ============================ 2) DERS: DERSLİK SEÇİMİ ============================ */
if ( 'class' === $nizamiye_category->scope && ! $nizamiye_class_id ) {
	$nizamiye_classes = Nizamiye_Classes::for_term( $nizamiye_term_id, nizamiye_is_manager() ? 0 : get_current_user_id() );
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( $nizamiye_category->name . ' Yoklaması', 'Yoklama alacağınız dersliği seçin.' ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-attendance&nizamiye_term=' . $nizamiye_term_id ) ); ?>">← Yoklama türlerine dön</a></p>
		<?php if ( $nizamiye_classes ) : ?>
			<div class="sms-cat-grid">
				<?php $nizamiye_sess = Nizamiye_Attendance_Types::sessions( (int) $nizamiye_category->id ); $nizamiye_sid = $nizamiye_sess ? (int) $nizamiye_sess[0]->id : 0; ?>
				<?php foreach ( $nizamiye_classes as $nizamiye_c ) : ?>
					<a class="sms-cat-card" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-attendance&cat=' . (int) $nizamiye_category->id . '&session=' . $nizamiye_sid . '&class_id=' . (int) $nizamiye_c->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">
						<span class="sms-cat-icon"><span class="dashicons dashicons-book-alt"></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $nizamiye_c->name ); ?></span>
						<span class="sms-cat-meta"><?php echo (int) $nizamiye_c->student_count; ?> öğrenci</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="sms-card sms-empty"><span class="dashicons dashicons-book-alt"></span><h2>Derslik yok</h2><p>Bu dönemde size ait derslik bulunmuyor.</p></div>
		<?php endif; ?>
	</div>
	<?php
	return;
}

/* ============================ 3) GENEL: OTURUM SEÇİMİ ============================ */
$nizamiye_sessions = Nizamiye_Attendance_Types::sessions( (int) $nizamiye_category->id );
if ( 'general' === $nizamiye_category->scope && count( $nizamiye_sessions ) > 1 && ! $nizamiye_sess_id ) {
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( $nizamiye_category->name . ' Yoklaması', 'Hangi oturumun yoklamasını alacaksınız?' ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-attendance&nizamiye_term=' . $nizamiye_term_id ) ); ?>">← Yoklama türlerine dön</a></p>
		<div class="sms-cat-grid">
			<?php foreach ( $nizamiye_sessions as $nizamiye_s ) : ?>
				<a class="sms-cat-card sms-session-card" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-attendance&cat=' . (int) $nizamiye_category->id . '&session=' . (int) $nizamiye_s->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">
					<span class="sms-cat-icon"><span class="dashicons <?php echo esc_attr( $nizamiye_category->icon ?: 'dashicons-clock' ); ?>"></span></span>
					<span class="sms-cat-name"><?php echo esc_html( $nizamiye_s->name ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return;
}

// Tek oturumlu genel kategori: oturumu otomatik seç.
if ( ! $nizamiye_sess_id && $nizamiye_sessions ) {
	$nizamiye_sess_id = (int) $nizamiye_sessions[0]->id;
}
$nizamiye_session = $nizamiye_sess_id ? Nizamiye_Attendance_Types::get_session( $nizamiye_sess_id ) : null;
if ( ! $nizamiye_session || (int) $nizamiye_session->category_id !== (int) $nizamiye_category->id ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>Oturum bulunamadı</h2></div></div>';
	return;
}

/* ============================ 4) YOKLAMA CETVELİ ============================ */
// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- yukarıdaki wp_verify_nonce() ile doğrulanır; ham değer yalnızca regex biçim kontrolü için okunur, kullanılan değer sanitize_text_field(wp_unslash()) ile temizlenir.
$nizamiye_date = $nizamiye_has_nonce && isset( $_GET['att_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['att_date'] ) ) ? sanitize_text_field( wp_unslash( $_GET['att_date'] ) ) : current_time( 'Y-m-d' );

if ( 'class' === $nizamiye_category->scope ) {
	if ( ! nizamiye_can_manage_class( $nizamiye_class_id ) ) {
		wp_die( 'Bu dersliğin yoklamasına erişim yetkiniz yok.' );
	}
	$nizamiye_class     = Nizamiye_Classes::get( $nizamiye_class_id );
	$nizamiye_students  = Nizamiye_Classes::students( $nizamiye_class_id );
	$nizamiye_scope_lbl = $nizamiye_class ? $nizamiye_class->name : '';
} else {
	if ( ! nizamiye_can_take_general_attendance() ) {
		wp_die( 'Genel yoklama alma yetkiniz yok.' );
	}
	$nizamiye_class_id  = 0;
	$nizamiye_ids       = nizamiye_general_attendance_student_ids( $nizamiye_term_id, 0, (int) $nizamiye_category->id );
	$nizamiye_students  = $nizamiye_ids ? Nizamiye_Students::query( array( 'term_id' => $nizamiye_term_id, 'status' => 'active', 'ids' => $nizamiye_ids ) ) : array();
	$nizamiye_scope_lbl = 'Genel';
}

$nizamiye_sheet    = Nizamiye_Attendance::sheet( (int) $nizamiye_category->id, (int) $nizamiye_session->id, (int) $nizamiye_class_id, $nizamiye_date );
$nizamiye_statuses = nizamiye_attendance_statuses();
$nizamiye_grades   = 'general' === $nizamiye_category->scope ? Nizamiye_Students::grades_in_term( $nizamiye_term_id ) : array();
$nizamiye_multi_session = count( $nizamiye_sessions ) > 1;
$title    = $nizamiye_category->name . ( $nizamiye_multi_session ? ' — ' . $nizamiye_session->name : '' ) . ' Yoklaması';
$nizamiye_back_url = $nizamiye_multi_session
	? nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-attendance&cat=' . (int) $nizamiye_category->id . '&nizamiye_term=' . $nizamiye_term_id ) )
	: admin_url( 'admin.php?page=nizamiye-attendance&nizamiye_term=' . $nizamiye_term_id );
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( $title, $nizamiye_scope_lbl ); ?>
	<p><a class="sms-back-link" href="<?php echo esc_url( $nizamiye_back_url ); ?>">← Geri</a></p>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<?php nizamiye_view_nonce_field(); ?>
				<input type="hidden" name="page" value="nizamiye-attendance">
				<input type="hidden" name="cat" value="<?php echo (int) $nizamiye_category->id; ?>">
				<input type="hidden" name="session" value="<?php echo (int) $nizamiye_session->id; ?>">
				<?php if ( $nizamiye_class_id ) : ?><input type="hidden" name="class_id" value="<?php echo (int) $nizamiye_class_id; ?>"><?php endif; ?>
				<input type="hidden" name="nizamiye_term" value="<?php echo (int) $nizamiye_term_id; ?>">
				<label class="sms-muted">Tarih</label>
				<input type="date" name="att_date" value="<?php echo esc_attr( $nizamiye_date ); ?>" onchange="this.form.submit()">
				<?php if ( $nizamiye_grades ) : ?>
					<select data-sms-filter-grade>
						<option value="">Tüm sınıflar</option>
						<?php foreach ( $nizamiye_grades as $nizamiye_g ) : ?>
							<option value="<?php echo (int) $nizamiye_g; ?>"><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<button type="submit" class="sms-btn sms-btn-ghost">Getir</button>
			</form>
		</div>
	</div>

	<div class="sms-card sms-mt">
		<div class="sms-card-head">
			<h2><?php echo esc_html( nizamiye_format_date( $nizamiye_date ) ); ?><?php echo $nizamiye_multi_session ? ' — ' . esc_html( $nizamiye_session->name ) : ''; ?></h2>
			<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-all-present>Tümünü "Var" işaretle</button>
		</div>
		<?php if ( $nizamiye_students ) : ?>
			<?php nizamiye_form_open( 'nizamiye_save_attendance' ); nizamiye_back_url_field(); ?>
				<input type="hidden" name="category_id" value="<?php echo (int) $nizamiye_category->id; ?>">
				<input type="hidden" name="session_id" value="<?php echo (int) $nizamiye_session->id; ?>">
				<input type="hidden" name="class_id" value="<?php echo (int) $nizamiye_class_id; ?>">
				<input type="hidden" name="att_date" value="<?php echo esc_attr( $nizamiye_date ); ?>">
				<div class="sms-att-list" data-sms-roster>
					<?php
					$nizamiye_short = nizamiye_attendance_status_short();
					foreach ( $nizamiye_students as $nizamiye_s ) :
						$nizamiye_row      = $nizamiye_sheet[ (int) $nizamiye_s->id ] ?? null;
						$nizamiye_current  = $nizamiye_row ? $nizamiye_row->status : 'present';
						$nizamiye_note_val = $nizamiye_row->note ?? '';
						?>
						<div class="sms-att-row sms-roster-row" data-grade="<?php echo (int) ( $nizamiye_s->grade_level ?? 0 ); ?>">
							<span class="sms-att-row-name"><?php echo esc_html( nizamiye_student_name( $nizamiye_s ) ); ?></span>
							<div class="sms-att-row-status">
								<div class="sms-seg" role="radiogroup">
									<?php foreach ( $nizamiye_statuses as $nizamiye_key => $nizamiye_label ) : ?>
										<label class="sms-seg-item sms-seg-<?php echo esc_attr( $nizamiye_key ); ?>">
											<input type="radio" name="att_status[<?php echo (int) $nizamiye_s->id; ?>]" value="<?php echo esc_attr( $nizamiye_key ); ?>" <?php checked( $nizamiye_current, $nizamiye_key ); ?>>
											<span><?php echo esc_html( $nizamiye_short[ $nizamiye_key ] ?? $nizamiye_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<button type="button" class="sms-note-btn <?php echo $nizamiye_note_val ? 'is-active' : ''; ?>" data-sms-note-toggle title="Not ekle/düzenle">
									<span class="dashicons dashicons-edit-page"></span>
								</button>
							</div>
							<div class="sms-att-row-note" data-sms-note-field <?php echo $nizamiye_note_val ? '' : 'style="display:none"'; ?>>
								<input type="text" class="sms-input-sm" name="att_note[<?php echo (int) $nizamiye_s->id; ?>]" value="<?php echo esc_attr( $nizamiye_note_val ); ?>" placeholder="Not…">
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="sms-pad">
					<button type="submit" class="sms-btn sms-btn-primary">Yoklamayı Kaydet</button>
					<?php if ( $nizamiye_sheet ) : ?><span class="sms-muted sms-ml">Bu tarih/oturum için kayıt mevcut; kaydederseniz güncellenir.</span><?php endif; ?>
				</div>
			</form>
		<?php else : ?>
			<div class="sms-empty"><span class="dashicons dashicons-groups"></span><h2>Öğrenci bulunamadı</h2><p><?php echo 'class' === $nizamiye_category->scope ? 'Derslik kadrosuna öğrenci ekleyin.' : 'Sorumlu olduğunuz sınıf seviyelerinde aktif öğrenci yok.'; ?></p></div>
		<?php endif; ?>
	</div>
</div>
