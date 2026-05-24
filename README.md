# PHP & SQLite Personal Portfolio

Modern, dinamik ve yönetim paneline sahip, çoklu dil (TR/EN) ve aydınlık/karanlık tema destekli tam donanımlı kişisel portföy web sitesi.

## 🌟 Özellikler

- **🎨 Modern ve Premium Tasarım:** Glassmorphism etkileri, yumuşak geçişli animasyonlar ve modern tipografi.
- **🌗 Aydınlık & Karanlık Tema:** Kullanıcı tercihine göre anında değişebilen, admin panelinden özel renk paletleri (Primary/Secondary) ayarlanabilen tema sistemi.
- **🌐 Çoklu Dil Desteği (TR/EN):** Google Çeviri API entegrasyonu sayesinde admin panelinden girilen Türkçe içerikler otomatik olarak İngilizceye çevrilir (Manuel çeviri de eklenebilir).
- **🛠️ Yönetim Paneli (Admin Dashboard):** Sitedeki tüm içerikleri kod bilgisi olmadan yönetme imkanı.
  - Genel Ayarlar (Site başlığı, sosyal medya linkleri, tema renkleri, özgeçmiş)
  - Yetenekler (Yüzdelik oranlar ve kategoriler)
  - Portföy/Projeler (Görsel ve link ekleyebilme)
  - Blog Yönetimi (Ziyaret sayısı takibi ve zengin metin okuma)
  - Zaman Çizelgesi (Eğitim ve İş deneyimleri)
  - Sertifikalar (Doğrulama linkleri ve görsel yükleme)
  - Gelen Kutusu (İletişim formundan gelen mesajları okuma ve yanıtlama)
- **📈 İstatistikler:** SQLite üzerinde sayfa görüntülenmesi ve ziyaretçi sayacı.
- **🔒 Güvenlik:** CSRF koruması, güvenli şifreleme (password_hash) ve PDO Prepared Statements ile SQL Injection koruması.

## 🚀 Kurulum

Bu proje **PHP (8.0+)** ve **SQLite3** kullanılarak geliştirilmiştir. Harici bir MySQL sunucusuna ihtiyaç duymaz, veritabanı doğrudan `data/site.db` dosyası içinde tutulur.

### Gereksinimler
- PHP 8.0 veya üzeri
- PHP eklentileri: `pdo_sqlite`, `sqlite3`, `curl` (otomatik çeviri için), `openssl` (otomatik çeviri için)

### Adımlar

1. Projeyi bilgisayarınıza indirin veya klonlayın.
2. Terminali (Komut İstemi / PowerShell) açın ve proje dizinine gidin.
3. Yerel PHP sunucusunu başlatın:
   ```bash
   php -S localhost:8000
   ```
4. Tarayıcınızda [http://localhost:8000](http://localhost:8000) adresine giderek siteyi görüntüleyin.
5. Yönetim paneline girmek için [http://localhost:8000/admin](http://localhost:8000/admin) adresini ziyaret edin.
   - Varsayılan Admin Kullanıcı Adı: `admin`
   - Varsayılan Admin Şifresi: `123456`
   *(Güvenliğiniz için giriş yaptıktan sonra şifrenizi Ayarlar sayfasından veya veritabanından değiştirmeniz önerilir.)*

## 📁 Dizin Yapısı

- `/admin/` - Yönetim paneli dosyaları (Oturum yönetimi, CRUD işlemleri).
- `/api/` - İletişim formu için AJAX tabanlı JSON endpointi.
- `/data/` - SQLite veritabanı dosyasının (site.db) bulunduğu klasör. Güvenlik gereği `.htaccess` ile dışarıdan erişime kapatılmıştır.
- `/includes/` - Veritabanı bağlantısı, auth işlemleri ve genel fonksiyonların barındığı alan.
- `/uploads/` - Blog kapakları, sertifikalar ve proje resimlerinin yüklendiği klasör.
- `index.php` - Ana portföy sayfası.
- `style.css` - Sitenin genel CSS kodları.
- `app.js` - Animasyonlar, tema geçişleri ve AJAX isteklerinin yönetildiği JavaScript kodları.

## 📄 Lisans
Bu proje açık kaynak kodlu olarak sunulmuştur, ancak kodu kullanırken veya kendi projelerinize uyarlarken **orijinal yazar (Muhammet Emin Balcıoğlu)** olarak beni referans göstermek/kaynak bildirmek **zorunludur**. Kaynak göstermek şartıyla kod üzerinde dilediğiniz gibi değişiklik yapabilir ve kullanabilirsiniz.
