<?php
/*
Plugin Name: RSS Feed to Blog Post
Description: Fetches RSS feed items and posts them as blog posts with customization options.
Version: 0.1
Author: DarkSideOfTheCode
*/

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');




function echo_log($what)
{
    echo '<pre>' . print_r($what, true) . '</pre>';
}

// Function to add a new menu item in the WordPress admin dashboard
function my_admin_menu()
{
    // add_menu_page function adds a new top-level menu to the WordPress admin interface
    // Parameters are: page title, menu title, capability, menu slug, function to display the page content
    add_menu_page('MalibúTech RSS', 'MalibúTech RSS', 'manage_options', 'malibutech_feed', 'malibutech_feed_callback');
}

// Function to display the content of the custom admin page
function malibutech_feed_callback()
{
    // Start of the page content
    echo '<div class="wrap">';
    // Instructions for the user
    echo '<p>Clicca il bottone per scaricare le news.</p>';
    // Start of the form
    echo '<form method="POST">';
    // Hidden input field to indicate that the RSS feed should be fetched when the form is submitted
    echo '<input type="hidden" name="fetch_rss_feed" value="1">';
    // Submit button
    echo '<input type="submit" value="Scarica News dai Feed RSS">';
    // End of the form
    echo '</form>';
    // End of the page content
    echo '</div>';

    // Check if the form has been submitted
    if (isset($_POST['fetch_rss_feed'])) {
        // Call the function to fetch the RSS feed and post to blog
        fetch_rss_feed_and_post_to_blog();
        // Display a message to indicate that the RSS feed has been fetched and posted to blog
        echo '<p>News scaricate dai Feed RSS e postate sul blog.</p>';
    }
}


function remove_non_paragraph_elements($article_content) {
    $dom = new DOMDocument;

    // Load the HTML into the DOMDocument instance
    // The @ before the method call suppresses any warnings that
    // loadHTML might throw because of invalid HTML in the input
    @$dom->loadHTML($article_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    // Create a new DOMXPath instance
    $xpath = new DOMXPath($dom);

    // Query all elements that are not <p>
    $nodes = $xpath->query("//*[not(self::p)]");

    // Remove each node from its parent
    foreach ($nodes as $node) {
        $node->parentNode->removeChild($node);
    }

    // Save the updated HTML
    $article_content = $dom->saveHTML();

    return $article_content;
}

function angiolino($url, $parserId)
{
    // Prepare the URL for the GET request
    echo_log("Preparing to fetch: " . $url . "\n");
    $request_url = 'http://87.17.176.223:3000/extract?url=' . urlencode($url);
    echo_log("\n");

    // echo_log($request_url);

    // Make the GET request
    $response = wp_remote_get($request_url);

    // echo_log($response);

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        echo "Something went wrong: $error_message";
        return;
    }

    // Get the body of the response
    echo_log("Preparing to retrieve body \n");

    $body = wp_remote_retrieve_body($response);

    echo_log("Body retrieved! \n");

    // The body should be a JSON string, so decode it into an array
    echo_log("Preparing to retrieve data \n");

    $data = json_decode($body, true);

    echo_log("data retrieved! \n");


    // Check if the request was successful
    if ($data['success']) {
        // The article content should be in the 'data' field
        $article_content = $data['data']['articleContent'];
        $cleaned_content = remove_non_paragraph_elements($article_content);
        // Return the article content
        return $article_content;
    } else {
        // Handle the error
        echo "Error: " . $data['error']['message'];
        return;
    }
}

// Create a function to fetch and process RSS feed items
function fetch_rss_feed_and_post_to_blog()
{
    // Define the RSS feed URLs as an associative array with URL, corresponding image URL, and tags
    $rss_feed_urls = array(
        'https://www.orizzontescuola.it/feed/' => array(
            'image_url' => 'https://img.freepik.com/free-photo/students-knowing-right-answer_329181-14271.jpg?w=996&t=st=1703699259~exp=1703699859~hmac=f226ad29a042afc6e3a7142709dfc47c05bdcd2bec12972541fc9a35c70dd7b0',
            'tags' => array('test-scuola', 'test-school'), // Add specific tags for this URL
            'category' => "Notizie",
            'parserId' => '.entry-content',
            'post_author' => 2, // Set the author

        ),
        // Add more URLs, image URLs, and tags as needed
    );

    // Loop through each RSS feed URL, its associated image URL, and tags
    foreach ($rss_feed_urls as $rss_feed_url => $data) {
        $post_image = $data['image_url'];
        $image_url = $data['image_url'];
        $post_tags = $data['tags'];
        $post_category = $data['category'];
        $post_author = $data['post_author'];

        // Fetch the RSS feed
        $rss = fetch_feed($rss_feed_url);

        if (!is_wp_error($rss)) {
            // Get the RSS feed items
            $max_items = $rss->get_item_quantity(10); // Change 10 to the number of items you want to fetch
            $rss_items = $rss->get_items(0, $max_items);

            // Loop through each feed item
            foreach ($rss_items as $item) {
                // Get the necessary data from the feed item
                $post_title = $item->get_title();
                // $post_content = $item->get_content(); 
                $post_date = $item->get_date('Y-m-d H:i:s');
                $post_link = $item->get_permalink();


                // Extracting image if available in the feed item
                $post_image = ''; // Initialize the variable for the image URL
                $enclosures = $item->get_enclosures();
                if (!empty($enclosures)) {
                    foreach ($enclosures as $enclosure) {
                        if ($enclosure->get_type() === 'image/jpeg' || $enclosure->get_type() === 'image/png') {
                            $post_image = $enclosure->get_link();
                            break; // Stop after finding the first image enclosure
                        }
                    }
                }

                $articleContent = angiolino($post_link, $data['parserId']);


                // Create a new post
                $new_post = array(
                    'post_title' => $post_title,
                    'post_content' => $articleContent, // Add custom tags to the content
                    'post_date' => $post_date,
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'post_author' => $post_author // Set the author

                );

                // Insert the post into the database
                $post_id = wp_insert_post($new_post);

                // Set the post as not commentable
                wp_update_post(array(
                    'ID' => $post_id,
                    'comment_status' => 'closed',
                ));

                echo_log("Analizing: " . $post_link . "\n");
                echo_log("Post ID: " . $post_id . "\n");


                // Check if the category exists
                $category_exists = term_exists($post_category, 'category');

                if ($category_exists == 0 || $category_exists == null) {
                    // Category doesn't exist, create a new one
                    $new_category_id = wp_insert_category(array('cat_name' => $post_category));
                    // Assign the new category to the post
                    wp_set_post_categories($post_id, array($new_category_id));
                } else {
                    // Category exists, assign it to the post
                    wp_set_post_categories($post_id, array($category_exists['term_id']));
                }

                // Set the tags for the post
                wp_set_post_tags($post_id, $post_tags, false);


                // If an image was found in the feed item, set it as the post's featured image
                if ($post_image !== '') {
                    echo_log("Image found in RSS\n");
                    $image = media_sideload_image($post_image, $post_id, $post_title);
                    if (!is_wp_error($image)) {
                        $image_url = wp_get_attachment_image_src($image, 'full');
                        set_post_thumbnail($post_id, $image_url[0]);
                    }
                } else {
                    // Set a default image as the post's featured image
                    echo_log("Image not found in RSS - using default image \n");
                    echo_log($image_url);
                    echo_log("\n");
                    $media = media_sideload_image($image_url, $post_id, $desc = "Test image", $return = 'id');
                    $media_2 = media_sideload_image($image_url, $post_id, $desc = "Test image 2");
                    echo_log($media);
                    echo_log("\n");
                    echo_log($media_2);
                    echo_log("\n");
                    echo_log(is_wp_error($media));
                    echo_log("\n");

                    if (!is_wp_error($media)) {
                        set_post_thumbnail($post_id, $media);
                    }
                }


            }
        }
    }
}

// Schedule the function to run at regular intervals
function schedule_fetch_rss_feed()
{
    if (!wp_next_scheduled('fetch_rss_feed_event')) {
        wp_schedule_event(time(), 'hourly', 'fetch_rss_feed_event');
    }
}
add_action('wp', 'schedule_fetch_rss_feed');
add_action('fetch_rss_feed_event', 'fetch_rss_feed_and_post_to_blog');
add_action('admin_menu', 'my_admin_menu');

