<?php
defined( 'ABSPATH' ) || exit;

$children = Nizamiye_Students::children_of( get_current_user_id() );
$term_id  = nizamiye_current_term_id();
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Öğrencilerim', 'Çocuklarınızın gelişimini, notlarını, yoklama ve alışkanlık takiplerini buradan izleyebilirsiniz.' ); ?>

	<?php if ( $children ) : ?>
		<div class="sms-class-grid">
			<?php foreach ( $children as $c ) :
				$enrollment = $term_id ? Nizamiye_Students::enrollment( (int) $c->id, $term_id ) : null;
				$att        = $term_id ? Nizamiye_Attendance::student_summary( (int) $c->id, $term_id ) : array( 'rate' => null, 'total' => 0 );
				$habits     = $term_id ? Nizamiye_Habits::student_habit_summary( (int) $c->id, $term_id ) : array();
				$habit_avg  = null;
				$vals       = array();
				foreach ( $habits as $h ) {
					if ( $h->log_count > 0 ) {
						$vals[] = (int) $h->rate;
					}
				}
				if ( $vals ) {
					$habit_avg = (int) round( array_sum( $vals ) / count( $vals ) );
				}
				?>
				<div class="sms-card sms-class-card">
					<div class="sms-class-card-top">
						<?php echo nizamiye_avatar( nizamiye_student_name( $c ), 'sms-avatar-lg' ); // phpcs:ignore ?>
						<div>
							<h3><?php echo esc_html( nizamiye_student_name( $c ) ); ?></h3>
							<span class="sms-muted">
								<?php echo $enrollment ? esc_html( nizamiye_grade_label( $enrollment->grade_level ) ) : esc_html( nizamiye_student_status_label( $c->status ) ); ?>
								<?php echo $c->school ? ' • ' . esc_html( $c->school ) : ''; ?>
							</span>
						</div>
					</div>
					<div class="sms-class-card-meta">
						<span><span class="dashicons dashicons-clipboard"></span> Devam: <strong class="<?php echo esc_attr( nizamiye_rate_class( $att['rate'] ) ); ?>"><?php echo null !== $att['rate'] ? (int) $att['rate'] . '%' : '—'; ?></strong></span>
						<span><span class="dashicons dashicons-yes-alt"></span> Alışkanlık: <strong class="<?php echo esc_attr( nizamiye_rate_class( $habit_avg ) ); ?>"><?php echo null !== $habit_avg ? esc_html( $habit_avg . '%' ) : '—'; ?></strong></span>
					</div>
					<div class="sms-class-card-actions">
						<a class="sms-btn sms-btn-primary sms-btn-sm" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-reports&student=' . (int) $c->id . '&nizamiye_term=' . $term_id ) ); ?>">Detaylı Karne</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="sms-card">
			<div class="sms-empty">
				<span class="dashicons dashicons-admin-users"></span>
				<h2>Hesabınıza bağlı öğrenci yok</h2>
				<p>Lütfen kurum yöneticinizle iletişime geçin.</p>
			</div>
		</div>
	<?php endif; ?>
</div>
