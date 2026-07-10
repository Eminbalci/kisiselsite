# Yetkilendirme ve Güvenlik Dokümantasyonu (Authentication & Security)

Bu doküman, kişisel portföy web sitesinin yönetim paneli ve genel güvenliğini sağlayan mekanizmaları, fonksiyon detaylarını, parametreleri, dönüş değerlerini ve olası hata/sınır durumlarını (edge cases) içerir.

---

## 🔒 Oturum Güvenliği ve Yetkilendirme (`includes/auth.php`)

### 1. Oturum Başlatma ve Çerez Yapılandırması
PHP oturumu başlatılmadan önce çerez parametreleri güvenli hale getirilir:
* **`session.cookie_httponly = 1`**: Çerezlerin Javascript (XSS) ile okunmasını engeller.
* **`session.use_only_cookies = 1`**: Oturum kimliklerinin (Session ID) sadece çerezler üzerinden iletilmesini zorunlu kılar.
* **`secure`**: HTTPS bağlantısı varsa `true` olarak ayarlanır, böylece çerezler sadece şifreli kanallardan gönderilir.
* **`samesite = 'Strict'`**: CSRF (Cross-Site Request Forgery) saldırılarını tamamen engellemek için tarayıcılar arası isteklerde çerez gönderimini kısıtlar.

---

### 2. Oturum Güvenliği Fonksiyonları

#### `is_logged_in()`
Kullanıcının sisteme giriş yapıp yapmadığını doğrular.

* **Parametreler:** Yok.
* **Dönüş Değeri (`bool`):**
  * `true`: Kullanıcı geçerli bir admin oturumuna sahip.
  * `false`: Oturum açılmamış.
* **Sınır Durumlar (Edge Cases):**
  * Oturum parametreleri bozulmuş veya temizlenmişse doğrudan `false` döner.

#### `require_login()`
Yönetim panelindeki sayfalara yetkisiz erişimi engeller. Kullanıcı giriş yapmamışsa otomatik olarak giriş sayfasına yönlendirir.

* **Parametreler:** Yok.
* **Dönüş Değeri (`void`):** Değer döndürmez, yönlendirme yaparak betik çalışmasını sonlandırır (`exit`).
* **Sınır Durumlar (Edge Cases):**
  * Eğer `login.php` gibi bir sayfada çağrılırsa sonsuz yönlendirme döngüsüne neden olabilir. Bu yüzden sadece yetkilendirme gerektiren sayfalarda çağrılmalıdır.

---

### 3. Session Hijacking (Oturum Çalma) Koruması
Oturum açıldığında, kullanıcının IP adresi ve User Agent bilgisi birleştirilerek MD5 özeti (`$_SESSION['user_fingerprint']`) oluşturulur.
* Her istekte bu özet yeniden hesaplanır ve mevcut oturumdaki parmak iziyle karşılaştırılır.
* **Eşleşmeme Durumunda:** Oturum yok edilir (`session_destroy`), kullanıcı `login.php?error=session_invalid` adresine yönlendirilir.

---

### 4. Oturum Zaman Aşımı (Session Expiration)
Kullanıcının son aktivite zamanı `$_SESSION['last_activity']` değişkeninde saklanır.
* **Limit:** 30 Dakika (1800 Saniye) boyunca herhangi bir istek atılmazsa oturum otomatik olarak sonlandırılır.
* Kullanıcı `login.php?error=session_timeout` adresine yönlendirilir.

---

## 🛡️ CSRF Koruması (Cross-Site Request Forgery)

#### `generate_csrf_token()`
Oturuma özel benzersiz, rastgele bir CSRF doğrulama anahtarı üretir.

* **Parametreler:** Yok.
* **Dönüş Değeri (`string`):** 64 karakterli, hexadecimal CSRF tokenı.
* **Sınır Durumlar (Edge Cases):**
  * `random_bytes` fonksiyonunun sistemde bulunmaması veya güvenli rastgele veri üretememesi durumunda PHP `Exception` fırlatır.

#### `verify_csrf_token($token)`
Form gönderimlerinde gelen CSRF tokenını oturumdaki token ile karşılaştırır.

* **Parametreler:**
  * `$token` (`string`): İstekle birlikte gönderilen token değeri.
* **Dönüş Değeri (`bool`):**
  * `true`: Tokenlar eşleşiyor ve istek güvenli.
  * `false`: Tokenlar eşleşmiyor ya da oturumda token tanımlı değil.
* **Zaman Güvenli Karşılaştırma:** Karşılaştırma işlemi `hash_equals` fonksiyonu kullanılarak yapılır. Bu sayede "Timing Attack" (Zamanlama Saldırıları) engellenir.
