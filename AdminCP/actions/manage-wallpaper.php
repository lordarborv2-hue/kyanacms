<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { die('Access Denied.'); }
require_once '../../config.php';
$status = 'An unknown error occurred.';

if (isset($_FILES['wallpaper_file']) && $_FILES['wallpaper_file']['error'] == 0) {
    $target_dir = '../../uploads/';
    $settings_file = '../../Configuration/settings.json';
    $file = $_FILES['wallpaper_file'];
    $max_size = 5 * 1024 * 1024;
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($file['size'] > $max_size) {
        $status = 'Error: File is too large. Maximum size is 5MB.';
    } elseif (!in_array($mime_type, $allowed_types) || getimagesize($file['tmp_name']) === false) {
        $status = 'Error: Invalid file type. Only JPG, PNG, and GIF are allowed.';
    } else {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = 'wallpaper-' . uniqid() . '.' . $extension;
        $target_file = $target_dir . $new_filename;
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $settings = json_decode(file_get_contents($settings_file), true);
            $old_wallpaper = $settings['wallpaper_url'] ?? '';
            if ($old_wallpaper && basename($old_wallpaper) !== 'default-wallpaper.jpg' && file_exists('../../' . $old_wallpaper)) {
                unlink('../../' . $old_wallpaper);
            }
            $settings['wallpaper_url'] = 'uploads/' . $new_filename;
            file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
            $status = 'Wallpaper updated successfully!';
        } else {
            $status = 'Error: Failed to move the uploaded file.';
        }
    }
} else {
    $status = 'Error: No file uploaded or an upload error occurred.';
}
header('Location: ../dashboard.php?page=wallpaper&status=' . urlencode($status));
exit;