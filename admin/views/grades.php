<?php
defined( 'ABSPATH' ) || exit;

$term_id  = sms_current_term_id();
$teacher  = sms_is_teacher();
$classes  = $term_id ? SMS_Classes::for_term( $term_id, $teacher ? get_current_user_id() : 0 ) : array();
$class_id = isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
$class    = $class_id ? SMS_Classes::get( $class_id ) : null;

if ( $class && ! sms_can_manage_class( $class_id ) ) {
	wp_die( 'Bu dersliğin notlarına erişim yetkiniz yok.' );
}
$students = $class ? SMS_Classes::students( $class_id ) : array();
$grades   = $class ? SMS_Grades::for_class( $class_id ) : array();

// Sınav geçmişini sınav başlığına göre grupla.
$exams = array();
foreach ( $grades as $g ) {
	$key = $g->title . '|' . $g->exam_date;
	if ( ! isset( $exams[ $key ] ) ) {
		$exams[ $key ] = array( 'title' => $g->title, 'type' => $g->exam_type, 'date' => $g->exam_date, 'rows' => array() );
	}
	$exams[ $key ]['rows'][] = $g;
}
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Notlar', 'Derslik seçin, sınav tanımlayın ve tüm öğrencilerin puanlarını tek ekranda girin.' ); ?>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<input type="hidden" name="page" value="sms-grades">
				<?php if ( $term_id ) : ?><input type="hidden" name="sms_term" value="<?php echo (int) $term_id; ?>"><?php endif; ?>
				<select name="class_id" onchange="this.form.submit()">
					<option value="0">— Derslik seçin —</option>
					<?php foreach ( $classes as $c ) : ?>
						<option value="<?php echo (int) $c->id; ?>" <?php selected( $class_id, (int) $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="sms-btn sms-btn-ghost">Getir</button>
			</form>
		</div>
	</div>

	<?php if ( $class ) : ?>
		<div class="sms-grid-2 sms-grid-uneven sms-mt">
			<div class="sms-card">
				<div class="sms-card-head"><h2>Yeni Sınav / Değerlendirme</h2><span class="sms-muted"><?php echo esc_html( $class->name ); ?></span></div>
				<?php if ( $students ) : ?>
					<div class="sms-pad">
						<?php sms_form_open( 'sms_save_grades' ); sms_back_url_field(); ?>
							<input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
							<div class="sms-field-row">
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
							</div>
							<div class="sms-field-row">
								<div class="sms-field"><label>Tarih</label><input type="date" name="exam_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></div>
								<div class="sms-field"><label>Tam Puan</label><input type="number" name="max_score" value="100" min="1" step="0.01"></div>
							</div>

							<table class="sms-table">
								<thead><tr><th>Öğrenci</th><th style="width:130px">Puan</th></tr></thead>
								<tbody>
								<?php foreach ( $students as $s ) : ?>
									<tr>
										<td class="sms-name-cell"><?php echo sms_avatar( sms_student_name( $s ) ); // phpcs:ignore ?><?php echo esc_html( sms_student_name( $s ) ); ?></td>
										<td><input type="number" class="sms-input-sm" name="score[<?php echo (int) $s->id; ?>]" min="0" step="0.01" placeholder="—"></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
							<p class="sms-muted">Boş bırakılan öğrenciler için not girilmez (sınava girmeyenler).</p>
							<button type="submit" class="sms-btn sms-btn-primary">Notları Kaydet</button>
						</form>
					</div>
				<?php else : ?>
					<div class="sms-empty"><span class="dashicons dashicons-groups"></span><h2>Derslikte öğrenci yok</h2><p>Önce derslik kadrosuna öğrenci ekleyin.</p></div>
				<?php endif; ?>
			</div>

			<div class="sms-card">
				<div class="sms-card-head"><h2>Sınav Geçmişi</h2></div>
				<?php if ( $exams ) : ?>
					<div class="sms-pad">
						<?php foreach ( $exams as $exam ) :
							$sum = 0;
							foreach ( $exam['rows'] as $r ) {
								$sum += (float) $r->score;
							}
							$avg = count( $exam['rows'] ) ? round( $sum / count( $exam['rows'] ), 1 ) : 0;
							?>
							<details class="sms-details">
								<summary>
									<strong><?php echo esc_html( $exam['title'] ); ?></strong>
									<span class="sms-muted"> <?php echo esc_html( $exam['type'] ); ?> • <?php echo esc_html( sms_format_date( $exam['date'] ) ); ?> • Ortalama: <?php echo esc_html( $avg ); ?></span>
								</summary>
								<table class="sms-table">
									<tbody>
									<?php foreach ( $exam['rows'] as $r ) : ?>
										<tr>
											<td><?php echo esc_html( $r->first_name . ' ' . $r->last_name ); ?></td>
											<td><strong><?php echo esc_html( rtrim( rtrim( (string) $r->score, '0' ), '.' ) ); ?></strong> <span class="sms-muted">/ <?php echo esc_html( rtrim( rtrim( (string) $r->max_score, '0' ), '.' ) ); ?></span></td>
											<td class="sms-actions-cell">
												<?php sms_form_open( 'sms_delete_grade', 'sms-inline sms-confirm' ); sms_back_url_field(); ?>
													<input type="hidden" name="grade_id" value="<?php echo (int) $r->id; ?>">
													<button type="submit" class="sms-btn sms-btn-danger-ghost sms-btn-sm" data-confirm="Bu not silinsin mi?">Sil</button>
												</form>
											</td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</details>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="sms-muted sms-pad">Bu derslikte henüz not girilmedi.</p>
				<?php endif; ?>
			</div>
		</div>
	<?php elseif ( $classes ) : ?>
		<div class="sms-card sms-mt"><div class="sms-empty"><span class="dashicons dashicons-welcome-write-blog"></span><h2>Derslik seçin</h2><p>Not girmek için yukarıdan bir derslik seçin.</p></div></div>
	<?php endif; ?>
</div>
