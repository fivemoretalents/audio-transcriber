<?php
/*
Plugin Name: Audio Transcriber
Description: A simple plugin to upload and transcribe audio files to a WordPress post.
Version: 1.0
Author: Kade
*/

// Enqueue audio-transcriber styling sheet
function audio_transcriber_enqueue_styles() {
    wp_enqueue_style('audio-transcriber-styles', plugin_dir_url(__FILE__) . 'audio-transcriber.css');
}
add_action('admin_enqueue_scripts', 'audio_transcriber_enqueue_styles');

// Add a menu item
function audio_transcriber_add_menu_page() {
    add_menu_page(
        'Audio Transcriber',        // Page title
        'Audio Transcriber',        // Menu title
        'manage_options',           // Reserved for admin users
        'audio-transcriber',        // Menu slug
        'audio_transcriber_display_page', // Callback function
        'dashicons-media-audio',    // Icon URL
        6                           // Position
    );
}
add_action('admin_menu', 'audio_transcriber_add_menu_page');

// Display the menu page content
function audio_transcriber_display_page() {
    ?>
    <div class="wrap">
        <h1>Audio Transcriber</h1>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
            <label for="audioFile">Select audio file:</label>
            <input type="file" name="audioFile" id="audioFile" accept="audio/*" required>
            <br><br>
            <label for="postTitle">Post Title:</label>
            <input type="text" name="postTitle" id="postTitle" required>
            <br><br>
            <label for="postCategory">Select Category:</label>
            <?php
            wp_dropdown_categories(array(
                'name' => 'postCategory',
                'hide_empty' => 0,
                'orderby' => 'name',
                'selected' => 0,
                'hierarchical' => true,
                'show_option_none' => 'None'
            ));
            ?>
            <br><br>
            <input type="submit" value="Upload and Transcribe">
            <input type="hidden" name="action" value="handle_audio_upload">
        </form>
    </div>
    <?php
}


// Handle file upload and create a post
function handle_audio_upload() {
    if (!empty($_FILES['audioFile']['name'])) {
        $uploaded_file = $_FILES['audioFile'];
        // Sanatize text
        $post_title = sanitize_text_field($_POST['postTitle']);
        $post_category = intval($_POST['postCategory']); // Sanitize category

        // Check for errors
        if ($uploaded_file['error'] != UPLOAD_ERR_OK) {
            wp_die('An error occurred while uploading the file.');
        }

        // Move the uploaded file to the WordPress uploads directory
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploaded_file, $upload_overrides);

        // Verify file was uploaded
        if ($movefile && !isset($movefile['error'])) {
           
            // API endpoint and parameters
            $url = 'https://api.lemonfox.ai/v1/audio/transcriptions';
            $apiKey = 'KEY'; //Must provide a valid api key from lemonfox api
            $language = 'english';
            $responseFormat = 'text';

            // Initialize cURL session
            $curl = curl_init();

            // Set cURL options
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $apiKey
                ),
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => array(
                    'file' => new CURLFile($movefile['file']),
                    'language' => $language,
                    'response_format' => $responseFormat
                )
            ));

            // Execute cURL session
            $response = curl_exec($curl);

            // Check for errors
            if (curl_errno($curl)) {
                echo 'Error: ' . curl_error($curl);
            } else {
                // Close cURL session
                curl_close($curl);

                // Handle response and create a new post
                $post_content = sanitize_text_field($response);

                // Create post object
                $new_post = array(
                    'post_title'   => $post_title,
                    'post_content' => $post_content,
                    'post_status'  => 'publish',
                    'post_author'  => get_current_user_id(),
                    'post_type'    => 'post',
                    'post_category' => array($post_category) // Set post category
                );

                // Insert the post into the database
                $post_id = wp_insert_post($new_post);

                if ($post_id) {
                    echo '<div class="updated"><p>Post created successfully! <a href="' . get_permalink($post_id) . '">View Post</a></p></div>';
                } else {
                    wp_die('Error creating post.');
                }
            }
        } else {
            wp_die($movefile['error']);
        }
    } else {
        wp_die('No file was uploaded.');
    }
}

add_action('admin_post_handle_audio_upload', 'handle_audio_upload');
?>

