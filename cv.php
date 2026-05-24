<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Dil kontrolü
$lang = isset($_GET['lang']) ? $_GET['lang'] : (isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr');
if (!in_array($lang, ['tr', 'en'])) {
    $lang = 'tr';
}

$settings = get_settings($pdo);

// Verileri çekme
// Eğitim
$stmt = $pdo->prepare("SELECT * FROM timeline WHERE type = 'edu' ORDER BY display_order ASC, date_range DESC");
$stmt->execute();
$education = $stmt->fetchAll();

// Deneyim
$stmt = $pdo->prepare("SELECT * FROM timeline WHERE type = 'exp' ORDER BY display_order ASC, date_range DESC");
$stmt->execute();
$experience = $stmt->fetchAll();

// Yetenekler
$stmt = $pdo->prepare("SELECT * FROM skills ORDER BY category ASC, percentage DESC");
$stmt->execute();
$all_skills = $stmt->fetchAll();

// Yetenekleri kategorilere ayır
$skills_by_category = [];
foreach ($all_skills as $skill) {
    $skills_by_category[$skill['category']][] = $skill;
}

// Çeviri yardımcı fonksiyonu
function t($tr, $en) {
    global $lang;
    return $lang === 'en' && !empty($en) ? $en : $tr;
}

// Tarih çeviri yardımcı fonksiyonu (Türkçe ay isimlerini İngilizceye çevirir)
function translate_date_string($date_str) {
    global $lang;
    if ($lang !== 'en') return $date_str;
    
    $tr_months = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık', 'Devam Ediyor', 'Yatay Geçiş'];
    $en_months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Present', 'Transferred'];
    
    $tr_months_lower = ['ocak', 'şubat', 'mart', 'nisan', 'mayıs', 'haziran', 'temmuz', 'ağustos', 'eylül', 'ekim', 'kasım', 'aralık', 'devam ediyor', 'yatay geçiş'];
    
    $date_str = str_ireplace($tr_months, $en_months, $date_str);
    $date_str = str_ireplace($tr_months_lower, $en_months, $date_str); // Case insensitive fallback
    
    return ucfirst($date_str);
}

// İsim, Ünvan vb.
$name = t($settings['admin_name'] ?? 'İsimsiz', $settings['admin_name_en'] ?? '');
$title = t($settings['admin_title'] ?? '', $settings['admin_title_en'] ?? '');
$about = t($settings['about_text'] ?? '', $settings['about_text_en'] ?? '');

$email = $settings['email'] ?? '';
$linkedin = $settings['linkedin_link'] ?? '';
$github = $settings['github_link'] ?? '';

?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($name); ?> - CV</title>
    <link rel="stylesheet" href="cv-style.css">
</head>
<body>
    <div class="print-controls">
        <button class="btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; vertical-align: middle;"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            <?php echo $lang === 'en' ? 'Print / Download PDF' : 'Yazdır / PDF İndir'; ?>
        </button>
    </div>

    <div class="cv-container">
        
        <!-- HEADER -->
        <header class="cv-header">
            <h1 class="cv-name"><?php echo escape($name); ?></h1>
            <div class="cv-title"><?php echo escape($title); ?></div>
            
            <div class="cv-contact">
                <?php if ($email): ?>
                    <span>✉️ <?php echo escape($email); ?></span>
                <?php endif; ?>
                <?php if ($linkedin): ?>
                    <span>🔗 <?php echo escape(str_replace('https://', '', $linkedin)); ?></span>
                <?php endif; ?>
                <?php if ($github): ?>
                    <span>💻 <?php echo escape(str_replace('https://', '', $github)); ?></span>
                <?php endif; ?>
            </div>
        </header>

        <!-- ÖZET / HAKKIMDA -->
        <?php if ($about): ?>
        <section class="cv-section">
            <h2 class="cv-section-title"><?php echo $lang === 'en' ? 'Professional Summary' : 'Profesyonel Özet'; ?></h2>
            <div class="cv-summary">
                <?php echo nl2br(escape($about)); ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- İŞ DENEYİMİ -->
        <?php if (count($experience) > 0): ?>
        <section class="cv-section">
            <h2 class="cv-section-title"><?php echo $lang === 'en' ? 'Experience' : 'İş Deneyimi'; ?></h2>
            
            <?php foreach ($experience as $exp): ?>
                <div class="cv-item">
                    <div class="cv-item-header">
                        <span class="cv-item-title"><?php echo escape(t($exp['title'], $exp['title_en'])); ?></span>
                        <span class="cv-item-date"><?php echo escape(translate_date_string($exp['date_range'])); ?></span>
                    </div>
                    <div class="cv-item-subtitle"><?php echo escape(t($exp['institution'], $exp['institution_en'])); ?></div>
                    <?php if (!empty($exp['description'])): ?>
                        <div class="cv-item-desc">
                            <?php echo nl2br(escape(t($exp['description'], $exp['description_en']))); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- EĞİTİM -->
        <?php if (count($education) > 0): ?>
        <section class="cv-section">
            <h2 class="cv-section-title"><?php echo $lang === 'en' ? 'Education' : 'Eğitim'; ?></h2>
            
            <?php foreach ($education as $edu): ?>
                <div class="cv-item">
                    <div class="cv-item-header">
                        <span class="cv-item-title"><?php echo escape(t($edu['title'], $edu['title_en'])); ?></span>
                        <span class="cv-item-date"><?php echo escape(translate_date_string($edu['date_range'])); ?></span>
                    </div>
                    <div class="cv-item-subtitle"><?php echo escape(t($edu['institution'], $edu['institution_en'])); ?></div>
                    <?php if (!empty($edu['description'])): ?>
                        <div class="cv-item-desc">
                            <?php echo nl2br(escape(t($edu['description'], $edu['description_en']))); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- PROJELER (PORTFÖY) -->
        <?php
        $stmt = $pdo->query("SELECT * FROM portfolio ORDER BY date_added DESC");
        $projects = $stmt->fetchAll();
        ?>
        <?php if (count($projects) > 0): ?>
        <section class="cv-section">
            <h2 class="cv-section-title"><?php echo $lang === 'en' ? 'Projects' : 'Projeler'; ?></h2>
            
            <?php foreach ($projects as $proj): ?>
                <div class="cv-item">
                    <div class="cv-item-header">
                        <span class="cv-item-title"><?php echo escape(t($proj['title'], $proj['title_en'] ?? '')); ?></span>
                        <span class="cv-item-date"><?php echo escape(date('m/Y', strtotime($proj['date_added']))); ?></span>
                    </div>
                    <?php if (!empty($proj['github_url']) || !empty($proj['live_url'])): ?>
                        <div class="cv-item-subtitle" style="font-size: 8.5pt;">
                            <?php if (!empty($proj['github_url'])): ?>
                                GitHub: <?php echo escape(str_replace('https://', '', $proj['github_url'])); ?>
                                <?php echo !empty($proj['live_url']) ? '|' : ''; ?>
                            <?php endif; ?>
                            <?php if (!empty($proj['live_url'])): ?>
                                Canlı: <?php echo escape(str_replace('https://', '', $proj['live_url'])); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($proj['description'])): ?>
                        <div class="cv-item-desc">
                            <?php echo nl2br(escape(t($proj['description'], $proj['description_en'] ?? ''))); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- SERTİFİKALAR -->
        <?php
        $stmt = $pdo->query("SELECT * FROM certificates ORDER BY date_issued DESC");
        $certificates = $stmt->fetchAll();
        ?>
        <?php if (count($certificates) > 0): ?>
        <section class="cv-section">
            <h2 class="cv-section-title"><?php echo $lang === 'en' ? 'Certificates' : 'Sertifikalar'; ?></h2>
            
            <?php foreach ($certificates as $cert): ?>
                <div class="cv-item">
                    <div class="cv-item-header">
                        <span class="cv-item-title"><?php echo escape(t($cert['title'], $cert['title_en'] ?? '')); ?></span>
                        <span class="cv-item-date"><?php 
                            $ts = strtotime($cert['date_issued']);
                            echo escape($ts ? date('m/Y', $ts) : translate_date_string($cert['date_issued'])); 
                        ?></span>
                    </div>
                    <div class="cv-item-subtitle" style="font-size: 8.5pt;">
                        <?php echo escape($cert['issuer']); ?>
                        <?php if (!empty($cert['link'])): ?>
                            | <a href="<?php echo escape($cert['link']); ?>" target="_blank" style="color: inherit; text-decoration: none;"><?php echo $lang === 'en' ? 'Verify' : 'Doğrula'; ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- YETENEKLER -->
        <?php if (count($skills_by_category) > 0): ?>
        <section class="cv-section">
            <h2 class="cv-section-title"><?php echo $lang === 'en' ? 'Skills' : 'Yetenekler'; ?></h2>
            
            <div class="cv-skills-grid">
                <?php 
                $category_translations = [
                    'dil' => 'Language',
                    'diller' => 'Languages',
                    'yabancı dil' => 'Language',
                    'yabanci dil' => 'Language',
                    'araçlar' => 'Tools',
                    'araclar' => 'Tools',
                    'veritabanı' => 'Database',
                    'veri tabanı' => 'Database',
                    'veri tabani' => 'Database',
                    'veritabanlari' => 'Databases',
                    'veritabanları' => 'Databases',
                    'diğer' => 'Other',
                    'diger' => 'Other',
                    'işletim sistemleri' => 'Operating Systems'
                ];
                foreach ($skills_by_category as $category => $skills): 
                    // Fallback for mb_strtolower if mbstring extension is disabled
                    if (function_exists('mb_strtolower')) {
                        $cat_lower = mb_strtolower(trim($category), 'UTF-8');
                    } else {
                        // Custom Turkish lowercase conversion
                        $cat_lower = str_replace(
                            ['I', 'İ', 'Ş', 'Ğ', 'Ü', 'Ö', 'Ç'], 
                            ['ı', 'i', 'ş', 'ğ', 'ü', 'ö', 'ç'], 
                            trim($category)
                        );
                        $cat_lower = strtolower($cat_lower);
                    }
                    $cat_display = ($lang === 'en' && isset($category_translations[$cat_lower])) ? $category_translations[$cat_lower] : $category;
                ?>
                    <div class="cv-skill-group">
                        <div class="cv-skill-category"><?php echo escape($cat_display); ?>:</div>
                        <div class="cv-skill-list">
                            <?php 
                                $skill_names = array_map(function($s) {
                                    return escape(t($s['name'], $s['name_en'] ?? ''));
                                }, $skills);
                                echo implode(' • ', $skill_names);
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- REFERANSLAR -->
        <?php
        $stmt = $pdo->query("SELECT * FROM `references` ORDER BY display_order ASC, id DESC");
        $references = $stmt->fetchAll();
        ?>
        <?php if (count($references) > 0): ?>
        <section class="cv-section" style="page-break-inside: avoid;">
            <h2 class="cv-section-title"><?php echo $lang === 'en' ? 'References' : 'Referanslar'; ?></h2>
            
            <div class="cv-skills-grid">
                <?php foreach ($references as $ref): ?>
                    <div class="cv-skill-group" style="margin-bottom: 10px;">
                        <div class="cv-skill-category" style="font-weight: 600;"><?php echo escape($ref['name']); ?></div>
                        <div class="cv-skill-list" style="font-size: 9pt;">
                            <?php if (!empty($ref['title']) || !empty($ref['company'])): ?>
                                <?php echo escape(t($ref['title'], $ref['title_en'] ?? '')); ?>
                                <?php echo (!empty($ref['title']) && !empty($ref['company'])) ? ' - ' : ''; ?>
                                <?php echo escape($ref['company']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($ref['contact_info'])): ?>
                                <span style="color: var(--cv-text-secondary);"><?php echo escape($ref['contact_info']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <!-- Otomatik yazdırma dialogu açmak isterseniz alttaki satırı açabilirsiniz -->
    <!-- <script>window.onload = function() { window.print(); }</script> -->
</body>
</html>
