<?php
defined( 'ABSPATH' ) || exit;

$term_id  = sms_current_term_id();
$teacher  = sms_is_teacher();
$classes  = $term_id ? SMS_Classes::for_term( $term_id, $teacher ? get_current_user_id() : 0 ) : array();
$class_id = isset( $_GET['class_id'] ) ? (int) $_GET['class_id'] : 0;
$date     = isset( $_GET['att_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['att_date'] ) ? sanitize_text_field( wp_unslash( $_GET['att_date'] ) ) : current_time( 'Y-m-d' );

$class    = $class_id ? SMS_Classes::get( $class_id ) : null;
if ( $class && ! sms_can_manage_class( $class_id ) ) {
	wp_die( 'Bu dersliğin yoklamasına erişim yetkiniz yok.' );
}
$students = $class ? SMS_Classes::students( $class_id ) : array();
$sheet    = $class ? SMS_Attendance::sheet( $class_id, $date ) : array();
$statuses = sms_attendance_statuses();
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Yoklama', 'Derslik ve tarih seçin, tüm sınıfın yoklamasını tek ekranda alın.' ); ?>

	<div class="sms-card">
		<div class="sms-pad">
			<form method="get" class="sms-filters">
				<input type="hidden" name="page" value="sms-attendance">
				<?php if ( $term_id ) : ?><input type="hidden" name="sms_term" value="<?php echo (int) $term_id; ?>"><?php endif; ?>
				<select name="class_id" onchange="this.form.submit()">
					<option value="0">— Derslik seçin —</option>
					<?php foreach ( $classes as $c ) : ?>
						<option value="<?php echo (int) $c->id; ?>" <?php selected( $class_id, (int) $c->id ); ?>><?php echo esc_html( $c->name . ' (' . $c->student_count . ' öğrenci)' ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="date" name="att_date" value="<?php echo esc_attr( $date ); ?>" onchange="this.form.submit()">
				<button type="submit" class="sms-btn sms-btn-ghost">Getir</button>
			</form>
		</div>
	</div>

	<?php if ( $class ) : ?>
		<div class="sms-card sms-mt">
			<div class="sms-card-head">
				<h2><?php echo esc_html( $class->name ); ?> — <?php echo esc_html( sms_format_date( $date ) ); ?></h2>
				<button type="button" class="sms-btn sms-btn-ghost sms-btn-sm" data-sms-all-present>Tümünü "Geldi" işaretle</button>
			</div>
			<?php if ( $students ) : ?>
				<?php sms_form_open( 'sms_save_attendance' ); sms_back_url_field(); ?>
					<input type="hidden" name="class_id" value="<?php echo (int) $class_id; ?>">
					<input type="hidden" name="att_date" value="<?php echo esc_attr( $date ); ?>">
					<table class="sms-table sms-att-table">
						<thead><tr><th>Öğrenci</th><th>Durum</th><th>Not</th></tr></thead>
						<tbody>
						<?php foreach ( $students as $s ) :
							$row     = $sheet[ (int) $s->id ] ?? null;
							$current = $row ? $row->status : 'present';
							?>
							<tr>
								<td class="sms-name-cell"><?php echo sms_avatar( sms_student_name( $s ) ); // phpcs:ignore ?><strong><?php echo esc_html( sms_student_name( $s ) ); ?></strong></td>
								<td>
									<div class="sms-seg" role="radiogroup">
										<?php foreach ( $statuses as $key => $label ) : ?>
											<label class="sms-seg-item sms-seg-<?php echo esc_attr( $key ); ?>">
												<input type="radio" name="att_status[<?php echo (int) $s->id; ?>]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
												<span><?php echo esc_html( $label ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</td>
								<td><input type="text" class="sms-input-sm" name="att_note[<?php echo (int) $s->id; ?>]" value="<?php echo esc_attr( $row->note ?? '' ); ?>" placeholder="Not…"></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<div class="sms-pad">
						<button type="submit" class="sms-btn sms-btn-primary">Yoklamayı Kaydet</button>
						<?php if ( $sheet ) : ?><span class="sms-muted sms-ml">Bu tarih için kayıt mevcut; kaydederseniz güncellenir.</span><?php endif; ?>
					</div>
				</form>
			<?php else : ?>
				<div class="sms-empty"><span class="dashicons dashicons-groups"></span><h2>Derslikte öğrenci yok</h2><p>Önce derslik kadrosuna öğrenci ekleyin.</p></div>
			<?php endif; ?>
		</div>
	<?php elseif ( $classes ) : ?>
		<div class="sms-card sms-mt"><div class="sms-empty"><span class="dashicons dashicons-clipboard"></span><h2>Derslik seçin</h2><p>Yoklama almak için yukarıdan bir derslik ve tarih seçin.</p></div></div>
	<?php else : ?>
		<div class="sms-card sms-mt"><div class="sms-empty"><span class="dashicons dashicons-book-alt"></span><h2>Derslik yok</h2><p>Bu dönemde size ait derslik bulunmuyor.</p></div></div>
	<?php endif; ?>
</div>
