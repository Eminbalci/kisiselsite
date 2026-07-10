# Veritabanı Mimarisi Dokümantasyonu (Database Architecture)

Bu doküman, kişisel portföy web sitesinin çift katmanlı veritabanı mimarisini (SQLite & MySQL), otomatik yedekleme/geçi mekanizmalarını (fallback), veri tablolarını ve tablo göç (migration) sistemini açıklar.

---

## 💾 Çift Katmanlı Veritabanı Yönetimi

Web sitesi, hem **SQLite3** hem de **MySQL** sürücülerini destekleyen dinamik bir mimariye sahiptir.

### 1. Yapılandırma (`includes/config.php`)
Sistemin hangi veritabanını kullanacağı `includes/config.php` dosyasında saklanır:
```php
return [
    'db_type' => 'sqlite', // 'sqlite' veya 'mysql'
    'mysql_host' => 'localhost',
    'mysql_db' => 'kisiselsite',
    'mysql_user' => 'root',
    'mysql_pass' => '',
];
```

### 2. Geri Çekilme (Fallback) Mekanizması
* Sistem öncelikle `config.php` içindeki `db_type` değerini kontrol eder.
* Eğer `mysql` seçilmişse, MySQL sunucusuna **3 saniyelik bir zaman aşımı sınırı** ile bağlanmaya çalışır.
* **MySQL Bağlantısı Başarısız Olursa veya SQLite Seçilmişse:** Sistem otomatik olarak yerel SQLite veritabanına (`data/site.db`) geri çekilir (fallback).
* MySQL bağlantısının kesilerek SQLite'a geçilmesi durumunda, admin paneline girildiğinde kullanıcıya bir uyarı mesajı gösterilir (`$_SESSION['db_fallback_warning']`).

---

## 📊 Tablo Yapıları (Schemas)

Sistemde kullanılan tüm tablolar sürücü bağımsızdır (MySQL için `AUTO_INCREMENT`, SQLite için `AUTOINCREMENT` otomatik algılanır).

### 1. `users` (Yöneticiler)
* `id`: Birincil Anahtar
* `username`: Kullanıcı adı (`VARCHAR(191) UNIQUE`)
* `password_hash`: BCRYPT ile şifrelenmiş parola (`TEXT`)
* `created_at`: Kayıt tarihi (`DATETIME`)

### 2. `settings` (Genel Ayarlar)
* `setting_key`: Benzersiz ayar anahtarı (`VARCHAR(191) PRIMARY KEY`)
* `setting_value`: Ayar değeri (`TEXT`)

### 3. `skills` (Yetenekler)
* `id`: Birincil Anahtar
* `name`: Yetenek adı
* `name_en`: Yetenek İngilizce adı
* `percentage`: Yetenek yüzdesi (`INTEGER`)
* `category`: Kategorisi (`TEXT`)

### 4. `skill_categories` (Yetenek Kategorileri)
* `id`: Birincil Anahtar
* `name`: Kategori adı (`VARCHAR(191) UNIQUE`)
* `name_en`: İngilizce kategori adı

### 5. `portfolio` (Projeler)
* `id`: Birincil Anahtar
* `title`: Proje başlığı
* `title_en`: İngilizce başlık
* `slug`: URL dostu benzersiz kelime (`VARCHAR(191) UNIQUE`)
* `description`: Kısa açıklama
* `description_en`: İngilizce kısa açıklama
* `content`: Detaylı proje içeriği (HTML/Markdown)
* `content_en`: İngilizce detaylı açıklama
* `image_path`: Ana görsel yolu
* `file_path`: İndirilebilir proje dosyası yolu (varsa)
* `project_link`: Canlı demo bağlantısı
* `views`: Proje görüntülenme sayısı (`INTEGER`)
* `category`: Proje kategorisi (`TEXT`)
* `date_added`: Eklenme tarihi

### 6. `blog` (Blog Yazıları)
* `id`: Birincil Anahtar
* `title`: Başlık
* `title_en`: İngilizce başlık
* `slug`: URL dostu benzersiz kelime (`VARCHAR(191) UNIQUE`)
* `content`: İçerik
* `content_en`: İngilizce içerik
* `image_path`: Kapak görseli yolu
* `views`: Okunma sayısı
* `date_added`: Yayınlanma tarihi

### 7. `blog_comments` (Yorumlar)
* `id`: Birincil Anahtar
* `post_id`: İlişkili blog yazısı ID'si
* `name`: Yorum yapan kişinin adı
* `email`: E-posta adresi
* `comment`: Yorum metni
* `is_approved`: Onay durumu (`0` veya `1`)
* `date_added`: Gönderilme tarihi

### 8. `references` (Özgeçmiş Referansları)
* `id`: Birincil Anahtar
* `name`: Referans kişi adı
* `title`: Unvanı
* `title_en`: İngilizce unvanı
* `company`: Şirket
* `contact_info`: İletişim bilgisi (Telefon/E-posta)
* `display_order`: Sıralama sırası (`INTEGER`)
* `date_added`: Eklenme tarihi

### 9. `timeline` (Eğitim & Deneyim)
* `id`: Birincil Anahtar
* `type`: Tür (`education` veya `experience`)
* `title`: Başlık / Unvan
* `title_en`: İngilizce başlık / Unvan
* `institution`: Kurum / Okul
* `institution_en`: İngilizce kurum adı
* `date_range`: Tarih aralığı (örn: "2020 - 2024")
* `description`: Detaylar
* `description_en`: İngilizce detaylar
* `display_order`: Sıralama sırası

### 10. `certificates` (Sertifikalar)
* `id`: Birincil Anahtar
* `title`: Sertifika adı
* `title_en`: İngilizce sertifika adı
* `issuer`: Veren kurum
* `date_issued`: Alınma tarihi
* `image_path`: Sertifika görsel/dosya yolu
* `link`: Doğrulama linki

### 11. `messages` (İletişim Formu Mesajları)
* `id`: Birincil Anahtar
* `name`: Gönderen adı
* `email`: Gönderen e-postası
* `message`: İletişim mesajı
* `is_read`: Okunma durumu (`0` veya `1`)
* `date_sent`: Gönderilme tarihi

### 12. `analytics` (İstatistik Sayaçları)
* `id`: Birincil Anahtar
* `visit_date`: Ziyaret tarihi (`DATE UNIQUE`)
* `page_views`: Sayfa görüntülenme sayısı
* `unique_visitors`: Tekil ziyaretçi sayısı

---

## 🚀 Dinamik Sütun Göçü (Migration)

Tablolarda yapılan değişikliklerin sunucuda elle SQL çalıştırmaya gerek kalmadan uygulanabilmesi için `includes/db.php` dosyası çalıştırıldığında çalışan dinamik bir kolon ekleme fonksiyonu mevcuttur:

```php
$addColumn = function($table, $column, $definition) use ($pdo, $driver) {
    if ($driver === 'mysql') {
        $clean_table = str_replace('`', '', $table);
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$clean_table` LIKE :column");
        $stmt->execute(['column' => $column]);
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `$clean_table` ADD COLUMN `$column` $definition");
        }
    } else {
        $stmt = $pdo->prepare("PRAGMA table_info($table)");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($column, $columns)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        }
    }
};
```

* **Çalışma Şekli:**
  * MySQL üzerinde ise `SHOW COLUMNS` sorgusu ile kolonun varlığı kontrol edilir.
  * SQLite üzerinde ise `PRAGMA table_info` kullanılarak kolon listesi çekilir ve kontrol edilir.
  * Eğer kolon tabloda yoksa, `ALTER TABLE ... ADD COLUMN` sorgusu otomatik tetiklenerek veriler kaybolmadan yapı güncellenir.
