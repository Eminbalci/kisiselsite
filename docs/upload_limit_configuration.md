# Dosya Yükleme Limitleri Yapılandırması (File Upload Limits Configuration)

Bu dokümanda, portföy web sitesi üzerindeki dosya yükleme limitlerinin (maksimum yükleme boyutu, bellek limitleri ve zaman aşımları) nasıl yapılandırıldığı ve sunucu ortamlarına göre nasıl uyarlanacağı açıklanmaktadır.

---

## 📌 Yapılandırma Detayları

Dosya yüklemelerinde varsayılan PHP limitlerinin (genellikle `2M` veya `8M`) aşılması durumunda oluşan yükleme hatalarını önlemek amacıyla aşağıdaki limitler tanımlanmıştır:

| Parametre | Değer | Açıklama |
| :--- | :--- | :--- |
| `upload_max_filesize` | `1000M` | Yüklenebilecek maksimum tekil dosya boyutu. |
| `post_max_size` | `1000M` | Bir POST isteğinde gönderilebilecek toplam maksimum veri boyutu. |
| `memory_limit` | `1024M` | PHP betiğinin kullanabileceği maksimum bellek miktarı. |
| `max_execution_time` | `3600` | Betiğin maksimum çalışma süresi (saniye). |
| `max_input_time` | `3600` | Girdi verilerini çözümleme için maksimum süre (saniye). |
| `default_socket_timeout`| `3600` | Soket bağlantıları için varsayılan zaman aşımı süresi. |

---

## 🛠️ Sunucu Ortamına Göre Yapılandırma Dosyaları

Sunucu türleri ve PHP çalışma modlarının (mod_php, CGI, FastCGI, PHP-FPM) farklılık göstermesi sebebiyle, yapılandırma 4 farklı düzeyde uygulanmıştır:

### 1. Apache mod_php için `.htaccess`
Eğer sunucunuzda Apache Web Server ve mod_php kullanılıyorsa, ayarlar ana dizindeki `.htaccess` dosyası üzerinden otomatik olarak uygulanır:
```apache
<IfModule mod_php7.c>
  php_value upload_max_filesize 1000M
  php_value post_max_size 1000M
  php_value memory_limit 1024M
  php_value max_execution_time 3600
  php_value max_input_time 3600
</IfModule>
<IfModule mod_php.c>
  php_value upload_max_filesize 1000M
  php_value post_max_size 1000M
  php_value memory_limit 1024M
  php_value max_execution_time 3600
  php_value max_input_time 3600
</IfModule>
```

### 2. Standart PHP için `php.ini`
Sunucunuzun ana dizininde (`public_html/php.ini`) ve admin paneli dizininde (`public_html/admin/php.ini`) yer alan bu dosya, CGI/FastCGI modlarında çalışırken ilgili dizindeki PHP işlemlerine limitleri uygular.

### 3. FPM / FastCGI için `.user.ini`
PHP-FPM kullanan modern sunucularda `.user.ini` dosyaları okunur. Admin dizininde yer alan `public_html/admin/.user.ini` dosyası, admin panelinde yapılan dosya yükleme işlemlerinde limitlerin geçerli olmasını sağlar.

---

## ⚠️ Dikkat Edilmesi Gerekenler ve Olası Sorunlar (Edge Cases)

* **`.user.ini` Önbellek Süresi:** Sunucular `.user.ini` dosyasındaki değişiklikleri anında algılamayabilir. PHP varsayılan olarak bu dosyaları 5 dakikada bir (300 saniye) kontrol eder. Değişikliklerin yansıması için birkaç dakika beklemeniz gerekebilir.
* **cPanel Arayüz Overwrite:** Bazı hosting firmaları `.htaccess` veya `.user.ini` üzerinden limit değiştirilmesini engeller. Bu durumda cPanel ana sayfanızdan **Select PHP Version** -> **Options** sekmesine giderek `upload_max_filesize` ve `post_max_size` değerlerini elle yükseltmeniz gerekmektedir.
* **Güvenlik / CSRF:** Dosya boyutu limitini aşan yüklemelerde PHP, tüm POST ve FILES verilerini sıfırlar. Bu durum, CSRF token doğrulamasının da başarısız olmasına sebep olabilir. Sistem bu durumu algılayarak kullanıcıya özel limit aşım uyarısı vermektedir.
