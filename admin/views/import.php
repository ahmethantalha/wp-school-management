<?php
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET okumaları yalnızca yukarıdaki wp_verify_nonce() doğrulaması geçerse kullanılır; aksi halde güvenli varsayılana düşülür.
$nizamiye_tab     = ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'nizamiye_view' ) ) && isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'students';
$nizamiye_term_id = nizamiye_current_term_id();
$nizamiye_tabs    = array(
	'students' => array( 'Öğrenciler', 'dashicons-groups' ),
	'teachers' => array( 'Öğretmenler', 'dashicons-businessperson' ),
	'parents'  => array( 'Veliler', 'dashicons-admin-users' ),
);
if ( ! isset( $nizamiye_tabs[ $nizamiye_tab ] ) ) {
	$nizamiye_tab = 'students';
}

$nizamiye_errors = get_transient( 'nizamiye_import_errors_' . get_current_user_id() );
if ( $nizamiye_errors ) {
	delete_transient( 'nizamiye_import_errors_' . get_current_user_id() );
}

$nizamiye_columns = array(
	'students' => 'ad, soyad, dogum_tarihi, okul, ogrenci_no, sinif, veli_eposta',
	'teachers' => 'ad, soyad, kullanici_adi, eposta, sinif_ogretmeni (1/0)',
	'parents'  => 'ad, soyad, kullanici_adi, eposta',
);
$nizamiye_hints = array(
	'students' => 'Veli eşleştirmesi için "veli_eposta" sütununa, sisteme önceden eklenmiş velinin e-postasını yazın. "ad_soyad" tek sütun da kabul edilir.',
	'teachers' => 'Şifre sütunu boşsa otomatik güçlü şifre üretilir. "sinif_ogretmeni" sütununa 1 yazarsanız o öğretmen genel (namaz/temizlik) yoklaması alabilir.',
	'parents'  => 'Şifre sütunu boşsa otomatik güçlü şifre üretilir. Aktarımdan sonra öğrenci kartından veli eşleştirmesi yapabilirsiniz.',
);
?>
<div class="wrap sms-wrap">
	<?php nizamiye_view_header( 'Toplu İçe Aktarma', 'Excel (.xlsx) veya CSV dosyasıyla öğrenci, öğretmen ve velileri topluca ekleyin.' ); ?>

	<div class="sms-tabs">
		<?php foreach ( $nizamiye_tabs as $nizamiye_key => $nizamiye_t ) : ?>
			<a class="sms-tab <?php echo $nizamiye_tab === $nizamiye_key ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=nizamiye-import&tab=' . $nizamiye_key ) ); ?>">
				<span class="dashicons <?php echo esc_attr( $nizamiye_t[1] ); ?>"></span> <?php echo esc_html( $nizamiye_t[0] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( $nizamiye_errors ) : ?>
		<div class="sms-notice sms-notice-info">
			<span class="dashicons dashicons-info"></span>
			<div>
				<strong>Aktarım uyarıları / atlanan satırlar:</strong>
				<ul class="sms-mini-list">
					<?php foreach ( array_slice( (array) $nizamiye_errors, 0, 30 ) as $nizamiye_e ) : ?>
						<li><?php echo esc_html( $nizamiye_e ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>

	<div class="sms-grid-2 sms-grid-uneven">
		<div class="sms-card">
			<div class="sms-card-head"><h2><?php echo esc_html( $nizamiye_tabs[ $nizamiye_tab ][0] ); ?> Yükle</h2></div>
			<div class="sms-pad">
				<?php if ( 'students' === $nizamiye_tab && ! $nizamiye_term_id ) : ?>
					<div class="sms-notice sms-notice-error"><span class="dashicons dashicons-warning"></span>Öğrenci aktarımı için önce aktif bir dönem oluşturun.</div>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="sms-form">
						<input type="hidden" name="action" value="nizamiye_import">
						<?php wp_nonce_field( 'nizamiye_import', '_nizamiye_nonce' ); ?>
						<input type="hidden" name="import_type" value="<?php echo esc_attr( $nizamiye_tab ); ?>">
						<input type="hidden" name="term_id" value="<?php echo (int) $nizamiye_term_id; ?>">
						<input type="hidden" name="_nizamiye_back" value="<?php echo esc_attr( admin_url( 'admin.php?page=nizamiye-import&tab=' . $nizamiye_tab ) ); ?>">

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
				<code class="sms-code"><?php echo esc_html( $nizamiye_columns[ $nizamiye_tab ] ); ?></code>
				<p class="sms-muted sms-mt"><?php echo esc_html( $nizamiye_hints[ $nizamiye_tab ] ); ?></p>
				<a class="sms-btn sms-btn-ghost sms-mt" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nizamiye_import_template&type=' . $nizamiye_tab ), 'nizamiye_template' ) ); ?>">
					<span class="dashicons dashicons-download"></span> Örnek CSV Şablonu İndir
				</a>
				<p class="sms-muted sms-mt-sm"><span class="dashicons dashicons-info"></span> Excel kullanıyorsanız şablonu doldurup <em>Farklı Kaydet → CSV</em> ile kaydedebilir ya da doğrudan .xlsx yükleyebilirsiniz.</p>
			</div>
		</div>
	</div>
</div>
