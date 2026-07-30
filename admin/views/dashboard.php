<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_term_id  = nizamiye_current_term_id();
$nizamiye_settings = nizamiye_get_settings();
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Anasayfa', $nizamiye_settings['school_name'] . ' — genel görünüm' ); ?>

	<?php if ( ! $nizamiye_term_id ) : ?>
		<div class="sms-card sms-empty">
			<span class="dashicons dashicons-calendar-alt"></span>
			<h2>Aktif dönem yok</h2>
			<p>İstatistikleri görmek için önce bir dönem oluşturun.</p>
		</div>
	</div>
	<?php return; endif; ?>

	<?php
	$nizamiye_counts       = Nizamiye_Reports::counts( $nizamiye_term_id );
	$nizamiye_teacher_only = nizamiye_is_teacher();
	$nizamiye_student_ids  = $nizamiye_teacher_only ? nizamiye_teacher_student_ids() : null;

	$nizamiye_habit_series = Nizamiye_Habits::daily_rates( $nizamiye_term_id, 14, $nizamiye_student_ids );
	$nizamiye_att_series   = Nizamiye_Attendance::daily_rates( $nizamiye_term_id, 14, $nizamiye_student_ids );
	$nizamiye_att_break    = Nizamiye_Attendance::term_breakdown( $nizamiye_term_id, $nizamiye_student_ids );
	$nizamiye_scores       = Nizamiye_Reports::student_scores( $nizamiye_term_id, $nizamiye_student_ids );
	$nizamiye_top          = array_slice( $nizamiye_scores, 0, 5 );
	$nizamiye_bottom       = array_slice( array_reverse( array_slice( $nizamiye_scores, 5 ) ), 0, 5 );
	$nizamiye_grade_sum    = Nizamiye_Reports::grade_level_summary( $nizamiye_term_id, $nizamiye_student_ids );
	$nizamiye_cat_sum      = Nizamiye_Reports::category_summary( $nizamiye_term_id, $nizamiye_student_ids );

	$nizamiye_att_labels = nizamiye_attendance_statuses();
	$nizamiye_donut      = array();
	$nizamiye_donut_colors = array( 'present' => '#22c55e', 'absent' => '#ef4444', 'late' => '#f59e0b', 'excused' => '#6366f1' );
	foreach ( $nizamiye_att_break as $nizamiye_status => $nizamiye_cnt ) {
		$nizamiye_donut[] = array( 'label' => $nizamiye_att_labels[ $nizamiye_status ] ?? $nizamiye_status, 'value' => $nizamiye_cnt, 'color' => $nizamiye_donut_colors[ $nizamiye_status ] ?? '#94a3b8' );
	}

	$nizamiye_line_points = array();
	foreach ( $nizamiye_habit_series as $nizamiye_date => $nizamiye_rate ) {
		$nizamiye_line_points[] = array( 'label' => date_i18n( 'j M', strtotime( $nizamiye_date ) ), 'value' => $nizamiye_rate );
	}
	$nizamiye_att_points = array();
	foreach ( $nizamiye_att_series as $nizamiye_date => $nizamiye_rate ) {
		$nizamiye_att_points[] = array( 'label' => date_i18n( 'j M', strtotime( $nizamiye_date ) ), 'value' => $nizamiye_rate );
	}
	?>

	<div class="sms-stat-grid">
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#6366f1"><span class="dashicons dashicons-groups"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $nizamiye_counts['students']; ?></span><span class="sms-stat-label"><?php echo $nizamiye_teacher_only ? 'Öğrencim' : 'Öğrenci'; ?></span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#0ea5e9"><span class="dashicons dashicons-businessperson"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $nizamiye_counts['teachers']; ?></span><span class="sms-stat-label">Öğretmen</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#f59e0b"><span class="dashicons dashicons-book-alt"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $nizamiye_counts['classes']; ?></span><span class="sms-stat-label">Derslik</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#22c55e"><span class="dashicons dashicons-yes-alt"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $nizamiye_counts['habits']; ?></span><span class="sms-stat-label">Alışkanlık</span></div>
		</div>
	</div>

	<div class="sms-grid-2">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Alışkanlık Tamamlama</h2><span class="sms-muted">Son 14 gün, genel ortalama</span></div>
			<div class="sms-chart" data-sms-chart="line" data-suffix="%" data-points='<?php echo esc_attr( wp_json_encode( $nizamiye_line_points ) ); ?>'></div>
		</div>
		<div class="sms-card">
			<div class="sms-card-head"><h2>Günlük Devam Oranı</h2><span class="sms-muted">Son 14 gün, yoklamalara göre</span></div>
			<div class="sms-chart" data-sms-chart="bar" data-suffix="%" data-points='<?php echo esc_attr( wp_json_encode( $nizamiye_att_points ) ); ?>'></div>
		</div>
	</div>

	<div class="sms-grid-3">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Yoklama Dağılımı</h2><span class="sms-muted">Dönem geneli</span></div>
			<?php if ( $nizamiye_donut ) : ?>
				<div class="sms-chart sms-chart-donut" data-sms-chart="donut" data-points='<?php echo esc_attr( wp_json_encode( $nizamiye_donut ) ); ?>'></div>
			<?php else : ?>
				<p class="sms-muted sms-pad">Henüz yoklama kaydı yok.</p>
			<?php endif; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2>⭐ Ayın Yıldızları</h2><span class="sms-muted">Bileşik başarı skoru</span></div>
			<?php if ( $nizamiye_top ) : ?>
				<ul class="sms-rank-list">
					<?php foreach ( $nizamiye_top as $nizamiye_i => $nizamiye_row ) : $nizamiye_st = $nizamiye_row['student']; ?>
						<li>
							<span class="sms-rank">#<?php echo (int) $nizamiye_i + 1; ?></span>
							<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_st ) ) ); ?>
							<a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_st->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><?php echo esc_html( nizamiye_student_name( $nizamiye_st ) ); ?></a>
							<span class="sms-score sms-rate-good"><?php echo (int) $nizamiye_row['score']; ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="sms-muted sms-pad">Skor hesaplamak için önce yoklama, not veya alışkanlık verisi girin.</p>
			<?php endif; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2>💪 Destek Bekleyenler</h2><span class="sms-muted">Gelişim fırsatı olan öğrenciler</span></div>
			<?php if ( $nizamiye_bottom ) : ?>
				<ul class="sms-rank-list">
					<?php foreach ( $nizamiye_bottom as $nizamiye_row ) : $nizamiye_st = $nizamiye_row['student']; ?>
						<li>
							<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_st ) ) ); ?>
							<a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_st->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>"><?php echo esc_html( nizamiye_student_name( $nizamiye_st ) ); ?></a>
							<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['score'] ) ); ?>"><?php echo (int) $nizamiye_row['score']; ?></span>
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
				<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&rtype=genel&group=sinif&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Detaylı Analiz →</a>
			</div>
			<?php if ( $nizamiye_grade_sum ) : ?>
				<table class="sms-table">
					<thead><tr><th>Sınıf</th><th>Öğrenci</th><th class="sms-center">Devam</th><th class="sms-center">Alışkanlık</th><th class="sms-center">Not Ort.</th></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_grade_sum as $nizamiye_row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( nizamiye_grade_label( $nizamiye_row['grade'] ) ); ?></strong></td>
							<td class="sms-muted"><?php echo (int) $nizamiye_row['count']; ?></td>
							<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['att'] ) ); ?>"><?php echo null !== $nizamiye_row['att'] ? esc_html( $nizamiye_row['att'] . '%' ) : '—'; ?></span></td>
							<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['habit'] ) ); ?>"><?php echo null !== $nizamiye_row['habit'] ? esc_html( $nizamiye_row['habit'] . '%' ) : '—'; ?></span></td>
							<td class="sms-center"><span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['grade_avg'] ) ); ?>"><?php echo null !== $nizamiye_row['grade_avg'] ? esc_html( $nizamiye_row['grade_avg'] . '%' ) : '—'; ?></span></td>
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
				<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&rtype=yoklama&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Detaylı Analiz →</a>
			</div>
			<?php if ( $nizamiye_cat_sum ) : ?>
				<table class="sms-table">
					<thead><tr><th>Tür</th><th class="sms-center">Katılım</th><th class="sms-center">Geldi</th><th class="sms-center">Gelmedi</th><th class="sms-center">Geç</th><th class="sms-center">İzinli</th></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_cat_sum as $nizamiye_row ) : ?>
						<tr>
							<td><span class="dashicons <?php echo esc_attr( $nizamiye_row['icon'] ?: 'dashicons-clipboard' ); ?>"></span> <strong><?php echo esc_html( $nizamiye_row['name'] ); ?></strong></td>
							<td class="sms-center"><span class="sms-score sms-score-big <?php echo esc_attr( nizamiye_rate_class( $nizamiye_row['rate'] ) ); ?>"><?php echo null !== $nizamiye_row['rate'] ? esc_html( $nizamiye_row['rate'] . '%' ) : '—'; ?></span></td>
							<td class="sms-center sms-rate-good"><?php echo (int) $nizamiye_row['present']; ?></td>
							<td class="sms-center sms-rate-low"><?php echo (int) $nizamiye_row['absent']; ?></td>
							<td class="sms-center sms-rate-mid"><?php echo (int) $nizamiye_row['late']; ?></td>
							<td class="sms-center" style="color:#6366f1"><?php echo (int) $nizamiye_row['excused']; ?></td>
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
