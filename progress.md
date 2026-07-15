# Okul / Öğrenci Takip Sistemi — Geliştirme Planı ve İlerleme

> **Mimari karar:** Sistem WordPress **eklentisi (plugin)** olarak geliştirildi, tema olarak değil.
> Sebep: Öğrenci verileri, roller ve iş mantığı temaya bağlı olmamalı. Tema değiştirildiğinde
> veriler ve işlevsellik aynen kalır; WPSchoolPress ve School Management ERP de aynı yaklaşımı kullanır.
> Eklenti, WP yönetim paneli içinde tamamen kendi modern tasarımına sahip sayfalar sunar.

---

## 1. Genel Mimari

- **Eklenti adı:** Okul Yönetim Sistemi (`wp-school-management`)
- **Kurulum:** Klasörü `wp-content/plugins/` altına kopyala → Eklentiyi etkinleştir.
- **Veri katmanı:** Özel veritabanı tabloları (`wp_sms_*`), `dbDelta` ile kurulur.
- **Roller:** `administrator` (tam yetki), `sms_teacher` (Öğretmen), `sms_parent` (Veli), `sms_student` (Öğrenci).
- **Yetki modeli:** Özel yetenekler (capability): `sms_manage` (yönetici), `sms_teach` (öğretmen),
  `sms_access` (panele giriş — tüm roller). Her kayıt düzeyinde ayrıca erişim kontrolü yapılır
  (öğretmen yalnızca kendi dersliklerini/öğrencilerini, veli yalnızca kendi çocuklarını görür).
- **Arayüz:** WP admin menüsü altında "Okul Yönetimi" — tamamen özel CSS ile modern, kart tabanlı,
  yumuşak gölgeli, İndigo vurgulu tasarım. Grafikler harici kütüphane olmadan, kendi hafif
  SVG grafik motorumuzla (bar, çizgi, halka) çizilir.

## 2. Veritabanı Şeması

| Tablo | Amaç |
|---|---|
| `wp_sms_terms` | Dönemler (2025-2026 vb.), tek aktif dönem |
| `wp_sms_students` | Öğrenci ana kaydı (ad, soyad, doğum tarihi, okul, veli, opsiyonel WP kullanıcısı, durum: aktif/mezun) |
| `wp_sms_enrollments` | Dönem bazlı kayıt: öğrenci × dönem × sınıf seviyesi (dönem bazlı filtreleme buradan) |
| `wp_sms_classes` | Derslikler/şubeler (ör. "Türkçe 6-A"): branş, sınıf seviyesi, öğretmen, dönem |
| `wp_sms_class_students` | Derslik ↔ öğrenci eşleşmesi (bir öğrenci birden çok derslikte olabilir) |
| `wp_sms_attendance` | Yoklama: derslik × öğrenci × tarih × durum (geldi/gelmedi/geç/izinli) |
| `wp_sms_habits` | Alışkanlıklar: ad, açıklama, takip tipi (`binary` = yaptı/yapmadı, `scale` = 1–N derece), dönem, oluşturan |
| `wp_sms_habit_students` | Alışkanlığa atanan öğrenciler |
| `wp_sms_habit_logs` | Günlük alışkanlık kayıtları (öğrenci × tarih × değer) |
| `wp_sms_grades` | Notlar: derslik × öğrenci × sınav adı/türü × puan |

Ayarlar `wp_options` içinde `sms_settings` anahtarında tutulur (kurum adı, son sınıf seviyesi vb.).

## 3. Dönem ve Otomatik Sınıf Atlatma Mantığı

- Yönetici **Dönemler** sayfasından yeni dönem açar.
- "Yeni Dönem Aç" sihirbazı, aktif dönemdeki öğrencileri **otomatik aktarır**:
  - Sınıf seviyesi +1 artırılarak yeni döneme kaydedilir (7 → 8).
  - Ayarlardaki **son sınıf** seviyesinde olanlar (örn. 8) yeni döneme aktarılmaz,
    öğrenci durumu **"mezun"** yapılır ve arşivde kalır (öğrenci listesinde görünmez,
    Öğrenciler sayfasındaki "Mezunlar" filtresiyle erişilir).
  - Aktarım öncesi önizleme gösterilir: kaç öğrenci terfi edecek, kaç öğrenci mezun olacak.
- Tüm kayıtlar (derslik, yoklama, not, alışkanlık) dönem kimliği üzerinden filtrelenir;
  eski dönem verileri arşiv olarak durur, üstteki dönem seçici ile geçmiş döneme bakılabilir.

## 4. Sayfalar

| Sayfa | Kimler | İçerik |
|---|---|---|
| **Anasayfa (Dashboard)** | Yönetici, Öğretmen | Öğrenci/öğretmen/derslik/alışkanlık sayaçları, son 14 gün alışkanlık tamamlama grafiği, yoklama dağılım halkası, "Ayın Yıldızları" (en iyi) ve "Destek Bekleyenler" (gelişim odaklı, "kötü" denmez) listeleri |
| **Dönemler** | Yönetici | Dönem listesi, yeni dönem + otomatik terfi/mezuniyet sihirbazı |
| **Öğrenciler** | Yönetici (tam), Öğretmen (kendi öğrencileri, salt okunur) | Liste (sınıf/durum/arama filtreli), ekle/düzenle: ad soyad, doğum tarihi, okul, numara, sınıf seviyesi, veli seçimi, opsiyonel giriş hesabı |
| **Öğretmenler** | Yönetici | Öğretmen hesabı oluşturma/listeleme, derslik sayıları |
| **Veliler** | Yönetici | Veli hesabı oluşturma/listeleme, bağlı çocuklar (bir veli → çok öğrenci; bir öğrenci → tek veli) |
| **Derslikler** | Yönetici (tam), Öğretmen (kendi derslikleri) | Şube mantığı: "Türkçe 6-A" gibi; branş, seviye, öğretmen ataması; sınıf filtreli + toplu seçimli öğrenci kadrosu yönetimi |
| **Yoklama** | Yönetici, Öğretmen (kendi dersliği) | Derslik + tarih seç → tek ekranda tüm sınıfın yoklaması (geldi/gelmedi/geç/izinli), "tümünü geldi işaretle" |
| **Alışkanlıklar** | Yönetici, Öğretmen | Alışkanlık oluştur (ad, açıklama, **takip tipi: yaptı-yapmadı VEYA 1–N dereceli** — oluştururken seçilir), sınıf filtreli + toplu seçimli öğrenci atama; günlük takip doldurma ekranı; tamamlama oranları |
| **Notlar** | Yönetici, Öğretmen (kendi dersliği) | Derslik seç → sınav tanımla (ad, tür, tarih) → tüm öğrencilere tek ekranda puan girişi; sınav geçmişi |
| **Raporlar** | Yönetici, Öğretmen; Veli/Öğrenci kendi kaydı | Öğrenci karnesi: not ortalaması, devam yüzdesi, alışkanlık tamamlama, grafikler, dönem geçmişi |
| **Öğrencilerim** | Veli | Çocuklarının kartları + hızlı istatistikler → detaylı rapora geçiş |
| **Ayarlar** | Yönetici | Kurum adı, son sınıf (mezuniyet) seviyesi, sınıf seviyesi aralığı |

## 5. Görev Listesi

### Altyapı
- [x] Eklenti iskeleti, sabitler, yükleme sırası (`wp-school-management.php`)
- [x] Veritabanı kurulumu — 10 tablo, `dbDelta` (`includes/class-sms-install.php`)
- [x] Roller ve yetenekler (`includes/class-sms-roles.php`)
- [x] Ortak yardımcılar: ayarlar, aktif dönem, erişim kontrolleri (`includes/sms-helpers.php`)

### İş Mantığı (repository katmanı)
- [x] Dönemler + otomatik terfi/mezuniyet (rollover) (`includes/class-sms-terms.php`)
- [x] Öğrenciler + dönem kayıtları (enrollment) (`includes/class-sms-students.php`)
- [x] Derslikler/şubeler + kadro yönetimi (`includes/class-sms-classes.php`)
- [x] Yoklama (`includes/class-sms-attendance.php`)
- [x] Alışkanlıklar + günlük kayıtlar + tamamlama oranları (`includes/class-sms-habits.php`)
- [x] Notlar (`includes/class-sms-grades.php`)
- [x] Rapor/istatistik sorguları (dashboard, karne) (`includes/class-sms-reports.php`)

### Yönetim Arayüzü
- [x] Menü, sayfa yönlendirme, varlık yükleme (`admin/class-sms-menu.php`)
- [x] Form işleyicileri — nonce + yetki kontrollü (`admin/class-sms-actions.php`)
- [x] Anasayfa / Dashboard (`admin/views/dashboard.php`)
- [x] Dönemler + rollover sihirbazı (`admin/views/terms.php`)
- [x] Öğrenciler listesi + düzenleme (`admin/views/students.php`, `student-edit.php`)
- [x] Öğretmenler (`admin/views/teachers.php`)
- [x] Veliler (`admin/views/parents.php`)
- [x] Derslikler + kadro yönetimi (`admin/views/classes.php`, `class-edit.php`)
- [x] Yoklama ekranı (`admin/views/attendance.php`)
- [x] Alışkanlıklar: liste, oluştur/düzenle, günlük takip (`admin/views/habits.php`, `habit-edit.php`, `habit-track.php`)
- [x] Notlar (`admin/views/grades.php`)
- [x] Öğrenci karnesi / raporlar (`admin/views/reports.php`, `student-report.php`)
- [x] Veli portalı "Öğrencilerim" (`admin/views/my-children.php`)
- [x] Ayarlar (`admin/views/settings.php`)

### Tasarım & Ön Yüz
- [x] Modern tasarım sistemi: CSS değişkenleri, kartlar, rozetler, tablolar, formlar (`assets/css/admin.css`)
- [x] Hafif SVG grafik motoru: çizgi, bar, halka (`assets/js/sms-charts.js`)
- [x] Etkileşimler: sınıf filtresi, toplu seçim, yoklama kısayolları (`assets/js/admin.js`)

### Paketleme
- [x] `uninstall.php` (isteğe bağlı veri temizliği — varsayılan: veriyi korur)
- [x] `readme.md` kurulum ve kullanım kılavuzu

### Gelecek Sürüm Fikirleri (v1.2+)
- [ ] E-posta/SMS bildirimleri (devamsızlık, düşük not uyarısı)
- [ ] Ödev takibi modülü
- [ ] Veli-öğretmen mesajlaşması
- [ ] PDF karne çıktısı
- [ ] Dışa aktarma (CSV/Excel rapor çıktısı)

## 7. Sürüm 1.1 — Toplu İçe Aktarma + Kategorili Yoklama

### Toplu İçe Aktarma (Excel/CSV)
- [x] Harici kütüphanesiz okuyucu: CSV (`;`/`,` otomatik ayırıcı, UTF-8 BOM) + XLSX (ZipArchive + SimpleXML, sharedStrings) (`includes/class-sms-import.php`)
- [x] Türkçe/İngilizce başlık eş anlamlıları (ad/soyad/ad_soyad, sınıf, veli_eposta…) otomatik eşlenir
- [x] Öğrenci içe aktarma: dönem kaydı + e-posta ile veli eşleştirme; öğretmen/veli hesabı içe aktarma (sınıf öğretmeni bayrağı dahil)
- [x] Sekmeli içe aktarma sayfası, sürükle-bırak alanı, indirilebilir CSV şablonları, satır bazlı uyarı raporu (`admin/views/import.php`)
- [x] Birim testleri: CSV + gerçek XLSX ayrıştırma doğrulandı

### Kategorili Yoklama Sistemi
- [x] **Yoklama kategorileri + oturumları**: `wp_sms_att_categories` + `wp_sms_att_sessions`.
  Varsayılanlar: Ders (derslik bazlı), Namaz (5 vakit: sabah/öğle/ikindi/akşam/yatsı), Temizlik, Telefon.
- [x] Yönetici **yeni kategori/oturum ekleyebilir** ("Yoklama Türleri" sayfası); Namaz gibi bir kategori altına birden çok oturum
- [x] Yoklama tablosu genelleştirildi: `category_id + session_id + class_id(0=genel) + term_id`; eski kayıtlar Ders kategorisine taşınır (migrasyon)
- [x] **"Yoklama Al" modern kart arayüzü**: kategori kartı → (namaz) oturum kartları / (ders) derslik kartları → cetvel (`admin/views/attendance.php`)
- [x] **Sınıf öğretmeni statüsü**: branş öğretmeni yalnızca kendi dersliğinin (Ders) yoklamasını; sınıf öğretmeni genel yoklamaları (namaz/temizlik/telefon) alır. Sorumlu sınıf seviyeleri atanabilir (kullanıcı meta).
- [x] Raporda **namaz/genel yoklama katılımı** oturum bazlı gösterilir (hangi vakitte var/yok)

### Yoklama İş Kuralları
- **Ders (scope=class):** yönetici + o dersliğin branş öğretmeni.
- **Genel (scope=general):** yönetici + sınıf öğretmeni; öğrenci kapsamı sınıf öğretmeninin sorumlu seviyeleridir (boşsa tümü).
- Kayıt benzersizliği: (kategori, oturum, derslik, öğrenci, tarih) — genelde derslik=0.

## 8. Sürüm 1.2 — Analiz Raporları, Güvenli Not Yükleme, Arayüz Kilidi

### Raporlar (analiz merkezi) — `admin/views/reports.php`
- [x] **Yoklama Analizi**: kategori (Namaz vb.) + tarih aralığı + metrik (katılım/geldi/gelmedi/geç/izinli %) seçimiyle
      öğrenci × oturum matrisi. Namazda 5 vakit ayrı sütun; her hücrede yüzde + adet (12/15). "Toplu" satırı liste genelini verir.
- [x] Tüm analizlerde **gruplama**: öğrenci bazında (tek tek) veya sınıf bazında (seviye toplamları); sınıf filtresi.
- [x] **Alışkanlık Analizi**: öğrenci × alışkanlık tamamlama matrisi + sınıf ortalamaları.
- [x] **Not Analizi**: öğrenci × derslik ortalaması matrisi + sınıf ortalamaları.
- [x] **Genel Başarı**: bileşik skor sıralaması / sınıf bazında özet.
- [x] Bireysel karneler ayrı **Karneler** sayfasına taşındı (`admin/views/cards.php`); karne görünümü aynen korunur.
- [x] **CSV dışa aktarma**: her analiz tablosunda "CSV İndir" butonu — geçerli filtrelerle (kategori, tarih aralığı,
      metrik, gruplama, sınıf) birebir aynı tabloyu indirir. Nonce + yetki korumalı; öğretmen yalnızca kendi
      öğrencilerini dışa aktarabilir; hücreler Excel formül enjeksiyonuna karşı etkisizleştirilir.

### Güvenli Toplu Not Yükleme — `admin/views/grades.php`
- [x] 1. adım: sınav adı/tür/tarih/tam puan girilir → **önceden doldurulmuş öğrenci listesi (CSV)** indirilir
      (derslik_id, ogrenci_id, no, ad_soyad, sınav bilgileri dolu; yalnız *puan* boş).
- [x] 2. adım: doldurulan liste (.csv/.xlsx) yüklenir — dosya bağlamı taşıdığı için istenildiği zaman yüklenebilir.
- [x] Veri güvenliği: derslik yetkisi + kadro üyeliği + **ad-soyad eşleşmesi** (Türkçe I/İ katlamalı) + puan aralığı doğrulaması;
      uyuşmayan satırlar asla yazılmaz, satır satır raporlanır. Birim testleriyle doğrulandı.

### Arayüz / Erişim
- [x] **WP admin kilidi**: yönetici olmayan tüm kullanıcılar (öğretmen/veli/öğrenci) yalnızca `sms-*` sayfalarını görebilir;
      diğer wp-admin ekranları panele yönlendirilir, admin bar ve WP menüleri tamamen gizlenir, başlığa hesap çipi + çıkış eklendi.
- [x] **Tam genişlik tasarım**: sabit 1280px sınırı kaldırıldı; ızgaralar akışkan (auto-fit), 2200px üzeri ortalanır; mobil iyileştirmeler.
- [x] **Dashboard detayları**: sınıf bazında özet tablosu + yoklama türü bazında katılım tablosu (detaylı analize bağlantılı).

### Sürüm 1.2.1 — Notlar Sayfası Hiyerarşisi + Arayüz Rötuşları
- [x] **Notlar gezinmesi yeniden tasarlandı**:
  - Branş öğretmeni: kendi derslikleri (kart) → sınav listesi → sınav → öğrenci bazında puanlar.
  - Yönetici / sınıf öğretmeni: branş kartları → branşın derslikleri → sınavlar → öğrenci bazında puanlar (salt okunur; yönetim yetkisi yalnızca dersliğin branş öğretmeni + yönetici).
- [x] **"Not Gir" ekranı ayrıldı**: tek kartta sınav bilgileri + tek tek puan girişi + "Öğrenci Listesini İndir" (aynı formdaki bilgilerle önceden doldurulmuş CSV) + doldurulmuş liste yükleme.
- [x] Üst çubuk (admin bar) sınırlı kullanıcılar için tamamen kaldırıldı; **sol menü görünür kalır**, sağ üstte profil çipi (avatar + ad + rol + çıkış). WP bildirimleri (notice/update-nag) da gizlendi.

### Güvenlik Notları (kişisel veri)
- Tüm formlar nonce + capability; tüm okuma/yazma yolları **kayıt düzeyinde** denetlenir
  (öğretmen yalnızca kendi öğrencileri/derslikleri, veli yalnızca kendi çocuğu).
- CSV indirmeleri nonce'lu ve `nocache_headers()` ile sunulur; not şablonu yalnızca derslik yetkisi olana verilir.
- Yüklenen dosyalar sunucuda saklanmaz; yalnızca geçici dosyadan okunur, uzantı beyaz listesi uygulanır.
- Toplu not girişinde kimlik `ogrenci_id` + ad-soyad çifte doğrulamasıyla teyit edilir.

## 6. Puanlama / "En İyi Öğrenciler" Formülü

Bileşik skor, mevcut bileşenlerin ağırlıklı ortalamasıdır (bir bileşen için veri yoksa ağırlık diğerlerine dağıtılır):

```
skor = 0.40 × devam% + 0.40 × alışkanlık_tamamlama% + 0.20 × not_ortalaması%
devam%      = (geldi + 0.5 × geç) / toplam_yoklama
alışkanlık% = binary: yaptı/toplam ; dereceli: değer/ölçek ortalaması
not%        = puan/maks_puan ortalaması
```

Dashboard'da en yüksek skorlular **"Ayın Yıldızları"**, en düşükler **"Destek Bekleyenler"**
başlığıyla (olumsuz etiket kullanılmadan) gösterilir.
