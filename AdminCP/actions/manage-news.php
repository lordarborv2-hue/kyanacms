<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { die('Access Denied.'); }
require_once '../../config.php';
date_default_timezone_set('Asia/Singapore');
$news_file = '../../Configuration/news.json';
$news_data = json_decode(file_get_contents($news_file), true);
$action = $_POST['action'] ?? '';
$server_key = ($_POST['server'] ?? '') . '_news';
if (!array_key_exists($server_key, $news_data)) { die('Invalid server specified.'); }

if ($action === 'add') {
    $new_post = ['id' => time(), 'date' => date('F j, Y, g:i a'), 'subject' => $_POST['subject'] ?? 'No Subject', 'details' => $_POST['details'] ?? ''];
    array_unshift($news_data[$server_key], $new_post);
    $status = 'Post added successfully.';
} elseif ($action === 'edit') {
    $post_id_to_edit = $_POST['id'] ?? '';
    foreach ($news_data[$server_key] as $index => $post) {
        if ($post['id'] == $post_id_to_edit) {
            $news_data[$server_key][$index]['subject'] = $_POST['subject'] ?? 'No Subject';
            $news_data[$server_key][$index]['details'] = $_POST['details'] ?? '';
            break;
        }
    }
    $status = 'Post updated successfully.';
} elseif ($action === 'delete') {
    $post_id_to_delete = $_POST['id'] ?? '';
    $filtered_posts = array_filter($news_data[$server_key], function($post) use ($post_id_to_delete) {
        if (!is_array($post) || !isset($post['id'])) return true; 
        return (int)$post['id'] != (int)$post_id_to_delete;
    });
    $news_data[$server_key] = array_values($filtered_posts);
    $status = 'Post deleted successfully.';
}
file_put_contents($news_file, json_encode($news_data, JSON_PRETTY_PRINT));
header('Location: ../dashboard.php?page=news&status=' . urlencode($status));
exit;