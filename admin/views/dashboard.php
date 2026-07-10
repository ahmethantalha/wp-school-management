<?php
defined( 'ABSPATH' ) || exit;

$term_id  = sms_current_term_id();
$settings = sms_get_settings();
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Anasayfa', $settings['school_name'] . ' — genel görünüm' ); ?>

	<?php if ( ! $term_id ) : ?>
		<div class="sms-card sms-empty">
			<span class="dashicons dashicons-calendar-alt"></span>
			<h2>Aktif dönem yok</h2>
			<p>İstatistikleri görmek için önce bir dönem oluşturun.</p>
		</div>
	</div>
	<?php return; endif; ?>

	<?php
	$counts       = SMS_Reports::counts( $term_id );
	$teacher_only = sms_is_teacher();
	$class_ids    = $teacher_only ? sms_teacher_class_ids() : null;
	$student_ids  = $teacher_only ? sms_teacher_student_ids() : null;

	$habit_series = SMS_Habits::daily_rates( $term_id, 14, $student_ids );
	$att_series   = SMS_Attendance::daily_rates( $term_id, 14, $class_ids );
	$att_break    = SMS_Attendance::term_breakdown( $term_id, $class_ids );
	$scores       = SMS_Reports::student_scores( $term_id, $student_ids );
	$top          = array_slice( $scores, 0, 5 );
	$bottom       = array_slice( array_reverse( array_slice( $scores, 5 ) ), 0, 5 );

	$att_labels = sms_attendance_statuses();
	$donut      = array();
	$donut_colors = array( 'present' => '#22c55e', 'absent' => '#ef4444', 'late' => '#f59e0b', 'excused' => '#6366f1' );
	foreach ( $att_break as $status => $cnt ) {
		$donut[] = array( 'label' => $att_labels[ $status ] ?? $status, 'value' => $cnt, 'color' => $donut_colors[ $status ] ?? '#94a3b8' );
	}

	$line_points = array();
	foreach ( $habit_series as $date => $rate ) {
		$line_points[] = array( 'label' => date_i18n( 'j M', strtotime( $date ) ), 'value' => $rate );
	}
	$att_points = array();
	foreach ( $att_series as $date => $rate ) {
		$att_points[] = array( 'label' => date_i18n( 'j M', strtotime( $date ) ), 'value' => $rate );
	}
	?>

	<div class="sms-stat-grid">
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#6366f1"><span class="dashicons dashicons-groups"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $counts['students']; ?></span><span class="sms-stat-label"><?php echo $teacher_only ? 'Öğrencim' : 'Öğrenci'; ?></span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#0ea5e9"><span class="dashicons dashicons-businessperson"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $counts['teachers']; ?></span><span class="sms-stat-label">Öğretmen</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#f59e0b"><span class="dashicons dashicons-book-alt"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $counts['classes']; ?></span><span class="sms-stat-label">Derslik</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#22c55e"><span class="dashicons dashicons-yes-alt"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $counts['habits']; ?></span><span class="sms-stat-label">Alışkanlık</span></div>
		</div>
	</div>

	<div class="sms-grid-2">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Alışkanlık Tamamlama</h2><span class="sms-muted">Son 14 gün, genel ortalama</span></div>
			<div class="sms-chart" data-sms-chart="line" data-suffix="%" data-points='<?php echo esc_attr( wp_json_encode( $line_points ) ); ?>'></div>
		</div>
		<div class="sms-card">
			<div class="sms-card-head"><h2>Günlük Devam Oranı</h2><span class="sms-muted">Son 14 gün, yoklamalara göre</span></div>
			<div class="sms-chart" data-sms-chart="bar" data-suffix="%" data-points='<?php echo esc_attr( wp_json_encode( $att_points ) ); ?>'></div>
		</div>
	</div>

	<div class="sms-grid-3">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Yoklama Dağılımı</h2><span class="sms-muted">Dönem geneli</span></div>
			<?php if ( $donut ) : ?>
				<div class="sms-chart sms-chart-donut" data-sms-chart="donut" data-points='<?php echo esc_attr( wp_json_encode( $donut ) ); ?>'></div>
			<?php else : ?>
				<p class="sms-muted sms-pad">Henüz yoklama kaydı yok.</p>
			<?php endif; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2>⭐ Ayın Yıldızları</h2><span class="sms-muted">Bileşik başarı skoru</span></div>
			<?php if ( $top ) : ?>
				<ul class="sms-rank-list">
					<?php foreach ( $top as $i => $row ) : $st = $row['student']; ?>
						<li>
							<span class="sms-rank">#<?php echo $i + 1; ?></span>
							<?php echo sms_avatar( sms_student_name( $st ) ); // phpcs:ignore ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $st->id . '&sms_term=' . $term_id ) ); ?>"><?php echo esc_html( sms_student_name( $st ) ); ?></a>
							<span class="sms-score sms-rate-good"><?php echo (int) $row['score']; ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="sms-muted sms-pad">Skor hesaplamak için önce yoklama, not veya alışkanlık verisi girin.</p>
			<?php endif; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2>💪 Destek Bekleyenler</h2><span class="sms-muted">Gelişim fırsatı olan öğrenciler</span></div>
			<?php if ( $bottom ) : ?>
				<ul class="sms-rank-list">
					<?php foreach ( $bottom as $row ) : $st = $row['student']; ?>
						<li>
							<?php echo sms_avatar( sms_student_name( $st ) ); // phpcs:ignore ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $st->id . '&sms_term=' . $term_id ) ); ?>"><?php echo esc_html( sms_student_name( $st ) ); ?></a>
							<span class="sms-score <?php echo esc_attr( sms_rate_class( $row['score'] ) ); ?>"><?php echo (int) $row['score']; ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="sms-muted sms-pad">Yeterli veri birikince burada gelişim odaklı liste görünecek.</p>
			<?php endif; ?>
		</div>
	</div>
</div>
