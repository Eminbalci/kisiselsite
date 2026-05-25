<?php
// admin_lang.php - Handles admin panel translations via output buffering

function translate_admin_html($buffer) {
    // 1. Inject the Language Switcher
    $lang = get_lang();
    $tr_color = $lang === 'tr' ? 'var(--brand-primary)' : 'var(--text-muted)';
    $en_color = $lang === 'en' ? 'var(--brand-primary)' : 'var(--text-muted)';
    
    $lang_switch = '<div class="lang-switch" style="margin-right:15px; display:inline-block;">
        <a href="?lang=tr" style="color: '.$tr_color.'; text-decoration:none; font-weight:bold;">TR</a> | 
        <a href="?lang=en" style="color: '.$en_color.'; text-decoration:none; font-weight:bold;">EN</a>
    </div>';
    
    // Insert before "<span>Hoş geldiniz" or similar user-info element
    $search = '<div class="user-info">';
    if (strpos($buffer, $search) !== false) {
        $buffer = str_replace($search, $search . "\n            " . $lang_switch, $buffer);
    } else {
        $search2 = '<div class="admin-user-info">';
        if (strpos($buffer, $search2) !== false) {
            $buffer = str_replace($search2, $search2 . "\n            " . $lang_switch, $buffer);
        }
    }
    
    // 2. Translate if English is selected
    if ($lang === 'en') {
        $replacements = [
            // Menu & Headers
            'Genel Ayarlar' => 'General Settings',
            'Yetenekler' => 'Skills',
            'Portföy' => 'Portfolio',
            'Zaman Çizelgesi' => 'Timeline',
            'Sertifikalar' => 'Certificates',
            'Referanslar' => 'References',
            'Gelen Kutusu' => 'Inbox',
            'Siteyi Görüntüle' => 'View Site',
            'CV İndir (TR)' => 'Download CV (TR)',
            'CV İndir (EN)' => 'Download CV (EN)',
            'Blog' => 'Blog',
            
            // Header
            'Hoş geldiniz,' => 'Welcome,',
            'Çıkış Yap' => 'Logout',
            'Yönetim Paneli |' => 'Admin Panel |',
            'Yönetim Paneli' => 'Admin Panel',
            'Portföy Admin' => 'Portfolio Admin',
            
            // Common Actions
            'Güncelle' => 'Update',
            'Ekle' => 'Add',
            'İptal Et' => 'Cancel',
            'İptal' => 'Cancel',
            'Sil' => 'Delete',
            'Düzenle' => 'Edit',
            'Kapat' => 'Close',
            'Kaydet' => 'Save',
            
            // Headings and Texts
            'Mevcut Yetenekler' => 'Current Skills',
            'Yeni Yetenek Ekle' => 'Add New Skill',
            'Yetenek Düzenle' => 'Edit Skill',
            'Yetenek Yönetimi' => 'Skill Management',
            'Sitenizde listelenecek yetenekleri ve seviyelerini buradan düzenleyebilirsiniz.' => 'You can manage the skills and their levels to be listed on your site here.',
            'Kategori Yönetimi' => 'Category Management',
            'Yeni Kategori Ekle' => 'Add New Category',
            'Mevcut Kategoriler' => 'Current Categories',
            'Portföy Yönetimi' => 'Portfolio Management',
            'Sitenizde sergilenecek projelerinizi buradan yönetebilirsiniz.' => 'You can manage the projects to be showcased on your site here.',
            'Blog Yönetimi' => 'Blog Management',
            'Sitenizde yayınlanacak blog yazılarını buradan yönetebilirsiniz.' => 'You can manage the blog posts to be published on your site here.',
            'Zaman Çizelgesi (Eğitim & Deneyim)' => 'Timeline (Education & Experience)',
            '(Eğitim & Deneyim)' => '(Education & Experience)',
            'Sitenizde listelenecek eğitim ve iş deneyimlerini buradan düzenleyebilirsiniz.' => 'You can manage the education and work experiences to be listed on your site here.',
            'Sertifikalar Yönetimi' => 'Certificate Management',
            'Sitenizde sergilenecek sertifikalarınızı buradan yönetebilirsiniz.' => 'You can manage the certificates to be showcased on your site here.',
            'Yeni Sertifika Ekle' => 'Add New Certificate',
            'Referanslar Yönetimi' => 'Reference Management',
            'Referanslar (Sadece CV\'de görünür)' => 'References (Visible only on CV)',
            'Buraya ekleyeceğiniz referanslar sitenin ana sayfasında görüntülenmez, sadece CV (PDF/Yazdırılabilir) sayfasında çıkar.' => 'References added here will not be displayed on the homepage, they will only appear on the CV (PDF/Printable) page.',
            'Mevcut Referanslar' => 'Current References',
            'Yeni Referans Ekle' => 'Add New Reference',
            'Referansı Düzenle' => 'Edit Reference',
            'Yeni Öğe Ekle' => 'Add New Item',
            'İletişim formundan gelen mesajları buradan görüntüleyebilirsiniz.' => 'You can view the messages from the contact form here.',
            'Henüz hiç mesajınız yok.' => 'You have no messages yet.',
            'Contact formundan gelen mesajları buradan görüntüleyebilirsiniz.' => 'You can view the messages from the contact form here.',
            
            // Missing Dashboard & Settings Strings
            'Mevcut Şifre (Değişiklikleri kaydetmek için gereklidir)' => 'Current Password (Required to save changes)',
            'Yeni Şifre (Değiştirmek istemiyorsanız boş bırakın)' => 'New Password (Leave empty if no change)',
            'Yeni Şifre (Tekrar)' => 'New Password (Repeat)',
            'Bilgileri Güncelle' => 'Update Information',
            'Sistem Bakımı & Yedekleme' => 'System Maintenance & Backup',
            'Web sitenizin SQLite veritabanı yedeğini tek tıkla indirebilirsiniz. Dosyayı yedekleyerek verilerinizi güvende tutun.' => 'You can download the SQLite database backup with one click. Keep your data safe by backing up the file.',
            'Veritabanını Yedekle (.db)' => 'Backup Database (.db)',
            'Dosya Seç' => 'Choose File',
            'Dosya seçilmedi' => 'No file chosen',
            'Yükle' => 'Upload',
            'Yok' => 'None',
            'Kullanıcı Adı' => 'Username',
            'Kullanıcı' => 'User',
            'Giriş Bilgilerini Güncelle' => 'Update Login Credentials',
            
            // Substring fixes (Add suffixes so they get translated as a whole before the root word)
            'Yetenek Adı' => 'Skill Name',
            'Proje Adı' => 'Project Name',
            'Kategori Adı' => 'Category Name',
            'Kategorisi' => 'Category',
            'Kategoriler' => 'Categories',
            'Açıklaması' => 'Description',
            'Sertifika Adı (TR)' => 'Certificate Name (TR)',
            'Sertifika Adı (EN)' => 'Certificate Name (EN)',
            'Veren Kurum' => 'Issuing Organization',
            'Doğrulama / Detay Linki (Opsiyonel)' => 'Verification / Detail Link (Optional)',
            'Sertifika Görseli (Opsiyonel)' => 'Certificate Image (Optional)',
            
            // Form Labels and Placeholders
            'Yetenek Adı (TR)' => 'Skill Name (TR)',
            'Yetenek Adı (EN)' => 'Skill Name (EN)',
            'Kategori Adı (EN) - İsteğe Bağlı' => 'Category Name (EN) - Optional',
            'Otomatik çevrilsin' => 'Auto translate',
            'Seviye (Yüzde %)' => 'Level (Percentage %)',
            'Kategori' => 'Category',
            'Yetenek' => 'Skill',
            'Yüzde' => 'Percentage',
            'Bir Kategori Seçin' => 'Select a Category',
            'Ad Soyad' => 'Full Name',
            'Ünvan / Meslek (TR)' => 'Title / Profession (TR)',
            'Ünvan / Meslek (EN)' => 'Title / Profession (EN)',
            'Kurum / Şirket' => 'Institution / Company',
            'Kurum' => 'Institution',
            'İletişim Bilgisi (E-posta veya Tel)' => 'Contact Info (Email or Phone)',
            'Sıralama' => 'Display Order',
            
            // Table Headers
            'YETENEK' => 'SKILL',
            'YÜZDE' => 'PERCENTAGE',
            'İŞLEMLER' => 'ACTIONS',
            'KATEGORİ' => 'CATEGORY',
            'Sıra' => 'Order',
            'Ünvan &amp; Şirket' => 'Title &amp; Company',
            'Ünvan & Şirket' => 'Title & Company',
            'İletişim' => 'Contact',
            'İşlemler' => 'Actions',
            'Başlık' => 'Title',
            'Tarih' => 'Date',
            'Durum' => 'Status',
            'Görüntülenme' => 'Views',
            'Görsel' => 'Image',
            'Sertifika' => 'Certificate',
            
            // Dashboard / Index Page
            'Genel Ayarlar &amp; İstatistikler' => 'General Settings &amp; Statistics',
            'Genel Ayarlar & İstatistikler' => 'General Settings & Statistics',
            'Sitenizin temel bilgilerini, ayarlarını ve anlık ziyaretçi istatistiklerini buradan yönetebilirsiniz.' => 'You can manage your site\'s basic information, settings, and real-time visitor statistics here.',
            'Bugün (Gösterim)' => 'Today (Views)',
            'Toplam Gösterim' => 'Total Views',
            'Okunmamış Mesajlar' => 'Unread Messages',
            'Kişisel Bilgiler &amp; Sosyal Bağlantılar' => 'Personal Info &amp; Social Links',
            'Kişisel Bilgiler & Sosyal Bağlantılar' => 'Personal Info & Social Links',
            'Profil Resmi Güncelle' => 'Update Profile Picture',
            'Görseller & İkon' => 'Images & Icons',
            'Site Başlığı' => 'Site Title',
            'Adınız Soyadınız' => 'Full Name',
            'Kısa Ünvan' => 'Short Title',
            'E-posta Adresiniz' => 'Email Address',
            'Hakkımda Metni (TR)' => 'About Text (TR)',
            'Hakkımda Metni (EN)' => 'About Text (EN)',
            'Logo Metni' => 'Logo Text',
            'Sol Üst Logo Text (Kaldırmak için boş bırakın)' => 'Top Left Logo Text (Leave empty to hide)',
            'Ünvanınız (TR)' => 'Your Title (TR)',
            'Ünvanınız (EN)' => 'Your Title (EN)',
            'Mevcut Logo/Profil Resmi:' => 'Current Logo/Profile Image:',
            'Yeni Profil Resmi Yükle (Logo olarak kullanılır)' => 'Upload New Profile Image (Used as Logo)',
            'Yeni Profil Resmi Seç (JPG, PNG, WEBP)' => 'Select New Profile Picture (JPG, PNG, WEBP)',
            'Favicon Güncelle' => 'Update Favicon',
            'Favicon Seç (ICO, PNG, SVG)' => 'Select Favicon (ICO, PNG, SVG)',
            'Mevcut İkon:' => 'Current Icon:',
            'Yeni Site İkonu (Favicon) Yükle' => 'Upload New Site Icon (Favicon)',
            'Tema Renkleri' => 'Theme Colors',
            'Ana Tema Rengi (Koyu Mod)' => 'Primary Theme Color (Dark Mode)',
            'İkincil Tema Rengi (Koyu Mod)' => 'Secondary Theme Color (Dark Mode)',
            'Ana Tema Rengi (Açık Mod)' => 'Primary Theme Color (Light Mode)',
            'İkincil Tema Rengi (Açık Mod)' => 'Secondary Theme Color (Light Mode)',
            'Karanlık Mod Birincil Renk' => 'Dark Mode Primary Color',
            'Karanlık Mod İkincil Renk' => 'Dark Mode Secondary Color',
            'Aydınlık Mod Birincil Renk' => 'Light Mode Primary Color',
            'Aydınlık Mod İkincil Renk' => 'Light Mode Secondary Color',
            
            // Messages Page
            'Gelen Mesajlar' => 'Inbox',
            'Ad' => 'Name',
            'E-posta' => 'Email',
            'Mesaj' => 'Message',
            'Okunmadı' => 'Unread',
            'Okundu İşaretle' => 'Mark as Read',
            'Henüz mesaj bulunmuyor.' => 'No messages yet.',
            
            // Portfolio
            'Yeni Proje Ekle' => 'Add New Project',
            'Mevcut Projeler' => 'Current Projects',
            'Proje Adı (TR)' => 'Project Name (TR)',
            'Proje Adı (EN)' => 'Project Name (EN)',
            'Kısa Açıklama (TR)' => 'Short Description (TR)',
            'Kısa Açıklama (EN)' => 'Short Description (EN)',
            'Proje İçeriği / Detay (TR)' => 'Project Content / Details (TR)',
            'Proje İçeriği / Detay (EN)' => 'Project Content / Details (EN)',
            'Proje Linki (Opsiyonel)' => 'Project Link (Optional)',
            'GitHub Linki (Opsiyonel)' => 'GitHub Link (Optional)',
            'Proje URL Sloganı / Link İsmi (Boş bırakırsanız başlığa göre otomatik oluşturulur)' => 'Project URL Slug (Generated automatically if empty)',
            'Teknolojiler (Virgülle ayırın)' => 'Technologies (Comma separated)',
            'Proje Görseli' => 'Project Image',
            
            // Blog
            'Yeni Yazı Ekle' => 'Add New Post',
            'Mevcut Yazılar' => 'Current Posts',
            'Yazı Başlığı (TR)' => 'Post Title (TR)',
            'Yazı Başlığı (EN)' => 'Post Title (EN)',
            'Özet (TR)' => 'Excerpt (TR)',
            'Özet (EN)' => 'Excerpt (EN)',
            'İçerik (TR)' => 'Content (TR)',
            'İçerik (EN)' => 'Content (EN)',
            'Etiketler (Virgülle ayırın)' => 'Tags (Comma separated)',
            'Kapak Görseli' => 'Cover Image',
            'Taslak' => 'Draft',
            'Yayında' => 'Published',
            
            // Timeline
            'Yeni Deneyim/Eğitim Ekle' => 'Add New Experience/Education',
            'Mevcut Zaman Çizelgesi' => 'Current Timeline',
            'Tür' => 'Type',
            'Eğitim' => 'Education',
            'Deneyim' => 'Experience',
            'Başlık (TR)' => 'Title (TR)',
            'Başlık (EN)' => 'Title (EN)',
            'Kurum (TR)' => 'Institution (TR)',
            'Kurum (EN)' => 'Institution (EN)',
            'Başlangıç Yılı' => 'Start Year',
            'Bitiş Yılı (Devam ediyorsa boş bırakın)' => 'End Year (Leave empty if ongoing)',
            'Açıklama (TR)' => 'Description (TR)',
            'Açıklama (EN)' => 'Description (EN)',
            
            // Alerts / Messages
            'Ayarlar başarıyla güncellendi.' => 'Settings updated successfully.',
            'Yetenek başarıyla eklendi.' => 'Skill added successfully.',
            'Yetenek başarıyla güncellendi.' => 'Skill updated successfully.',
            'Yetenek başarıyla silindi.' => 'Skill deleted successfully.',
            'Kategori başarıyla eklendi.' => 'Category added successfully.',
            'Kategori başarıyla silindi.' => 'Category deleted successfully.'
        ];
        
        // Sort by length descending to prevent partial replacements
        uksort($replacements, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        // Use regular expressions to prevent substring replacement in Turkish text
        foreach ($replacements as $tr => $en) {
            // Negative lookbehinds and lookaheads for letters
            $pattern = '/(?<![a-zA-ZçğıöşüÇĞİÖŞÜ])' . preg_quote($tr, '/') . '(?![a-zA-ZçğıöşüÇĞİÖŞÜ])/u';
            $buffer = preg_replace($pattern, $en, $buffer);
        }
        
        // Also fix placeholders
        $buffer = str_replace('placeholder="Örn: PHP, Figma, React"', 'placeholder="E.g. PHP, Figma, React"', $buffer);
        $buffer = str_replace('placeholder="Örn: Mobile, DevOps"', 'placeholder="E.g. Mobile, DevOps"', $buffer);
        $buffer = str_replace('placeholder="Adınız Soyadınız"', 'placeholder="Your Full Name"', $buffer);
        $buffer = str_replace('placeholder="Örn: Frontend Developer"', 'placeholder="E.g. Frontend Developer"', $buffer);
        $buffer = str_replace('placeholder="Örn: ABC Yazılım A.Ş."', 'placeholder="E.g. ABC Software Inc."', $buffer);
        $buffer = str_replace('placeholder="E-posta veya telefon numarası girin"', 'placeholder="Enter email or phone number"', $buffer);
    }
    
    return $buffer;
}
