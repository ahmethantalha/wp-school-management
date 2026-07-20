<?php
defined( 'ABSPATH' ) || exit;

$settings = nizamiye_get_settings();
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Ayarlar', 'Kurum bilgileri ve sınıf seviyesi yapılandırması', false ); ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div class="sms-card">
			<div class="sms-card-head"><h2>Genel Ayarlar</h2></div>
			<div class="sms-pad">
				<?php nizamiye_form_open( 'nizamiye_save_settings' ); nizamiye_back_url_field(); ?>
					<div class="sms-field">
						<label>Kurum Adı</label>
						<input type="text" name="school_name" value="<?php echo esc_attr( $settings['school_name'] ); ?>">
					</div>
					<div class="sms-field-row">
						<div class="sms-field">
							<label>En Küçük Sınıf Seviyesi</label>
							<input type="number" name="min_grade" min="1" max="12" value="<?php echo (int) $settings['min_grade']; ?>">
						</div>
						<div class="sms-field">
							<label>En Büyük Sınıf Seviyesi</label>
							<input type="number" name="max_grade" min="1" max="12" value="<?php echo (int) $settings['max_grade']; ?>">
						</div>
					</div>
					<div class="sms-field">
						<label>Kurumdaki Son Sınıf (Mezuniyet Sınıfı)</label>
						<input type="number" name="final_grade" min="1" max="12" value="<?php echo (int) $settings['final_grade']; ?>">
						<p class="sms-muted">Yeni dönem açıldığında bu seviyedeki öğrenciler bir üst sınıfa aktarılmak yerine <strong>mezun</strong> statüsüne alınır ve arşivlenir.</p>
					</div>
					<button type="submit" class="sms-btn sms-btn-primary">Ayarları Kaydet</button>
				</form>
			</div>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2>Nasıl Çalışır?</h2></div>
			<div class="sms-pad sms-muted">
				<ol class="sms-help-list">
					<li><strong>Dönem açın:</strong> Dönemler sayfasından örn. "2025-2026" dönemini oluşturun.</li>
					<li><strong>Kadroyu kurun:</strong> Öğretmen ve veli hesaplarını, ardından öğrencileri ekleyin (sınıf seviyesi ve veli eşleştirmesiyle).</li>
					<li><strong>Derslikleri oluşturun:</strong> Şube mantığıyla (Türkçe 6-A, Fen 7-B…) derslik açın, öğretmen ve öğrenci atayın.</li>
					<li><strong>Takip edin:</strong> Yoklama alın, not girin, alışkanlık tanımlayıp günlük doldurun.</li>
					<li><strong>Dönem sonu:</strong> Yeni dönem açtığınızda öğrenciler otomatik olarak bir üst sınıfa aktarılır; son sınıflar mezun olur.</li>
				</ol>
			</div>
		</div>
	</div>
</div>
