# 🚀 PHP & SQLite Personal Portfolio

Modern, dinamik ve yönetim paneline sahip, çoklu dil (TR/EN) ve aydınlık/karanlık tema destekli tam donanımlı kişisel portföy web sitesi.

---

## 🌟 Öne Çıkan Özellikler

- **🎨 Modern ve Premium Tasarım:** Glassmorphism etkileri, yumuşak geçişli animasyonlar, özel fontlar (Outfit) ve modern tipografi.
- **🌗 Aydınlık & Karanlık Tema:** Kullanıcı tercihine göre anında değişebilen, admin panelinden özel renk paletleri (Primary/Secondary) ayarlanabilen modern tema sistemi.
- **🌐 Çoklu Dil Desteği (TR/EN):** Google Çeviri API entegrasyonu sayesinde admin panelinden girilen Türkçe içerikler otomatik olarak İngilizceye çevrilir (Manuel çeviri de yapılabilir).
- **📄 Yazdırılabilir CV Desteği:** Özelleştirilmiş ve doğrudan PDF olarak kaydedilebilir/yazdırılabilir TR/EN CV şablonu (`cv.php`).
- **📸 Resimlerde Lazy Loading:** Web performansını artırmak amacıyla görsellerin tembel yüklenmesi (lazy loading) desteği.
- **🛠️ Yönetim Paneli (Admin Dashboard):** Sitedeki tüm içerikleri kod bilgisi olmadan yönetme imkanı:
  - **Genel Ayarlar:** Site başlığı, sosyal medya linkleri, logo yazıları, profil fotoğrafı, favicon yönetimi ve yönetici bilgileri.
  - **Yetenekler:** Yüzdelik oranlar, kategoriler ve dil bazlı isimler.
  - **Portföy/Projeler:** Görsel, kategori, detaylı TR/EN açıklama ve GitHub/Canlı linkleri.
  - **Blog Yönetimi:** Blog gönderileri ekleme, kapak görseli yükleme, TR/EN metin desteği ve ziyaretçi sayaçları.
  - **Yorum Yönetimi:** Blog yazılarına gelen ziyaretçi yorumlarını onaylama, yayından kaldırma ve silme işlemleri.
  - **Zaman Çizelgesi:** Eğitim ve İş deneyimleri (Sıralama ve tarih aralıkları TR/EN yönetimi).
  - **Sertifikalar:** Doğrulama linkleri, veren kuruluşlar ve görsel yükleme.
  - **Referans Yönetimi:** Sadece CV sayfasında görünür referans bilgileri ekleme, düzenleme ve sıralama.
  - **Gelen Kutusu:** İletişim formundan gelen mesajları okuma, filtreleme ve yönetme.
- **📈 İstatistikler ve Analitik:** SQLite üzerinde çalışan entegre sayfa görüntülenmesi ve günlük tekil ziyaretçi sayacı.
- **🔒 Güvenlik:** 
  - CSRF (Cross-Site Request Forgery) koruması.
  - Güvenli şifreleme (`password_hash`).
  - PDO Prepared Statements ile SQL Injection koruması.
  - Güvenli dosya yükleme kontrolleri (MIME/Uzantı doğrulama).

---

## 🚀 Kurulum ve Çalıştırma

Bu proje **PHP (8.0+)** ve **SQLite3** kullanılarak geliştirilmiştir. Harici bir MySQL sunucusuna ihtiyaç duymaz, veritabanı doğrudan `data/site.db` dosyası içinde güvenli bir şekilde tutulur.

### Gereksinimler
- PHP 8.0 veya üzeri
- PHP eklentileri: `pdo_sqlite`, `sqlite3`, `curl` (otomatik çeviri için), `openssl` (otomatik çeviri için)

### Adımlar

1. Projeyi bilgisayarınıza indirin veya klonlayın:
   ```bash
   git clone https://github.com/Eminbalci/kisiselsite.git
   ```
2. Proje dizinine gidin:
   ```bash
   cd kisiselsite
   ```
3. Yerel PHP sunucusunu başlatın:
   ```bash
   php -S localhost:8000
   ```
4. Tarayıcınızda [http://localhost:8000](http://localhost:8000) adresine giderek siteyi görüntüleyin.
5. Yönetim paneline girmek için [http://localhost:8000/admin](http://localhost:8000/admin) adresini ziyaret edin:
   - Varsayılan Admin Kullanıcı Adı: `admin`
   - Varsayılan Admin Şifresi: `123456`
   *(Güvenliğiniz için giriş yaptıktan sonra şifrenizi Ayarlar sayfasından değiştirmeniz önerilir.)*

---

## 🌍 Canlı Sunucuya (cPanel / Apache vb.) Yükleme (Deployment)

Projeyi cPanel veya benzeri bir hosting panelinde yayınlamak oldukça kolaydır:

1. Proje dosyalarının tamamını bilgisayarınızda bir **.zip** dosyası haline getirin (Git dosyalarını veya yedekleri dahil etmenize gerek yoktur).
2. Hosting hesabınıza (cPanel) giriş yapın ve **Dosya Yöneticisi (File Manager)**'ni açın.
3. Sitenizin yayınlanacağı ana dizine (genellikle `public_html`) girin ve `.zip` dosyasını yükleyip klasöre çıkartın (Extract).
4. **Klasör ve Veritabanı İzinleri (ÖNEMLİ):**
   SQLite veritabanının düzgün çalışabilmesi ve veri yazabilmesi için şu izinleri (Permissions) ayarlayın:
   - `data` klasörünün izni **755** olmalıdır.
   - `data/site.db` dosyasının izni **664** veya **666** olmalıdır.
   - `uploads` klasörünün izni resim yüklenebilmesi için **755** olmalıdır.
5. **PHP Modülleri:**
   cPanel'de **Select PHP Version** alanından PHP sürümünün **8.0 veya üzeri** olduğundan ve şu modüllerin aktif olduğundan emin olun: `pdo_sqlite`, `sqlite3`, `curl`, `openssl`.

---

## 📁 Dizin Yapısı

- `/admin/` - Yönetim paneli dosyaları (Dashboard, CRUD işlemleri, yorum ve referans yönetimi).
- `/api/` - İletişim formu için AJAX tabanlı JSON endpointi.
- `/data/` - SQLite veritabanı dosyasının (`site.db`) bulunduğu klasör. Güvenlik gereği `.htaccess` ile dışarıdan doğrudan erişime kapatılmıştır.
- `/includes/` - Veritabanı bağlantısı, dil dosyaları, yetkilendirme (Auth) ve genel yardımcı fonksiyonların barındığı alan.
- `/uploads/` - Blog kapakları, sertifikalar, projeler ve profil resimlerinin yüklendiği klasör.
- `index.php` - Ana portföy sayfası.
- `cv.php` - Yazdırılabilir / PDF formatında CV sayfası.
- `style.css` - Sitenin genel CSS kodları.
- `app.js` - Animasyonlar, tema geçişleri ve AJAX isteklerinin yönetildiği JavaScript kodları.

---

## 📄 Lisans
Bu proje açık kaynak kodlu olarak sunulmuştur, ancak kodu kullanırken veya kendi projelerinize uyarlarken **orijinal yazar (Muhammet Emin Balcıoğlu)** olarak beni referans göstermek/kaynak bildirmek **zorunludur**. Kaynak göstermek şartıyla kod üzerinde dilediğiniz gibi değişiklik yapabilir ve kullanabilirsiniz.
