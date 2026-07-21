<?php
defined( 'ABSPATH' ) || exit;

$student_id = nizamiye_verify_view_nonce() && isset( $_GET['student'] ) ? (int) $_GET['student'] : 0;

if ( ! $student_id || ! nizamiye_can_access_student( $student_id ) ) {
	wp_die( 'Bu öğrencinin karnesine erişim yetkiniz yok.' );
}

$term_id = nizamiye_current_term_id();
$report  = Nizamiye_Reports::student_report( $student_id, $term_id );
$student = $report['student'];

if ( ! $student ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>Öğrenci bulunamadı</h2></div></div>';
	return;
}

$att        = $report['att_all'];
$statuses   = nizamiye_attendance_statuses();
$habit_avg  = null;
if ( $report['habits'] ) {
	$vals = array_filter( array_map( function ( $h ) {
		return $h->log_count > 0 ? (int) $h->rate : null;
	}, $report['habits'] ), function ( $v ) { return null !== $v; } );
	if ( $vals ) {
		$habit_avg = (int) round( array_sum( $vals ) / count( $vals ) );
	}
}
$grade_avg = null;
if ( $report['grade_avgs'] ) {
	$vals = array_map( function ( $g ) { return (int) $g->avg_rate; }, $report['grade_avgs'] );
	$grade_avg = (int) round( array_sum( $vals ) / count( $vals ) );
}
$parent = $student->parent_user_id ? get_userdata( (int) $student->parent_user_id ) : null;

$print_url = wp_nonce_url( add_query_arg( array(
	'action'   => 'nizamiye_print_report',
	'student'  => $student_id,
	'nizamiye_term' => $term_id,
), admin_url( 'admin-post.php' ) ), 'nizamiye_print_report_' . $student_id );
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Öğrenci Karnesi', '' ); ?>

	<div class="sms-toolbar">
		<?php if ( current_user_can( 'nizamiye_teach' ) ) : ?>
			<a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-cards&nizamiye_term=' . $term_id ) ); ?>">← Karnelere dön</a>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
		<a class="sms-btn sms-btn-primary sms-btn-sm" href="<?php echo esc_url( $print_url ); ?>" target="_blank" rel="noopener">
			<span class="dashicons dashicons-pdf"></span> PDF Karne İndir
		</a>
	</div>

	<div class="sms-card sms-profile-card">
		<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $student ), 'sms-avatar-lg' ) ); ?>
		<div class="sms-profile-info">
			<h2><?php echo esc_html( nizamiye_student_name( $student ) ); ?></h2>
			<div class="sms-profile-meta">
				<?php if ( $report['enrollment'] ) : ?><span class="sms-badge sms-badge-indigo"><?php echo esc_html( nizamiye_grade_label( $report['enrollment']->grade_level ) ); ?></span><?php endif; ?>
				<span class="sms-badge <?php echo 'active' === $student->status ? 'sms-badge-green' : 'sms-badge-amber'; ?>"><?php echo esc_html( nizamiye_student_status_label( $student->status ) ); ?></span>
				<?php if ( $student->school ) : ?><span class="sms-muted"><span class="dashicons dashicons-building"></span> <?php echo esc_html( $student->school ); ?></span><?php endif; ?>
				<?php if ( $student->birth_date ) : ?><span class="sms-muted"><span class="dashicons dashicons-cake"></span> <?php echo esc_html( nizamiye_format_date( $student->birth_date ) ); ?></span><?php endif; ?>
				<?php if ( $parent ) : ?><span class="sms-muted"><span class="dashicons dashicons-admin-users"></span> Veli: <?php echo esc_html( $parent->display_name ); ?></span><?php endif; ?>
			</div>
			<?php if ( $report['classes'] ) : ?>
				<div class="sms-profile-classes">
					<?php foreach ( $report['classes'] as $c ) : ?><span class="sms-badge"><?php echo esc_html( $c->name ); ?></span><?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="sms-stat-grid sms-mt">
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#22c55e"><span class="dashicons dashicons-clipboard"></span></span>
			<div><span class="sms-stat-value <?php echo esc_attr( nizamiye_rate_class( $att['rate'] ) ); ?>"><?php echo null !== $att['rate'] ? (int) $att['rate'] . '%' : '—'; ?></span><span class="sms-stat-label">Devam Oranı</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#6366f1"><span class="dashicons dashicons-yes-alt"></span></span>
			<div><span class="sms-stat-value <?php echo esc_attr( nizamiye_rate_class( $habit_avg ) ); ?>"><?php echo null !== $habit_avg ? esc_html( $habit_avg . '%' ) : '—'; ?></span><span class="sms-stat-label">Alışkanlık Tamamlama</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#f59e0b"><span class="dashicons dashicons-welcome-write-blog"></span></span>
			<div><span class="sms-stat-value <?php echo esc_attr( nizamiye_rate_class( $grade_avg ) ); ?>"><?php echo null !== $grade_avg ? esc_html( $grade_avg . '%' ) : '—'; ?></span><span class="sms-stat-label">Not Ortalaması</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#0ea5e9"><span class="dashicons dashicons-calendar-alt"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $att['total']; ?></span><span class="sms-stat-label">Toplam Yoklama</span></div>
		</div>
	</div>

	<div class="sms-grid-2 sms-mt">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Alışkanlıklar</h2><span class="sms-muted">Dönem geneli tamamlama</span></div>
			<?php if ( $report['habits'] ) : ?>
				<table class="sms-table">
					<thead><tr><th>Alışkanlık</th><th>Takip</th><th>Kayıt</th><th>Tamamlama</th></tr></thead>
					<tbody>
					<?php foreach ( $report['habits'] as $h ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $h->name ); ?></strong></td>
							<td class="sms-muted"><?php echo esc_html( nizamiye_habit_track_type_label( $h ) ); ?></td>
							<td><?php echo (int) $h->log_count; ?></td>
							<td>
								<?php if ( $h->log_count > 0 ) : ?>
									<div class="sms-progress sms-progress-inline"><div class="sms-progress-bar <?php echo esc_attr( nizamiye_rate_class( (int) $h->rate ) ); ?>" style="width:<?php echo (int) $h->rate; ?>%"></div></div>
									<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( (int) $h->rate ) ); ?>"><?php echo (int) $h->rate; ?>%</span>
								<?php else : ?>
									<span class="sms-muted">Kayıt yok</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="sms-muted sms-pad">Bu dönemde atanmış alışkanlık yok.</p>
			<?php endif; ?>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2>Yoklama Özeti</h2><span class="sms-muted">Yoklama türüne göre katılım oranı</span></div>
			<div class="sms-pad">
				<?php if ( $report['att_cats'] ) : ?>
					<div class="sms-cat-summary-list">
						<?php foreach ( $report['att_cats'] as $cat ) : ?>
							<div class="sms-cat-summary-row">
								<div class="sms-cat-summary-head">
									<span class="dashicons <?php echo esc_attr( $cat['icon'] ?: 'dashicons-clipboard' ); ?>"></span>
									<strong><?php echo esc_html( $cat['category'] ); ?></strong>
									<span class="sms-muted sms-cat-summary-count"><?php echo (int) $cat['overall_total']; ?> kayıt</span>
									<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $cat['overall_rate'] ) ); ?>"><?php echo null !== $cat['overall_rate'] ? (int) $cat['overall_rate'] . '%' : '—'; ?></span>
								</div>
								<?php if ( $cat['multi_session'] ) : ?>
									<div class="sms-session-stats">
										<?php foreach ( $cat['sessions'] as $s ) : ?>
											<div class="sms-session-stat">
												<span class="sms-session-stat-name"><?php echo esc_html( $s['name'] ); ?></span>
												<span class="sms-session-stat-rate <?php echo esc_attr( nizamiye_rate_class( $s['rate'] ) ); ?>"><?php echo null !== $s['rate'] ? (int) $s['rate'] . '%' : '—'; ?></span>
												<span class="sms-muted"><?php echo (int) $s['present']; ?>/<?php echo (int) $s['total']; ?></span>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="sms-muted">Bu dönemde yoklama kaydı yok.</p>
				<?php endif; ?>
				<?php if ( $report['recent_att'] ) : ?>
					<h4 class="sms-mt">Son 3 Yoklama</h4>
					<ul class="sms-mini-list">
						<?php foreach ( $report['recent_att'] as $a ) : ?>
							<li>
								<span class="sms-dot sms-att-<?php echo esc_attr( $a->status ); ?>"></span>
								<?php
									$ctx = $a->class_name ? $a->class_name : trim( $a->category_name . ( $a->session_name && $a->session_name !== $a->category_name ? ' · ' . $a->session_name : '' ) );
									echo esc_html( nizamiye_format_date( $a->att_date ) . ' — ' . $ctx . ': ' . ( $statuses[ $a->status ] ?? $a->status ) );
								?>
								<?php echo $a->note ? '<span class="sms-muted">(' . esc_html( $a->note ) . ')</span>' : ''; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $report['reading'] ) ) : ?>
		<div class="sms-card sms-mt">
			<div class="sms-card-head"><h2>📖 Kitap Okuma</h2><span class="sms-muted">Dönem geneli okunan kitaplar ve toplam sayfa</span></div>
			<div class="sms-pad sms-cat-report">
				<?php foreach ( $report['reading'] as $rh ) : ?>
					<div class="sms-cat-report-block">
						<h4><span class="dashicons dashicons-book"></span> <?php echo esc_html( $rh['habit_name'] ); ?>
							<span class="sms-badge sms-badge-indigo"><?php echo (int) $rh['total_pages']; ?> sayfa • <?php echo count( $rh['books'] ); ?> kitap</span>
						</h4>
						<ul class="sms-mini-list">
							<?php foreach ( $rh['books'] as $book ) : ?>
								<li>
									<strong><?php echo esc_html( $book['title'] ); ?></strong>
									<span class="sms-muted"> — <?php echo (int) $book['pages']; ?> sayfa (<?php echo (int) $book['days']; ?> gün) • son: <?php echo esc_html( nizamiye_format_date( $book['last_date'] ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="sms-grid-2 sms-mt">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Notlar</h2></div>
			<?php if ( $report['grades'] ) : ?>
				<table class="sms-table">
					<thead><tr><th>Sınav</th><th>Ders</th><th>Tarih</th><th>Puan</th></tr></thead>
					<tbody>
					<?php foreach ( $report['grades'] as $g ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $g->title ); ?></strong> <span class="sms-muted"><?php echo esc_html( $g->exam_type ); ?></span></td>
							<td class="sms-muted"><?php echo esc_html( $g->class_name ); ?></td>
							<td class="sms-muted"><?php echo esc_html( nizamiye_format_date( $g->exam_date ) ); ?></td>
							<td><strong><?php echo esc_html( rtrim( rtrim( (string) $g->score, '0' ), '.' ) ); ?></strong> <span class="sms-muted">/ <?php echo esc_html( rtrim( rtrim( (string) $g->max_score, '0' ), '.' ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="sms-muted sms-pad">Bu dönemde girilmiş not yok.</p>
			<?php endif; ?>
		</div>

		<div>
			<div class="sms-card">
				<div class="sms-card-head"><h2>Ders Ortalamaları</h2></div>
				<?php if ( $report['grade_avgs'] ) : ?>
					<div class="sms-pad">
						<?php foreach ( $report['grade_avgs'] as $g ) : ?>
							<div class="sms-avg-row">
								<span><?php echo esc_html( $g->class_name ); ?> <span class="sms-muted">(<?php echo (int) $g->exam_count; ?> sınav)</span></span>
								<div class="sms-progress sms-progress-inline"><div class="sms-progress-bar <?php echo esc_attr( nizamiye_rate_class( (int) $g->avg_rate ) ); ?>" style="width:<?php echo (int) $g->avg_rate; ?>%"></div></div>
								<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( (int) $g->avg_rate ) ); ?>"><?php echo (int) $g->avg_rate; ?>%</span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="sms-muted sms-pad">Ortalama hesaplanacak not yok.</p>
				<?php endif; ?>
			</div>

			<div class="sms-card sms-mt">
				<div class="sms-card-head"><h2>Dönem Geçmişi</h2></div>
				<div class="sms-pad">
					<ul class="sms-mini-list">
						<?php foreach ( $report['history'] as $h ) : ?>
							<li><?php echo esc_html( $h->term_name . ' — ' . nizamiye_grade_label( $h->grade_level ) ); ?> <?php echo 'graduated' === $h->status ? '🎓 Mezun' : ( $h->is_active ? '<span class="sms-badge sms-badge-green">Aktif</span>' : '' ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
