<?php
defined( 'ABSPATH' ) || exit;

$term_id = nizamiye_current_term_id();
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- salt görüntüleme filtreleri (GET), durum değişikliği yok.
$cat_id  = isset( $_GET['cat'] ) ? (int) $_GET['cat'] : 0;
$sess_id = isset( $_GET['session'] ) ? (int) $_GET['session'] : 0;
$class_id = isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Eski bağlantı uyumu: class_id var ama kategori yoksa Ders kategorisini varsay.
if ( $class_id && ! $cat_id ) {
	$cat_id = nizamiye_ders_category_id();
}

$category = $cat_id ? Nizamiye_Attendance_Types::get_category( $cat_id ) : null;

/* ============================ 1) KATEGORİ SEÇİMİ ============================ */
if ( ! $category ) {
	$cats = $term_id ? Nizamiye_Attendance_Types::accessible_categories( $term_id ) : array();
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( 'Yoklama Al', 'Almak istediğiniz yoklama türünü seçin.' ); ?>

		<?php if ( ! $term_id ) : ?>
			<div class="sms-card sms-empty"><span class="dashicons dashicons-calendar-alt"></span><h2>Aktif dönem yok</h2><p>Önce bir dönem oluşturun.</p></div>
		<?php elseif ( $cats ) : ?>
			<div class="sms-cat-grid">
				<?php foreach ( $cats as $cat ) :
					$scount = Nizamiye_Attendance_Types::session_count( (int) $cat->id );
					?>
					<a class="sms-cat-card sms-scope-<?php echo esc_attr( $cat->scope ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-attendance&cat=' . (int) $cat->id . '&nizamiye_term=' . $term_id ) ); ?>">
						<span class="sms-cat-icon"><span class="dashicons <?php echo esc_attr( $cat->icon ?: 'dashicons-clipboard' ); ?>"></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $cat->name ); ?></span>
						<span class="sms-cat-meta">
							<?php echo 'class' === $cat->scope ? 'Derslik bazlı' : ( $scount > 1 ? esc_html( $scount . ' oturum' ) : 'Genel yoklama' ); ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
			<p class="sms-muted sms-mt"><span class="dashicons dashicons-info"></span> Yeni yoklama türü mü lazım? Yönetici olarak <a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-att-types' ) ); ?>">Yoklama Türleri</a> sayfasından ekleyebilirsiniz.</p>
		<?php else : ?>
			<div class="sms-card sms-empty"><span class="dashicons dashicons-clipboard"></span><h2>Yetkili olduğunuz yoklama türü yok</h2><p>Branş öğretmenleri kendi dersliklerinin, sınıf öğretmenleri genel yoklamaların (namaz, temizlik, telefon) sorumlusudur.</p></div>
		<?php endif; ?>
	</div>
	<?php
	return;
}

/* ============================ 2) DERS: DERSLİK SEÇİMİ ============================ */
if ( 'class' === $category->scope && ! $class_id ) {
	$classes = Nizamiye_Classes::for_term( $term_id, nizamiye_is_manager() ? 0 : get_current_user_id() );
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( $category->name . ' Yoklaması', 'Yoklama alacağınız dersliği seçin.' ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-attendance&nizamiye_term=' . $term_id ) ); ?>">← Yoklama türlerine dön</a></p>
		<?php if ( $classes ) : ?>
			<div class="sms-cat-grid">
				<?php $sess = Nizamiye_Attendance_Types::sessions( (int) $category->id ); $sid = $sess ? (int) $sess[0]->id : 0; ?>
				<?php foreach ( $classes as $c ) : ?>
					<a class="sms-cat-card" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-attendance&cat=' . (int) $category->id . '&session=' . $sid . '&class_id=' . (int) $c->id . '&nizamiye_term=' . $term_id ) ); ?>">
						<span class="sms-cat-icon"><span class="dashicons dashicons-book-alt"></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $c->name ); ?></span>
						<span class="sms-cat-meta"><?php echo (int) $c->student_count; ?> öğrenci</span>
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
$sessions = Nizamiye_Attendance_Types::sessions( (int) $category->id );
if ( 'general' === $category->scope && count( $sessions ) > 1 && ! $sess_id ) {
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( $category->name . ' Yoklaması', 'Hangi oturumun yoklamasını alacaksınız?' ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-attendance&nizamiye_term=' . $term_id ) ); ?>">← Yoklama türlerine dön</a></p>
		<div class="sms-cat-grid">
			<?php foreach ( $sessions as $s ) : ?>
				<a class="sms-cat-card sms-session-card" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-attendance&cat=' . (int) $category->id . '&session=' . (int) $s->id . '&nizamiye_term=' . $term_id ) ); ?>">
					<span class="sms-cat-icon"><span class="dashicons <?php echo esc_attr( $category->icon ?: 'dashicons-clock' ); ?>"></span></span>
					<span class="sms-cat-name"><?php echo esc_html( $s->name ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return;
}

// Tek oturumlu genel kategori: oturumu otomatik seç.
if ( ! $sess_id && $sessions ) {
	$sess_id = (int) $sessions[0]->id;
}
$session = $sess_id ? Nizamiye_Attendance_Types::get_session( $sess_id ) : null;
if ( ! $session || (int) $session->category_id !== (int) $category->id ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>Oturum bulunamadı</h2></div></div>';
	return;
}

/* ============================ 4) YOKLAMA CETVELİ ============================ */
// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- salt görüntüleme (GET), durum değişikliği yok; ham değer yalnızca regex biçim doğrulaması için okunur, kullanılan değer sanitize_text_field(wp_unslash()) ile temizlenir.
$date = isset( $_GET['att_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['att_date'] ) ) ? sanitize_text_field( wp_unslash( $_GET['att_date'] ) ) : current_time( 'Y-m-d' );

if ( 'class' === $category->scope ) {
	if ( ! nizamiye_can_manage_class( $class_id ) ) {
		wp_die( 'Bu dersliğin yoklamasına erişim yetkiniz yok.' );
	}
	$class     = Nizamiye_Classes::get( $class_id );
	$students  = Nizamiye_Classes::students( $class_id );
	$scope_lbl = $class ? $class->name : '';
} else {
	if ( ! nizamiye_can_take_general_attendance() ) {
		wp_die( 'Genel yoklama alma yetkiniz yok.' );
	}
	$class_id  = 0;
	$ids       = nizamiye_general_attendance_student_ids( $term_id, 0, (int) $category->id );
	$students  = $ids ? Nizamiye_Students::query( array( 'term_id' => $term_id, 'status' => 'active', 'ids' => $ids ) ) : array();
	$scope_lbl = 'Genel';
}

$sheet    = Nizamiye_Attendance::sheet( (int) $category->id, (int) $session->id, (int) $class_id, $date );
$statuses = nizamiye_attendance_statuses();
$grades   = 'general' === $category->scope ? Nizamiye_Students::grades_in_term( $term_id ) : array();
$multi_session = count( $sessions ) > 1;
$title    = $category->name . ( $multi_session ? ' — ' . $session->name : '' ) . ' Yoklaması';
$back_url = $multi_session
	? admin_url( 'admin.php?page=sms-attendance&cat=' . (int) $category->id . '&nizamiye_term=' . $term_id )
	: admin_url( 'admin.php?page=sms-attendance&nizamiye_term=' . $term_id );
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( $title, $scope_lbl ); ?>
	<p><a class="sms-back-link" href="<?php echo esc_url( $back_url ); ?>">← Geri</a></p>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<input type="hidden" name="page" value="sms-attendance">
				<input type="hidden" name="cat" value="<?php echo (int) $category->id; ?>">
				<input type="hidden" name="session" value="<?php echo (int) $session->id; ?>">
				<?php if ( $class_id ) : ?><input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>"><?php endif; ?>
				<input type="hidden" name="nizamiye_term" value="<?php echo (int) $term_id; ?>">
				<label class="sms-muted">Tarih</label>
				<input type="date" name="att_date" value="<?php echo esc_attr( $date ); ?>" onchange="this.form.submit()">
				<?php if ( $grades ) : ?>
					<select data-sms-filter-grade>
						<option value="">Tüm sınıflar</option>
						<?php foreach ( $grades as $g ) : ?>
							<option value="<?php echo (int) $g; ?>"><?php echo esc_html( nizamiye_grade_label( $g ) ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<button type="submit" class="sms-btn sms-btn-ghost">Getir</button>
			</form>
		</div>
	</div>

	<div class="sms-card sms-mt">
		<div class="sms-card-head">
			<h2><?php echo esc_html( nizamiye_format_date( $date ) ); ?><?php echo $multi_session ? ' — ' . esc_html( $session->name ) : ''; ?></h2>
			<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-all-present>Tümünü "Var" işaretle</button>
		</div>
		<?php if ( $students ) : ?>
			<?php nizamiye_form_open( 'nizamiye_save_attendance' ); nizamiye_back_url_field(); ?>
				<input type="hidden" name="category_id" value="<?php echo (int) $category->id; ?>">
				<input type="hidden" name="session_id" value="<?php echo (int) $session->id; ?>">
				<input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
				<input type="hidden" name="att_date" value="<?php echo esc_attr( $date ); ?>">
				<div class="sms-att-list" data-sms-roster>
					<?php
					$short = nizamiye_attendance_status_short();
					foreach ( $students as $s ) :
						$row      = $sheet[ (int) $s->id ] ?? null;
						$current  = $row ? $row->status : 'present';
						$note_val = $row->note ?? '';
						?>
						<div class="sms-att-row sms-roster-row" data-grade="<?php echo (int) ( $s->grade_level ?? 0 ); ?>">
							<span class="sms-att-row-name"><?php echo esc_html( nizamiye_student_name( $s ) ); ?></span>
							<div class="sms-att-row-status">
								<div class="sms-seg" role="radiogroup">
									<?php foreach ( $statuses as $key => $label ) : ?>
										<label class="sms-seg-item sms-seg-<?php echo esc_attr( $key ); ?>">
											<input type="radio" name="att_status[<?php echo (int) $s->id; ?>]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
											<span><?php echo esc_html( $short[ $key ] ?? $label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
								<button type="button" class="sms-note-btn <?php echo $note_val ? 'is-active' : ''; ?>" data-sms-note-toggle title="Not ekle/düzenle">
									<span class="dashicons dashicons-edit-page"></span>
								</button>
							</div>
							<div class="sms-att-row-note" data-sms-note-field <?php echo $note_val ? '' : 'style="display:none"'; ?>>
								<input type="text" class="sms-input-sm" name="att_note[<?php echo (int) $s->id; ?>]" value="<?php echo esc_attr( $note_val ); ?>" placeholder="Not…">
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="sms-pad">
					<button type="submit" class="sms-btn sms-btn-primary">Yoklamayı Kaydet</button>
					<?php if ( $sheet ) : ?><span class="sms-muted sms-ml">Bu tarih/oturum için kayıt mevcut; kaydederseniz güncellenir.</span><?php endif; ?>
				</div>
			</form>
		<?php else : ?>
			<div class="sms-empty"><span class="dashicons dashicons-groups"></span><h2>Öğrenci bulunamadı</h2><p><?php echo 'class' === $category->scope ? 'Derslik kadrosuna öğrenci ekleyin.' : 'Sorumlu olduğunuz sınıf seviyelerinde aktif öğrenci yok.'; ?></p></div>
		<?php endif; ?>
	</div>
</div>
