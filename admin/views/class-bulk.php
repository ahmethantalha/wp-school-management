<?php
defined( 'ABSPATH' ) || exit;

/**
 * Toplu derslik oluşturma sihirbazı.
 *
 * Branş × (sınıf, şube) matrisinden seçilen her kombinasyon için bir derslik
 * açar ve isteğe bağlı olarak kadroyu öğrencilerin şubesinden doldurur. Aynı
 * branş + sınıf + şube için derslik zaten varsa atlanır — sihirbaz idempotenttir.
 */

if ( ! nizamiye_is_manager() ) {
	wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
}

$nizamiye_term_id = nizamiye_current_term_id();
if ( ! $nizamiye_term_id ) {
	echo '<div class="wrap sms-wrap"><div class="sms-card sms-empty"><h2>Önce bir dönem oluşturun</h2></div></div>';
	return;
}

$nizamiye_grades   = Nizamiye_Students::grades_in_term( $nizamiye_term_id );
$nizamiye_subjects = Nizamiye_Classes::subjects_in_term( $nizamiye_term_id );
$nizamiye_teachers = nizamiye_users_by_role( 'nizamiye_teacher' );

// Matris: her sınıf seviyesi için o seviyede fiilen kullanılan şubeler.
$nizamiye_matrix = array();
foreach ( $nizamiye_grades as $nizamiye_g ) {
	$nizamiye_matrix[ (int) $nizamiye_g ] = Nizamiye_Students::sections_in_term( $nizamiye_term_id, (int) $nizamiye_g );
}
$nizamiye_any_section = (bool) array_filter( $nizamiye_matrix );
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Toplu Derslik Oluştur', 'Seçtiğiniz branşları, sınıf ve şube kombinasyonlarıyla çarpıp derslikleri tek seferde açar.' ); ?>

	<p><a class="sms-back-link" href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-classes&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">← Dersliklere dön</a></p>

	<?php if ( ! $nizamiye_grades ) : ?>
		<div class="sms-card sms-empty">
			<span class="dashicons dashicons-groups"></span>
			<h2>Bu dönemde öğrenci yok</h2>
			<p>Toplu derslik oluşturmak için önce öğrencileri bu döneme kaydedin.</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<?php if ( ! $nizamiye_any_section ) : ?>
		<div class="sms-notice sms-notice-info">
			<span class="dashicons dashicons-info"></span>
			Hiçbir öğrenciye şube atanmamış. Şubesiz de derslik açabilirsiniz (sınıfın tamamı tek derslik olur),
			ama şube bazlı çalışmak için önce
			<a href="<?php echo esc_url( nizamiye_view_nonce_url( admin_url( 'admin.php?page=nizamiye-students&nizamiye_term=' . $nizamiye_term_id ) ) ); ?>">Öğrenciler</a>
			sayfasından toplu şube ataması yapın.
		</div>
	<?php endif; ?>

	<?php nizamiye_form_open( 'nizamiye_bulk_classes' ); nizamiye_back_url_field(); ?>
		<input type="hidden" name="term_id" value="<?php echo (int) $nizamiye_term_id; ?>">

		<div class="sms-card">
			<div class="sms-card-head"><h2>1. Branşlar</h2></div>
			<div class="sms-pad">
				<p class="sms-muted">Her satıra bir branş yazın. Bu dönemde hâlihazırda kullanılan branşlarla dolduruldu.</p>
				<div class="sms-field"><textarea name="subjects" rows="6" placeholder="Türkçe&#10;Matematik&#10;Fen Bilimleri&#10;İngilizce&#10;Sosyal Bilgiler"><?php echo esc_textarea( implode( "\n", $nizamiye_subjects ) ); ?></textarea></div>
			</div>
		</div>

		<div class="sms-card sms-mt">
			<div class="sms-card-head"><h2>2. Sınıf ve şubeler</h2></div>
			<div class="sms-pad">
				<p class="sms-muted">Derslik açılacak kombinasyonları işaretleyin. "Şubesiz" seçeneği o sınıfın tamamı için tek bir derslik açar.</p>
				<table class="sms-table">
					<thead><tr><th>Sınıf</th><th>Şubeler</th></tr></thead>
					<tbody>
					<?php foreach ( $nizamiye_grades as $nizamiye_g ) : $nizamiye_g = (int) $nizamiye_g; ?>
						<tr>
							<td><strong><?php echo esc_html( nizamiye_grade_label( $nizamiye_g ) ); ?></strong></td>
							<td>
								<?php foreach ( $nizamiye_matrix[ $nizamiye_g ] as $nizamiye_sec ) : ?>
									<label class="sms-check sms-check-inline">
										<input type="checkbox" name="combo[]" value="<?php echo esc_attr( $nizamiye_g . ':' . $nizamiye_sec ); ?>" checked>
										<?php echo esc_html( nizamiye_section_label( $nizamiye_g, $nizamiye_sec ) ); ?>
									</label>
								<?php endforeach; ?>
								<label class="sms-check sms-check-inline">
									<input type="checkbox" name="combo[]" value="<?php echo esc_attr( $nizamiye_g . ':' ); ?>" <?php checked( empty( $nizamiye_matrix[ $nizamiye_g ] ) ); ?>>
									<span class="sms-muted">Şubesiz (sınıfın tamamı)</span>
								</label>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="sms-card sms-mt">
			<div class="sms-card-head"><h2>3. Seçenekler</h2></div>
			<div class="sms-pad">
				<div class="sms-field">
					<label>Derslik adı deseni</label>
					<input type="text" name="name_pattern" value="{brans} {sinif}-{sube}" placeholder="{brans} {sinif}-{sube}">
					<p class="sms-muted">
						Kullanılabilir yer tutucular: <code>{brans}</code>, <code>{sinif}</code>, <code>{sube}</code>.
						Örnek çıktı: <strong>Türkçe 6-A</strong>. Şubesiz kombinasyonlarda <code>-{sube}</code> kısmı kendiliğinden düşer.
					</p>
				</div>
				<div class="sms-field">
					<label>Varsayılan öğretmen (isteğe bağlı)</label>
					<select name="teacher_id">
						<option value="0">— Atanmadı —</option>
						<?php foreach ( $nizamiye_teachers as $nizamiye_t ) : ?>
							<option value="<?php echo (int) $nizamiye_t->ID; ?>"><?php echo esc_html( $nizamiye_t->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="sms-muted">Tüm oluşturulan dersliklere atanır; sonradan derslik ekranından tek tek değiştirebilirsiniz.</p>
				</div>
				<label class="sms-check">
					<input type="checkbox" name="auto_roster" value="1" checked>
					Kadroları şubeden doldur ve bağlı tut
				</label>
				<p class="sms-muted">
					İşaretliyse kadro öğrencilerin şubesinden doldurulur; sonradan şubeye yazılan öğrenciler bu dersliklere
					otomatik eklenir. Çıkarma işlemi asla kendiliğinden olmaz — derslik ekranındaki senkron butonuyla onaylarsınız.
				</p>
			</div>
		</div>

		<div class="sms-toolbar sms-mt">
			<span class="sms-muted">Zaten var olan derslikler atlanır; sihirbazı tekrar çalıştırmak kopya oluşturmaz.</span>
			<button type="submit" class="sms-btn sms-btn-primary">Derslikleri Oluştur</button>
		</div>
	</form>
</div>
