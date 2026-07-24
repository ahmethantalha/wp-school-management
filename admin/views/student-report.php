<?php
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_student_id = ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) ) && isset( $_GET['student'] ) ? (int) $_GET['student'] : 0;

if ( ! $nizamiye_student_id || ! nizamiye_can_access_student( $nizamiye_student_id ) ) {
	wp_die( 'Bu öğrencinin karnesine erişim yetkiniz yok.' );
}

$nizamiye_term_id = nizamiye_current_term_id();
$nizamiye_report  = Nizamiye_Reports::student_report( $nizamiye_student_id, $nizamiye_term_id );
$nizamiye_student = $nizamiye_report['student'];

if ( ! $nizamiye_student ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>Öğrenci bulunamadı</h2></div></div>';
	return;
}

$nizamiye_att        = $nizamiye_report['att_all'];
$nizamiye_statuses   = nizamiye_attendance_statuses();
$nizamiye_habit_avg  = null;
if ( $nizamiye_report['habits'] ) {
	$nizamiye_vals = array_filter( array_map( function ( $nizamiye_h ) {
		return $nizamiye_h->log_count > 0 ? (int) $nizamiye_h->rate : null;
	}, $nizamiye_report['habits'] ), function ( $nizamiye_v ) { return null !== $nizamiye_v; } );
	if ( $nizamiye_vals ) {
		$nizamiye_habit_avg = (int) round( array_sum( $nizamiye_vals ) / count( $nizamiye_vals ) );
	}
}
$nizamiye_grade_avg = null;
if ( $nizamiye_report['grade_avgs'] ) {
	$nizamiye_vals = array_map( function ( $nizamiye_g ) { return (int) $nizamiye_g->avg_rate; }, $nizamiye_report['grade_avgs'] );
	$nizamiye_grade_avg = (int) round( array_sum( $nizamiye_vals ) / count( $nizamiye_vals ) );
}
$nizamiye_parent = $nizamiye_student->parent_user_id ? get_userdata( (int) $nizamiye_student->parent_user_id ) : null;

$nizamiye_print_url = wp_nonce_url( add_query_arg( array(
	'action'   => 'nizamiye_print_report',
	'student'  => $nizamiye_student_id,
	'nizamiye_term' => $nizamiye_term_id,
), admin_url( 'admin-post.php' ) ), 'nizamiye_print_report_' . $nizamiye_student_id );
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Öğrenci Karnesi', '' ); ?>

	<div class="sms-toolbar">
		<?php if ( current_user_can( 'nizamiye_teach' ) ) : ?>
			<a class="sms-back-link" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-cards&nizamiye_term=' . $nizamiye_term_id ) ); ?>">← Karnelere dön</a>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
		<a class="sms-btn sms-btn-primary sms-btn-sm" href="<?php echo esc_url( $nizamiye_print_url ); ?>" target="_blank" rel="noopener">
			<span class="dashicons dashicons-pdf"></span> PDF Karne İndir
		</a>
	</div>

	<div class="sms-card sms-profile-card">
		<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_student ), 'sms-avatar-lg' ) ); ?>
		<div class="sms-profile-info">
			<h2><?php echo esc_html( nizamiye_student_name( $nizamiye_student ) ); ?></h2>
			<div class="sms-profile-meta">
				<?php if ( $nizamiye_report['enrollment'] ) : ?><span class="sms-badge sms-badge-indigo"><?php echo esc_html( nizamiye_grade_label( $nizamiye_report['enrollment']->grade_level ) ); ?></span><?php endif; ?>
				<span class="sms-badge <?php echo 'active' === $nizamiye_student->status ? 'sms-badge-green' : 'sms-badge-amber'; ?>"><?php echo esc_html( nizamiye_student_status_label( $nizamiye_student->status ) ); ?></span>
				<?php if ( $nizamiye_student->school ) : ?><span class="sms-muted"><span class="dashicons dashicons-building"></span> <?php echo esc_html( $nizamiye_student->school ); ?></span><?php endif; ?>
				<?php if ( $nizamiye_student->birth_date ) : ?><span class="sms-muted"><span class="dashicons dashicons-cake"></span> <?php echo esc_html( nizamiye_format_date( $nizamiye_student->birth_date ) ); ?></span><?php endif; ?>
				<?php if ( $nizamiye_parent ) : ?><span class="sms-muted"><span class="dashicons dashicons-admin-users"></span> Veli: <?php echo esc_html( $nizamiye_parent->display_name ); ?></span><?php endif; ?>
			</div>
			<?php if ( $nizamiye_report['classes'] ) : ?>
				<div class="sms-profile-classes">
					<?php foreach ( $nizamiye_report['classes'] as $nizamiye_c ) : ?><span class="sms-badge"><?php echo esc_html( $nizamiye_c->name ); ?></span><?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="sms-stat-grid sms-mt">
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#22c55e"><span class="dashicons dashicons-clipboard"></span></span>
			<div><span class="sms-stat-value <?php echo esc_attr( nizamiye_rate_class( $nizamiye_att['rate'] ) ); ?>"><?php echo null !== $nizamiye_att['rate'] ? (int) $nizamiye_att['rate'] . '%' : '—'; ?></span><span class="sms-stat-label">Devam Oranı</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#6366f1"><span class="dashicons dashicons-yes-alt"></span></span>
			<div><span class="sms-stat-value <?php echo esc_attr( nizamiye_rate_class( $nizamiye_habit_avg ) ); ?>"><?php echo null !== $nizamiye_habit_avg ? esc_html( $nizamiye_habit_avg . '%' ) : '—'; ?></span><span class="sms-stat-label">Alışkanlık Tamamlama</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#f59e0b"><span class="dashicons dashicons-welcome-write-blog"></span></span>
			<div><span class="sms-stat-value <?php echo esc_attr( nizamiye_rate_class( $nizamiye_grade_avg ) ); ?>"><?php echo null !== $nizamiye_grade_avg ? esc_html( $nizamiye_grade_avg . '%' ) : '—'; ?></span><span class="sms-stat-label">Not Ortalaması</span></div>
		</div>
		<div class="sms-stat-card">
			<span class="sms-stat-icon" style="--c:#0ea5e9"><span class="dashicons dashicons-calendar-alt"></span></span>
			<div><span class="sms-stat-value"><?php echo (int) $nizamiye_att['total']; ?></span><span class="sms-stat-label">Toplam Yoklama</span></div>
		</div>
	</div>

	<div class="sms-grid-2 sms-mt">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Alışkanlıklar</h2><span class="sms-muted">Dönem geneli tamamlama</span></div>
			<?php if ( $nizamiye_report['habits'] ) : ?>
				<table class="sms-table">
					<thead><tr><th>Alışkanlık</th><th>Takip</th><th>Kayıt</th><th>Tamamlama</th></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_report['habits'] as $nizamiye_h ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $nizamiye_h->name ); ?></strong></td>
							<td class="sms-muted"><?php echo esc_html( nizamiye_habit_track_type_label( $nizamiye_h ) ); ?></td>
							<td><?php echo (int) $nizamiye_h->log_count; ?></td>
							<td>
								<?php if ( $nizamiye_h->log_count > 0 ) : ?>
									<div class="sms-progress sms-progress-inline"><div class="sms-progress-bar <?php echo esc_attr( nizamiye_rate_class( (int) $nizamiye_h->rate ) ); ?>" style="width:<?php echo (int) $nizamiye_h->rate; ?>%"></div></div>
									<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( (int) $nizamiye_h->rate ) ); ?>"><?php echo (int) $nizamiye_h->rate; ?>%</span>
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
				<?php if ( $nizamiye_report['att_cats'] ) : ?>
					<div class="sms-cat-summary-list">
						<?php foreach ( $nizamiye_report['att_cats'] as $nizamiye_cat ) : ?>
							<div class="sms-cat-summary-row">
								<div class="sms-cat-summary-head">
									<span class="dashicons <?php echo esc_attr( $nizamiye_cat['icon'] ?: 'dashicons-clipboard' ); ?>"></span>
									<strong><?php echo esc_html( $nizamiye_cat['category'] ); ?></strong>
									<span class="sms-muted sms-cat-summary-count"><?php echo (int) $nizamiye_cat['overall_total']; ?> kayıt</span>
									<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( $nizamiye_cat['overall_rate'] ) ); ?>"><?php echo null !== $nizamiye_cat['overall_rate'] ? (int) $nizamiye_cat['overall_rate'] . '%' : '—'; ?></span>
								</div>
								<?php if ( $nizamiye_cat['multi_session'] ) : ?>
									<div class="sms-session-stats">
										<?php foreach ( $nizamiye_cat['sessions'] as $nizamiye_s ) : ?>
											<div class="sms-session-stat">
												<span class="sms-session-stat-name"><?php echo esc_html( $nizamiye_s['name'] ); ?></span>
												<span class="sms-session-stat-rate <?php echo esc_attr( nizamiye_rate_class( $nizamiye_s['rate'] ) ); ?>"><?php echo null !== $nizamiye_s['rate'] ? (int) $nizamiye_s['rate'] . '%' : '—'; ?></span>
												<span class="sms-muted"><?php echo (int) $nizamiye_s['present']; ?>/<?php echo (int) $nizamiye_s['total']; ?></span>
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
				<?php if ( $nizamiye_report['recent_att'] ) : ?>
					<h4 class="sms-mt">Son 3 Yoklama</h4>
					<ul class="sms-mini-list">
						<?php foreach ( $nizamiye_report['recent_att'] as $nizamiye_a ) : ?>
							<li>
								<span class="sms-dot sms-att-<?php echo esc_attr( $nizamiye_a->status ); ?>"></span>
								<?php
									$nizamiye_ctx = $nizamiye_a->class_name ? $nizamiye_a->class_name : trim( $nizamiye_a->category_name . ( $nizamiye_a->session_name && $nizamiye_a->session_name !== $nizamiye_a->category_name ? ' · ' . $nizamiye_a->session_name : '' ) );
									echo esc_html( nizamiye_format_date( $nizamiye_a->att_date ) . ' — ' . $nizamiye_ctx . ': ' . ( $nizamiye_statuses[ $nizamiye_a->status ] ?? $nizamiye_a->status ) );
								?>
								<?php echo $nizamiye_a->note ? '<span class="sms-muted">(' . esc_html( $nizamiye_a->note ) . ')</span>' : ''; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $nizamiye_report['reading'] ) ) : ?>
		<div class="sms-card sms-mt">
			<div class="sms-card-head"><h2>📖 Kitap Okuma</h2><span class="sms-muted">Dönem geneli okunan kitaplar ve toplam sayfa</span></div>
			<div class="sms-pad sms-cat-report">
				<?php foreach ( $nizamiye_report['reading'] as $nizamiye_rh ) : ?>
					<div class="sms-cat-report-block">
						<h4><span class="dashicons dashicons-book"></span> <?php echo esc_html( $nizamiye_rh['habit_name'] ); ?>
							<span class="sms-badge sms-badge-indigo"><?php echo (int) $nizamiye_rh['total_pages']; ?> sayfa • <?php echo count( $nizamiye_rh['books'] ); ?> kitap</span>
						</h4>
						<ul class="sms-mini-list">
							<?php foreach ( $nizamiye_rh['books'] as $nizamiye_book ) : ?>
								<li>
									<strong><?php echo esc_html( $nizamiye_book['title'] ); ?></strong>
									<span class="sms-muted"> — <?php echo (int) $nizamiye_book['pages']; ?> sayfa (<?php echo (int) $nizamiye_book['days']; ?> gün) • son: <?php echo esc_html( nizamiye_format_date( $nizamiye_book['last_date'] ) ); ?></span>
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
			<?php if ( $nizamiye_report['grades'] ) : ?>
				<table class="sms-table">
					<thead><tr><th>Sınav</th><th>Ders</th><th>Tarih</th><th>Puan</th></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_report['grades'] as $nizamiye_g ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $nizamiye_g->title ); ?></strong> <span class="sms-muted"><?php echo esc_html( $nizamiye_g->exam_type ); ?></span></td>
							<td class="sms-muted"><?php echo esc_html( $nizamiye_g->class_name ); ?></td>
							<td class="sms-muted"><?php echo esc_html( nizamiye_format_date( $nizamiye_g->exam_date ) ); ?></td>
							<td><strong><?php echo esc_html( rtrim( rtrim( (string) $nizamiye_g->score, '0' ), '.' ) ); ?></strong> <span class="sms-muted">/ <?php echo esc_html( rtrim( rtrim( (string) $nizamiye_g->max_score, '0' ), '.' ) ); ?></span></td>
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
				<?php if ( $nizamiye_report['grade_avgs'] ) : ?>
					<div class="sms-pad">
						<?php foreach ( $nizamiye_report['grade_avgs'] as $nizamiye_g ) : ?>
							<div class="sms-avg-row">
								<span><?php echo esc_html( $nizamiye_g->class_name ); ?> <span class="sms-muted">(<?php echo (int) $nizamiye_g->exam_count; ?> sınav)</span></span>
								<div class="sms-progress sms-progress-inline"><div class="sms-progress-bar <?php echo esc_attr( nizamiye_rate_class( (int) $nizamiye_g->avg_rate ) ); ?>" style="width:<?php echo (int) $nizamiye_g->avg_rate; ?>%"></div></div>
								<span class="sms-score <?php echo esc_attr( nizamiye_rate_class( (int) $nizamiye_g->avg_rate ) ); ?>"><?php echo (int) $nizamiye_g->avg_rate; ?>%</span>
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
						<?php foreach ( $nizamiye_report['history'] as $nizamiye_h ) : ?>
							<li><?php echo esc_html( $nizamiye_h->term_name . ' — ' . nizamiye_grade_label( $nizamiye_h->grade_level ) ); ?> <?php echo 'graduated' === $nizamiye_h->status ? '🎓 Mezun' : ( $nizamiye_h->is_active ? '<span class="sms-badge sms-badge-green">Aktif</span>' : '' ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
