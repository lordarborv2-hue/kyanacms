<?php
$settings_file = '../Configuration/settings.json';
$news_file = '../Configuration/news.json';

$settings = json_decode(file_get_contents($settings_file), true);
$news_data = file_exists($news_file) ? json_decode(file_get_contents($news_file), true) : ['mid_rate_news' => [], 'hard_rate_news' => []];
?>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

<h2 style="text-align:center; margin-bottom:20px;">Manage News</h2>

<div style="background:#fff; padding:20px; border:1px solid #ddd; border-radius:5px; max-width: 900px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <h3 id="form-title" style="margin-top:0;">Add New Post</h3>
    
    <form action="actions/manage-news.php" method="POST" id="news-form">
        <input type="hidden" name="action" id="form-action" value="add_post">
        <input type="hidden" name="post_index" id="post_index" value="">

        <div style="display:flex; gap:15px; margin-bottom:15px;">
            <div style="flex:1;">
                <label for="server_select" style="font-weight:bold; display:block; margin-bottom:5px;">Select Server:</label>
                <select id="server_select" name="server" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
                    <option value="mid_rate"><?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?></option>
                    <option value="hard_rate"><?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?></option>
                </select>
            </div>
            <div style="flex:2;">
                <label for="subject" style="font-weight:bold; display:block; margin-bottom:5px;">Subject:</label>
                <input type="text" id="subject" name="subject" placeholder="Enter news title..." required 
                       style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;">
            </div>
        </div>

        <label style="font-weight:bold; display:block; margin-bottom:5px;">Details:</label>
        
        <textarea id="details" name="details"></textarea>

        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="submit" id="submit-btn" class="button" style="flex:1; padding:12px; font-size:16px; background:#007bff; color:white; border:none; cursor:pointer;">Add Post</button>
            <button type="button" id="cancel-btn" class="button" style="flex:1; padding:12px; background:#6c757d; color:white; border:none; cursor:pointer; display:none;" onclick="resetForm()">Cancel Edit</button>
        </div>
    </form>
</div>

<div style="max-width: 900px; margin: 40px auto;">
    <h3>Existing News Posts</h3>

    <h4 style="background:#eebb00; color:#000; padding:10px; border-radius:5px 5px 0 0; margin-bottom:0;"><?php echo htmlspecialchars($settings['mid_rate_server']['name']); ?></h4>
    <table style="width:100%; border-collapse:collapse; background:#fff; margin-bottom:30px; border:1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <thead>
            <tr style="background:#f8f9fa; text-align:left; border-bottom:2px solid #ddd;">
                <th style="padding:12px; width:120px;">Date</th>
                <th style="padding:12px; width:200px;">Subject</th>
                <th style="padding:12px;">Preview</th>
                <th style="padding:12px; width:180px; text-align:center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($news_data['mid_rate_news'])): ?>
                <?php foreach ($news_data['mid_rate_news'] as $index => $post): ?>
                <?php 
                    // Base64 Encode to prevent special characters breaking JS
                    $safe_subject = base64_encode($post['subject']);
                    $safe_details = base64_encode($post['details']);
                ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; color:#666; font-size:0.9em;"><?php echo $post['date']; ?></td>
                    <td style="padding:12px; font-weight:bold; color:#333;"><?php echo $post['subject']; ?></td>
                    <td style="padding:12px; color:#555; font-size:0.85em; font-style:italic;">
                        <?php echo strip_tags(substr($post['details'] ?? '', 0, 100)); ?>...
                    </td>
                    <td style="padding:12px;">
                        <div style="display:flex; justify-content:center; align-items:center; gap:8px;">
                            <button type="button" 
                                    onclick="editPost(this)" 
                                    data-server="mid_rate"
                                    data-index="<?php echo $index; ?>"
                                    data-subject="<?php echo $safe_subject; ?>"
                                    data-details="<?php echo $safe_details; ?>"
                                    style="background:#007bff; color:#fff; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; font-size:0.9em; line-height:1;">
                                Edit
                            </button>
                            
                            <form action="actions/manage-news.php" method="POST" style="margin:0; padding:0; display:inline-block;">
                                <input type="hidden" name="action" value="delete_post">
                                <input type="hidden" name="server" value="mid_rate">
                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                <button type="submit" onclick="return confirm('Delete this post?');" 
                                        style="background:#dc3545; color:#fff; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; font-size:0.9em; line-height:1;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="padding:20px; text-align:center; color:#999;">No news found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h4 style="background:#dc3545; color:#fff; padding:10px; border-radius:5px 5px 0 0; margin-bottom:0;"><?php echo htmlspecialchars($settings['hard_rate_server']['name']); ?></h4>
    <table style="width:100%; border-collapse:collapse; background:#fff; border:1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
        <thead>
            <tr style="background:#f8f9fa; text-align:left; border-bottom:2px solid #ddd;">
                <th style="padding:12px; width:120px;">Date</th>
                <th style="padding:12px; width:200px;">Subject</th>
                <th style="padding:12px;">Preview</th>
                <th style="padding:12px; width:180px; text-align:center;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($news_data['hard_rate_news'])): ?>
                <?php foreach ($news_data['hard_rate_news'] as $index => $post): ?>
                <?php 
                    $safe_subject = base64_encode($post['subject']);
                    $safe_details = base64_encode($post['details']);
                ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; color:#666; font-size:0.9em;"><?php echo $post['date']; ?></td>
                    <td style="padding:12px; font-weight:bold; color:#333;"><?php echo $post['subject']; ?></td>
                    <td style="padding:12px; color:#555; font-size:0.85em; font-style:italic;">
                        <?php echo strip_tags(substr($post['details'] ?? '', 0, 100)); ?>...
                    </td>
                    <td style="padding:12px;">
                        <div style="display:flex; justify-content:center; align-items:center; gap:8px;">
                            <button type="button"
                                    onclick="editPost(this)" 
                                    data-server="hard_rate"
                                    data-index="<?php echo $index; ?>"
                                    data-subject="<?php echo $safe_subject; ?>"
                                    data-details="<?php echo $safe_details; ?>"
                                    style="background:#007bff; color:#fff; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; font-size:0.9em; line-height:1;">
                                Edit
                            </button>
                            
                            <form action="actions/manage-news.php" method="POST" style="margin:0; padding:0; display:inline-block;">
                                <input type="hidden" name="action" value="delete_post">
                                <input type="hidden" name="server" value="hard_rate">
                                <input type="hidden" name="index" value="<?php echo $index; ?>">
                                <button type="submit" onclick="return confirm('Delete this post?');" 
                                        style="background:#dc3545; color:#fff; border:none; padding:8px 12px; border-radius:4px; cursor:pointer; font-size:0.9em; line-height:1;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="padding:20px; text-align:center; color:#999;">No news found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    $(document).ready(function() {
        // 1. Initialize Summernote with YOUR PREFERRED TOOLBAR
        $('#details').summernote({
            placeholder: 'Write your news details here...',
            tabsize: 2,
            height: 300,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear', 'fontname', 'fontsize']], // Font settings
                ['color', ['color']], // Color setting
                ['para', ['ul', 'ol', 'paragraph']], // Lists and Paragraph
                ['table', ['table']], // Table support
                ['insert', ['link', 'picture', 'video']], // Media
                ['view', ['fullscreen', 'codeview', 'help']] // View options
            ]
        });

        // 2. Force Sync on Submit
        $('#news-form').on('submit', function() {
            var content = $('#details').summernote('code');
            document.getElementById('details').value = content;
        });
    });

    // 3. EDIT POST FUNCTION
    function editPost(btn) {
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Get Attributes
        var server = btn.getAttribute('data-server');
        var index = btn.getAttribute('data-index');
        var b64Subject = btn.getAttribute('data-subject');
        var b64Details = btn.getAttribute('data-details');

        // Decode Base64 safely
        try {
            var subject = decodeURIComponent(escape(window.atob(b64Subject)));
            var details = decodeURIComponent(escape(window.atob(b64Details)));
        } catch (e) {
            console.error("Decoding error", e);
            var subject = "";
            var details = "";
        }

        // Fill Form Fields
        document.getElementById('form-title').textContent = "Edit Existing Post";
        document.getElementById('form-action').value = "update_post";
        document.getElementById('post_index').value = index;
        document.getElementById('server_select').value = server;
        document.getElementById('subject').value = subject;
        
        // Load into Editor (Without Resetting first, to avoid clearing glitches)
        $('#details').summernote('code', details);

        // Change Button State
        var submitBtn = document.getElementById('submit-btn');
        submitBtn.textContent = "Update Post";
        submitBtn.style.background = "#007bff"; // Ensure blue
        
        document.getElementById('cancel-btn').style.display = "block";
    }

    // 4. RESET FORM FUNCTION
    function resetForm() {
        document.getElementById('form-title').textContent = "Add New Post";
        document.getElementById('form-action').value = "add_post";
        document.getElementById('post_index').value = "";
        document.getElementById('subject').value = "";
        
        // Clear Editor
        $('#details').summernote('reset');
        
        // Reset Buttons
        var submitBtn = document.getElementById('submit-btn');
        submitBtn.textContent = "Add Post";
        submitBtn.style.background = "#007bff"; 
        
        document.getElementById('cancel-btn').style.display = "none";
    }
</script>