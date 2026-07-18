# Okul Yönetim Sistemi (WordPress Eklentisi)

Öğrenci yurtları, okullar ve eğitim kurumları için **dönem bazlı öğrenci takip sistemi**.
Öğrenci/öğretmen/veli yönetimi, derslik (şube) yönetimi, yoklama, not girişi,
alışkanlık takibi ve görsel raporlar sunar.

> Geliştirme planı ve mimari kararlar için [progress.md](progress.md) dosyasına bakın.

## Kurulum

1. Bu depoyu `wp-content/plugins/wp-school-management` klasörüne kopyalayın
   (veya depoyu zip olarak indirip WordPress **Eklentiler → Yeni Ekle → Eklenti Yükle** ile yükleyin).
2. **Eklentiler** sayfasından *Okul Yönetim Sistemi*'ni etkinleştirin.
3. Sol menüde **Okul Yönetimi** menüsü belirir.

## Hızlı Başlangıç

1. **Ayarlar** → kurum adını ve **son sınıf (mezuniyet) seviyesini** belirleyin (örn. 8).
2. **Dönemler** → ilk dönemi oluşturun (örn. `2025-2026`).
3. **Öğretmenler / Veliler** → hesapları oluşturun.
4. **Öğrenciler** → öğrencileri ekleyin: ad soyad, doğum tarihi, okul, sınıf seviyesi, veli eşleştirmesi
   ve isteğe bağlı öğrenci giriş hesabı.
5. **Derslikler** → şube mantığıyla derslik açın (örn. *Türkçe 6-A*), öğretmen atayın,
   sınıf filtreli + toplu seçimli kadro ekranından öğrencileri ekleyin.
6. **Yoklama / Notlar / Alışkanlıklar** → günlük takibinizi yapın.
7. Dönem sonunda **Dönemler → Yeni Dönem Aç**: tüm öğrenciler otomatik olarak bir üst sınıfa
   aktarılır; son sınıftakiler **mezun** statüsüne geçip arşivlenir (Öğrenciler sayfasında
   "Mezunlar" filtresiyle görünür).

## Roller ve Yetkiler

| Rol | Yetki |
|---|---|
| Yönetici (administrator) | Tam yetki: tüm sayfalar ve kayıtlar |
| Öğretmen (`sms_teacher`) | Kendi derslikleri: yoklama, not, alışkanlık oluşturma/doldurma; kendi öğrencilerini görüntüleme |
| Veli (`sms_parent`) | Yalnızca kendi çocuklarının karnesi: notlar, yoklama, alışkanlık takipleri |
| Öğrenci (`sms_student`) | Yalnızca kendi karnesi |

Veli ve öğrenci hesapları giriş yaptığında doğrudan panele yönlendirilir.

## Toplu İçe Aktarma (Excel / CSV)

**İçe Aktar** sayfasından öğrenci, öğretmen ve velileri topluca yükleyebilirsiniz:

- `.xlsx` (Excel) veya `.csv` desteklenir; her sekmede indirilebilir **örnek şablon** bulunur.
- İlk satır başlık satırıdır; başlıklar Türkçe/İngilizce yazılabilir (ör. `ad`, `soyad`, `sinif`, `veli_eposta`).
- Öğrenci aktarımında `veli_eposta` sütunu, sisteme önceden eklenmiş veliyle otomatik eşleştirir.
- Öğretmen aktarımında `sinif_ogretmeni` sütununa `1` yazılırsa öğretmen sınıf öğretmeni olarak işaretlenir.
- Atlanan/uyarılı satırlar aktarım sonrası listelenir.

## Yoklama Sistemi (Kategoriler + Oturumlar)

Yoklama artık **kategori** ve **oturum** bazlıdır. **Yoklama → Yoklama Al** ekranında kart seçersiniz:

- **Ders** (derslik bazlı): branş öğretmeni yalnızca kendi dersliğinin yoklamasını alır.
- **Namaz**: kategori seçince vakit kartları (Sabah, Öğle, İkindi, Akşam, Yatsı) çıkar.
- **Temizlik / Telefon**: tek ekranda genel yoklama.

**Sınıf öğretmeni** (öğretmen kartındaki onay kutusu), branş dersi olmasa da genel yoklamaları
(namaz/temizlik/telefon) alabilen sorumlu hocadır. İsteğe bağlı olarak sorumlu olduğu sınıf
seviyeleri seçilebilir (boş bırakılırsa tüm seviyeler).

Yönetici **Yoklama Türleri** sayfasından yeni kategori ekleyebilir, mevcut kategorilere oturum
ekleyip çıkarabilir (örn. "Etüt" kategorisi, Namaz'a yeni bir vakit vb.) ve **genel kategorilerin
hangi sınıflarda görüneceğini** seçebilir (örn. Namaz yoklaması yalnızca 6-8. sınıflarda). Raporlarda
öğrencinin her vakit/oturumdaki katılımı ayrı ayrı görüntülenir.

## Raporlar ve Karneler

- **Raporlar** sayfası analiz merkezidir: Yoklama / Alışkanlık / Not / Genel Başarı sekmeleri,
  öğrenci ya da sınıf bazında gruplama, sınıf filtresi.
  - *Yoklama Analizi*: kategori (örn. Namaz) seçin — her öğrencinin 5 vakit için ayrı ayrı katılım
    oranı, metrik seçimiyle (geldi/gelmedi/geç/izinli %) görüntülenir; en alttaki "Toplu" satırı
    liste genelini özetler.
  - Tarih filtresi iki modludur: **Tarih Aralığı** (mevcut from/to) veya **Ay / Yıl** (belirli bir ay,
    ya da "Tüm Yıl" seçilerek o yılın tamamı) — kompakt, diğer filtrelerin yanında.
- **Karneler** sayfası bireysel öğrenci karnelerine açılır; her karnede **"PDF Karne İndir"** butonu
  bulunur, tarayıcının yazdırma özelliğiyle tek sayfalık bir PDF üretir.
- Dashboard'da sınıf bazında özet ve yoklama türlerine göre katılım tabloları bulunur.

## Toplu Not Yükleme (güvenli akış)

Notlar sayfasında derslik seçtikten sonra **Toplu Not Yükleme** kartı:

1. Sınav adı, tür, tarih ve tam puanı girip **Öğrenci Listesini İndir** deyin — inen CSV'de
   tüm bilgiler doludur, yalnızca *puan* sütunu boştur.
2. Puanları doldurup dosyayı yükleyin (hemen ya da günler sonra — dosya derslik/sınav bilgisini taşır).

Yükleme sırasında öğrenci kimliği, ad-soyad eşleşmesi, derslik yetkisi ve puan aralığı doğrulanır;
uyuşmayan satırlar kaydedilmez ve tek tek raporlanır.

## Erişim Güvenliği

Yönetici dışındaki tüm kullanıcılar (öğretmen/veli/öğrenci) WordPress arayüzünü sadeleştirilmiş
görür: WP menüleri gizlenir, eklenti sayfaları dışına yönlendirme yapılır. Üst çubuk mobil menü
anahtarı için korunur ama sadeleştirilir; sağ üstteki profil menüsünden çıkış yapılır.

## Alışkanlık Takibi

- Alışkanlık oluştururken **takip metodu** seçilir:
  - **Yaptı / Yapmadı** — iki seçenekli takip
  - **Dereceli** — 1'den N'e (2–10 arası ayarlanabilir) yapma derecesi
  - **Kitap / Sayfa Takibi** — günlük kitap adı + o gün okunan sayfa sayısı; karnede toplam sayfa
    ve okunan kitapların listesi (kitap bazlı toplam sayfa, gün sayısı, son okuma tarihi) görüntülenir.
- Öğrenci ataması sınıf filtresi ve "görünenleri seç" toplu seçimiyle yapılır.
- Öğretmenler kendi öğrencilerinin kayıtlarını doldurur; yönetici herkesinkini doldurabilir.

## Teknik Notlar

- Veriler `wp_sms_*` önekli özel tablolarda tutulur; tüm operasyonel kayıtlar **dönem bazlıdır**.
- Grafikler harici kütüphane olmadan hafif bir SVG motoruyla çizilir (CDN bağımlılığı yok).
- Tüm formlar nonce + yetenek (capability) kontrollüdür; kayıt düzeyinde erişim denetimi yapılır.
- Eklenti kaldırıldığında veriler varsayılan olarak **korunur**
  (silmek için `SMS_REMOVE_DATA_ON_UNINSTALL` sabitine bakın: `uninstall.php`).

## Gereksinimler

- WordPress 6.0+
- PHP 7.4+
