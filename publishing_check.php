<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/models/tour.php');
require_once(dirname(__FILE__) . '/route_params.php');
require_once(dirname(__FILE__) . '/user_service.php');
require_once(dirname(__FILE__) . '/logging.php');

/**
 * A small container object for messages returned by this check.
 */
class PublishingCheckFailure {

    public $link;

    public $link_name;

    public $messages = array();

    public function __construct($messages) {
        $this->messages = $messages;
    }

    public function set_link($link_params) {
        $this->link = RouteParams::admin_link($link_params);
    }

}

/**
 * A class to check if a given tour may be published.
 *
 * NOTE: The checks done by this method overlap with those done in checking
 * database validity. One could re-use those methods, but as db-validty
 * and "publishability" should be allowed to change independently from one
 * another, this was not done on purpose.
 */
class PublishingCheck {

    const INVALID_ID                = "Ungültige Id.";
    const EMPTY_NAME                = "Name ist leer.";

    const LINK_TO_TOUR_NAME         = 'Tour-Felder';

    const EMPTY_INTRO               = "Einführungstext fehlt.";
    const INVALID_TOUR_TYPE         = "Tour-Typ ist nicht gültig.";
    const INVALID_WALK_LENGTH       = "Feld 'Entfernung' ist ungültig.";
    const INVALID_DURATION          = "Feld 'Dauer' ist ungültig.";
    const EMPTY_TAG_WHEN            = "Tag 'Wann' ist leer oder ungültig.";
    const EMPTY_TAG_WHAT            = "Tag 'Was' ist leer.";
    const EMPTY_TAG_WHERE           = "Tag 'Wo' ist leer.";
    const EMPTY_ACCESSIBILITY       = "Feld 'Zugänglichkeit' ist leer.";
    const EMPTY_USER_OR_USER_NAME   = 'Keine Autorenangabe erschließbar.';


    const LINK_TO_TOUR_TRACK_NAME   = 'Tour-Weg';

    const EMPTY_TRACK               = 'Kein Tour-Weg vorhanden';
    const EMPTY_SCENES              = 'Keine Tour-Szenen vorhanden';
    const INVALID_COORDINATE        = 'Ungültiger Koordinatenwert';
    const INVALID_SCENE             = 'Ungültige Szene';


    const LINK_TO_MAPSTOPS_NAME     = 'Tour-Stops';

    const NO_MAPSTOPS               = 'Keine Tour-Stops';
    const MAPSTOP_WITHOUT_COORDINATE = 'Mapstop hat keine Coordinaten';

    const LINK_TO_MAPSTOP_NAME      = 'Tour-Stop #%d';

    const NO_POSTS                  = 'Stop enthält keine Seiten';
    const EMPTY_DESCRIPTION         = "Feld 'Beschreibung' ist leer";
    const NONEXISTING_POST          = 'Enthält gelöschten Post';

    const LINK_TO_PAGE_POST_NAME    = 'Stop-Beitrag #%d';

    const EMPTY_GUID                = 'Keine guid für Post';
    const EMPTY_CONTENT             = 'Inhalt ist leer';
    const MEDIAITEM_NOT_REACHABLE   =
        'Verlinkte Datei ist nicht erreichbar: <a href="%s">%s</a>';

    const LINK_TO_LEXICON_POST_NAME = 'Lexkon-Beitrag #%d';
    const MEDIAITEM_IN_LEXICON_POST =
        'Lexikon Artikel darf keine Medien (Audio, Video, Bilder) enthalten.';
    const LEXICON_POST_EMPTY_TITLE  = 'Lexikon-Titel ist leer oder fehlerhaft';

    const LINK_TO_AREA_NAME         = 'Verknüpftes Gebiet #%d';
    const LINK_TO_PLACE_NAME        = 'Verknüpfter Ort #%d';


    /**
     * Checks if a given tour may be published.
     *
     * @return array    An array of PublishingCheckFailures indicating
     *                  conditions that would have to be met to make the tour
     *                  publishable.
     */
    public static function run($tour) {
        // this goes over all tour related objects, runs the check functions
        // for them and turns the string messages returned into objects of type
        // PublishingCheckFailure with related information for the user. (link
        // to the object in question and a name for that link)
        $failures = array();

        if(empty($tour)) {
            throw new \Exception("No tour to check.");
        }

        // check the tour's own fields
        $messages = self::check_tour_fields($tour);
        if(!empty($messages)) {
            $failure = new PublishingCheckFailure($messages);
            $failure->set_link(RouteParams::edit_tour($tour->id));
            $failure->link_name = self::LINK_TO_TOUR_NAME;
            $failures[] = $failure;
        }

        if ($tour->type === 'indoor-tour') {
            $messages = self::check_tour_scenes($tour->scenes);
            if (!empty($messages)) {
                $failure = new PublishingCheckFailure($messages);
                $failure->set_link(RouteParams::edit_tour_stops($tour->id));
                $failure->link_name = self::LINK_TO_MAPSTOPS_NAME;
                $failures[] = $failure;
            }
        } else {
            // check the tour track
            $messages = self::check_tour_track($tour->coordinates);
            if (!empty($messages)) {
                $failure = new PublishingCheckFailure($messages);
                $failure->set_link(RouteParams::edit_tour_track($tour->id));
                $failure->link_name = self::LINK_TO_TOUR_TRACK_NAME;
                $failures[] = $failure;
            }
        }

        // collect lexicon posts for later inspection
        $lexicon_posts_by_id = array();

        // check the presence of tour mapstops
        if(!$tour->mapstops) {
            $failure = new PublishingCheckFailure(array(self::NO_MAPSTOPS));
            $failure->set_link(RouteParams::edit_tour_stops($tour->id));
            $failure->link_name = self::LINK_TO_MAPSTOPS_NAME;
            $failures[] = $failure;
        } else {
            // check the individual mapstops
            foreach ($tour->mapstops as $stop) {
                $msgs = self::check_mapstop($stop);
                if(!empty($msgs)) {
                    $failures[] = self::make_mapstop_failure($stop, $msgs);
                }

                // check the mapstop's wordpress posts. The presence of at least
                // one of these is given by the mapstop check.
                foreach ($stop->post_ids as $post_id) {
                    $post = get_post($post_id);
                    // the post's existence should be guaranteed by the model
                    // layer, but check anyway
                    if(empty($post)) {
                        $msgs = array(self::NONEXISTING_POST);
                        $failure = self::make_mapstop_failure($stop, $msgs);
                        $failures[] = $failure;
                    } else {
                        $msgs = self::check_wordpress_post($post);
                        if(!empty($msgs)) {
                            $failures[] = self::make_post_failure($post, $msgs);
                        }
                    }
                    // collect lexicon posts for later inspection
                    $lexs = PostService::get_linked_lexicon_posts($post, true);
                    foreach ($lexs as $lexicon_post) {
                        $lexicon_posts_by_id[$lexicon_post->ID] = $lexicon_post;
                    }
                }

                // check the mapstop's place
                $msgs = self::check_place($stop->place);
                if(!empty($msgs)) {
                    $failures[] = self::make_place_failure($stop->place, $msgs);
                }
            }
        }

        // check the tour's area
        $msgs = self::check_area($tour->area);
        if(!empty($msgs)) {
            $failures[] = self::make_area_failure($tour->area, $msgs);
        }

        // if the post contains lexicon articles, check those
        foreach ($lexicon_posts_by_id as $id => $lexicon_post) {
            $msgs = self::check_wordpress_post($lexicon_post);
            if(!empty($msgs)) {
                $failures[] = self::make_post_failure($lexicon_post, $msgs);
            }
        }

        return $failures;
    }

    /**
     * Check tour fields and return error messages if it should not be
     * published as part of a tour.
     *
     * @return array    An array of strings indicating errors.
     */
    private static function check_tour_fields($tour) {
        $ms = array();

        self::check($tour->id > 0, $ms, self::INVALID_ID);
        self::check(!empty($tour->name), $ms, self::EMPTY_NAME);
        self::check(!empty($tour->intro), $ms, self::EMPTY_INTRO);
        self::check($tour->has_valid_type(), $ms, self::INVALID_TOUR_TYPE);

        $condition = is_int($tour->walk_length) && $tour->walk_length > 0;
        self::check($condition, $ms, self::INVALID_WALK_LENGTH);

        $condition = is_int($tour->duration) && $tour->duration > 0;
        self::check($condition, $ms, self::INVALID_DURATION);

        $condition = !empty($tour->get_tag_when_formatted());
        self::check($condition, $ms, self::EMPTY_TAG_WHEN);
        self::check(!empty($tour->tag_what), $ms, self::EMPTY_TAG_WHAT);
        self::check(!empty($tour->tag_where), $ms, self::EMPTY_TAG_WHERE);
        self::check(!empty($tour->get_author_name()), $ms,
            self::EMPTY_USER_OR_USER_NAME);

        $condition = !empty($tour->accessibility);
        self::check($condition, $ms, self::EMPTY_ACCESSIBILITY);

        return $ms;
    }

    /**
     * Check the tour track and return error messages if it should not be
     * published as part of a tour.
     *
     * @return array    An array of strings indicating errors.
     */
    private static function check_tour_track($coordinates) {
        $ms = array();

        $has_track = self::check(!empty($coordinates), $ms, self::EMPTY_TRACK);
        if($has_track) {
            foreach ($coordinates as $c) {
                self::check_coordinate($c, $ms);
            }
        }

        return $ms;
    }

    /**
     * Check the tour scene structure and content. Return error message on any issue.
     *
     * @param $scenes
     * @return array
     */
    private static function check_tour_scenes($scenes) {
        $ms = array();

        $has_scene = self::check(!empty($scenes), $ms, self::EMPTY_TRACK);
        if($has_scene) {
            foreach ($scenes as $scene) {
                self::check_scene($scene, $ms);
            }
        }

        return $ms;
    }

    /**
     * Check an area's fields and return error messages if it should not be
     * published as part of a tour.
     *
     * @return array    An array of strings indicating errors.
     */
    private static function check_area($area) {
        $ms = array();

        self::check($area->id > 0, $ms, self::INVALID_ID);
        self::check(!empty($area->name), $ms, self::EMPTY_NAME);
        self::check_coordinate($area->coordinate1, $ms);
        self::check_coordinate($area->coordinate2, $ms);

        return $ms;
    }

    /**
     * Check a place's fields and return error messages if it should not be
     * published as part of a tour.
     *
     * @return array    An array of strings indicating errors.
     */
    private static function check_place($place) {
        $ms = array();

        self::check($place->id > 0, $ms, self::INVALID_ID);
        self::check(!empty($place->name), $ms, self::EMPTY_NAME);
        self::check_coordinate($place->coordinate, $ms);

        return $ms;
    }

    /**
     * Check a mapstop's fields and return error messages if it should not be
     * published as part of a tour.
     *
     * @return array    An array of strings indicating errors.
     */
    private static function check_mapstop($stop) {
        $ms = array();

        self::check($stop->id > 0, $ms, self::INVALID_ID);
        self::check(!empty($stop->name), $ms, self::EMPTY_NAME);
        self::check(!empty($stop->description), $ms, self::EMPTY_DESCRIPTION);

        self::check(!empty($stop->post_ids), $ms, self::NO_POSTS);

        return $ms;
    }

    /**
     * Check a wordpress post and return error messages if it should not be
     * published as part of a tour.
     *
     * @return array    An array of strings indicating errors.
     */
    private static function check_wordpress_post($post) {
        $ms = array();

        self::check($post->ID > 0, $ms, self::INVALID_ID);
        self::check(!empty($post->guid), $ms, self::EMPTY_GUID);

        $stripped = preg_replace('/\s+/', '', $post->post_content);
        $not_empty = self::check(!empty($stripped), $ms, self::EMPTY_CONTENT);
        if(!$not_empty) {
            // do no further checking on an empty post
            return $ms;
        }

        // on a lexicon post a valid title has to be present
        if(PostService::is_lexicon_post($post)) {
            self::check(!empty(PostService::get_lexicon_post_title($post)), $ms,
                self::LEXICON_POST_EMPTY_TITLE);
        }

        // check that links to media (audio, video, images) are reachable as
        // attached mediaitems (or in case of lexicon posts do not exist at all)
        if(PostService::is_lexicon_post($post)) {
            self::check(!PostService::links_to_media($post), $ms,
                self::MEDIAITEM_IN_LEXICON_POST);
        } else {
            // This could simply use PostService::get_bad_media_urls()
            // it doesn't, because we need to additionally check, that the url
            // parsed from the post and the one reported are equal
            $mediaitems = PostService::get_post_media($post);
            $media_links = PostService::parse_for_media_links($post);
            foreach ($media_links as $link) {
                $found = false;
                foreach ($mediaitems as $mediaitem) {
                    if(self::urls_have_same_path($mediaitem->guid, $link)) {
                        $found = true;
                        break;
                    }
                }
                if(!$found) {
                    $ms[] = sprintf(self::MEDIAITEM_NOT_REACHABLE,
                        $link, $link);
                }
            }
        }

        return $ms;
    }

    // convenience function to add a string message to a messages array if a
    // condition to check does not apply (when $condition is false)
    private static function check($condition, &$messages, $message) {
        if(!$condition) {
            array_push($messages, $message);
        }
        return $condition;
    }

    // checks a coordinate, adds any messages to the supplied messages array
    private static function check_coordinate($c, &$ms) {
        self::check($c->has_valid_latitude(), $ms, self::INVALID_COORDINATE);
        self::check($c->has_valid_longitude(), $ms, self::INVALID_COORDINATE);
    }

    /**
     * Checks scene if valid, adds error message to message array if not.
     *
     * @param $scene
     * @param $ms
     */
    private static function check_scene($scene, &$ms) {
        self::check($scene->is_valid(), $ms, self::INVALID_SCENE);

        // check if every mapstop has set a coordinate
        foreach ($scene->mapstops as $mapstop) {
            self::check(isset($scene->coordinates[$mapstop->id]), $ms, self::MAPSTOP_WITHOUT_COORDINATE);
        }
    }

    // convenience function to construct a PublishingCheckFailure from a
    // mapstop object
    private static function make_mapstop_failure($mapstop, $messages) {
        $failure = new PublishingCheckFailure($messages);
        $failure->set_link(RouteParams::edit_mapstop($mapstop->id));
        $name = sprintf(self::LINK_TO_MAPSTOP_NAME, $mapstop->id);
        $failure->link_name = $name;
        return $failure;
    }

    // convenience function to construct a PublishingCheckFailure from a
    // WP_Post object
    private static function make_post_failure($post, $messages) {
        $failure = new PublishingCheckFailure($messages);
        $failure->link = get_edit_post_link($post);
        if(PostService::is_lexicon_post($post)) {
            $link_name_template = self::LINK_TO_LEXICON_POST_NAME;
        } else {
            $link_name_template = self::LINK_TO_PAGE_POST_NAME;
        }
        $failure->link_name = sprintf($link_name_template, $post->ID);
        return $failure;
    }

    // convenience function to construct a PublishingCheckFailure from an
    // area object
    private static function make_area_failure($area, $messages) {
        $failure = new PublishingCheckFailure($messages);
        $failure->set_link(RouteParams::edit_area($area->id));
        $failure->link_name = sprintf(self::LINK_TO_AREA_NAME, $area->id);
        return $failure;
    }

    // convenience function to construct a PublishingCheckFailure from a
    // place object
    private static function make_place_failure($place, $messages) {
        $failure = new PublishingCheckFailure($messages);
        $failure->set_link(RouteParams::edit_place($place->id));
        $failure->link_name = sprintf(self::LINK_TO_PLACE_NAME, $place->id);
        return $failure;
    }

    // helper function to check that two urls refer to the same basic resource
    // ignoring query paramters
    private static function urls_have_same_path($url_a, $url_b) {
        $parts_a = parse_url($url_a);
        $parts_b = parse_url($url_b);

        $ok = true;
        foreach(array('scheme', 'host', 'path') as $key) {
            $ok = $ok && ($parts_a[$key] === $parts_b[$key]);
        }
        return $ok;
    }

}

?>