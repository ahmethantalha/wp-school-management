<?php
defined( 'ABSPATH' ) || exit;

$term_id = nizamiye_current_term_id();
$teacher = nizamiye_is_teacher();
$habits  = $term_id ? Nizamiye_Habits::for_term( $term_id, $teacher ? get_current_user_id() : 0 ) : array();
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Alışkanlıklar', 'Kitap okuma, sabah namazına kalkış gibi alışkanlıklar tanımlayın ve günlük takip edin.' ); ?>

	<?php if ( $term_id ) : ?>
		<div class="sms-toolbar">
			<span></span>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-habits&view=edit&nizamiye_term=' . $term_id ) ); ?>" class="sms-btn sms-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> Yeni Alışkanlık</a>
		</div>
	<?php endif; ?>

	<?php if ( $habits ) : ?>
		<div class="sms-class-grid">
			<?php foreach ( $habits as $h ) :
				$rate    = Nizamiye_Habits::completion_rate( (int) $h->id );
				$creator = $h->created_by ? get_userdata( (int) $h->created_by ) : null;
				?>
				<div class="sms-card sms-class-card">
					<div class="sms-class-card-top">
						<span class="sms-class-emblem sms-emblem-green"><span class="dashicons dashicons-yes-alt"></span></span>
						<div>
							<h3><?php echo esc_html( $h->name ); ?></h3>
							<span class="sms-muted">
								<?php echo esc_html( nizamiye_habit_track_type_label( $h ) ); ?>
								<?php echo $creator ? ' • ' . esc_html( $creator->display_name ) : ''; ?>
							</span>
						</div>
					</div>
					<?php if ( $h->description ) : ?><p class="sms-muted sms-clamp"><?php echo esc_html( $h->description ); ?></p><?php endif; ?>
					<div class="sms-progress">
						<div class="sms-progress-bar <?php echo esc_attr( nizamiye_rate_class( null !== $rate ? (int) $rate : null ) ); ?>" style="width:<?php echo null !== $rate ? (int) $rate : 0; ?>%"></div>
					</div>
					<div class="sms-class-card-meta">
						<span><span class="dashicons dashicons-groups"></span> <?php echo (int) $h->student_count; ?> öğrenci</span>
						<span><span class="dashicons dashicons-chart-line"></span> <?php echo null !== $rate ? (int) $rate . '% tamamlama' : 'Henüz kayıt yok'; ?></span>
					</div>
					<div class="sms-class-card-actions">
						<a class="sms-btn sms-btn-primary sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-habits&view=track&habit_id=' . (int) $h->id . '&nizamiye_term=' . $term_id ) ) ); ?>">Takip Doldur</a>
						<a class="sms-btn sms-btn-ghost sms-btn-sm" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-habits&view=edit&habit_id=' . (int) $h->id . '&nizamiye_term=' . $term_id ) ) ); ?>">Düzenle</a>
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
