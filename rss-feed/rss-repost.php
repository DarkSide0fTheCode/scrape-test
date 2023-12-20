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

function fetch_article_content_from_url($url, $parserId) {
    print_r("entered Parser!\n");

    $response = wp_remote_get($url, array(
        'headers' => array(
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'accept-language' => 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7,ru;q=0.6',
            'cache-control' => 'max-age=0',
            'if-modified-since' => 'Sun, 17 Dec 2023 19:06:04 GMT',
            'sec-ch-ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'none',
            'sec-fetch-user' => '?1',
            'upgrade-insecure-requests' => '1'
        ),
        'httpversion' => '1.1',
        'sslverify' => false
    ));

    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        echo "Something went wrong: $error_message";
    } else {
        echo 'Response:<pre>';
        print_r( wp_remote_retrieve_body( $response ) );
        echo '</pre>';
    }

    if (is_wp_error($response)) {
        return 'Error: ' . $response->get_error_message();
    } else {
        print_r("Parser check passed!\n");

        $html = wp_remote_retrieve_body($response); // Retrieve the HTML content from the response

        // Extract the article content from the HTML (example using Simple HTML DOM Parser)
        require_once ABSPATH . 'wp-admin/includes/file.php'; // Include necessary files for working with the filesystem
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Load Simple HTML DOM Parser library
        if (!class_exists('simple_html_dom')) {
            require_once 'simple_html_dom.php'; // Replace 'path/to/simple_html_dom.php' with the correct path to the library
        }

        // Create a DOM object
        $dom = new simple_html_dom();
        
        // Load HTML content into the DOM object
        $dom->load($html);

        // Example: Find the article content by targeting a specific HTML element (you may need to adjust this based on the site structure)
        $article_content = '';
        $article_element = $dom->find('.article-content', 0); // Replace '.article-content' with the selector for the article content

        if ($article_element) {
            $article_content = $article_element->innertext(); // Get the inner HTML of the article element
        }

        // Clean up
        $dom->clear();
        unset($dom);

        print_r("Parsed content!\n");
        print_r($article_content);

        // Return the extracted article content
        return $article_content;
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
                $post_content = $item->get_content();
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

                fetch_article_content_from_url($post_link, $data['parserId']);
                
                // Custom tags to be inserted into the post content
                $custom_tags = '<p>' . implode('</p><p>', $post_tags) . '</p>';

                // Create a new post
                $new_post = array(
                    'post_title'    => $post_title,
                    'post_content'  => $post_content . $custom_tags, // Add custom tags to the content
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
