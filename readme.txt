=== Okul Yönetim Sistemi ===
Contributors: ahmethantalha
Tags: education, attendance, gradebook, student management, reports
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Öğrenci yurtları, okullar ve eğitim kurumları için dönem bazlı öğrenci takip sistemi: yoklama, not, alışkanlık takibi ve PDF karneler.

== Description ==

**Okul Yönetim Sistemi**, öğrenci yurtları, okullar ve eğitim kurumları için hazırlanmış, dönem
bazlı çalışan kapsamlı bir öğrenci takip eklentisidir. Öğrenci, öğretmen ve veli yönetiminden
yoklama, not girişi, alışkanlık takibine ve görsel raporlara kadar bir kurumun ihtiyaç duyduğu
temel takip araçlarını tek bir panelde toplar.

= Öne Çıkan Özellikler =

* **Öğrenci / Öğretmen / Veli Yönetimi** — her rol için ayrı WordPress kullanıcı rolü ve giriş sonrası doğrudan panele yönlendirme.
* **Derslik (şube) yönetimi** — sınıf filtresi ve toplu seçimli kadro ekranı ile hızlı öğrenci atama.
* **Kategori ve oturum bazlı yoklama** — ders yoklamasının yanı sıra namaz vakitleri, temizlik, telefon gibi genel kategoriler; yönetici yeni kategori/oturum tanımlayabilir ve kategorinin hangi sınıf seviyelerinde görüneceğini seçebilir.
* **Alışkanlık takibi** — Yaptı/Yapmadı, dereceli (1'den N'e) veya kitap/sayfa takibi (günlük kitap adı + okunan sayfa sayısı) yöntemleriyle.
* **Not girişi ve toplu yükleme** — derslik bazlı sınav notları; CSV şablonu indirip toplu yükleme desteği.
* **Raporlar** — yoklama, alışkanlık, not ve genel başarı için öğrenci/sınıf bazlı analiz sekmeleri; tarih aralığı veya ay/yıl bazlı filtreleme.
* **PDF Karneler** — her öğrenci için gerçek, indirilebilir bir `.pdf` karne üretir; Karneler sayfasından birden fazla öğrenci seçip karnelerini **tek bir ZIP dosyası** (öğrenci başına ayrı PDF) olarak toplu indirebilirsiniz.
* **Toplu içe aktarma** — öğrenci/öğretmen/veli listelerini Excel (.xlsx) veya CSV ile içe aktarma, örnek şablonlarla.
* **Dönem geçişi** — yeni dönem açıldığında tüm öğrenciler otomatik bir üst sınıfa taşınır, mezuniyet seviyesindekiler mezun statüsüne geçip arşivlenir.
* **Kayıt düzeyinde erişim denetimi** — öğretmenler yalnızca kendi derslik/öğrencilerini, veliler yalnızca kendi çocuklarını görür.

= Roller =

* **Yönetici** — tam yetki.
* **Öğretmen** — kendi derslikleri için yoklama/not/alışkanlık girişi, kendi öğrencilerini görüntüleme.
* **Veli** — yalnızca kendi çocuklarının karnesini görüntüleme.
* **Öğrenci** — yalnızca kendi karnesini görüntüleme.

= Üçüncü Taraf Kütüphane =

PDF karne üretimi, eklentiyle birlikte paket içinde gelen [Dompdf](https://github.com/dompdf/dompdf)
kütüphanesi (LGPL lisanslı, GPL ile uyumlu) ile sunucu tarafında yapılır. Dompdf uzak sunuculara
istek atmayacak şekilde yapılandırılmıştır (`isRemoteEnabled` kapalı); eklenti hiçbir harici
servise veri göndermez.

== Installation ==

1. Eklenti dosyalarını `wp-content/plugins/wp-school-management` klasörüne yükleyin (veya
   **Eklentiler → Yeni Ekle → Eklenti Yükle** ile zip dosyasını yükleyin).
2. **Eklentiler** sayfasından *Okul Yönetim Sistemi*'ni etkinleştirin.
3. Sol menüde beliren **Okul Yönetimi** menüsünden **Ayarlar**'a girip kurum adını ve mezuniyet
   sınıf seviyesini belirleyin.
4. **Dönemler** sayfasından ilk dönemi oluşturun, ardından öğretmen/veli/öğrenci kayıtlarını
   ekleyin veya toplu içe aktarın.

== Frequently Asked Questions ==

= Öğrenci verileri eklenti kaldırıldığında silinir mi? =

Hayır, varsayılan olarak korunur. Verilerin de silinmesini istiyorsanız `wp-config.php`
dosyanıza `define( 'SMS_REMOVE_DATA_ON_UNINSTALL', true );` satırını eklemeniz gerekir.

= Eklenti harici bir servise veri gönderiyor mu? =

Hayır. Tüm veriler kendi veritabanınızdaki `wp_sms_*` önekli tablolarda tutulur, hiçbir uzak
sunucuya istek gönderilmez. PDF üretimi de tamamen sunucu tarafında, paket içindeki Dompdf
kütüphanesiyle yapılır.

= Birden fazla dersliğe / şubeye ihtiyacım var, destekliyor mu? =

Evet. Derslikler bağımsız şubeler olarak tanımlanır (örn. "Türkçe 6-A"), her dersliğe öğretmen
ve öğrenci ataması yapılabilir.

= Namaz vakitleri gibi özel yoklama kategorileri tanımlayabilir miyim? =

Evet. **Yoklama Türleri** sayfasından yeni kategori ve oturum (vakit/seans) tanımlayabilir,
kategorinin hangi sınıf seviyelerinde görüneceğini seçebilirsiniz.

= Karneleri toplu olarak indirebilir miyim? =

Evet. **Karneler** sayfasında öğrencileri onay kutularıyla (tümünü seç dahil) seçip, her biri
için ayrı bir PDF içeren tek bir ZIP dosyası indirebilirsiniz.

== Screenshots ==

1. Dashboard — sınıf bazında özet ve yoklama katılım tabloları.
2. Yoklama alma ekranı — kategori/oturum kartları.
3. Raporlar — yoklama/alışkanlık/not analiz sekmeleri.
4. Öğrenci karnesi — yoklama özeti, alışkanlıklar ve not ortalamaları.

== Changelog ==

= 1.3.1 =
* Karne "Yoklama Özeti" artık her yoklama kategorisi için ayrı yüzde gösteriyor (önceden tüm
  kategoriler karışık toplam olarak gösteriliyordu); çoklu oturumlu kategoriler (namaz vakitleri
  gibi) alt kırılımlarıyla listeleniyor.
* Karneler sayfasına toplu öğrenci seçimi ve seçilen öğrencilerin karnelerini ZIP içinde
  (öğrenci başına ayrı PDF) toplu indirme özelliği eklendi.
* PDF karne indirme artık tarayıcı yazdırmasına değil, paket içindeki Dompdf kütüphanesiyle
  sunucu tarafında üretilen gerçek `.pdf` dosyasına dayanıyor.
* Kitap okuma alışkanlığı eklerken öğrencinin daha önce okuduğu kitaplar otomatik önerilir.

= 1.3.0 =
* Genel yoklama kategorileri (namaz vb.) için sınıf seviyesi kısıtlaması eklendi.
* Kitap/sayfa takibi alışkanlık türü eklendi.
* Raporlarda ay/yıl bazlı tarih filtresi eklendi.
* Karnede "Son Yoklamalar" listesi 3 kayıtla sınırlandırıldı.

= 1.2.2 - 1.2.4 =
* Mobil kenar çubuğu ve yoklama ekranı düzeni iyileştirildi.
* Giriş sonrası performans: tekrarlanan sorgular önbelleğe alındı, veritabanı sürüm kontrolü
  yalnızca yönetim panelinde çalışacak şekilde taşındı.
* Yoklama raporlarına oturum (vakit) bazlı kırılım eklendi.
* Mobilde profil menüsünden çıkış yapılamama hatası düzeltildi.

= 1.2.0 =
* Analiz raporları sekmeleri, güvenli toplu not yükleme akışı ve arayüz sadeleştirmesi eklendi.
* Analiz tablolarına CSV dışa aktarma butonu eklendi.

= 1.1.0 =
* Toplu içe aktarma (Excel/CSV) ve kategori/oturum bazlı yoklama sistemi eklendi.

= 1.0.0 =
* İlk sürüm: dönem bazlı öğrenci takip altyapısı, öğrenci/öğretmen/veli yönetimi, temel yoklama,
  not ve alışkanlık takibi.

== Upgrade Notice ==

= 1.3.1 =
Karne PDF üretimi artık sunucu tarafında gerçekleşiyor ve toplu ZIP indirme desteği ekleniyor;
güncelleme sonrası ek bir işlem gerekmez.
