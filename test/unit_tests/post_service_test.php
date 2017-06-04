<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../logging.php');
require_once(dirname(__FILE__) . '/../../post_service.php');
require_once(dirname(__FILE__) . '/../test_helper.php');
require_once(dirname(__FILE__) . '/../test_case.php');

class PostServiceTest extends TestCase {

    private $posts = array(
        'lexicon_post_1' => array(
            'ID' => 0,
            'post_title' => 'example-lexicon-post-test-case',
            'post_content' => 'Here is the lexicon article content.
                Here is a link to another lexicon article not mentioned in the links post:
                <a href="lexicon_post_2">Article Two</a>'
        ),
        'lexicon_post_2' => array(
            'ID' => 0,
            'post_title' => 'example-lexicon-post-test-case',
            'post_content' => 'Another lexicon article linking back to the first one:
                <a href="lexicon_post_1">Article One</a>'
        ),
        'mediaitem_post' => array(
            'ID' => 0,
            'post_title' => 'example-mediaitem-post-test-case',
            'post_content' => 'Hier Audio:
                [audio wav="http://localhost/wp-content/uploads/2017/03/audio.wav"][/audio]
                Und hier ein Video:
                [video width="960" height="540" mp4="http://localhost/wp-content/uploads/2017/05/video.mp4"][/video]
                Und hier noch ein Bild:
                <img class="size-full wp-image-1410" src="http://localhost/wp-content/uploads/2017/04/image.jpg" alt="" width="1280" height="800" />'
        ),
        'links_post' => array(
            'ID' => 0,
            'post_title' => 'example-lexicon-post-test-case',
            'post_content' => 'Here is a link to a lexicon article:
                <a href="lexicon_post_1">The lexicon link</a>
                And here is a bad link to an external resource:
                <a href="http://example.com">
                And now a mediaitem that should not be reported as a link though it might contain one:
                [audio wav="http://localhost/wp-content/uploads/2017/03/rec_20170123-1542.wav"][/audio]'
        )
    );

    private $mediaitem_urls = array(
        'http://localhost/wp-content/uploads/2017/03/audio.wav',
        'http://localhost/wp-content/uploads/2017/05/video.mp4',
        'http://localhost/wp-content/uploads/2017/04/image.jpg'
    );

    private $external_url = "http://example.com";


    public function __construct() {
        parent::__construct();
        $this->name = 'PostService Unit Test';
    }

    public function test_lexicon_category_exists() {
        // query wordpress to find out if the category is added
        $cat = get_category_by_slug(PostService::LEXICON_CATEGORY);

        $this->assert(!empty($cat),
            "The lexicon category should be present after plugin installation.");
    }

    public function test_parse_for_mediaitem_links() {
        $post = $this->posts['mediaitem_post'];
        $urls = PostService::parse_for_media_links($post);

        $this->check_urls($urls, $this->mediaitem_urls, "links for mediaitems");
    }

    public function test_parse_for_page_links() {
        $urls = PostService::parse_for_page_links($this->posts['links_post']);

        $expected_urls = array(
            $this->external_url,
            PostService::get_client_lexicon_url($this->posts['lexicon_post_1'])
        );

        $this->check_urls($urls, $expected_urls, "links to pages");
    }

    public function test_get_linked_lexicon_posts() {
        $post = $this->posts['links_post'];

        // the non-recursive call should only find one post
        $expected = array( $this->posts['lexicon_post_1'] );
        $posts = PostService::get_linked_lexicon_posts($post, false);
        $this->check_posts($posts, $expected, "non-recursive");

        // the recursive call should find one more linked from the first
        array_push($expected, $this->posts['lexicon_post_2']);
        $posts = PostService::get_linked_lexicon_posts($post, true);
        $this->check_posts($posts, $expected, "recursive");
    }

    public function do_test() {
        $this->test_lexicon_category_exists();

        $this->setup();

        $this->test_parse_for_mediaitem_links();
        $this->test_parse_for_page_links();
        $this->test_get_linked_lexicon_posts();

        $this->cleanup();
    }

    // insert the posts needed for testing
    private function setup() {
        // traverse all posts, insert them and save the inserted posts instead
        // of the array in this->posts
        foreach ($this->posts as $key => $post_array) {
            // set the lexicon category on the lexicon posts
            if ($key == 'lexicon_post_1' || $key == 'lexicon_post_2') {
                $post_array['post_category'] = array(
                    PostService::get_lexicon_category()->term_id
                );
            }

            $id = $this->helper->make_wp_post($post_array);
            $this->posts[$key] = get_post($id);
        }
        // now that all posts have urls, replace mentions of the posts
        // in links (mentions by key) with the actual urls of those posts
        foreach ($this->posts as $key => $haystack_post) {
            foreach ($this->posts as $needle => $needle_post) {
                $count = 0;
                $replacement = $needle_post->guid;
                $new_content = preg_replace("/$needle/", $replacement,
                    $haystack_post->post_content, -1, $count);

                if($count > 0) {
                    $haystack_post->post_content = $new_content;
                    $id = \wp_update_post($haystack_post);
                    $this->posts[$key] = \get_post($id);
                }
            }
        }
    }

    // delete the posts we creted for the test
    private function cleanup() {
        $this->helper->delete_wp_posts_created();
    }

    // check if a number of urls is included in a list
    private function check_urls($got_urls, $expected_urls, $test_name) {
        $this->assert(count($got_urls) === count($expected_urls),
            "Should find the right amount of urls. ($test_name)");

        foreach ($expected_urls as $expected_url) {
            $found = false;
            // wordpress might modify the url with query params, so just check
            // that the expecte url is the start of the found url
            foreach($got_urls as $url) {
                if(substr($url, 0, strlen($expected_url)) === $expected_url) {
                    $found = true;
                    break;
                }
            }
            $this->assert($found,
                "Url '$expected_url' should have been found. ($test_name)");
        }
    }

    // check that a number of posts is the same as expected
    private function check_posts($got_posts, $expected_posts, $test_name) {
        $this->assert(count($got_posts) === count($expected_posts),
            "Should find the right post amount. ($test_name)");

        foreach ($expected_posts as $expected) {
            $found = false;
            foreach ($got_posts as $got) {
                if($got->ID > 0 && $got->ID === $expected->ID) {
                    $found = true;
                    break;
                }
            }
            $this->assert($found,
                "Should find lexicon post: $expected->ID. ($test_name)");
        }
    }

}

// Create test, add it to the global test cases, then run
$post_service_test = new PostServiceTest();

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $post_service_test;

$post_service_test->do_test();
$post_service_test->report();

?>