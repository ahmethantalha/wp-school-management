<?php
defined( 'ABSPATH' ) || exit;

$nizamiye_term_id = nizamiye_current_term_id();
$nizamiye_teacher = nizamiye_is_teacher();
$nizamiye_habits  = $nizamiye_term_id ? Nizamiye_Habits::for_term( $nizamiye_term_id, $nizamiye_teacher ? get_current_user_id() : 0 ) : array();
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Alışkanlıklar', 'Kitap okuma, sabah namazına kalkış gibi alışkanlıklar tanımlayın ve günlük takip edin.' ); ?>

	<?php if ( $nizamiye_term_id ) : ?>
		<div class="sms-toolbar">
			<span></span>
			<a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-habits&view=edit&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>" class="sms-btn sms-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> Yeni Alışkanlık</a>
		</div>
	<?php endif; ?>

	<?php if ( $nizamiye_habits ) : ?>
		<div class="sms-class-grid">
			<?php foreach ( $nizamiye_habits as $nizamiye_h ) :
				$nizamiye_rate    = Nizamiye_Habits::completion_rate( (int) $nizamiye_h->id );
				$nizamiye_creator = $nizamiye_h->created_by ? get_userdata( (int) $nizamiye_h->created_by ) : null;
				?>
				<div class="sms-card sms-class-card">
					<div class="sms-class-card-top">
						<span class="sms-class-emblem sms-emblem-green"><span class="dashicons dashicons-yes-alt"></span></span>
						<div>
							<h3><?php echo esc_html( $nizamiye_h->name ); ?></h3>
							<span class="sms-muted">
								<?php echo esc_html( nizamiye_habit_track_type_label( $nizamiye_h ) ); ?>
								<?php echo $nizamiye_creator ? ' • ' . esc_html( $nizamiye_creator->display_name ) : ''; ?>
							</span>
						</div>
					</div>
					<?php if ( $nizamiye_h->description ) : ?><p class="sms-muted sms-clamp"><?php echo esc_html( $nizamiye_h->description ); ?></p><?php endif; ?>
					<div class="sms-progress">
						<div class="sms-progress-bar <?php echo esc_attr( nizamiye_rate_class( null !== $nizamiye_rate ? (int) $nizamiye_rate : null ) ); ?>" style="width:<?php echo null !== $nizamiye_rate ? (int) $nizamiye_rate : 0; ?>%"></div>
					</div>
					<div class="sms-class-card-meta">
						<span><span class="dashicons dashicons-groups"></span> <?php echo (int) $nizamiye_h->student_count; ?> öğrenci</span>
						<span><span class="dashicons dashicons-chart-line"></span> <?php echo null !== $nizamiye_rate ? (int) $nizamiye_rate . '% tamamlama' : 'Henüz kayıt yok'; ?></span>
					</div>
					<div class="sms-class-card-actions">
						<a class="sms-btn sms-btn-primary sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-habits&view=track&habit_id=' . (int) $nizamiye_h->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Takip Doldur</a>
						<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-habits&view=edit&habit_id=' . (int) $nizamiye_h->id . '&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Düzenle</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="sms-card">
			<div class="sms-empty">
				<span class="dashicons dashicons-yes-alt"></span>
				<h2>Henüz alışkanlık yok</h2>
				<p>"Yeni Alışkanlık" ile ilk alışkanlığı oluşturun; takip tipini (yaptı/yapmadı veya dereceli) oluştururken seçersiniz.</p>
			</div>
		</div>
	<?php endif; ?>
</div>
