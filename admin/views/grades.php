<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_term_id = nizamiye_current_term_id();
$nizamiye_has_nonce = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_gview   = $nizamiye_has_nonce && isset( $_GET['gview'] ) ? sanitize_key( $_GET['gview'] ) : '';
$nizamiye_subject = $nizamiye_has_nonce && isset( $_GET['subject'] ) ? sanitize_text_field( wp_unslash( $_GET['subject'] ) ) : '';
$nizamiye_class_id = $nizamiye_has_nonce && isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Eski bağlantı uyumu: class_id verilmiş ama görünüm seçilmemişse sınav listesine git.
if ( $nizamiye_class_id && ! $nizamiye_gview ) {
	$nizamiye_gview = 'class';
}

// Yönetici ve sınıf öğretmeni tüm branşları gezebilir; branş öğretmeni kendi dersliklerini görür.
$nizamiye_full_browse = nizamiye_is_manager() || nizamiye_is_class_teacher();
$nizamiye_grades_url  = admin_url( 'admin.php?page=nizamiye-grades&nizamiye_term=' . $nizamiye_term_id );

$nizamiye_class = $nizamiye_class_id ? Nizamiye_Classes::get( $nizamiye_class_id ) : null;
if ( $nizamiye_class && ! nizamiye_can_view_grades( $nizamiye_class_id ) ) {
	wp_die( 'Bu dersliğin notlarına erişim yetkiniz yok.' );
}
$nizamiye_can_manage = $nizamiye_class ? nizamiye_can_manage_class( $nizamiye_class_id ) : false;

$nizamiye_import_errors = get_transient( 'nizamiye_grade_import_errors_' . get_current_user_id() );
if ( $nizamiye_import_errors ) {
	delete_transient( 'nizamiye_grade_import_errors_' . get_current_user_id() );
}

/* ============================ NOT GİRİŞİ EKRANI ============================ */
if ( 'entry' === $nizamiye_gview && $nizamiye_class ) {
	if ( ! $nizamiye_can_manage ) {
		wp_die( 'Bu dersliğe not girme yetkiniz yok.' );
	}
	$nizamiye_students  = Nizamiye_Classes::students( $nizamiye_class_id );
	$nizamiye_class_url = nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'class', 'class_id' => $nizamiye_class_id, 'nizamiye_term' => $nizamiye_term_id ), admin_url( 'admin.php' ) ) );
	$nizamiye_tpl_base  = wp_nonce_url( add_query_arg( array( 'action' => 'nizamiye_grade_template', 'class_id' => $nizamiye_class_id ), admin_url( 'admin-post.php' ) ), 'nizamiye_grade_template' );
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( 'Not Gir: ' . $nizamiye_class->name, 'Sınav bilgilerini doldurun; ardından ister tek tek girin, ister listeyi indirip toplu yükleyin.' ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( $nizamiye_class_url ); ?>">← Sınav listesine dön</a></p>

		<?php if ( $nizamiye_import_errors ) : ?>
			<div class="sms-notice sms-notice-info">
				<span class="dashicons dashicons-info"></span>
				<div>
					<strong>Toplu yükleme uyarıları / atlanan satırlar:</strong>
					<ul class="sms-mini-list">
						<?php foreach ( array_slice( (array) $nizamiye_import_errors, 0, 30 ) as $nizamiye_e ) : ?>
							<li><?php echo esc_html( $nizamiye_e ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $nizamiye_students ) : ?>
			<div class="sms-card">
				<div class="sms-card-head"><h2>Sınav Bilgileri ve Not Girişi</h2><span class="sms-muted"><?php echo count( $nizamiye_students ); ?> öğrenci</span></div>
				<div class="sms-pad">
					<?php nizamiye_form_open( 'nizamiye_save_grades' ); nizamiye_back_url_field( $nizamiye_class_url ); ?>
						<input type="hidden" name="class_id" value="<?php echo (int) $nizamiye_class_id; ?>">
						<div class="sms-entry-meta">
							<div class="sms-field"><label>Sınav Adı *</label><input type="text" name="title" placeholder="Örn. 1. Yazılı" required></div>
							<div class="sms-field">
								<label>Tür</label>
								<select name="exam_type">
									<option value="Yazılı">Yazılı</option>
									<option value="Sözlü">Sözlü</option>
									<option value="Quiz">Quiz</option>
									<option value="Deneme">Deneme</option>
									<option value="Proje">Proje</option>
									<option value="Performans">Performans</option>
								</select>
							</div>
							<div class="sms-field"><label>Tarih</label><input type="date" name="exam_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></div>
							<div class="sms-field"><label>Tam Puan</label><input type="number" name="max_score" value="100" min="1" step="0.01"></div>
						</div>

						<div class="sms-entry-toolbar">
							<button type="button" class="sms-btn sms-btn-ghost" data-sms-grade-template data-url="<?php echo esc_url( $nizamiye_tpl_base ); ?>">
								<span class="dashicons dashicons-download"></span> Öğrenci Listesini İndir (toplu yükleme için)
							</button>
							<span class="sms-muted">İndirilen listede yalnızca <em>puan</em> sütunu boştur; doldurup aşağıdan yükleyin ya da puanları buradan tek tek girin.</span>
						</div>

						<div class="sms-table-scroll">
						<table class="sms-table">
							<thead><tr><th>Öğrenci</th><th style="width:140px">Puan</th></tr></thead>
							<tbody>
							<?php foreach ( $nizamiye_students as $nizamiye_s ) : ?>
								<tr>
									<td class="sms-name-cell"><?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_s ) ) ); ?><?php echo esc_html( nizamiye_student_name( $nizamiye_s ) ); ?></td>
									<td><input type="number" class="sms-input-sm" name="score[<?php echo (int) $nizamiye_s->id; ?>]" min="0" step="0.01" placeholder="—"></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						</div>
						<p class="sms-muted">Boş bırakılan öğrenciler için not girilmez (sınava girmeyenler).</p>
						<button type="submit" class="sms-btn sms-btn-primary">Notları Kaydet</button>
					</form>

					<hr class="sms-hr">

					<h4>Doldurulmuş Listeyi Yükle</h4>
					<p class="sms-muted">İndirdiğiniz listeyi puanlarla doldurup yükleyin — hemen ya da daha sonra (dosya derslik ve sınav bilgisini taşır). Kimlik, ad-soyad, yetki ve puan aralığı doğrulanır; uyuşmayan satırlar kaydedilmez.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="sms-form">
						<input type="hidden" name="action" value="nizamiye_grade_import">
						<?php wp_nonce_field( 'nizamiye_grade_import', '_nizamiye_nonce' ); ?>
						<?php nizamiye_back_url_field( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'entry', 'class_id' => $nizamiye_class_id, 'nizamiye_term' => $nizamiye_term_id ), admin_url( 'admin.php' ) ) ) ); ?>
						<div class="sms-inline-form">
							<input type="file" name="grade_file" accept=".xlsx,.csv,.txt" required>
							<button type="submit" class="sms-btn sms-btn-primary sms-btn-sm"><span class="dashicons dashicons-upload"></span> Yükle</button>
						</div>
					</form>
				</div>
			</div>
		<?php else : ?>
			<div class="sms-card"><div class="sms-empty"><span class="dashicons dashicons-groups"></span><h2>Derslikte öğrenci yok</h2><p>Önce derslik kadrosuna öğrenci ekleyin.</p></div></div>
		<?php endif; ?>
	</div>
	<?php
	return;
}

/* ============================ SINAV DETAYI (öğrenci bazında) ============================ */
if ( 'exam' === $nizamiye_gview && $nizamiye_class ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
	$title     = $nizamiye_has_nonce && isset( $_GET['title'] ) ? sanitize_text_field( wp_unslash( $_GET['title'] ) ) : '';
	$nizamiye_exam_date = $nizamiye_has_nonce && isset( $_GET['exam_date'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_date'] ) ) : '';
	$nizamiye_exam_type = $nizamiye_has_nonce && isset( $_GET['exam_type'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_type'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$nizamiye_scores    = $title ? Nizamiye_Grades::exam_scores( $nizamiye_class_id, $title, $nizamiye_exam_date, $nizamiye_exam_type ) : array();
	$nizamiye_class_url = nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'class', 'class_id' => $nizamiye_class_id, 'nizamiye_term' => $nizamiye_term_id ), admin_url( 'admin.php' ) ) );

	$nizamiye_sum = 0;
	$nizamiye_max = 0;
	foreach ( $nizamiye_scores as $nizamiye_r ) {
		$nizamiye_sum += (float) $nizamiye_r->score;
		$nizamiye_max  = max( $nizamiye_max, (float) $nizamiye_r->max_score );
	}
	$nizamiye_avg = $nizamiye_scores ? round( $nizamiye_sum / count( $nizamiye_scores ), 1 ) : 0;
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( $title ?: 'Sınav', $nizamiye_class->name . ( $nizamiye_exam_type ? ' • ' . $nizamiye_exam_type : '' ) . ( $nizamiye_exam_date ? ' • ' . nizamiye_format_date( $nizamiye_exam_date ) : '' ) ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( $nizamiye_class_url ); ?>">← Sınav listesine dön</a></p>

		<div class="sms-card">
			<div class="sms-card-head">
				<h2>Öğrenci Bazında Puanlar</h2>
				<span class="sms-muted"><?php echo count( $nizamiye_scores ); ?> öğrenci • Ortalama: <strong><?php echo esc_html( $nizamiye_avg ); ?></strong><?php echo $nizamiye_max ? ' / ' . esc_html( rtrim( rtrim( (string) $nizamiye_max, '0' ), '.' ) ) : ''; ?></span>
			</div>
			<?php if ( $nizamiye_scores ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table">
					<thead><tr><th>#</th><th>Öğrenci</th><th>Puan</th><th>Yüzde</th><?php if ( $nizamiye_can_manage ) : ?><th></th><?php endif; ?></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_scores as $nizamiye_i => $nizamiye_r ) :
						$nizamiye_rate = $nizamiye_r->max_score > 0 ? (int) round( $nizamiye_r->score / $nizamiye_r->max_score * 100 ) : null;
						?>
						<tr>
							<td class="sms-muted">#<?php echo (int) $nizamiye_i + 1; ?></td>
							<td class="sms-name-cell"><?php echo wp_kses_post( nizamiye_avatar( $nizamiye_r->first_name . ' ' . $nizamiye_r->last_name ) ); ?><strong><?php echo esc_html( $nizamiye_r->first_name . ' ' . $nizamiye_r->last_name ); ?></strong></td>
							<td><strong><?php echo esc_html( rtrim( rtrim( (string) $nizamiye_r->score, '0' ), '.' ) ); ?></strong> <span class="sms-muted">/ <?php echo esc_html( rtrim( rtrim( (string) $nizamiye_r->max_score, '0' ), '.' ) ); ?></span></td>
							<td><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_rate ) ); ?>"><?php echo null !== $nizamiye_rate ? esc_html( $nizamiye_rate . '%' ) : '—'; ?></span></td>
							<?php if ( $nizamiye_can_manage ) : ?>
								<td class="sms-actions-cell">
									<?php nizamiye_form_open( 'nizamiye_delete_grade', 'sms-inline sms-confirm' ); nizamiye_back_url_field(); ?>
										<input type="hidden" name="grade_id" value="<?php echo (int) $nizamiye_r->id; ?>">
										<button type="submit" class="sms-btn sms-btn-danger-ghost sms-btn-sm" data-confirm="Bu not silinsin mi?">Sil</button>
									</form>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php else : ?>
				<div class="sms-empty"><h2>Kayıt bulunamadı</h2></div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
}

/* ============================ DERSLİĞİN SINAVLARI ============================ */
if ( 'class' === $nizamiye_gview && $nizamiye_class ) {
	$nizamiye_exams    = Nizamiye_Grades::exams_for_class( $nizamiye_class_id );
	$nizamiye_teacher_u = $nizamiye_class->teacher_id ? get_userdata( (int) $nizamiye_class->teacher_id ) : null;
	$nizamiye_back_url  = $nizamiye_full_browse
		? nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'subject', 'subject' => ( trim( (string) $nizamiye_class->subject ) ?: 'Diğer' ), 'nizamiye_term' => $nizamiye_term_id ), admin_url( 'admin.php' ) ) )
		: $nizamiye_grades_url;
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( 'Notlar: ' . $nizamiye_class->name, ( $nizamiye_class->subject ?: 'Branş belirtilmedi' ) . ( $nizamiye_class->grade_level ? ' • ' . nizamiye_grade_label( $nizamiye_class->grade_level ) : '' ) . ( $nizamiye_teacher_u ? ' • ' . $nizamiye_teacher_u->display_name : '' ) ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( $nizamiye_back_url ); ?>">← Geri</a></p>

		<?php if ( $nizamiye_can_manage ) : ?>
			<div class="sms-toolbar">
				<span></span>
				<a class="sms-btn sms-btn-primary" href="<?php echo esc_url( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'entry', 'class_id' => $nizamiye_class_id, 'nizamiye_term' => $nizamiye_term_id ), admin_url( 'admin.php' ) ) ) ); ?>">
					<span class="dashicons dashicons-plus-alt2"></span> Not Gir
				</a>
			</div>
		<?php endif; ?>

		<div class="sms-card">
			<div class="sms-card-head"><h2>Sınavlar</h2><span class="sms-muted"><?php echo count( $nizamiye_exams ); ?> sınav</span></div>
			<?php if ( $nizamiye_exams ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table">
					<thead><tr><th>Sınav</th><th>Tür</th><th>Tarih</th><th>Öğrenci</th><th>Ortalama</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_exams as $nizamiye_e ) :
						$nizamiye_exam_url = nizamiye_view_nonce_url( add_query_arg( array(
							'page'      => 'nizamiye-grades',
							'gview'     => 'exam',
							'class_id'  => $nizamiye_class_id,
							'title'     => $nizamiye_e->title,
							'exam_date' => (string) $nizamiye_e->exam_date,
							'exam_type' => $nizamiye_e->exam_type,
							'nizamiye_term'  => $nizamiye_term_id,
						), admin_url( 'admin.php' ) ) );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $nizamiye_exam_url ); ?>"><strong><?php echo esc_html( $nizamiye_e->title ); ?></strong></a></td>
							<td><span class="sms-badge sms-badge-indigo"><?php echo esc_html( $nizamiye_e->exam_type ?: '—' ); ?></span></td>
							<td class="sms-muted"><?php echo esc_html( nizamiye_format_date( $nizamiye_e->exam_date ) ); ?></td>
							<td><?php echo (int) $nizamiye_e->cnt; ?></td>
							<td><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( (int) $nizamiye_e->avg_rate ) ); ?>"><?php echo esc_html( $nizamiye_e->avg_score ); ?></span> <span class="sms-muted">/ <?php echo esc_html( rtrim( rtrim( (string) $nizamiye_e->max_score, '0' ), '.' ) ); ?> (%<?php echo (int) $nizamiye_e->avg_rate; ?>)</span></td>
							<td class="sms-actions-cell"><a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( $nizamiye_exam_url ); ?>">Öğrenci Bazında</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php else : ?>
				<div class="sms-empty">
					<span class="dashicons dashicons-welcome-write-blog"></span>
					<h2>Henüz sınav yok</h2>
					<p><?php echo $nizamiye_can_manage ? '"Not Gir" ile ilk sınavı oluşturun.' : 'Bu derslikte henüz not girilmedi.'; ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
}

/* ============================ BRANŞIN DERSLİKLERİ ============================ */
if ( 'subject' === $nizamiye_gview && $nizamiye_full_browse ) {
	$nizamiye_classes = $nizamiye_term_id && $nizamiye_subject ? Nizamiye_Grades::classes_for_subject( $nizamiye_term_id, $nizamiye_subject ) : array();
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( 'Notlar: ' . $nizamiye_subject, 'Derslik seçin; sınav listesine ulaşın.' ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( $nizamiye_grades_url ); ?>">← Branşlara dön</a></p>

		<?php if ( $nizamiye_classes ) : ?>
			<div class="sms-cat-grid">
				<?php foreach ( $nizamiye_classes as $nizamiye_c ) : $nizamiye_t = $nizamiye_c->teacher_id ? get_userdata( (int) $nizamiye_c->teacher_id ) : null; ?>
					<a class="sms-cat-card" href="<?php echo esc_url( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'class', 'class_id' => (int) $nizamiye_c->id, 'nizamiye_term' => $nizamiye_term_id ), admin_url( 'admin.php' ) ) ) ); ?>">
						<span class="sms-cat-icon"><span class="dashicons dashicons-book-alt"></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $nizamiye_c->name ); ?></span>
						<span class="sms-cat-meta"><?php echo $nizamiye_t ? esc_html( $nizamiye_t->display_name ) : 'Öğretmen atanmadı'; ?> • <?php echo (int) $nizamiye_c->exam_count; ?> sınav</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="sms-card"><div class="sms-empty"><span class="dashicons dashicons-book-alt"></span><h2>Derslik yok</h2><p>Bu branşta derslik bulunmuyor.</p></div></div>
		<?php endif; ?>
	</div>
	<?php
	return;
}

/* ============================ GİRİŞ: BRANŞLAR / KENDİ DERSLİKLERİ ============================ */
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Notlar', $nizamiye_full_browse ? 'Branş seçin; derslik ve sınavlara ulaşın.' : 'Dersliğinizi seçin; sınav listesine ulaşın.' ); ?>

	<?php if ( ! $nizamiye_term_id ) : ?>
		<div class="sms-card"><div class="sms-empty"><span class="dashicons dashicons-calendar-alt"></span><h2>Aktif dönem yok</h2></div></div>
	<?php elseif ( $nizamiye_full_browse ) : ?>
		<?php $nizamiye_subjects = Nizamiye_Grades::subjects_for_term( $nizamiye_term_id ); ?>
		<?php if ( $nizamiye_subjects ) : ?>
			<div class="sms-cat-grid">
				<?php foreach ( $nizamiye_subjects as $nizamiye_s ) : ?>
					<a class="sms-cat-card" href="<?php echo esc_url( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'subject', 'subject' => $nizamiye_s->subject, 'nizamiye_term' => $nizamiye_term_id ), admin_url( 'admin.php' ) ) ) ); ?>">
						<span class="sms-cat-icon"><span class="sms-subject-initial"><?php echo esc_html( mb_strtoupper( mb_substr( $nizamiye_s->subject, 0, 2 ) ) ); ?></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $nizamiye_s->subject ); ?></span>
						<span class="sms-cat-meta"><?php echo (int) $nizamiye_s->class_count; ?> derslik • <?php echo (int) $nizamiye_s->grade_count; ?> not kaydı</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="sms-card"><div class="sms-empty"><span class="dashicons dashicons-book-alt"></span><h2>Derslik yok</h2><p>Önce Derslikler sayfasından derslik oluşturun.</p></div></div>
		<?php endif; ?>
	<?php else : ?>
		<?php $nizamiye_classes = Nizamiye_Classes::for_term( $nizamiye_term_id, get_current_user_id() ); ?>
		<?php if ( $nizamiye_classes ) : ?>
			<div class="sms-cat-grid">
				<?php foreach ( $nizamiye_classes as $nizamiye_c ) : ?>
					<a class="sms-cat-card" href="<?php echo esc_url( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'class', 'class_id' => (int) $nizamiye_c->id, 'nizamiye_term' => $nizamiye_term_id ), admin_url( 'admin.php' ) ) ) ); ?>">
						<span class="sms-cat-icon"><span class="dashicons dashicons-book-alt"></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $nizamiye_c->name ); ?></span>
						<span class="sms-cat-meta"><?php echo (int) $nizamiye_c->student_count; ?> öğrenci</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="sms-card"><div class="sms-empty"><span class="dashicons dashicons-book-alt"></span><h2>Derslik yok</h2><p>Bu dönemde size atanmış derslik bulunmuyor.</p></div></div>
		<?php endif; ?>
	<?php endif; ?>
</div>
