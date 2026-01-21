<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) { die('Access Denied.'); }
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $news_file = '../../Configuration/news.json';
    
    // Create file if missing
    if (!file_exists($news_file)) {
        file_put_contents($news_file, json_encode(['mid_rate_news' => [], 'hard_rate_news' => []]));
    }
    
    $news_data = json_decode(file_get_contents($news_file), true);
    $action = $_POST['action'] ?? '';
    $server = $_POST['server'] ?? 'mid_rate';
    $target_key = ($server === 'hard_rate') ? 'hard_rate_news' : 'mid_rate_news';

    // --- ADD POST ---
    if ($action === 'add_post') {
        $new_post = [
            'subject' => $_POST['subject'],
            'details' => $_POST['details'],
            'date'    => date('M d, Y')
        ];
        
        // Add to beginning of array
        array_unshift($news_data[$target_key], $new_post);
        $status = "News posted successfully!";
    }

    // --- DELETE POST ---
    elseif ($action === 'delete_post') {
        $index = (int)$_POST['index'];
        if (isset($news_data[$target_key][$index])) {
            array_splice($news_data[$target_key], $index, 1); // Remove item and re-index
            $status = "News deleted successfully!";
        } else {
            $status = "Error: Post not found.";
        }
    }

    // --- UPDATE POST ---
    elseif ($action === 'update_post') {
        $index = (int)$_POST['post_index'];
        if (isset($news_data[$target_key][$index])) {
            // Update fields
            $news_data[$target_key][$index]['subject'] = $_POST['subject'];
            $news_data[$target_key][$index]['details'] = $_POST['details'];
            // Optional: Update date on edit? 
            // $news_data[$target_key][$index]['date'] = date('M d, Y') . ' (Edited)';
            
            $status = "News updated successfully!";
        } else {
            $status = "Error: Post to update not found.";
        }
    }

    // SAVE & REDIRECT
    file_put_contents($news_file, json_encode($news_data, JSON_PRETTY_PRINT));
    header('Location: ../dashboard.php?page=news&status=' . urlencode($status));
    exit;
}
?>