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

Yönetici **Yoklama Türleri** sayfasından yeni kategori ekleyebilir ve mevcut kategorilere oturum
ekleyip çıkarabilir (örn. "Etüt" kategorisi, Namaz'a yeni bir vakit vb.). Raporlarda öğrencinin
her vakit/oturumdaki katılımı ayrı ayrı görüntülenir.

## Alışkanlık Takibi

- Alışkanlık oluştururken **takip metodu** seçilir:
  - **Yaptı / Yapmadı** — iki seçenekli takip
  - **Dereceli** — 1'den N'e (2–10 arası ayarlanabilir) yapma derecesi
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
