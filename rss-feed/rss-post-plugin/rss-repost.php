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

function angiolino($url, $parserId) {
    // Prepare the URL for the GET request
    $request_url = 'http://87.17.176.223:3000/extract?url=' . urlencode($url);

    print_r($request_url);

    // Make the GET request
    $response = wp_remote_get($request_url);

    print_r($response);

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        echo "Something went wrong: $error_message";
        return;
    }

    // Get the body of the response
    $body = wp_remote_retrieve_body($response);

    // The body should be a JSON string, so decode it into an array
    $data = json_decode($body, true);

    // Check if the request was successful
    if ($data['success']) {
        // The article content should be in the 'data' field
        $article_content = $data['data']['articleContent'];

        // Return the article content
        return $article_content;
    } else {
        // Handle the error
        echo "Error: " . $data['error']['message'];
        return;
    }
}

// Create a function to fetch and process RSS feed items
function fetch_rss_feed_and_post_to_blog() {
    // Define the RSS feed URLs as an associative array with URL, corresponding image URL, and tags
    $rss_feed_urls = array(
        'https://www.orizzontescuola.it/feed/' => array(
            'image_url' => 'https://www.orizzontescuola.it/wp-content/uploads/2020/12/Classe-1536x1152.jpeg',
            'tags' => array('test-scuola', 'test-school'), // Add specific tags for this URL
            'category' => 3,
            'parserId' => '.entry-content'
        ),
        // Add more URLs, image URLs, and tags as needed
    );

    // Loop through each RSS feed URL, its associated image URL, and tags
    foreach ($rss_feed_urls as $rss_feed_url => $data) {
        $post_image = $data['image_url'];
        $post_tags = $data['tags'];
        $post_category = $data['category'];

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
                
                // Custom tags to be inserted into the post content
                $custom_tags = '<p>' . implode('</p><p>', $post_tags) . '</p>';

                // Create a new post
                $new_post = array(
                    'post_title'    => $post_title,
                    'post_content'  => $articleContent, // Add custom tags to the content
                    'post_date'     => $post_date,
                    'post_type'     => 'post',
                    'post_status'   => 'publish'
                );

                // Insert the post into the database
                $post_id = wp_insert_post($new_post);
                
                // Assign a specific category to the post
                wp_set_post_categories($post_id, array($post_category));

                // If an image was found in the feed item, set it as the post's featured image
                if ($post_image !== '') {
                    print_r("Image found in RSS\n");
                    $image = media_sideload_image($post_image, $post_id, $post_title);
                    if (!is_wp_error($image)) {
                        $image_url = wp_get_attachment_image_src($image, 'full');
                        set_post_thumbnail($post_id, $image_url[0]);
                    }
                } else {
                    // Set a default image as the post's featured image
                    print_r("Image not found in RSS - using default image \n");
                    print_r($data['image_url']);
                    $image = media_sideload_image($data['image_url'], $post_id, 'Default Image RSS Made');
                    if (!is_wp_error($image)) {
                        print_r("Dio \n");
                        print_r($image);
                        $image_url = wp_get_attachment_image_src($image, 'full');
                        set_post_thumbnail($post_id, $image_url[0]);
                    }
                }

            }
        }
    }
}

// Schedule the function to run at regular intervals
function schedule_fetch_rss_feed() {
    if (!wp_next_scheduled('fetch_rss_feed_event')) {
        wp_schedule_event(time(), 'hourly', 'fetch_rss_feed_event');
    }
}
add_action('wp', 'schedule_fetch_rss_feed');

// Hook the fetch_rss_feed_and_post_to_blog function to the scheduled event
add_action('fetch_rss_feed_event', 'fetch_rss_feed_and_post_to_blog');
