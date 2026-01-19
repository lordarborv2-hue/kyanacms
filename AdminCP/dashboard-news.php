<?php
$settings = json_decode(file_get_contents('../Configuration/settings.json'), true);
$news_data = json_decode(file_get_contents('../Configuration/news.json'), true);
foreach ($news_data as $server => $posts) {
    if (!empty($posts)) {
        usort($news_data[$server], function($a, $b) { return $b['id'] <=> $a['id']; });
    }
}
$edit_mode = false;
$edit_post = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $edit_server_key = ($_GET['server'] ?? '') . '_news';
    if (isset($news_data[$edit_server_key])) {
        foreach ($news_data[$edit_server_key] as $post) {
            if ($post['id'] == $edit_id) {
                $edit_mode = true;
                $edit_post = $post;
                $edit_post['server'] = $_GET['server'];
                break;
            }
        }
    }
}
?>
<form action="actions/manage-news.php" method="POST">
    <h2><?php echo $edit_mode ? 'Edit Post' : 'Add New Post'; ?></h2>
    <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'add'; ?>">
    <?php if ($edit_mode): ?><input type="hidden" name="id" value="<?php echo $edit_post['id']; ?>"><?php endif; ?>
    <label for="server">Select Server:</label>
    <select name="server" id="server">
        <option value="mid_rate" <?php if ($edit_mode && $edit_post['server'] === 'mid_rate') echo 'selected'; ?>><?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?></option>
        <option value="hard_rate" <?php if ($edit_mode && $edit_post['server'] === 'hard_rate') echo 'selected'; ?>><?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?></option>
    </select>
    <label for="subject">Subject:</label>
    <input type="text" id="subject" name="subject" value="<?php echo $edit_mode ? htmlspecialchars($edit_post['subject']) : ''; ?>" required>
    <label for="details">Details:</label>
    <textarea id="details-editor" name="details" required><?php 
        // Don't escape HTML content - the editor needs raw HTML
        echo $edit_mode ? $edit_post['details'] : ''; 
    ?></textarea>
    <button type="submit" class="button"><?php echo $edit_mode ? 'Update Post' : 'Add Post'; ?></button>
    <?php if ($edit_mode): ?>
        <a href="?page=news" class="button" style="background-color: #6c757d; display: inline-block; text-decoration: none; text-align: center; margin-left: 10px;">Cancel</a>
    <?php endif; ?>
</form>
<?php foreach(['mid_rate_news' => $settings['mid_rate_server']['name'], 'hard_rate_news' => $settings['hard_rate_server']['name']] as $key => $title): ?>
    <h2>Manage <?php echo $title; ?> News</h2>
    <ul class="news-list" id="<?php echo str_replace('_news', '', $key); ?>-news-list">
        <?php if (empty($news_data[$key])): echo '<li>No news posts yet.</li>'; else: foreach ($news_data[$key] as $post): ?>
            <li class="news-item">
                <div class="news-header">
                    <h3><?php echo htmlspecialchars($post['subject']); ?></h3>
                    <span class="post-date"><?php echo htmlspecialchars($post['date'] ?? 'No date'); ?></span>
                </div>
                <div class="collapsible-content">
                    <div class="news-content"><?php echo $post['details']; ?></div>
                    <div class="news-actions">
                        <a href="?page=news&action=edit&server=<?php echo str_replace('_news', '', $key); ?>&id=<?php echo $post['id']; ?>" class="button edit">Edit</a>
                        <form action="actions/manage-news.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="server" value="<?php echo str_replace('_news', '', $key); ?>">
                            <input type="hidden" name="id" value="<?php echo $post['id'] ?? ''; ?>">
                            <button type="submit" class="button delete">Delete</button>
                        </form>
                    </div>
                </div>
            </li>
        <?php endforeach; endif; ?>
    </ul>
<?php endforeach; ?>