<?php
defined( 'ABSPATH' ) || exit;

$term_id = nizamiye_current_term_id();
$has_nonce = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' );
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$gview   = $has_nonce && isset( $_GET['gview'] ) ? sanitize_key( $_GET['gview'] ) : '';
$subject = $has_nonce && isset( $_GET['subject'] ) ? sanitize_text_field( wp_unslash( $_GET['subject'] ) ) : '';
$class_id = $has_nonce && isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// Eski bağlantı uyumu: class_id verilmiş ama görünüm seçilmemişse sınav listesine git.
if ( $class_id && ! $gview ) {
	$gview = 'class';
}

// Yönetici ve sınıf öğretmeni tüm branşları gezebilir; branş öğretmeni kendi dersliklerini görür.
$full_browse = nizamiye_is_manager() || nizamiye_is_class_teacher();
$grades_url  = admin_url( 'admin.php?page=nizamiye-grades&nizamiye_term=' . $term_id );

$class = $class_id ? Nizamiye_Classes::get( $class_id ) : null;
if ( $class && ! nizamiye_can_view_grades( $class_id ) ) {
	wp_die( 'Bu dersliğin notlarına erişim yetkiniz yok.' );
}
$can_manage = $class ? nizamiye_can_manage_class( $class_id ) : false;

$import_errors = get_transient( 'nizamiye_grade_import_errors_' . get_current_user_id() );
if ( $import_errors ) {
	delete_transient( 'nizamiye_grade_import_errors_' . get_current_user_id() );
}

/* ============================ NOT GİRİŞİ EKRANI ============================ */
if ( 'entry' === $gview && $class ) {
	if ( ! $can_manage ) {
		wp_die( 'Bu dersliğe not girme yetkiniz yok.' );
	}
	$students  = Nizamiye_Classes::students( $class_id );
	$class_url = nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'class', 'class_id' => $class_id, 'nizamiye_term' => $term_id ), admin_url( 'admin.php' ) ) );
	$tpl_base  = wp_nonce_url( add_query_arg( array( 'action' => 'nizamiye_grade_template', 'class_id' => $class_id ), admin_url( 'admin-post.php' ) ), 'nizamiye_grade_template' );
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( 'Not Gir: ' . $class->name, 'Sınav bilgilerini doldurun; ardından ister tek tek girin, ister listeyi indirip toplu yükleyin.' ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( $class_url ); ?>">← Sınav listesine dön</a></p>

		<?php if ( $import_errors ) : ?>
			<div class="sms-notice sms-notice-info">
				<span class="dashicons dashicons-info"></span>
				<div>
					<strong>Toplu yükleme uyarıları / atlanan satırlar:</strong>
					<ul class="sms-mini-list">
						<?php foreach ( array_slice( (array) $import_errors, 0, 30 ) as $e ) : ?>
							<li><?php echo esc_html( $e ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $students ) : ?>
			<div class="sms-card">
				<div class="sms-card-head"><h2>Sınav Bilgileri ve Not Girişi</h2><span class="sms-muted"><?php echo count( $students ); ?> öğrenci</span></div>
				<div class="sms-pad">
					<?php nizamiye_form_open( 'nizamiye_save_grades' ); nizamiye_back_url_field( $class_url ); ?>
						<input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
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
							<button type="button" class="sms-btn sms-btn-ghost" data-sms-grade-template data-url="<?php echo esc_url( $tpl_base ); ?>">
								<span class="dashicons dashicons-download"></span> Öğrenci Listesini İndir (toplu yükleme için)
							</button>
							<span class="sms-muted">İndirilen listede yalnızca <em>puan</em> sütunu boştur; doldurup aşağıdan yükleyin ya da puanları buradan tek tek girin.</span>
						</div>

						<div class="sms-table-scroll">
						<table class="sms-table">
							<thead><tr><th>Öğrenci</th><th style="width:140px">Puan</th></tr></thead>
							<tbody>
							<?php foreach ( $students as $s ) : ?>
								<tr>
									<td class="sms-name-cell"><?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $s ) ) ); ?><?php echo esc_html( nizamiye_student_name( $s ) ); ?></td>
									<td><input type="number" class="sms-input-sm" name="score[<?php echo (int) $s->id; ?>]" min="0" step="0.01" placeholder="—"></td>
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
						<?php nizamiye_back_url_field( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'entry', 'class_id' => $class_id, 'nizamiye_term' => $term_id ), admin_url( 'admin.php' ) ) ) ); ?>
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
if ( 'exam' === $gview && $class ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
	$title     = $has_nonce && isset( $_GET['title'] ) ? sanitize_text_field( wp_unslash( $_GET['title'] ) ) : '';
	$exam_date = $has_nonce && isset( $_GET['exam_date'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_date'] ) ) : '';
	$exam_type = $has_nonce && isset( $_GET['exam_type'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_type'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$scores    = $title ? Nizamiye_Grades::exam_scores( $class_id, $title, $exam_date, $exam_type ) : array();
	$class_url = nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'class', 'class_id' => $class_id, 'nizamiye_term' => $term_id ), admin_url( 'admin.php' ) ) );

	$sum = 0;
	$max = 0;
	foreach ( $scores as $r ) {
		$sum += (float) $r->score;
		$max  = max( $max, (float) $r->max_score );
	}
	$avg = $scores ? round( $sum / count( $scores ), 1 ) : 0;
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( $title ?: 'Sınav', $class->name . ( $exam_type ? ' • ' . $exam_type : '' ) . ( $exam_date ? ' • ' . nizamiye_format_date( $exam_date ) : '' ) ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( $class_url ); ?>">← Sınav listesine dön</a></p>

		<div class="sms-card">
			<div class="sms-card-head">
				<h2>Öğrenci Bazında Puanlar</h2>
				<span class="sms-muted"><?php echo count( $scores ); ?> öğrenci • Ortalama: <strong><?php echo esc_html( $avg ); ?></strong><?php echo $max ? ' / ' . esc_html( rtrim( rtrim( (string) $max, '0' ), '.' ) ) : ''; ?></span>
			</div>
			<?php if ( $scores ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table">
					<thead><tr><th>#</th><th>Öğrenci</th><th>Puan</th><th>Yüzde</th><?php if ( $can_manage ) : ?><th></th><?php endif; ?></tr></thead>
					<tbody>
					<?php foreach ( $scores as $i => $r ) :
						$rate = $r->max_score > 0 ? (int) round( $r->score / $r->max_score * 100 ) : null;
						?>
						<tr>
							<td class="sms-muted">#<?php echo (int) $i + 1; ?></td>
							<td class="sms-name-cell"><?php echo wp_kses_post( nizamiye_avatar( $r->first_name . ' ' . $r->last_name ) ); ?><strong><?php echo esc_html( $r->first_name . ' ' . $r->last_name ); ?></strong></td>
							<td><strong><?php echo esc_html( rtrim( rtrim( (string) $r->score, '0' ), '.' ) ); ?></strong> <span class="sms-muted">/ <?php echo esc_html( rtrim( rtrim( (string) $r->max_score, '0' ), '.' ) ); ?></span></td>
							<td><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $rate ) ); ?>"><?php echo null !== $rate ? esc_html( $rate . '%' ) : '—'; ?></span></td>
							<?php if ( $can_manage ) : ?>
								<td class="sms-actions-cell">
									<?php nizamiye_form_open( 'nizamiye_delete_grade', 'sms-inline sms-confirm' ); nizamiye_back_url_field(); ?>
										<input type="hidden" name="grade_id" value="<?php echo (int) $r->id; ?>">
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
if ( 'class' === $gview && $class ) {
	$exams    = Nizamiye_Grades::exams_for_class( $class_id );
	$teacher_u = $class->teacher_id ? get_userdata( (int) $class->teacher_id ) : null;
	$back_url  = $full_browse
		? nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'subject', 'subject' => ( trim( (string) $class->subject ) ?: 'Diğer' ), 'nizamiye_term' => $term_id ), admin_url( 'admin.php' ) ) )
		: $grades_url;
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( 'Notlar: ' . $class->name, ( $class->subject ?: 'Branş belirtilmedi' ) . ( $class->grade_level ? ' • ' . nizamiye_grade_label( $class->grade_level ) : '' ) . ( $teacher_u ? ' • ' . $teacher_u->display_name : '' ) ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( $back_url ); ?>">← Geri</a></p>

		<?php if ( $can_manage ) : ?>
			<div class="sms-toolbar">
				<span></span>
				<a class="sms-btn sms-btn-primary" href="<?php echo esc_url( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'entry', 'class_id' => $class_id, 'nizamiye_term' => $term_id ), admin_url( 'admin.php' ) ) ) ); ?>">
					<span class="dashicons dashicons-plus-alt2"></span> Not Gir
				</a>
			</div>
		<?php endif; ?>

		<div class="sms-card">
			<div class="sms-card-head"><h2>Sınavlar</h2><span class="sms-muted"><?php echo count( $exams ); ?> sınav</span></div>
			<?php if ( $exams ) : ?>
				<div class="sms-table-scroll">
				<table class="sms-table">
					<thead><tr><th>Sınav</th><th>Tür</th><th>Tarih</th><th>Öğrenci</th><th>Ortalama</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $exams as $e ) :
						$exam_url = nizamiye_view_nonce_url( add_query_arg( array(
							'page'      => 'nizamiye-grades',
							'gview'     => 'exam',
							'class_id'  => $class_id,
							'title'     => $e->title,
							'exam_date' => (string) $e->exam_date,
							'exam_type' => $e->exam_type,
							'nizamiye_term'  => $term_id,
						), admin_url( 'admin.php' ) ) );
						?>
						<tr>
							<td><a href="<?php echo esc_url( $exam_url ); ?>"><strong><?php echo esc_html( $e->title ); ?></strong></a></td>
							<td><span class="sms-badge sms-badge-indigo"><?php echo esc_html( $e->exam_type ?: '—' ); ?></span></td>
							<td class="sms-muted"><?php echo esc_html( nizamiye_format_date( $e->exam_date ) ); ?></td>
							<td><?php echo (int) $e->cnt; ?></td>
							<td><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( (int) $e->avg_rate ) ); ?>"><?php echo esc_html( $e->avg_score ); ?></span> <span class="sms-muted">/ <?php echo esc_html( rtrim( rtrim( (string) $e->max_score, '0' ), '.' ) ); ?> (%<?php echo (int) $e->avg_rate; ?>)</span></td>
							<td class="sms-actions-cell"><a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( $exam_url ); ?>">Öğrenci Bazında</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			<?php else : ?>
				<div class="sms-empty">
					<span class="dashicons dashicons-welcome-write-blog"></span>
					<h2>Henüz sınav yok</h2>
					<p><?php echo $can_manage ? '"Not Gir" ile ilk sınavı oluşturun.' : 'Bu derslikte henüz not girilmedi.'; ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
}

/* ============================ BRANŞIN DERSLİKLERİ ============================ */
if ( 'subject' === $gview && $full_browse ) {
	$classes = $term_id && $subject ? Nizamiye_Grades::classes_for_subject( $term_id, $subject ) : array();
	?>
	<div class="wrap sms-wrap">
		<?php nizamiye_view_header( 'Notlar: ' . $subject, 'Derslik seçin; sınav listesine ulaşın.' ); ?>
		<p><a class="sms-back-link" href="<?php echo esc_url( $grades_url ); ?>">← Branşlara dön</a></p>

		<?php if ( $classes ) : ?>
			<div class="sms-cat-grid">
				<?php foreach ( $classes as $c ) : $t = $c->teacher_id ? get_userdata( (int) $c->teacher_id ) : null; ?>
					<a class="sms-cat-card" href="<?php echo esc_url( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'class', 'class_id' => (int) $c->id, 'nizamiye_term' => $term_id ), admin_url( 'admin.php' ) ) ) ); ?>">
						<span class="sms-cat-icon"><span class="dashicons dashicons-book-alt"></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $c->name ); ?></span>
						<span class="sms-cat-meta"><?php echo $t ? esc_html( $t->display_name ) : 'Öğretmen atanmadı'; ?> • <?php echo (int) $c->exam_count; ?> sınav</span>
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
	<?php nizamiye_view_header( 'Notlar', $full_browse ? 'Branş seçin; derslik ve sınavlara ulaşın.' : 'Dersliğinizi seçin; sınav listesine ulaşın.' ); ?>

	<?php if ( ! $term_id ) : ?>
		<div class="sms-card"><div class="sms-empty"><span class="dashicons dashicons-calendar-alt"></span><h2>Aktif dönem yok</h2></div></div>
	<?php elseif ( $full_browse ) : ?>
		<?php $subjects = Nizamiye_Grades::subjects_for_term( $term_id ); ?>
		<?php if ( $subjects ) : ?>
			<div class="sms-cat-grid">
				<?php foreach ( $subjects as $s ) : ?>
					<a class="sms-cat-card" href="<?php echo esc_url( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'subject', 'subject' => $s->subject, 'nizamiye_term' => $term_id ), admin_url( 'admin.php' ) ) ) ); ?>">
						<span class="sms-cat-icon"><span class="sms-subject-initial"><?php echo esc_html( mb_strtoupper( mb_substr( $s->subject, 0, 2 ) ) ); ?></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $s->subject ); ?></span>
						<span class="sms-cat-meta"><?php echo (int) $s->class_count; ?> derslik • <?php echo (int) $s->grade_count; ?> not kaydı</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="sms-card"><div class="sms-empty"><span class="dashicons dashicons-book-alt"></span><h2>Derslik yok</h2><p>Önce Derslikler sayfasından derslik oluşturun.</p></div></div>
		<?php endif; ?>
	<?php else : ?>
		<?php $classes = Nizamiye_Classes::for_term( $term_id, get_current_user_id() ); ?>
		<?php if ( $classes ) : ?>
			<div class="sms-cat-grid">
				<?php foreach ( $classes as $c ) : ?>
					<a class="sms-cat-card" href="<?php echo esc_url( nizamiye_view_nonce_url( add_query_arg( array( 'page' => 'nizamiye-grades', 'gview' => 'class', 'class_id' => (int) $c->id, 'nizamiye_term' => $term_id ), admin_url( 'admin.php' ) ) ) ); ?>">
						<span class="sms-cat-icon"><span class="dashicons dashicons-book-alt"></span></span>
						<span class="sms-cat-name"><?php echo esc_html( $c->name ); ?></span>
						<span class="sms-cat-meta"><?php echo (int) $c->student_count; ?> öğrenci</span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="sms-card"><div class="sms-empty"><span class="dashicons dashicons-book-alt"></span><h2>Derslik yok</h2><p>Bu dönemde size atanmış derslik bulunmuyor.</p></div></div>
		<?php endif; ?>
	<?php endif; ?>
</div>
