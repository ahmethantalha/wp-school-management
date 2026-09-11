=== Nizamiye ===
Contributors: ahmethantalha
Tags: eğitim, yoklama, not defteri, öğrenci yönetimi, raporlar
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Okullar ve öğrenci yurtları için dönem bazlı öğrenci takibi: yoklama, notlar, alışkanlık takibi ve PDF karneler.

== Açıklama ==

**Nizamiye**, öğrenci yurtları, okullar ve eğitim kurumları için geliştirilmiş, dönem bazlı
ve kapsamlı bir öğrenci takip eklentisidir. Öğrenci, öğretmen ve veli yönetiminden yoklamaya,
not girişinden alışkanlık takibine ve görsel raporlara kadar bir kurumun ihtiyaç duyduğu temel
takip araçlarını tek bir panelde bir araya getirir.

= Temel Özellikler =

* **Öğrenci / Öğretmen / Veli yönetimi** — her rol için ayrı bir WordPress kullanıcı rolü ve giriş sonrası doğrudan panele yönlendirme.
* **Derslik yönetimi** — sınıf filtresi ve toplu seçimli kadro ekranıyla hızlı öğrenci ataması.
* **Kategori ve oturum bazlı yoklama** — normal ders yoklamasının yanı sıra namaz vakitleri, temizlik nöbeti, telefon kontrolü gibi genel kategoriler; yönetici yeni kategori/oturum tanımlayabilir ve her kategorinin hangi sınıf seviyelerinde geçerli olacağını seçebilir.
* **Alışkanlık takibi** — Yaptı/Yapmadı, puanlı (1'den N'e) veya kitap/sayfa takibi (günlük kitap adı + okunan sayfa) yöntemleri.
* **Not girişi ve toplu yükleme** — derslik bazlı sınav notları; CSV şablonu indirme ve toplu yükleme desteği.
* **Raporlar** — öğrenci veya sınıf bazında gruplanmış yoklama, alışkanlık, not ve genel performans analiz sekmeleri; tarih aralığı veya ay/yıl filtresi.
* **Veli bilgilendirme listeleri** — bir alışkanlık ya da yoklama türü için günlük, haftalık veya aylık, tek sayfalık toplu isim listesi raporu (kitap okumada okunan kitaplar ve toplam sayfa). PDF, PNG veya JPG olarak indirilip velilere doğrudan gönderilebilir.
* **PDF karneler** — her öğrenci için gerçek, indirilebilir bir `.pdf` karne üretir; Karneler sayfasından birden çok öğrenci seçip karnelerini **tek bir ZIP dosyası** halinde toplu indirebilirsiniz (öğrenci başına bir PDF).
* **Toplu içe aktarma** — öğrenci/öğretmen/veli listelerini Excel (.xlsx) veya CSV ile içe aktarma, örnek şablonlarla birlikte.
* **Dönem geçişi** — yeni dönem açıldığında tüm öğrenciler otomatik olarak bir üst sınıfa aktarılır; mezuniyet seviyesindekiler mezun olarak işaretlenip arşivlenir.
* **Kayıt düzeyinde erişim denetimi** — öğretmenler yalnızca kendi dersliklerini/öğrencilerini, veliler yalnızca kendi çocuklarını görür.

= Roller =

* **Yönetici** — tam yetki.
* **Öğretmen** — kendi derslikleri için yoklama/not/alışkanlık girişi, kendi öğrencilerini görüntüleme.
* **Veli** — yalnızca kendi çocuklarının karnesini görüntüleme.
* **Öğrenci** — yalnızca kendi karnesini görüntüleme.

= Üçüncü Taraf Kütüphane =

PDF üretimi (karneler ve liste raporları), eklentiyle birlikte gelen
[Dompdf](https://github.com/dompdf/dompdf) kütüphanesi (LGPL lisanslı, GPL uyumlu) kullanılarak
sunucu tarafında yapılır. Dompdf, uzak sunuculara hiçbir istek göndermeyecek şekilde
yapılandırılmıştır (`isRemoteEnabled` kapalıdır); eklenti harici hiçbir servise veri göndermez.

Liste raporlarının PNG/JPG olarak indirilmesi tarayıcıda
[html2canvas](https://github.com/niklasvh/html2canvas) 1.4.1 (MIT lisanslı) ile yapılır; kütüphane
küçültülmemiş haliyle `assets/vendor/` altında paketlenmiştir. Yalnızca liste raporu ekranlarında
yüklenir, tamamen istemci tarafında çalışır ve hiçbir ağ isteği yapmaz.

== Kurulum ==

1. Eklenti dosyalarını `wp-content/plugins/nizamiye` klasörüne yükleyin (veya zip dosyasını
   **Eklentiler → Yeni Ekle → Eklenti Yükle** üzerinden yükleyin).
2. **Eklentiler** sayfasından *Nizamiye*'yi etkinleştirin.
3. Sol menüde beliren **Okul Yönetimi** menüsünü açın, **Ayarlar**'a gidin ve kurumunuzun adını
   ve mezuniyet sınıf seviyesini belirleyin.
4. **Dönemler** sayfasından ilk döneminizi oluşturun, ardından öğretmen/veli/öğrenci kayıtlarınızı
   ekleyin veya toplu olarak içe aktarın.

== Sıkça Sorulan Sorular ==

= Eklenti kaldırıldığında öğrenci verileri siliniyor mu? =

Hayır, veriler varsayılan olarak korunur. Verilerin de silinmesini istiyorsanız `wp-config.php`
dosyanıza `define( 'NIZAMIYE_REMOVE_DATA_ON_UNINSTALL', true );` satırını ekleyin.

= Eklenti herhangi bir harici servise veri gönderiyor mu? =

Hayır. Tüm veriler kendi veritabanınızda `wp_nizamiye_*` önekli özel tablolarda saklanır; hiçbir
uzak sunucuya istek gönderilmez. PDF üretimi de tamamen sunucu tarafında, eklentiyle birlikte
gelen Dompdf kütüphanesiyle yapılır.

= Birden fazla derslik/şubeye ihtiyacım var, destekleniyor mu? =

Evet. Derslikler bağımsız şubeler olarak tanımlanır (ör. "Türkçe 6-A") ve her dersliğe bir
öğretmen ve öğrenciler atanabilir.

= Namaz vakitleri gibi özel yoklama kategorileri tanımlayabilir miyim? =

Evet. **Yoklama Türleri** sayfasından yeni kategoriler ve oturumlar (vakitler/zaman dilimleri)
tanımlayabilir, her kategorinin hangi sınıf seviyelerinde görüneceğini seçebilirsiniz.

= Karneleri toplu olarak indirebilir miyim? =

Evet. **Karneler** sayfasında öğrencileri onay kutularıyla seçip ("tümünü seç" dahil), her
öğrenci için ayrı bir PDF içeren tek bir ZIP dosyası indirebilirsiniz.

== Ekran Görüntüleri ==

1. Anasayfa — sınıf bazlı özet ve yoklama katılım tabloları.
2. Yoklama giriş ekranı — kategori/oturum kartları.
3. Raporlar — yoklama/alışkanlık/not analiz sekmeleri.
4. Öğrenci karnesi — yoklama özeti, alışkanlıklar ve not ortalamaları.
5. Toplu içe aktarma — CSV/Excel dosyasından öğrenci, öğretmen veya veli ekleme.
6. Dönemler sayfası — akademik dönemleri yönetme ve aktif dönemi değiştirme.

== Değişiklik Günlüğü ==

= 1.4.0 =
* Alışkanlıklar ve yoklama için, velilere gönderilmek üzere günlük / haftalık / aylık toplu
  liste raporu eklendi. Her rapor, tüm öğrencileri isim isim gösteren tek sayfalık bir listedir:
  kitap okuma alışkanlığında günlük raporda o günkü kitap adı ve sayfa sayısı, haftalık/aylık
  raporda okunan kitaplar ve toplam sayfa; yoklamada günlük raporda durum, haftalık/aylık
  raporda Geldi/Gelmedi/Geç/İzinli sayıları ve katılım oranı yer alır.
* Dönem filtresi Günlük / Haftalık / Aylık seçenekleri sunar. Haftalık modda ay seçildiğinde o
  ayın Pazartesi-Pazar haftaları listelenir; ay sınırını aşan hafta gerçek tarih aralığını korur
  ve buna göre etiketlenir (örneğin "31 Ağustos – 6 Eylül").
* Her rapor PDF (sunucu tarafında Dompdf) ya da PNG/JPG (tarayıcıda html2canvas) olarak
  indirilebilir. Ekrandaki önizleme, PDF ve görsel aynı şablondan ve aynı stil dosyasından
  üretildiği için üçü birbirinden ayrışamaz. Satır yoğunluğu öğrenci sayısına göre kendini
  ayarlar; uzun listelerin tek sayfaya sığması için A4 yönü yatay yapılabilir.
* Raporlara alışkanlık kartındaki "Rapor" butonundan, alışkanlık takip ekranından ve yoklama
  cetvelinden ulaşılır.
* Raporlar sayfasındaki alışkanlık analizi sekmesi artık tarih filtresini dikkate alıyor.
  Önceden, sorgu takip tarihine göre filtrelemediği için her zaman dönemin tamamını kapsıyordu;
  aynı sekmenin CSV dışa aktarması da bundan etkileniyordu. Alışkanlık sütun başlıkları artık
  seçili aralık için o alışkanlığın liste raporuna bağlanıyor.

= 1.3.6 =
* WordPress 7.1 ile uyumluluk doğrulandı ve "Tested up to" değeri 7.1'e yükseltildi. Kod
  değişikliği gerekmedi: eklenti tamamen klasik yönetim panelinde çalışır, blok düzenleyici
  varlığı kaydetmez, medya kipiyle bütünleşmez ve jQuery ya da jQuery UI bağımlılığı taşımaz;
  bu nedenle 7.1'deki her zaman iframe içinde sunulan gönderi düzenleyicisi, istemci tarafı
  medya işleme ve jQuery UI 1.14.2 değişiklikleri eklentiyi etkilemez.
* Önceki sürümlerde eksik kalan 1.3.2 - 1.3.5 değişiklik günlüğü kayıtları eklendi.
* Bu readme dosyasının Türkçe çevirisi `readme-tr_TR.txt` olarak eklendi.

= 1.3.5 =
* Sekme ve dönem geçiş bağlantıları düzeltildi. Eklentinin GET parametrelerine eklenen nonce
  doğrulaması, eklentinin kendi ürettiği bazı bağlantılarda taşınmıyordu; bu yüzden bir sekmeye
  (ör. İçe Aktar sayfasındaki "Öğretmenler"), bir dönem seçicisine ya da "geri dön" / "yeni ekle" /
  "detaylı analiz" bağlantısına tıklandığında sayfa sessizce ilk sekmeye veya aktif döneme geri
  dönüyordu.

= 1.3.4 =
* Etkinleştirme sırasındaki ölümcül hata düzeltildi. Nizamiye'ye yeniden adlandırma sonrasında ana
  eklenti dosyası, henüz yeniden adlandırılmamış dokuz `includes/class-nizamiye-*.php` dosyasını
  require ediyordu; bu yüzden eklenti "Failed opening required" hatasıyla yüklenemiyor ve hiç
  etkinleşmiyordu.

= 1.3.3 =
* Ortak yardımcı fonksiyon dosyasında kalan son Plugin Check uyarıları giderildi.

= 1.3.2 =
* Görünüm dosyalarında kullanılan tüm değişkenler `nizamiye_` önekiyle adlandırıldı; böylece
  eklenti, temadan veya başka eklentilerden gelen global değişkenlerle artık çakışamaz.

= 1.3.1 =
* Karnedeki "Yoklama Özeti" artık her yoklama kategorisi için ayrı bir yüzde gösteriyor (önceden
  tüm kategoriler tek bir toplamda karışık gösteriliyordu); namaz vakitleri gibi çok oturumlu
  kategoriler alt kırılımlarıyla listeleniyor.
* Karneler sayfasına toplu öğrenci seçimi ve seçilen öğrencilerin karnelerinin toplu ZIP olarak
  indirilmesi eklendi (öğrenci başına bir PDF).
* PDF karne indirme artık tarayıcının yazdırma işlevi yerine, eklentiyle gelen Dompdf kütüphanesiyle
  sunucu tarafında üretilen gerçek bir `.pdf` dosyasına dayanıyor.
* Okuma alışkanlığı eklenirken öğrencinin daha önce okuduğu kitaplar otomatik olarak öneriliyor.

= 1.3.0 =
* Genel yoklama kategorileri (ör. namaz vakitleri) için sınıf seviyesi kısıtlaması eklendi.
* Kitap/sayfa takibi alışkanlık türü eklendi.
* Raporlara ay/yıl bazlı tarih filtresi eklendi.
* Karnedeki "Son Yoklamalar" listesi 3 kayıtla sınırlandırıldı.

= 1.2.2 - 1.2.4 =
* Mobil kenar çubuğu ve yoklama ekranı düzeni iyileştirildi.
* Giriş sonrası performans: tekrarlanan sorgular önbelleğe alındı ve veritabanı sürüm kontrolü
  artık yalnızca yönetim panelinde çalışıyor.
* Yoklama raporlarına oturum (zaman dilimi) bazlı kırılım eklendi.
* Profil menüsünden çıkış yapmanın mobilde çalışmaması sorunu giderildi.

= 1.2.0 =
* Analiz rapor sekmeleri, daha güvenli bir toplu not yükleme akışı ve arayüz sadeleştirmesi eklendi.
* Analiz tablolarına CSV dışa aktarma düğmesi eklendi.

= 1.1.0 =
* Toplu içe aktarma (Excel/CSV) ve kategori/oturum bazlı yoklama sistemi eklendi.

= 1.0.0 =
* İlk sürüm: dönem bazlı öğrenci takip altyapısı, öğrenci/öğretmen/veli yönetimi, temel yoklama,
  not ve alışkanlık takibi.

== Yükseltme Notu ==

= 1.3.6 =
WordPress 7.1 uyumluluğu doğrulandı ve readme değişiklik günlüğü tamamlandı. Bu sürümde kod
değişikliği yoktur; güncelledikten sonra herhangi bir işlem yapmanız gerekmez.

= 1.3.4 =
Eklentinin etkinleşmesini engelleyen ölümcül bir hatayı düzeltir. 1.3.2 veya 1.3.3 sürümündeyseniz
güncelleme zorunludur.
