<?php
defined( 'ABSPATH' ) || exit;

$term_id  = nizamiye_current_term_id();
$settings = nizamiye_get_settings();
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Anasayfa', $settings['school_name'] . ' — genel görünüm' ); ?>

	<?php if ( ! $term_id ) : ?>
		<div class="sms-card sms-empty">
			<span class="dashicons dashicons-calendar-alt"></span>
			<h2>Aktif dönem yok</h2>
			<p>İstatistikleri görmek için önce bir dönem oluşturun.</p>
		</div>
	</div>
	<?php return; endif; ?>

	<?php
	$counts       = Nizamiye_Reports::counts( $term_id );
	$teacher_only = nizamiye_is_teacher();
	$student_ids  = $teacher_only ? nizamiye_teacher_student_ids() : null;

	$habit_series = Nizamiye_Habits::daily_rates( $term_id, 14, $student_ids );
	$att_series   = Nizamiye_Attendance::daily_rates( $term_id, 14, $student_ids );
	$att_break    = Nizamiye_Attendance::term_breakdown( $term_id, $student_ids );
	$scores       = Nizamiye_Reports::student_scores( $term_id, $student_ids );
	$top          = array_slice( $scores, 0, 5 );
	$bottom       = array_slice( array_reverse( array_slice( $scores, 5 ) ), 0, 5 );
	$grade_sum    = Nizamiye_Reports::grade_level_summary( $term_id, $student_ids );
	$cat_sum      = Nizamiye_Reports::category_summary( $term_id, $student_ids );

	$att_labels = nizamiye_attendance_statuses();
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
							<span class="sms-rank">#<?php echo (int) $i + 1; ?></span>
							<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $st ) ) ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $st->id . '&nizamiye_term=' . $term_id ) ); ?>"><?php echo esc_html( nizamiye_student_name( $st ) ); ?></a>
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
							<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $st ) ) ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $st->id . '&nizamiye_term=' . $term_id ) ); ?>"><?php echo esc_html( nizamiye_student_name( $st ) ); ?></a>
							<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $row['score'] ) ); ?>"><?php echo (int) $row['score']; ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="sms-muted sms-pad">Yeterli veri birikince burada gelişim odaklı liste görünecek.</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="sms-grid-2 sms-mt">
		<div class="sms-card">
			<div class="sms-card-head">
				<h2>Sınıf Bazında Özet</h2>
				<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-reports&rtype=genel&group=sinif&nizamiye_term=' . $term_id ) ); ?>">Detaylı Analiz →</a>
			</div>
			<?php if ( $grade_sum ) : ?>
				<table class="sms-table">
					<thead><tr><th>Sınıf</th><th>Öğrenci</th><th class="sms-center">Devam</th><th class="sms-center">Alışkanlık</th><th class="sms-center">Not Ort.</th></tr></thead>
					<tbody>
					<?php foreach ( $grade_sum as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( nizamiye_grade_label( $row['grade'] ) ); ?></strong></td>
							<td class="sms-muted"><?php echo (int) $row['count']; ?></td>
							<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $row['att'] ) ); ?>"><?php echo null !== $row['att'] ? esc_html( $row['att'] . '%' ) : '—'; ?></span></td>
							<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $row['habit'] ) ); ?>"><?php echo null !== $row['habit'] ? esc_html( $row['habit'] . '%' ) : '—'; ?></span></td>
							<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $row['grade_avg'] ) ); ?>"><?php echo null !== $row['grade_avg'] ? esc_html( $row['grade_avg'] . '%' ) : '—'; ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="sms-muted sms-pad">Henüz sınıf bazında veri yok.</p>
			<?php endif; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head">
				<h2>Yoklama Türlerine Göre</h2>
				<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-reports&rtype=yoklama&nizamiye_term=' . $term_id ) ); ?>">Detaylı Analiz →</a>
			</div>
			<?php if ( $cat_sum ) : ?>
				<table class="sms-table">
					<thead><tr><th>Tür</th><th class="sms-center">Katılım</th><th class="sms-center">Geldi</th><th class="sms-center">Gelmedi</th><th class="sms-center">Geç</th><th class="sms-center">İzinli</th></tr></thead>
					<tbody>
					<?php foreach ( $cat_sum as $row ) : ?>
						<tr>
							<td><span class="dashicons <?php echo esc_attr( $row['icon'] ?: 'dashicons-clipboard' ); ?>"></span> <strong><?php echo esc_html( $row['name'] ); ?></strong></td>
							<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( nizamiye_rate_class( $row['rate'] ) ); ?>"><?php echo null !== $row['rate'] ? esc_html( $row['rate'] . '%' ) : '—'; ?></span></td>
							<td class="sms-center sms-rate-good"><?php echo (int) $row['present']; ?></td>
							<td class="sms-center sms-rate-low"><?php echo (int) $row['absent']; ?></td>
							<td class="sms-center sms-rate-mid"><?php echo (int) $row['late']; ?></td>
							<td class="sms-center" style="color:#6366f1"><?php echo (int) $row['excused']; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="sms-muted sms-pad">Henüz yoklama kaydı yok.</p>
			<?php endif; ?>
		</div>
	</div>
</div>
