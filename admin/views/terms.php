<?php
defined( 'ABSPATH' ) || exit;

$terms    = Nizamiye_Terms::all();
$active   = Nizamiye_Terms::active();
$settings = nizamiye_get_settings();
$preview  = $active ? Nizamiye_Terms::rollover_preview( (int) $active->id ) : null;
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Dönemler', 'Eğitim dönemlerini yönetin; yeni dönem açıldığında öğrenciler otomatik olarak bir üst sınıfa aktarılır.', false ); ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Dönem Listesi</h2></div>
			<?php if ( $terms ) : ?>
				<table class="sms-table">
					<thead><tr><th>Dönem</th><th>Tarih Aralığı</th><th>Öğrenci</th><th>Durum</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $terms as $t ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $t->name ); ?></strong></td>
							<td class="sms-muted"><?php echo esc_html( nizamiye_format_date( $t->start_date ) . ' – ' . nizamiye_format_date( $t->end_date ) ); ?></td>
							<td><?php echo (int) Nizamiye_Students::count_for_term( (int) $t->id ); ?></td>
							<td><?php echo $t->is_active ? '<span class="sms-badge sms-badge-green">Aktif</span>' : '<span class="sms-badge">Arşiv</span>'; ?></td>
							<td class="sms-actions-cell">
								<?php if ( ! $t->is_active ) : ?>
									<?php nizamiye_form_open( 'nizamiye_activate_term', 'sms-inline' ); nizamiye_back_url_field(); ?>
										<input type="hidden" name="term_id" value="<?php echo (int) $t->id; ?>">
										<button type="submit" class="sms-btn sms-btn-ghost sms-btn-sm">Aktif Yap</button>
									</form>
									<?php nizamiye_form_open( 'nizamiye_delete_term', 'sms-inline sms-confirm' ); nizamiye_back_url_field(); ?>
										<input type="hidden" name="term_id" value="<?php echo (int) $t->id; ?>">
										<button type="submit" class="sms-btn sms-btn-danger-ghost sms-btn-sm" data-confirm="Bu dönemi silmek istediğinize emin misiniz?">Sil</button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="sms-muted sms-pad">Henüz dönem yok. Sağdaki formdan ilk dönemi oluşturun.</p>
			<?php endif; ?>
		</div>

		<div>
			<div class="sms-card">
				<div class="sms-card-head"><h2><?php echo $active ? 'Yeni Dönem Aç' : 'İlk Dönemi Oluştur'; ?></h2></div>
				<div class="sms-pad">
					<?php if ( $active && $preview ) : ?>
						<div class="sms-notice sms-notice-info sms-mb">
							<span class="dashicons dashicons-info"></span>
							<div>
								<strong>Otomatik aktarım önizlemesi</strong> (aktif dönem: <?php echo esc_html( $active->name ); ?>)<br>
								<?php echo (int) $preview['promote']; ?> öğrenci bir üst sınıfa geçecek,
								<?php echo (int) $preview['graduate']; ?> öğrenci (<?php echo (int) $preview['final_grade']; ?>. sınıf) mezun olup arşivlenecek.
								<?php if ( $preview['breakdown'] ) : ?>
									<ul class="sms-mini-list">
										<?php foreach ( $preview['breakdown'] as $b ) : ?>
											<li><?php echo (int) $b['count']; ?> öğrenci: <?php echo (int) $b['from']; ?>. sınıf → <?php echo null === $b['to'] ? '🎓 Mezun' : (int) $b['to'] . '. sınıf'; ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<?php nizamiye_form_open( $active ? 'nizamiye_open_term' : 'nizamiye_save_term' ); nizamiye_back_url_field(); ?>
						<?php if ( ! $active ) : ?><input type="hidden" name="activate" value="1"><?php endif; ?>
						<div class="sms-field">
							<label>Dönem Adı *</label>
							<input type="text" name="name" placeholder="Örn. 2025-2026" required>
						</div>
						<div class="sms-field-row">
							<div class="sms-field"><label>Başlangıç</label><input type="date" name="start_date"></div>
							<div class="sms-field"><label>Bitiş</label><input type="date" name="end_date"></div>
						</div>
						<?php if ( $active ) : ?>
							<label class="sms-check">
								<input type="checkbox" name="auto_promote" value="1" checked>
								Öğrencileri otomatik aktar (sınıf atlat, son sınıfları mezun et)
							</label>
						<?php endif; ?>
						<button type="submit" class="sms-btn sms-btn-primary sms-btn-block"><?php echo $active ? 'Dönemi Aç ve Aktar' : 'Dönemi Oluştur'; ?></button>
					</form>
				</div>
			</div>

			<div class="sms-card sms-mt">
				<div class="sms-pad sms-muted">
					<span class="dashicons dashicons-lightbulb"></span>
					Kurumunuzun son sınıfı şu an <strong><?php echo (int) $settings['final_grade']; ?>. sınıf</strong> olarak tanımlı.
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-settings' ) ); ?>">Ayarlar</a> sayfasından değiştirebilirsiniz.
				</div>
			</div>
		</div>
	</div>
</div>
