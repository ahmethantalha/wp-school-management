<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_children = Nizamiye_Students::children_of( get_current_user_id() );
$nizamiye_term_id  = nizamiye_current_term_id();
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Öğrencilerim', 'Çocuklarınızın gelişimini, notlarını, yoklama ve alışkanlık takiplerini buradan izleyebilirsiniz.' ); ?>

	<?php if ( $nizamiye_children ) : ?>
		<div class="sms-class-grid">
			<?php foreach ( $nizamiye_children as $nizamiye_c ) :
				$nizamiye_enrollment = $nizamiye_term_id ? Nizamiye_Students::enrollment( (int) $nizamiye_c->id, $nizamiye_term_id ) : null;
				$nizamiye_att        = $nizamiye_term_id ? Nizamiye_Attendance::student_summary( (int) $nizamiye_c->id, $nizamiye_term_id ) : array( 'rate' => null, 'total' => 0 );
				$nizamiye_habits     = $nizamiye_term_id ? Nizamiye_Habits::student_habit_summary( (int) $nizamiye_c->id, $nizamiye_term_id ) : array();
				$nizamiye_habit_avg  = null;
				$nizamiye_vals       = array();
				foreach ( $nizamiye_habits as $nizamiye_h ) {
					if ( $nizamiye_h->log_count > 0 ) {
						$nizamiye_vals[] = (int) $nizamiye_h->rate;
					}
				}
				if ( $nizamiye_vals ) {
					$nizamiye_habit_avg = (int) round( array_sum( $nizamiye_vals ) / count( $nizamiye_vals ) );
				}
				?>
				<div class="sms-card sms-class-card">
					<div class="sms-class-card-top">
						<?php echo wp_kses_post( nizamiye_avatar( nizamiye_student_name( $nizamiye_c ), 'sms-avatar-lg' ) ); ?>
						<div>
							<h3><?php echo esc_html( nizamiye_student_name( $nizamiye_c ) ); ?></h3>
							<span class="sms-muted">
								<?php echo $nizamiye_enrollment ? esc_html( nizamiye_grade_label( $nizamiye_enrollment->grade_level ) ) : esc_html( nizamiye_student_status_label( $nizamiye_c->status ) ); ?>
								<?php echo $nizamiye_c->school ? ' • ' . esc_html( $nizamiye_c->school ) : ''; ?>
							</span>
						</div>
					</div>
					<div class="sms-class-card-meta">
						<span><span class="dashicons dashicons-clipboard"></span> Devam: <strong class="<?php echo esc_attr( nizamiye_rate_class( $nizamiye_att['rate'] ) ); ?>"><?php echo null !== $nizamiye_att['rate'] ? (int) $nizamiye_att['rate'] . '%' : '—'; ?></strong></span>
						<span><span class="dashicons dashicons-yes-alt"></span> Alışkanlık: <strong class="<?php echo esc_attr( nizamiye_rate_class( $nizamiye_habit_avg ) ); ?>"><?php echo null !== $nizamiye_habit_avg ? esc_html( $nizamiye_habit_avg . '%' ) : '—'; ?></strong></span>
					</div>
					<div class="sms-class-card-actions">
						<a class="sms-btn sms-btn-primary sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-reports&student=' . (int) $nizamiye_c->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Detaylı Karne</a>
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
