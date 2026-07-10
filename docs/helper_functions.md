# Yardımcı Fonksiyonlar Dokümantasyonu (Helper Functions)

Bu doküman, `includes/functions.php` dosyasındaki sistem genelinde kullanılan yardımcı (helper) fonksiyonları, bu fonksiyonların parametrelerini, geri dönüş tiplerini, çalışma mantıklarını ve sınır durumlarını belgeler.

---

## 🌐 Çoklu Dil ve Çeviri Fonksiyonları

### `get_lang()`
Kullanıcının aktif dil tercihini (TR/EN) döner ve oturuma kaydeder.

* **Parametreler:** Yok.
* **Dönüş Değeri (`string`):** Aktif dil kodu (`'tr'` veya `'en'`).
* **Çalışma Mantığı:** URL parametresinde `?lang=en` veya `?lang=tr` tanımlıysa oturum dilini günceller. Tanımlı değilse varsayılan olarak `'tr'` veya oturumdaki mevcut dili döner.

---

### `__($tr_text, $en_text)`
Aktif dile göre Türkçe veya İngilizce metni seçerek ekrana yazdırır.

* **Parametreler:**
  * `$tr_text` (`string`): Türkçe metin içeriği.
  * `$en_text` (`string`): İngilizce metin içeriği.
* **Dönüş Değeri (`string`):** Aktif dil `'en'` ise İngilizce metni (boş değilse), aksi halde Türkçe metni döner.

---

### `get_setting_lang($settings, $key)`
Dinamik ayarlar dizisi (`$settings`) içerisinden aktif dile uygun olan değeri döndürür.

* **Parametreler:**
  * `$settings` (`array`): Ayarlar anahtar-değer dizisi.
  * `$key` (`string`): İstenen ayarın Türkçe anahtarı (örn: `'about_text'`).
* **Dönüş Değeri (`string`):** Aktif dile uygun olan ayar değeri (örn: `'about_text_en'` veya `'about_text'`).

---

### `auto_translate($text, $source = 'tr', $target = 'en')`
Google Translate ücretsiz web API'sini kullanarak verilen Türkçe metni otomatik olarak İngilizceye çevirir.

* **Parametreler:**
  * `$text` (`string`): Çevrilecek metin.
  * `$source` (`string`): Kaynak dil kodu (Varsayılan: `'tr'`).
  * `$target` (`string`): Hedef dil kodu (Varsayılan: `'en'`).
* **Dönüş Değeri (`string`):** Çevrilmiş metin. Hata durumunda boş string (`''`) döner.
* **Çalışma Mantığı:**
  1. Öncelikle sistemde `cURL` kütüphanesinin aktif olup olmadığını kontrol eder. Aktif ise cURL isteği atar.
  2. cURL aktif değilse, `allow_url_fopen` seçeneğinin açık olması şartıyla `file_get_contents` üzerinden veri çeker.
  3. Alınan JSON yanıtını çözümler ve tüm segmentleri birleştirerek çevrilmiş metni oluşturur.
* **Edge Cases (Sınır Durumlar):**
  * Çevrimiçi API erişimi olmadığında veya Google Translate geçici olarak istekleri engellediğinde boş değer döner.
  * Çok uzun metinlerde API sorgu limiti veya URL karakter limiti aşılabilir.

---

## 📈 İstatistik Sayaçları

### `log_page_view($pdo)`
Günlük tekil ziyaretçi ve toplam sayfa görüntülenme istatistiklerini SQLite/MySQL veritabanına kaydeder.

* **Parametreler:**
  * `$pdo` (`PDO`): Aktif veritabanı bağlantı nesnesi.
* **Dönüş Değeri (`void`):** Değer döndürmez.
* **Çalışma Mantığı:**
  * Ziyaretçinin o günkü ilk ziyareti ise `$_SESSION['visited_today_YYYY-MM-DD']` değeri tanımlanarak `unique_visitors` sayacı 1 artırılır.
  * Ziyaretçinin sonraki sayfa geçişlerinde sadece `page_views` sayacı artırılmaya devam eder.

---

## 🔒 Güvenli Dosya ve Görsel Yükleme

### `sanitize_filename($filename)`
Dosya adlarındaki Türkçe karakterleri İngilizceye çevirir ve zararlı olabilecek özel karakterleri temizleyerek dosya adını güvenli hale getirir.

* **Parametreler:**
  * `$filename` (`string`): Orijinal dosya adı (uzantısı dahil).
* **Dönüş Değeri (`string`):** Temizlenmiş, web uyumlu dosya adı.
* **Edge Cases (Sınır Durumlar):**
  * Dosya adı tamamen geçersiz karakterlerden oluşuyorsa rastgele bir isim üretir (örn: `file_4b8f1a2c.jpg`).

---

### `get_unique_filename($target_dir, $filename)`
Hedef klasörde aynı isimde bir dosya varsa, dosya adının sonuna sayı ekleyerek benzersiz bir dosya adı üretir (örn: `resim-1.jpg`).

* **Parametreler:**
  * `$target_dir` (`string`): Dosyanın yükleneceği klasör yolu.
  * `$filename` (`string`): Güvenli hale getirilmek istenen dosya adı.
* **Dönüş Değeri (`string`):** Çakışmayan, benzersiz dosya adı.

---

### `handle_image_upload($file, $target_dir = '../uploads/')`
Yüklenen görselleri MIME tipi doğrulaması yaparak güvenli bir şekilde sunucuya kaydeder.

* **Parametreler:**
  * `$file` (`array`): `$_FILES['input_name']` dizisi.
  * `$target_dir` (`string`): Yüklenecek hedef klasör (Varsayılan: `'../uploads/'`).
* **Dönüş Değeri (`string`):** Yüklenen yeni dosyanın adı.
* **İstisnalar (Exceptions):**
  * Yükleme sırasında hata oluşursa (`UPLOAD_ERR_OK` değilse), MIME tipi desteklenmeyen bir biçimse veya dosya taşınamazsa `Exception` fırlatır.
* **Güvenlik Detayı:** Sadece `JPG`, `PNG`, `GIF` ve `WEBP` formatlarına izin verilir. MIME tipi tespiti için öncelikle tarayıcıya güvenmeyen `finfo` kütüphanesi kullanılır.

---

### `handle_file_upload($file, $target_dir = '../uploads/')`
Zararlı kod yürütülmesini engellemek için tehlikeli uzantıları engelleyerek genel dosya yüklemelerini yönetir.

* **Parametreler:**
  * `$file` (`array`): `$_FILES['input_name']` dizisi.
  * `$target_dir` (`string`): Yüklenecek hedef klasör.
* **Dönüş Değeri (`string`):** Yüklenen yeni dosyanın adı.
* **Güvenlik Detayı:** `.php`, `.phtml`, `.cgi`, `.pl`, `.py`, `.bat`, `.sh`, `.cmd`, `.js` ve `.htaccess` gibi sunucuda kod çalıştırabilecek tüm tehlikeli uzantılar **kesinlikle engellenir**.

---

### `handle_favicon_upload($file, $target_dir = '../uploads/')`
Site favicon yükleme işlemlerini yönetir. Mevcut tüm favicon dosyalarını temizleyerek çakışmayı önler.

* **Parametreler:**
  * `$file` (`array`): `$_FILES['favicon']` dizisi.
* **Dönüş Değeri (`string`):** `'favicon.ico'` vb. yeni favicon adı.

---

## 📝 Metin ve Renk Yardımcıları

### `slugify($text)`
Bir başlık metnini URL dostu benzersiz bir slug haline getirir (örn: "Yeni Blog Yazısı" -> "yeni-blog-yazisi").

* **Parametreler:**
  * `$text` (`string`): Sluglaştırılacak metin.
* **Dönüş Değeri (`string`):** URL uyumlu küçük harfli metin.

---

### `hex2rgba($hex, $opacity)`
Hex renk kodunu (örn: `#2563eb`) CSS uyumlu `rgba(r, g, b, a)` formatına dönüştürür.

* **Parametreler:**
  * `$hex` (`string`): Hex renk kodu.
  * `$opacity` (`float`): Opaklık derecesi (`0.0` - `1.0` arası).
* **Dönüş Değeri (`string`):** `rgba(...)` CSS renk kodu.
* **Edge Cases (Sınır Durumlar):**
  * Hem 3 karakterli (örn: `#fff`) hem de 6 karakterli (örn: `#ffffff`) hex kodlarını destekler.
