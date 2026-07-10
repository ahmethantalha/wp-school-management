<?php
defined( 'ABSPATH' ) || exit;

$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'students';
$term_id = sms_current_term_id();
$tabs    = array(
	'students' => array( 'Öğrenciler', 'dashicons-groups' ),
	'teachers' => array( 'Öğretmenler', 'dashicons-businessperson' ),
	'parents'  => array( 'Veliler', 'dashicons-admin-users' ),
);
if ( ! isset( $tabs[ $tab ] ) ) {
	$tab = 'students';
}

$errors = get_transient( 'sms_import_errors_' . get_current_user_id() );
if ( $errors ) {
	delete_transient( 'sms_import_errors_' . get_current_user_id() );
}

$columns = array(
	'students' => 'ad, soyad, dogum_tarihi, okul, ogrenci_no, sinif, veli_eposta',
	'teachers' => 'ad, soyad, kullanici_adi, eposta, sinif_ogretmeni (1/0)',
	'parents'  => 'ad, soyad, kullanici_adi, eposta',
);
$hints = array(
	'students' => 'Veli eşleştirmesi için "veli_eposta" sütununa, sisteme önceden eklenmiş velinin e-postasını yazın. "ad_soyad" tek sütun da kabul edilir.',
	'teachers' => 'Şifre sütunu boşsa otomatik güçlü şifre üretilir. "sinif_ogretmeni" sütununa 1 yazarsanız o öğretmen genel (namaz/temizlik) yoklaması alabilir.',
	'parents'  => 'Şifre sütunu boşsa otomatik güçlü şifre üretilir. Aktarımdan sonra öğrenci kartından veli eşleştirmesi yapabilirsiniz.',
);
?>
<div class="wrap sms-wrap">
	<?php sms_view_header( 'Toplu İçe Aktarma', 'Excel (.xlsx) veya CSV dosyasıyla öğrenci, öğretmen ve velileri topluca ekleyin.' ); ?>

	<div class="sms-tabs">
		<?php foreach ( $tabs as $key => $t ) : ?>
			<a class="sms-tab <?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=sms-import&tab=' . $key ) ); ?>">
				<span class="dashicons <?php echo esc_attr( $t[1] ); ?>"></span> <?php echo esc_html( $t[0] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( $errors ) : ?>
		<div class="sms-notice sms-notice-info">
			<span class="dashicons dashicons-info"></span>
			<div>
				<strong>Aktarım uyarıları / atlanan satırlar:</strong>
				<ul class="sms-mini-list">
					<?php foreach ( array_slice( (array) $errors, 0, 30 ) as $e ) : ?>
						<li><?php echo esc_html( $e ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div class="sms-card">
			<div class="sms-card-head"><h2><?php echo esc_html( $tabs[ $tab ][0] ); ?> Yükle</h2></div>
			<div class="sms-pad">
				<?php if ( 'students' === $tab && ! $term_id ) : ?>
					<div class="sms-notice sms-notice-error"><span class="dashicons dashicons-warning"></span>Öğrenci aktarımı için önce aktif bir dönem oluşturun.</div>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="sms-form">
						<input type="hidden" name="action" value="sms_import">
						<?php wp_nonce_field( 'sms_import', '_sms_nonce' ); ?>
						<input type="hidden" name="import_type" value="<?php echo esc_attr( $tab ); ?>">
						<input type="hidden" name="term_id" value="<?php echo (int) $term_id; ?>">
						<input type="hidden" name="_sms_back" value="<?php echo esc_attr( admin_url( 'admin.php?page=sms-import&tab=' . $tab ) ); ?>">

						<div class="sms-dropzone">
							<span class="dashicons dashicons-upload"></span>
							<input type="file" name="import_file" accept=".xlsx,.csv,.txt" required>
							<p class="sms-muted">.xlsx veya .csv dosyası seçin</p>
						</div>

						<button type="submit" class="sms-btn sms-btn-primary sms-mt">İçe Aktar</button>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<div class="sms-card">
			<div class="sms-card-head"><h2>Dosya Biçimi</h2></div>
			<div class="sms-pad">
				<p class="sms-muted">İlk satır <strong>başlık</strong> satırı olmalıdır. Beklenen sütunlar:</p>
				<code class="sms-code"><?php echo esc_html( $columns[ $tab ] ); ?></code>
				<p class="sms-muted sms-mt"><?php echo esc_html( $hints[ $tab ] ); ?></p>
				<a class="sms-btn sms-btn-ghost sms-mt" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sms_import_template&type=' . $tab ), 'sms_template' ) ); ?>">
					<span class="dashicons dashicons-download"></span> Örnek CSV Şablonu İndir
				</a>
				<p class="sms-muted sms-mt-sm"><span class="dashicons dashicons-info"></span> Excel kullanıyorsanız şablonu doldurup <em>Farklı Kaydet → CSV</em> ile kaydedebilir ya da doğrudan .xlsx yükleyebilirsiniz.</p>
			</div>
		</div>
	</div>
</div>
