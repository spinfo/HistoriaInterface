<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/db.php');

/**
 * WP_Post objects in the tour manager are used as:
 *  - pages of a mapstops
 *  - lexicon articles
 *
 * This static service class offers utility methods to deal with such posts.
 */
class PostService {

    const CLIENT_LEXICON_URL_SCHEME = 'lexicon://%d';
    const CLIENT_LEXICON_URL_REGEX = '/^lexicon:\/\/([0-9]+)/';

    const LEXICON_CATEGORY = 'Lexikon';

    const LEXICON_TITLE_PREFIX = 'Lex: ';

    const MEDIAITEM_XPATHS = array('//audio', '//video', '//source', '//img');

    const HTML_TEMPLATE = '
        <html>
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            </head>
            <body>
                %s
            </body>
        </html>';

    /**
     * Return the Lexicon posts category.
     */
    public static function get_lexicon_category() {
        return \get_category_by_slug(self::LEXICON_CATEGORY);
    }
    /**
     * Any action, that should be applied to the raw html content of a post
     * before publishing is performed in here.
     *
     * NOTE: This does some expensive stuff and might be a good place to start
     * refactoring if performance becomes an issue in publishing tours
     *
     * @return string
     */
    public static function get_content_to_publish($post) {
        // apply wordpress shortcodes to the post content
        $content = \do_shortcode($post->post_content);

        // apply the wordpress paragraph (i.e. <p>-tag) substitution
        $content = \wpautop($content);

        // if the post has links to lexicon entries, replace those with the
        // lexicon url scheme used by the client
        // use the private function _parse_for..., to not introduce a circle
        $urls = self::_parse_for_page_links($content);
        foreach ($urls as $url) {
            $id = self::get_post_id($url);
            if($id > 0) {
                $post = \get_post($id);
                if(!empty($post)) {
                    $new_url = self::get_client_lexicon_url($post);
                    $content = str_replace($url, $new_url, $content);
                } else {
                    debug_log("Failed to get post for link: $url");
                }
            }
        }

        // if the post contains thumbnail urls these will be replaced with
        // the full sized image's url
        $urls = self::_parse_for_media_links($content);
        foreach ($urls as $url) {
            $new_url = self::_cleanup_thumbnail_url($url);
            if($new_url !== $url) {
                $content = str_replace($url, $new_url, $content);
            }
        }

        return $content;
    }

    /**
     * Return the content of the 'src' attribute of all contained audio-,
     * video-, source- and img-tags.
     *
     * @return array    An array of strings with the linked mediaitem-urls
     */
    public static function parse_for_media_links($post) {
        $content_to_publish = self::get_content_to_publish($post);
        return self::_parse_for_media_links($content_to_publish);
    }

    // parses all media links from a piece of html
    private static function _parse_for_media_links($html) {
        $dom = self::parse_to_dom_xpath($html);

        $result = array();
        foreach (self::MEDIAITEM_XPATHS as $expression) {
            $urls = self::_get_attr_values($dom->query($expression), 'src');
            $result = array_merge($result, $urls);
        }
        return array_unique($result);
    }

    /**
     * Whether the post links to a media item (audio, video, image)
     *
     * @return bool
     */
    public static function links_to_media($post) {
        return (count(self::parse_for_media_links($post)) > 0);
    }

    /**
     * Whether the post is a lexicon post.
     *
     * @return bool
     */
    public static function is_lexicon_post($post) {
        return ($post instanceof \WP_Post) &&
            \has_category(self::LEXICON_CATEGORY, $post);
    }


    /**
     * Parse a posts content for links to other pages, returns values of 'href'-
     * Attributes of contained 'a' elements, excluding those meant for
     * mediaitems.
     *
     * @return array    An array of Strings
     */
    public static function parse_for_page_links($post) {
        $content = self::get_content_to_publish($post);
        return self::_parse_for_page_links($content);
    }

    public static function get_post_id($url) {
        $matches = array();
        if(preg_match(self::CLIENT_LEXICON_URL_REGEX, $url, $matches)) {
            $id = (int) $matches[1];
        } else {
            $id = \url_to_postid($url);
        }
        return $id;
    }

    /**
     * A lexicon post may have a title with a certain prefix. This returns the
     * the title without that prefix.
     *
     * @return string
     */
    public static function get_lexicon_post_title($post) {
        if(empty($post) || empty($post->post_title) ||
            !is_string($post->post_title))
        {
            return "";
        }
        return preg_replace('/^' . self::LEXICON_TITLE_PREFIX . '/', '',
            $post->post_title);
    }

    // internal function to parse a posts content
    private static function _parse_for_page_links($post_content) {
        $dom = self::parse_to_dom_xpath($post_content);

        // get '<a>' tags but not those below audio or video elements
        $anchors = $dom->query('//*[not(self::audio or self::video)]/a');
        return array_unique(self::_get_attr_values($anchors, 'href'));
    }


    /**
     * Retrieve all lexicon articles linked to from a post or a number of posts.
     *
     * NOTE: If recursive, this will fetch all lexicon articles linked to from
     * posts found, which makes this a potentially (very) expensive operation.
     *
     * @param $posts    mixed   A WP_Post object or an array of those.
     * @param $recurse  boolean Whether to recursively fetch lexicon posts.
     *                          (default: false)
     *
     * @return array    An array of WP_Post pbjects.
     */
    public static function get_linked_lexicon_posts($posts, $recurse = false) {
        $results_by_id = array();

        if(is_array($posts)) {
            foreach ($posts as $post) {
                self::_get_linked_lexicon_posts($post, $results_by_id,
                    $recurse);
            }
        } else {
            self::_get_linked_lexicon_posts($posts, $results_by_id, $recurse);
        }

        return array_values($results_by_id);
    }

    /**
     * Return the url the client uses for identification of lexicon articles.
     * Return an empty string if the post is not a post / not a lexicon post.
     *
     * @return string
     */
    public static function get_client_lexicon_url($post) {
        if(!self::is_lexicon_post($post)) {
            return "";
        }
        return sprintf(self::CLIENT_LEXICON_URL_SCHEME, $post->ID);
    }

    /**
     * Retrieve all media as attachments linked to a post (accepts post id as
     * input.
     *
     * @return array    An array of attachments, class WP_Post
     */
    public static function get_post_media($post) {
        if(is_int($post)) {
            $post = \get_post($post);
        }
        return self::_get_media($post)['found'];
    }

    /**
     * Retrieve media urls in the post, that are not retrievable as an
     * attachment object.
     */
    public static function get_bad_media_urls($post) {
        if(is_int($post)) {
            $post = \get_post($post);
        }
        return self::_get_media($post)['not_found'];
    }

    // helper function produces an array with two keys like this: array(
    //      'found' =>
    //          [array of attachments (class WP_Post) found for the post],
    //      'not_found' =>
    //          [array of media urls in the post for which nothing was found]
    // )
    private static function _get_media($post) {
        $not_found_by_url = array();
        $found_by_id = array();

        $urls = self::parse_for_media_links($post);
        foreach ($urls as $url) {

            // remove any parameters from the url before querying
            $base = strtok($url, '?');

            // get the id and query for  the post
            $id = DB::get_wp_media_id($base);
            $post = \get_post($id);
            if(!empty($post)) {
                $found_by_id[$post->ID] = $post;
            } else {
                $not_found_by_url[$url] = true;
            }
        }

        return array(
            'found' => array_values($found_by_id),
            'not_found' => array_keys($not_found_by_url)
        );
    }

    /**
     * This removes a token like "-300x900" from the end of a thumbnail url.
     * Returns the url unchanged if it does not contain such a pattern
     */
    private static function _cleanup_thumbnail_url($url) {
        // First regex group is the thumbnail suffix, second one the file
        // extension. Replaces the whole match with just the extension.
        return preg_replace("/(-[0-9]+x[0-9]+)(\.[a-zA-Z0-9]+)$/", "$2", $url);
    }

    // helper function to aggregate lexicon posts linked to from another post
    private static function _get_linked_lexicon_posts($post, &$lexcion_posts,
        $do_recurse = false)
    {
        $urls = self::parse_for_page_links($post);

        // check each url found in the post to see if it is a lexicon article
        foreach($urls as $url) {
            $id = self::get_post_id($url);
            // stop if the lexicon article is already in our collection
            if($id > 0 && !array_key_exists($id, $lexcion_posts)) {
                $post = get_post($id);

                if(self::is_lexicon_post($post)) {
                    $lexcion_posts[$id] = $post;

                    // recurse
                    if($do_recurse) {
                        self::_get_linked_lexicon_posts($post, $lexcion_posts);
                    }
                }
            }
        }
    }


    // helper function to get the values of the attr from every (top level)
    // node in the node list
    private static function _get_attr_values($node_list, $attr) {
        $result = array();
        foreach ($node_list as $node) {
            $value = $node->getAttribute($attr);
            if(!empty($value)) {
                array_push($result, $value);
            }
        }
        return $result;
    }

    // helper function to parse a post's content into a DOM, which can be
    // evaluated for xpath expressions
    private static function parse_to_dom_xpath($html) {
        $html = sprintf(self::HTML_TEMPLATE, $html);
        // load the post content as a html document to examine it further
        // (errors/warnings while loading the dom are suppresed)
        libxml_use_internal_errors(true);
        $doc = new \DomDocument;
        $result = $doc->loadHTML($html);
        $dom = new \DomXPath($doc);
        // clear internal errors, as they might clog up memory over time, then
        // reset error handling to the default
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        return $dom;
    }

}