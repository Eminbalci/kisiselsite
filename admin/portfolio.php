<?php
@set_time_limit(3600);
@ini_set('max_execution_time', '3600');
@ini_set('max_input_time', '3600');

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Force login
require_login();

$settings = get_settings($pdo);

$success_message = isset($_GET['success_msg']) ? $_GET['success_msg'] : '';
$error_message = isset($_GET['error_msg']) ? $_GET['error_msg'] : '';

// Edit Mode Variables
$edit_mode = false;
$edit_id = 0;
$edit_title = '';
$edit_title_en = '';
$edit_description = '';
$edit_description_en = '';
$edit_content = '';
$edit_content_en = '';
$edit_file = '';
$edit_link = '';
$edit_image = '';
$edit_slug = '';
$edit_category = '';
$project_images = [];
$uploaded_project_files = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the post data was discarded because it exceeded the post_max_size limit
    if (empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_size = ini_get('post_max_size');
        $error_message = "Yüklemeye çalıştığınız dosya sunucu limitlerini aşıyor (Maksimum limit: {$max_size}). Lütfen PHP ayarlarındaki post_max_size ve upload_max_filesize limitlerini artırın veya daha küçük bir dosya yükleyin.";
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
        
        if (!verify_csrf_token($csrf_token)) {
            $error_message = 'Güvenlik doğrulaması başarısız (CSRF).';
        } else {
            if ($action === 'add') {
            $title = trim($_POST['title']);
            $title_en = trim($_POST['title_en'] ?? '');
            if (empty($title_en) && !empty($title)) $title_en = auto_translate($title, 'tr', 'en');
            $description = trim($_POST['description']);
            $description_en = trim($_POST['description_en'] ?? '');
            if (empty($description_en) && !empty($description)) $description_en = auto_translate($description, 'tr', 'en');
            $content = trim($_POST['content']);
            $content_en = trim($_POST['content_en'] ?? '');
            if (empty($content_en) && !empty($content)) $content_en = auto_translate($content, 'tr', 'en');
            $project_link = trim($_POST['project_link']);
            $slug = trim($_POST['slug']);
            $category = trim($_POST['category']);
            
            if (empty($slug)) {
                $slug = slugify($title);
            } else {
                $slug = slugify($slug);
            }
            
            // Uniqueness check
            $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM portfolio WHERE slug = :slug");
            $check_stmt->execute(['slug' => $slug]);
            if ($check_stmt->fetch()['count'] > 0) {
                $slug = $slug . '-' . time();
            }
            
            if (empty($title) || empty($description)) {
                $error_message = 'Başlık ve açıklama alanları zorunludur.';
            } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
                $error_message = 'Lütfen proje için bir görsel yükleyin.';
            } else {
                try {
                    // Upload image
                    $uploaded_file = handle_image_upload($_FILES['image']);
                    
                    // Upload file if any
                    $file_path = '';
                    if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $file_path = handle_file_upload($_FILES['project_file']);
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO portfolio (title, title_en, description, description_en, content, content_en, file_path, image_path, project_link, slug, category) VALUES (:title, :title_en, :description, :description_en, :content, :content_en, :file_path, :image_path, :project_link, :slug, :category)");
                    $stmt->execute([
                        'title' => $title,
                        'title_en' => $title_en,
                        'description' => $description,
                        'description_en' => $description_en,
                        'content' => $content,
                        'content_en' => $content_en,
                        'file_path' => $file_path,
                        'image_path' => $uploaded_file,
                        'project_link' => $project_link,
                        'slug' => $slug,
                        'category' => $category
                    ]);
                    $project_id = $pdo->lastInsertId();
                    
                    // Handle gallery images
                    if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
                        $files = $_FILES['gallery_images'];
                        $file_count = count($files['name']);
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                $single_file = [
                                    'name' => $files['name'][$i],
                                    'type' => $files['type'][$i],
                                    'tmp_name' => $files['tmp_name'][$i],
                                    'error' => $files['error'][$i],
                                    'size' => $files['size'][$i]
                                ];
                                try {
                                    $gallery_image_path = handle_image_upload($single_file);
                                    $ins_img = $pdo->prepare("INSERT INTO project_images (project_id, image_path, display_order) VALUES (:project_id, :image_path, :display_order)");
                                    $ins_img->execute([
                                        'project_id' => $project_id,
                                        'image_path' => $gallery_image_path,
                                        'display_order' => $i
                                    ]);
                                } catch (Exception $ex) {
                                    $error_message .= " Galeri resmi yüklenemedi: " . $ex->getMessage();
                                }
                            }
                        }
                    }
                    
                    // Handle dynamic project files
                    $has_new_files = false;
                    if (isset($_FILES['new_project_files']) && is_array($_FILES['new_project_files']['name'])) {
                        foreach ($_FILES['new_project_files']['name'] as $name) {
                            if (!empty($name)) {
                                $has_new_files = true;
                                break;
                            }
                        }
                    }
                    
                    if ($has_new_files) {
                        $files = $_FILES['new_project_files'];
                        $labels = $_POST['new_project_file_labels'] ?? [];
                        $labels_en = $_POST['new_project_file_labels_en'] ?? [];
                        $file_count = count($files['name']);
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                    $single_file = [
                                        'name' => $files['name'][$i],
                                        'type' => $files['type'][$i],
                                        'tmp_name' => $files['tmp_name'][$i],
                                        'error' => $files['error'][$i],
                                        'size' => $files['size'][$i]
                                    ];
                                    try {
                                        $uploaded_file_path = handle_file_upload($single_file);
                                        
                                        $label = trim($labels[$i] ?? '');
                                        if (empty($label)) $label = 'Dosyayı İndir';
                                        
                                        $label_en = trim($labels_en[$i] ?? '');
                                        if (empty($label_en)) $label_en = 'Download File';
                                        
                                        $ins_file = $pdo->prepare("INSERT INTO project_files (project_id, file_path, file_label, file_label_en, display_order) VALUES (:project_id, :file_path, :file_label, :file_label_en, :display_order)");
                                        $ins_file->execute([
                                            'project_id' => $project_id,
                                            'file_path' => $uploaded_file_path,
                                            'file_label' => $label,
                                            'file_label_en' => $label_en,
                                            'display_order' => $i
                                        ]);
                                    } catch (Exception $ex) {
                                        $error_message .= (empty($error_message) ? "" : " | ") . "Dosya yüklenemedi: " . $ex->getMessage();
                                    }
                                } else {
                                    $err_code = $files['error'][$i];
                                    $file_name = htmlspecialchars($files['name'][$i]);
                                    $err_msg = "Dosya yükleme hatası ({$file_name}): ";
                                    switch ($err_code) {
                                        case UPLOAD_ERR_INI_SIZE:
                                            $max_size = ini_get('upload_max_filesize');
                                            $err_msg .= "Dosya boyutu sunucu limitini aşıyor (Maksimum: {$max_size}).";
                                            break;
                                        case UPLOAD_ERR_FORM_SIZE:
                                            $err_msg .= "Dosya boyutu form limitini aşıyor.";
                                            break;
                                        case UPLOAD_ERR_PARTIAL:
                                            $err_msg .= "Dosya sadece kısmen yüklenebildi.";
                                            break;
                                        case UPLOAD_ERR_NO_TMP_DIR:
                                            $err_msg .= "Sunucuda geçici klasör bulunamadı.";
                                            break;
                                        case UPLOAD_ERR_CANT_WRITE:
                                            $err_msg .= "Dosya diske yazılamadı.";
                                            break;
                                        default:
                                            $err_msg .= "Bilinmeyen bir hata oluştu (Hata kodu: {$err_code}).";
                                            break;
                                    }
                                    $error_message .= (empty($error_message) ? "" : " | ") . $err_msg;
                                }
                            }
                        }
                    }
                    
                    if (empty($error_message)) {
                        $success_message = 'Proje başarıyla eklendi.';
                    } else {
                        $success_message = 'Proje eklendi fakat bazı dosyalar/resimler yüklenemedi: ' . $error_message;
                        $error_message = '';
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id']);
            $title = trim($_POST['title']);
            $title_en = trim($_POST['title_en'] ?? '');
            if (empty($title_en) && !empty($title)) $title_en = auto_translate($title, 'tr', 'en');
            $description = trim($_POST['description']);
            $description_en = trim($_POST['description_en'] ?? '');
            if (empty($description_en) && !empty($description)) $description_en = auto_translate($description, 'tr', 'en');
            $content = trim($_POST['content']);
            $content_en = trim($_POST['content_en'] ?? '');
            if (empty($content_en) && !empty($content)) $content_en = auto_translate($content, 'tr', 'en');
            $project_link = trim($_POST['project_link']);
            $slug = trim($_POST['slug']);
            $category = trim($_POST['category']);
            
            if (empty($slug)) {
                $slug = slugify($title);
            } else {
                $slug = slugify($slug);
            }
            
            // Uniqueness check
            $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM portfolio WHERE slug = :slug AND id != :id");
            $check_stmt->execute(['slug' => $slug, 'id' => $id]);
            if ($check_stmt->fetch()['count'] > 0) {
                $slug = $slug . '-' . time();
            }
            
            if (empty($title) || empty($description)) {
                $error_message = 'Başlık ve açıklama alanları zorunludur.';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT image_path, file_path FROM portfolio WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $current_project = $stmt->fetch();
                    
                    if (!$current_project) {
                        throw new Exception("Proje bulunamadı.");
                    }
                    
                    $image_path = $current_project['image_path'];
                    $file_path = $current_project['file_path'];
                    
                    // Handle legacy file deletion
                    if (isset($_POST['delete_legacy_file']) && $_POST['delete_legacy_file'] === '1') {
                        if (!empty($file_path)) {
                            $old_file_path = '../uploads/' . $file_path;
                            if (file_exists($old_file_path)) {
                                @unlink($old_file_path);
                            }
                            $file_path = '';
                        }
                    }
                    
                    // Check if new image is uploaded
                    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $new_image = handle_image_upload($_FILES['image']);
                        $old_image_path = '../uploads/' . $image_path;
                        if (file_exists($old_image_path)) {
                            @unlink($old_image_path);
                        }
                        $image_path = $new_image;
                    }
                    
                    // Check if new file is uploaded
                    if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $new_file = handle_file_upload($_FILES['project_file']);
                        if (!empty($file_path)) {
                            $old_file_path = '../uploads/' . $file_path;
                            if (file_exists($old_file_path)) {
                                @unlink($old_file_path);
                            }
                        }
                        $file_path = $new_file;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE portfolio SET title = :title, title_en = :title_en, description = :description, description_en = :description_en, content = :content, content_en = :content_en, file_path = :file_path, image_path = :image_path, project_link = :project_link, slug = :slug, category = :category WHERE id = :id");
                    $stmt->execute([
                        'title' => $title,
                        'title_en' => $title_en,
                        'description' => $description,
                        'description_en' => $description_en,
                        'content' => $content,
                        'content_en' => $content_en,
                        'file_path' => $file_path,
                        'image_path' => $image_path,
                        'project_link' => $project_link,
                        'slug' => $slug,
                        'category' => $category,
                        'id' => $id
                    ]);
                    
                    // Handle image deletions
                    if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                        foreach ($_POST['delete_images'] as $img_id) {
                            $img_id = intval($img_id);
                            $stmt_get_img = $pdo->prepare("SELECT image_path FROM project_images WHERE id = :id AND project_id = :project_id");
                            $stmt_get_img->execute(['id' => $img_id, 'project_id' => $id]);
                            $img_data = $stmt_get_img->fetch();
                            if ($img_data) {
                                $img_file_path = '../uploads/' . $img_data['image_path'];
                                if (file_exists($img_file_path)) {
                                    @unlink($img_file_path);
                                }
                                $stmt_del_single = $pdo->prepare("DELETE FROM project_images WHERE id = :id");
                                $stmt_del_single->execute(['id' => $img_id]);
                            }
                        }
                    }
                    
                    // Handle new gallery uploads
                    if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
                        $files = $_FILES['gallery_images'];
                        $file_count = count($files['name']);
                        // Fetch current max display_order
                        $stmt_max = $pdo->prepare("SELECT MAX(display_order) as max_order FROM project_images WHERE project_id = :project_id");
                        $stmt_max->execute(['project_id' => $id]);
                        $max_res = $stmt_max->fetch();
                        $next_order = isset($max_res['max_order']) ? intval($max_res['max_order']) + 1 : 0;
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                $single_file = [
                                    'name' => $files['name'][$i],
                                    'type' => $files['type'][$i],
                                    'tmp_name' => $files['tmp_name'][$i],
                                    'error' => $files['error'][$i],
                                    'size' => $files['size'][$i]
                                ];
                                try {
                                    $gallery_image_path = handle_image_upload($single_file);
                                    $ins_img = $pdo->prepare("INSERT INTO project_images (project_id, image_path, display_order) VALUES (:project_id, :image_path, :display_order)");
                                    $ins_img->execute([
                                        'project_id' => $id,
                                        'image_path' => $gallery_image_path,
                                        'display_order' => $next_order + $i
                                    ]);
                                } catch (Exception $ex) {
                                    $error_message .= " Galeri resmi yüklenemedi: " . $ex->getMessage();
                                }
                            }
                        }
                    }
                    
                    // Handle existing files updates
                    if (isset($_POST['existing_file_labels']) && is_array($_POST['existing_file_labels'])) {
                        foreach ($_POST['existing_file_labels'] as $file_id => $label) {
                            $file_id = intval($file_id);
                            $label = trim($label);
                            $label_en = trim($_POST['existing_file_labels_en'][$file_id] ?? '');
                            if (empty($label)) $label = 'Dosyayı İndir';
                            if (empty($label_en)) $label_en = 'Download File';
                            
                            $stmt_up_file = $pdo->prepare("UPDATE project_files SET file_label = :file_label, file_label_en = :file_label_en WHERE id = :id AND project_id = :project_id");
                            $stmt_up_file->execute([
                                'file_label' => $label,
                                'file_label_en' => $label_en,
                                'id' => $file_id,
                                'project_id' => $id
                            ]);
                        }
                    }
                    
                    // Handle file deletions
                    if (isset($_POST['delete_files']) && is_array($_POST['delete_files'])) {
                        foreach ($_POST['delete_files'] as $file_id) {
                            $file_id = intval($file_id);
                            $stmt_get_f = $pdo->prepare("SELECT file_path FROM project_files WHERE id = :id AND project_id = :project_id");
                            $stmt_get_f->execute(['id' => $file_id, 'project_id' => $id]);
                            $file_data = $stmt_get_f->fetch();
                            if ($file_data) {
                                $file_disk_path = '../uploads/' . $file_data['file_path'];
                                if (file_exists($file_disk_path)) {
                                    @unlink($file_disk_path);
                                }
                                $stmt_del_f = $pdo->prepare("DELETE FROM project_files WHERE id = :id");
                                $stmt_del_f->execute(['id' => $file_id]);
                            }
                        }
                    }
                    
                    // Handle new project files
                    $has_new_files = false;
                    if (isset($_FILES['new_project_files']) && is_array($_FILES['new_project_files']['name'])) {
                        foreach ($_FILES['new_project_files']['name'] as $name) {
                            if (!empty($name)) {
                                $has_new_files = true;
                                break;
                            }
                        }
                    }
                    
                    if ($has_new_files) {
                        $files = $_FILES['new_project_files'];
                        $labels = $_POST['new_project_file_labels'] ?? [];
                        $labels_en = $_POST['new_project_file_labels_en'] ?? [];
                        $file_count = count($files['name']);
                        
                        // Fetch current max display_order
                        $stmt_max_f = $pdo->prepare("SELECT MAX(display_order) as max_order FROM project_files WHERE project_id = :project_id");
                        $stmt_max_f->execute(['project_id' => $id]);
                        $max_res_f = $stmt_max_f->fetch();
                        $next_order_f = isset($max_res_f['max_order']) ? intval($max_res_f['max_order']) + 1 : 0;
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                                    $single_file = [
                                        'name' => $files['name'][$i],
                                        'type' => $files['type'][$i],
                                        'tmp_name' => $files['tmp_name'][$i],
                                        'error' => $files['error'][$i],
                                        'size' => $files['size'][$i]
                                    ];
                                    try {
                                        $uploaded_file_path = handle_file_upload($single_file);
                                        
                                        $label = trim($labels[$i] ?? '');
                                        if (empty($label)) $label = 'Dosyayı İndir';
                                        
                                        $label_en = trim($labels_en[$i] ?? '');
                                        if (empty($label_en)) $label_en = 'Download File';
                                        
                                        $ins_file = $pdo->prepare("INSERT INTO project_files (project_id, file_path, file_label, file_label_en, display_order) VALUES (:project_id, :file_path, :file_label, :file_label_en, :display_order)");
                                        $ins_file->execute([
                                            'project_id' => $id,
                                            'file_path' => $uploaded_file_path,
                                            'file_label' => $label,
                                            'file_label_en' => $label_en,
                                            'display_order' => $next_order_f + $i
                                        ]);
                                    } catch (Exception $ex) {
                                        $error_message .= (empty($error_message) ? "" : " | ") . "Dosya yüklenemedi: " . $ex->getMessage();
                                    }
                                } else {
                                    $err_code = $files['error'][$i];
                                    $file_name = htmlspecialchars($files['name'][$i]);
                                    $err_msg = "Dosya yükleme hatası ({$file_name}): ";
                                    switch ($err_code) {
                                        case UPLOAD_ERR_INI_SIZE:
                                            $max_size = ini_get('upload_max_filesize');
                                            $err_msg .= "Dosya boyutu sunucu limitini aşıyor (Maksimum: {$max_size}).";
                                            break;
                                        case UPLOAD_ERR_FORM_SIZE:
                                            $err_msg .= "Dosya boyutu form limitini aşıyor.";
                                            break;
                                        case UPLOAD_ERR_PARTIAL:
                                            $err_msg .= "Dosya sadece kısmen yüklenebildi.";
                                            break;
                                        case UPLOAD_ERR_NO_TMP_DIR:
                                            $err_msg .= "Sunucuda geçici klasör bulunamadı.";
                                            break;
                                        case UPLOAD_ERR_CANT_WRITE:
                                            $err_msg .= "Dosya diske yazılamadı.";
                                            break;
                                        default:
                                            $err_msg .= "Bilinmeyen bir hata oluştu (Hata kodu: {$err_code}).";
                                            break;
                                    }
                                    $error_message .= (empty($error_message) ? "" : " | ") . $err_msg;
                                }
                            }
                        }
                    }
                    
                    if (empty($error_message)) {
                        $success_message = 'Proje başarıyla güncellendi.';
                    } else {
                        $success_message = 'Proje güncellendi fakat bazı dosyalar/resimler yüklenemedi: ' . $error_message;
                        $error_message = '';
                    }
                } catch (Exception $e) {
                    $error_message = $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            try {
                // Fetch image and file to delete from disk
                $stmt = $pdo->prepare("SELECT image_path, file_path FROM portfolio WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $project = $stmt->fetch();
                
                if ($project) {
                    $image_path = '../uploads/' . $project['image_path'];
                    if (file_exists($image_path)) {
                        @unlink($image_path);
                    }
                    
                    if (!empty($project['file_path'])) {
                        $old_file = '../uploads/' . $project['file_path'];
                        if (file_exists($old_file)) {
                            @unlink($old_file);
                        }
                    }
                    
                    // Fetch and delete gallery images first
                    $stmt_g = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = :project_id");
                    $stmt_g->execute(['project_id' => $id]);
                    $g_images = $stmt_g->fetchAll();
                    foreach ($g_images as $g_img) {
                        $g_path = '../uploads/' . $g_img['image_path'];
                        if (file_exists($g_path)) {
                            @unlink($g_path);
                        }
                    }
                    $stmt_del_g = $pdo->prepare("DELETE FROM project_images WHERE project_id = :project_id");
                    $stmt_del_g->execute(['project_id' => $id]);
                    
                    // Fetch and delete project files first
                    $stmt_f_del = $pdo->prepare("SELECT file_path FROM project_files WHERE project_id = :project_id");
                    $stmt_f_del->execute(['project_id' => $id]);
                    $p_files_del = $stmt_f_del->fetchAll();
                    foreach ($p_files_del as $p_f_del) {
                        $f_del_path = '../uploads/' . $p_f_del['file_path'];
                        if (file_exists($f_del_path)) {
                            @unlink($f_del_path);
                        }
                    }
                    $stmt_del_p_files = $pdo->prepare("DELETE FROM project_files WHERE project_id = :project_id");
                    $stmt_del_p_files->execute(['project_id' => $id]);
                    
                    $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $success_message = 'Proje başarıyla silindi.';
                } else {
                    $error_message = 'Proje bulunamadı.';
                }
            } catch (PDOException $e) {
                $error_message = 'Veritabanı hatası: ' . $e->getMessage();
            }
        }
    }
}
}

// Check if we are loading an item for edit mode via GET
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM portfolio WHERE id = :id");
    $stmt->execute(['id' => $edit_id]);
    $project = $stmt->fetch();
    
    if ($project) {
        $edit_mode = true;
        $edit_title = $project['title'];
        $edit_title_en = $project['title_en'] ?? '';
        $edit_description = $project['description'];
        $edit_description_en = $project['description_en'] ?? '';
        $edit_content = $project['content'];
        $edit_content_en = $project['content_en'] ?? '';
        $edit_file = $project['file_path'];
        $edit_link = $project['project_link'];
        $edit_image = $project['image_path'];
        $edit_slug = $project['slug'];
        $edit_category = $project['category'] ?? '';
        
        // Fetch project images
        $stmt_images = $pdo->prepare("SELECT * FROM project_images WHERE project_id = :project_id ORDER BY display_order ASC, id ASC");
        $stmt_images->execute(['project_id' => $edit_id]);
        $project_images = $stmt_images->fetchAll();

        // Fetch project files
        $stmt_files = $pdo->prepare("SELECT * FROM project_files WHERE project_id = :project_id ORDER BY display_order ASC, id ASC");
        $stmt_files->execute(['project_id' => $edit_id]);
        $uploaded_project_files = $stmt_files->fetchAll();
    }
}

// Get all portfolio items
$stmt = $pdo->query("SELECT * FROM portfolio ORDER BY date_added DESC");
$portfolio_items = $stmt->fetchAll();

// Generate CSRF token
$token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli | Portföy</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../admin-style.css">
    <!-- EasyMDE Markdown Editor -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    <!-- Favicon -->
    <?php if (!empty($settings['site_favicon']) && file_exists('../uploads/' . $settings['site_favicon'])): ?>
        <link rel="icon" type="<?php 
            $fav_ext = pathinfo($settings['site_favicon'], PATHINFO_EXTENSION);
            if ($fav_ext === 'svg') echo 'image/svg+xml';
            elseif ($fav_ext === 'ico') echo 'image/x-icon';
            else echo 'image/' . $fav_ext;
        ?>" href="../uploads/<?php echo escape($settings['site_favicon']); ?>">
    <?php endif; ?>
</head>
<body>
    <header class="admin-header">
        <div class="header-logo">
            <h1>Portföy Admin</h1>
        </div>
        <div class="user-info">
            <span>Hoş geldiniz, <strong><?php echo escape($_SESSION['admin_username']); ?></strong></span>
            <a href="logout.php" class="btn btn-sm btn-danger">Çıkış Yap</a>
        </div>
    </header>

    <div class="admin-container">
        <aside class="admin-sidebar">
            <nav class="admin-nav">
                <a href="index.php" class="nav-item">
                    <span class="icon">⚙️</span> Genel Ayarlar
                </a>
                <a href="skills.php" class="nav-item">
                    <span class="icon">📊</span> Yetenekler
                </a>
                <a href="portfolio.php" class="nav-item active">
                    <span class="icon">💼</span> Portföy
                </a>
                <a href="blog.php" class="nav-item">
                    <span class="icon">📝</span> Blog
                </a>
                <a href="comments.php" class="nav-item">
                    <span class="icon">💬</span> Yorumlar
                </a>
                <a href="timeline.php" class="nav-item">
                    <span class="icon">⏳</span> Zaman Çizelgesi
                </a>
                <a href="certificates.php" class="nav-item">
                    <span class="icon">🏆</span> Sertifikalar
                </a>
                <a href="references.php" class="nav-item">
                    <span class="icon">🤝</span> Referanslar
                </a>
                <a href="messages.php" class="nav-item">
                    <span class="icon">✉️</span> Gelen Kutusu
                </a>
                <div class="nav-divider"></div>
                <a href="../index.php" target="_blank" class="nav-item">
                    <span class="icon">🌐</span> Siteyi Görüntüle
                </a>
                <a href="../cv.php?lang=tr" target="_blank" class="nav-item" style="color: var(--brand-secondary);">
                    <span class="icon">📄</span> CV İndir (TR)
                </a>
                <a href="../cv.php?lang=en" target="_blank" class="nav-item" style="color: var(--brand-secondary);">
                    <span class="icon">📄</span> CV İndir (EN)
                </a>
            </nav>
        </aside>

        <main class="admin-main">
            <h2>Portföy Yönetimi</h2>
            <p class="main-subtitle">Sitenizde sergilenecek projelerinizi buradan yönetebilirsiniz.</p>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo escape($success_message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error"><?php echo escape($error_message); ?></div>
            <?php endif; ?>

            <div class="grid grid-3-1">
                <!-- Portfolio Form Card -->
                <div class="card">
                    <h3><?php echo $edit_mode ? 'Projeyi Düzenle' : 'Yeni Proje Ekle'; ?></h3>
                    <form id="portfolio-form" action="portfolio.php<?php echo $edit_mode ? '?edit=' . $edit_id : ''; ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
                        
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="title">Proje Adı (TR)</label>
                            <input type="text" id="title" name="title" value="<?php echo escape($edit_title); ?>" placeholder="Örn: E-Ticaret Arayüz Tasarımı" required>
                        </div>

                        <div class="form-group">
                            <label for="title_en">Proje Adı (EN)</label>
                            <input type="text" id="title_en" name="title_en" value="<?php echo escape($edit_title_en); ?>" placeholder="Örn: E-Commerce UI Design">
                        </div>

                        <div class="form-group">
                            <label for="project_link">Proje Linki (Opsiyonel)</label>
                            <input type="url" id="project_link" name="project_link" value="<?php echo escape($edit_link); ?>" placeholder="Örn: https://github.com/kullanici/proje">
                        </div>

                        <div class="form-group">
                            <label for="slug">Proje URL Sloganı / Link İsmi (Boş bırakırsanız başlığa göre otomatik oluşturulur)</label>
                            <input type="text" id="slug" name="slug" value="<?php echo escape($edit_slug); ?>" placeholder="Örn: yemek-siparis-sistemi">
                        </div>

                        <div class="form-group">
                            <label for="category">Proje Kategorisi (Örn: Web, Mobil, Gömülü Sistemler, Mekatronik vb.)</label>
                            <input type="text" id="category" name="category" value="<?php echo escape($edit_category); ?>" placeholder="Örn: Web">
                        </div>

                        <div class="form-group">
                            <label for="description">Proje Kısa Açıklaması (TR)</label>
                            <textarea id="description" name="description" rows="3" placeholder="Projenin amacını, kullanılan teknolojileri vb. kısaca özetleyin..." required><?php echo escape($edit_description); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="description_en">Proje Kısa Açıklaması (EN)</label>
                            <textarea id="description_en" name="description_en" rows="3"><?php echo escape($edit_description_en); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="content">Proje Detaylı İçeriği (TR)</label>
                            <textarea id="content" name="content" rows="8" placeholder="Proje detaylarını, kurulum rehberini, kullanım kılavuzunu veya ek açıklamaları buraya yazın..."><?php echo escape($edit_content); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="content_en">Proje Detaylı İçeriği (EN)</label>
                            <textarea id="content_en" name="content_en" rows="8"><?php echo escape($edit_content_en); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="image">Proje Görseli <?php echo $edit_mode ? '(Değiştirmek istemiyorsanız boş bırakın)' : ''; ?></label>
                            <?php if ($edit_mode && !empty($edit_image)): ?>
                                <div class="thumbnail-preview mb-1">
                                    <img src="../uploads/<?php echo escape($edit_image); ?>" alt="Önizleme">
                                </div>
                            <?php endif; ?>
                            <input type="file" id="image" name="image" accept="image/*" <?php echo $edit_mode ? '' : 'required'; ?>>
                        </div>

                        <div class="form-group" style="border-top: 1px solid var(--border); padding-top: 15px; margin-top: 15px;">
                            <label style="font-weight: 600;">İndirilebilir Proje Dosyaları</label>
                            
                            <!-- Existing files list -->
                            <?php if ($edit_mode && !empty($edit_file)): ?>
                                <div class="file-item-edit legacy-file" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 10px; border: 1px solid rgba(217, 119, 6, 0.3); border-radius: 6px; background: rgba(217, 119, 6, 0.02); margin-bottom: 15px;">
                                    <div style="flex: 1; min-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <span style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: #d97706; display: block; margin-bottom: 2px; font-weight: 700;">Eski Tekli Proje Dosyası</span>
                                        <a href="../uploads/<?php echo escape($edit_file); ?>" target="_blank" style="color: var(--accent); font-size: 0.9rem; text-decoration: none; font-weight: 600;"><?php echo escape($edit_file); ?></a>
                                    </div>
                                    <label style="color: var(--danger); font-size: 0.85rem; display: flex; align-items: center; gap: 4px; margin: 0; cursor: pointer;">
                                        <input type="checkbox" name="delete_legacy_file" value="1"> Sil
                                    </label>
                                </div>
                            <?php endif; ?>

                            <?php if ($edit_mode && !empty($uploaded_project_files)): ?>
                                <div class="existing-files-list mb-1" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                                    <?php foreach ($uploaded_project_files as $p_file): ?>
                                        <div class="file-item-edit" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--border); border-radius: 6px; background: rgba(255,255,255,0.02);">
                                            <div style="flex: 1; min-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <a href="../uploads/<?php echo escape($p_file['file_path']); ?>" target="_blank" style="color: var(--accent); font-size: 0.9rem; text-decoration: none; font-weight: 600;"><?php echo escape($p_file['file_path']); ?></a>
                                            </div>
                                            <div style="display: flex; gap: 5px;">
                                                <input type="text" name="existing_file_labels[<?php echo $p_file['id']; ?>]" value="<?php echo escape($p_file['file_label']); ?>" placeholder="Buton İsmi (TR)" style="width: 130px; font-size: 0.85rem; padding: 4px 8px; margin: 0;">
                                                <input type="text" name="existing_file_labels_en[<?php echo $p_file['id']; ?>]" value="<?php echo escape($p_file['file_label_en']); ?>" placeholder="Buton İsmi (EN)" style="width: 130px; font-size: 0.85rem; padding: 4px 8px; margin: 0;">
                                            </div>
                                            <label style="color: var(--danger); font-size: 0.85rem; display: flex; align-items: center; gap: 4px; margin: 0; cursor: pointer;">
                                                <input type="checkbox" name="delete_files[]" value="<?php echo $p_file['id']; ?>"> Sil
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Dynamic File Upload Container -->
                            <div id="dynamic-files-container" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px;">
                                <div class="file-upload-row" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 10px; border: 1px dashed var(--border); border-radius: 6px;">
                                    <input type="file" name="new_project_files[]" style="flex: 1; min-width: 180px;">
                                    <input type="text" name="new_project_file_labels[]" placeholder="Buton İsmi (TR - Örn: Kodlar)" style="width: 150px; font-size: 0.85rem; padding: 4px 8px; margin: 0;">
                                    <input type="text" name="new_project_file_labels_en[]" placeholder="Buton İsmi (EN - Örn: Codes)" style="width: 150px; font-size: 0.85rem; padding: 4px 8px; margin: 0;">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" style="padding: 4px 8px; margin: 0; display: none;">Kaldır</button>
                                </div>
                            </div>
                            
                            <button type="button" id="add-file-btn" class="btn btn-sm btn-secondary" style="margin-top: 5px;">+ Yeni Dosya Ekle</button>
                        </div>

                        <div class="form-group">
                            <label for="gallery_images">Proje Galerisi Resimleri (Ekstra)</label>
                            <?php if ($edit_mode && !empty($project_images)): ?>
                                <div class="gallery-previews-container mb-1" style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">
                                    <?php foreach ($project_images as $p_img): ?>
                                        <div class="gallery-preview-item" style="position: relative; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid var(--border);">
                                            <img src="../uploads/<?php echo escape($p_img['image_path']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <label style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(220,53,69,0.9); color: white; font-size: 10px; text-align: center; cursor: pointer; padding: 2px 0; margin: 0; display: block;">
                                                <input type="checkbox" name="delete_images[]" value="<?php echo $p_img['id']; ?>" style="margin-right: 3px;"> Sil
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="gallery_images" name="gallery_images[]" multiple accept="image/*">
                            <small class="form-text" style="color: var(--text-secondary); display: block; margin-top: 5px;">Birden fazla resim seçerek projeye galeri ekleyebilirsiniz.</small>
                        </div>

                        <div class="flex gap-1 mt-1">
                            <button type="submit" class="btn btn-primary"><?php echo $edit_mode ? 'Güncelle' : 'Kaydet'; ?></button>
                            <?php if ($edit_mode): ?>
                                <a href="portfolio.php" class="btn btn-secondary">İptal Et</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Projects List Card -->
                <div class="card">
                    <h3>Mevcut Projeler</h3>
                    <?php if (count($portfolio_items) === 0): ?>
                        <p class="empty-text">Henüz eklenmiş bir proje yok.</p>
                    <?php else: ?>
                        <div class="portfolio-list-admin">
                            <?php foreach ($portfolio_items as $item): ?>
                                <div class="portfolio-item-admin">
                                    <?php if (!empty($item['image_path']) && file_exists('../uploads/' . $item['image_path'])): ?>
                                        <img src="../uploads/<?php echo escape($item['image_path']); ?>" alt="<?php echo escape($item['title']); ?>" class="portfolio-thumb">
                                    <?php else: ?>
                                        <div class="portfolio-thumb-placeholder">
                                            <span><?php echo escape(mb_strtoupper(mb_substr($item['title'], 0, 1))); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="portfolio-info">
                                        <h4><?php echo escape($item['title']); ?></h4>
                                        <p class="portfolio-desc-brief"><?php echo mb_strimwidth(escape($item['description']), 0, 80, '...'); ?></p>
                                        <div class="actions">
                                            <a href="portfolio.php?edit=<?php echo $item['id']; ?>" class="btn btn-xs btn-primary-outline">Düzenle</a>
                                            <form action="portfolio.php" method="POST" onsubmit="return confirm('Bu projeyi silmek istediğinize emin misiniz? Sildiğinizde resmi de kalıcı olarak silinecektir.');" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo escape($token); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-danger-outline">Sil</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        const easyMDE = new EasyMDE({ 
            element: document.getElementById('content'),
            forceSync: true,
            spellChecker: false,
            maxHeight: "300px"
        });
        const easyMDE_en = new EasyMDE({ 
            element: document.getElementById('content_en'),
            forceSync: true,
            spellChecker: false,
            maxHeight: "300px"
        });

        // Dynamic File Upload Handlers
        const addFileBtn = document.getElementById('add-file-btn');
        const filesContainer = document.getElementById('dynamic-files-container');
        if (addFileBtn && filesContainer) {
            addFileBtn.addEventListener('click', () => {
                const newRow = document.createElement('div');
                newRow.className = 'file-upload-row';
                newRow.style = 'display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 10px; border: 1px dashed var(--border); border-radius: 6px; margin-top: 5px;';
                newRow.innerHTML = `
                    <input type="file" name="new_project_files[]" style="flex: 1; min-width: 180px;">
                    <input type="text" name="new_project_file_labels[]" placeholder="Buton İsmi (TR)" style="width: 150px; font-size: 0.85rem; padding: 4px 8px; margin: 0;">
                    <input type="text" name="new_project_file_labels_en[]" placeholder="Buton İsmi (EN)" style="width: 150px; font-size: 0.85rem; padding: 4px 8px; margin: 0;">
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" style="padding: 4px 8px; margin: 0;">Kaldır</button>
                `;
                filesContainer.appendChild(newRow);
            });
        }

        // Form upload loading overlay
        const form = document.getElementById('portfolio-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                const fileInputs = form.querySelectorAll('input[type="file"]');
                let hasFilesToUpload = false;
                fileInputs.forEach(input => {
                    if (input.files.length > 0) {
                        hasFilesToUpload = true;
                    }
                });

                if (hasFilesToUpload) {
                    e.preventDefault();

                    // Sync EasyMDE content before creating FormData
                    if (typeof easyMDE !== 'undefined') document.getElementById('content').value = easyMDE.value();
                    if (typeof easyMDE_en !== 'undefined') document.getElementById('content_en').value = easyMDE_en.value();

                    // Create and show loading overlay
                    let overlay = document.getElementById('upload-progress-overlay');
                    if (!overlay) {
                        overlay = document.createElement('div');
                        overlay.id = 'upload-progress-overlay';
                        overlay.style.cssText = `
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(8, 12, 20, 0.85);
                            backdrop-filter: blur(12px);
                            -webkit-backdrop-filter: blur(12px);
                            display: flex;
                            flex-direction: column;
                            justify-content: center;
                            align-items: center;
                            z-index: 99999;
                            color: white;
                            font-family: 'Outfit', sans-serif;
                        `;
                        overlay.innerHTML = `
                            <div style="background: rgba(20, 24, 33, 0.95); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 40px; width: 90%; max-width: 440px; text-align: center; box-shadow: 0 25px 60px rgba(0,0,0,0.65); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);">
                                <div style="display: inline-block; width: 60px; height: 60px; border: 4px solid rgba(255,255,255,0.05); border-radius: 50%; border-top-color: #d97706; animation: spin 1s linear infinite; margin-bottom: 25px;"></div>
                                <h4 style="margin: 0 0 10px 0; font-size: 1.4rem; color: #fff; font-weight: 700; letter-spacing: -0.02em;">Dosyalar Yükleniyor</h4>
                                <p style="margin: 0 0 25px 0; font-size: 0.9rem; color: #9ca3af; line-height: 1.5;">Yüksek boyutlu dosyalar aktarılıyor. Lütfen işlem tamamlanana kadar bekleyin, tarayıcıyı kapatmayın.</p>
                                
                                <div style="background: rgba(255, 255, 255, 0.05); border-radius: 8px; height: 8px; width: 100%; overflow: hidden; margin-bottom: 12px; position: relative; border: 1px solid rgba(255,255,255,0.03);">
                                    <div id="upload-progress-bar" style="background: linear-gradient(90deg, #d97706, #fbbf24); height: 100%; width: 0%; transition: width 0.1s ease-out; border-radius: 8px;"></div>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #e5e7eb; font-weight: 500; font-variant-numeric: tabular-nums;">
                                    <span id="upload-progress-percent">0%</span>
                                    <span id="upload-progress-bytes">0 MB / 0 MB</span>
                                </div>
                            </div>
                            <style>
                                @keyframes spin {
                                    to { transform: rotate(360deg); }
                                }
                            </style>
                        `;
                        document.body.appendChild(overlay);
                    }

                    const progressBar = document.getElementById('upload-progress-bar');
                    const progressPercent = document.getElementById('upload-progress-percent');
                    const progressBytes = document.getElementById('upload-progress-bytes');

                    function formatBytes(bytes, decimals = 2) {
                        if (bytes === 0) return '0 Bytes';
                        const k = 1024;
                        const dm = decimals < 0 ? 0 : decimals;
                        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                        const i = Math.floor(Math.log(bytes) / Math.log(k));
                        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
                    }

                    const formData = new FormData(form);
                    const xhr = new XMLHttpRequest();
                    const uploadUrl = window.location.pathname + window.location.search;
                    
                    xhr.open('POST', uploadUrl, true);

                    xhr.upload.onprogress = (evt) => {
                        if (evt.lengthComputable) {
                            const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            progressBar.style.width = percentComplete + '%';
                            progressPercent.textContent = percentComplete + '%';
                            progressBytes.textContent = formatBytes(evt.loaded) + ' / ' + formatBytes(evt.total);
                        }
                    };

                    xhr.onload = () => {
                        if (xhr.status === 200) {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(xhr.responseText, 'text/html');
                            const successAlert = doc.querySelector('.alert-success');
                            const errorAlert = doc.querySelector('.alert-error');

                            const isEditMode = form.querySelector('input[name="action"]').value === 'edit';
                            const editIdInput = form.querySelector('input[name="id"]');
                            const editId = editIdInput ? editIdInput.value : '';

                            let targetUrl = 'portfolio.php';

                            if (successAlert) {
                                const msg = successAlert.textContent.trim();
                                if (isEditMode && editId) {
                                    targetUrl += `?edit=${editId}&success_msg=${encodeURIComponent(msg)}`;
                                } else {
                                    targetUrl += `?success_msg=${encodeURIComponent(msg)}`;
                                }
                            } else if (errorAlert) {
                                const msg = errorAlert.textContent.trim();
                                if (isEditMode && editId) {
                                    targetUrl += `?edit=${editId}&error_msg=${encodeURIComponent(msg)}`;
                                } else {
                                    targetUrl += `?error_msg=${encodeURIComponent(msg)}`;
                                }
                            } else {
                                if (isEditMode && editId) {
                                    targetUrl += `?edit=${editId}`;
                                }
                            }

                            window.location.href = targetUrl;
                        } else {
                            alert('Bir yükleme hatası oluştu. Durum kodu: ' + xhr.status);
                            if (overlay) overlay.remove();
                        }
                    };

                    xhr.onerror = () => {
                        alert('Bağlantı hatası oluştu.');
                        if (overlay) overlay.remove();
                    };

                    xhr.send(formData);
                }
            });
        }
    </script>
</body>
</html>
