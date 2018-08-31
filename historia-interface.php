<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/logging.php');

/*
Plugin Name: HistoriaInterface
Plugin URI: https://historia-app.de
Description: A plugin to create, manage and host tours for the HistoriaApp.
Version: 0.3
Author: David Neugebauer
Author URI: https://github.com/spinfo/HistoriaInterface
License: Eclipse Public License v1.0
*/

/**
 * INSTALLATION/ACTIVATION
 */
// database version number, not used at the moment, but might be helpful on
// later upgrades. Increment on any db change
global $shtm_db_version;
$shtm_db_version = '0.1';

function na($o) {
    echo "<pre>";
    if (is_array($o)) {
        print_r($o);
    } else if (is_object($o)) {
        var_export($o);
    } else {
        var_dump($o);
    }
    echo "</pre>";
}

function shtm_install() {
    // require these here because they are not always all relevant
    require_once(dirname(__FILE__) . '/models/areas.php');
    require_once(dirname(__FILE__) . '/models/coordinates.php');
    require_once(dirname(__FILE__) . '/models/places.php');
    require_once(dirname(__FILE__) . '/models/mapstops.php');
    require_once(dirname(__FILE__) . '/models/tours.php');
    require_once(dirname(__FILE__) . '/models/tour_records.php');
    require_once(dirname(__FILE__) . '/post_service.php');

    // get the table name prefix for tables as well as the default charset
    // from wp, add our name to the prefix
    global $wpdb;
    $table_prefix = $wpdb->prefix . "shtm_";
    $charset_collate = $wpdb->get_charset_collate();

    // add the custom contributor role with file upload capability
    $caps = get_role('contributor')->capabilities;
    $caps['upload_files'] = true;
    add_role('contributor-with-upload', 'Contributor with Upload', $caps);

    // add the post category of the lexicon articles
    wp_create_category(PostService::LEXICON_CATEGORY);

    // CREATE TABLES
    // sql syntax for use with dbDelta() has specific rules, cf.:
    //      https://codex.wordpress.org/Creating_Tables_with_Plugins

    // sql for the coordinates table
    // lat/lon data type chosen according to:
    //      http://stackoverflow.com/a/25120203/1879728
    //      http://mysql.rjweb.org/doc.php/latlng
    $table_name = Coordinates::instance()->table;
    $coordinates_sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        lat decimal(10,6) NOT NULL,
        lon decimal(10,6) NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // sql for the areas table
    $table_name = Areas::instance()->table;
    $areas_sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        coordinate1_id bigint(20) UNSIGNED NOT NULL,
        coordinate2_id bigint(20) UNSIGNED NOT NULL,
        name text NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // sql for the places table
    $table_name = Places::instance()->table;
    $places_sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        coordinate_id bigint(20) UNSIGNED NOT NULL,
        area_id bigint(20) UNSIGNED NOT NULL,
        name text NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id),
        KEY shtm_places_user_id (user_id),
        KEY shtm_places_area_id (area_id)
    ) $charset_collate;";

    // sql for the tours table
    // NOTE:
    //  - tag_when_start and tag_when_end are julian dates (with fraction of day)
    //    (end is empty if this is an instant and not a duration)
    $table_name = Tours::instance()->table;
    $tours_sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        area_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        name TEXT DEFAULT '',
        intro TEXT DEFAULT '',
        type ENUM('round-tour', 'tour', 'public-transport-tour', 'bike-tour', 'indoor-tour') NOT NULL,
        walk_length INT NOT NULL DEFAULT 0,
        duration INT NOT NULL DEFAULT 0,
        tag_what TEXT DEFAULT '',
        tag_where TEXT DEFAULT '',
        tag_when_start DECIMAL(13,6) NOT NULL DEFAULT 0.0,
        tag_when_end DECIMAL(13,6),
        tag_when_start_format VARCHAR(64) NOT NULL,
        tag_when_end_format VARCHAR(64) DEFAULT '',
        accessibility TEXT DEFAULT '',
        author TEXT DEFAULT '',
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $table_name = Tours::instance()->join_coordinates_table;
    $tours_to_coordinates_sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        tour_id bigint(20) UNSIGNED NOT NULL,
        coordinate_id bigint(20) UNSIGNED NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id),
        KEY shtm_tour_to_coordinates (tour_id)
    ) $charset_collate;";

    // sql for the mapstop table
    // NOTE: The key tour_id-position could in principle be made unique. This
    // however is not well supported in update mechanisms and wasn't done.
    $table_name = Mapstops::instance()->table;
    $mapstops_sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        tour_id bigint(20) UNSIGNED NOT NULL,
        place_id bigint(20) UNSIGNED NOT NULL,
        name text NOT NULL,
        description text NOT NULL,
        position smallint UNSIGNED NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id),
        KEY shtm_mapstop_position_in_tour_id (tour_id, position),
        KEY shtm_mapstops_place_id (place_id)
    ) $charset_collate;";

    // sql for joining posts on mapstops
    // a mapstop delete will cascade to this table
    $table_name = Mapstops::instance()->join_posts_table;
    $mapstops_table = Mapstops::instance()->table;
    $mapstops_to_posts_sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        mapstop_id bigint(20) UNSIGNED NOT NULL,
        post_id bigint(20) UNSIGNED NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id),
        KEY shtm_mapstops_to_posts (mapstop_id, post_id),
        UNIQUE shtm_mapstop_unique_post (post_id),
        CONSTRAINT wp_shtm_mapstops_to_posts_ibfk_1 FOREIGN KEY (mapstop_id) REFERENCES $mapstops_table(id) ON DELETE CASCADE
    ) $charset_collate;";

    // sql for the tour records table
    $table_name = TourRecords::instance()->table;
    $tour_records_sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        tour_id bigint(20) UNSIGNED NOT NULL,
        area_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        name text NOT NULL,
        is_active boolean NOT NULL,
        content mediumtext NOT NULL,
        media_url text NOT NULL,
        download_size bigint(20) UNSIGNED NOT NULL,
        published_at bigint(20) UNSIGNED NOT NULL,
        created_at timestamp DEFAULT now(),
        updated_at timestamp DEFAULT now() ON UPDATE now(),
        PRIMARY KEY  (id),
        KEY shtm_tour_record_area_active (area_id, is_active),
        KEY shtm_tour_record_tour (tour_id),
        UNIQUE shtm_tour_record_unique_published_at_for_tour (tour_id, published_at)
    ) $charset_collate;";

    // sql for joining posts / scenes to tours
    $table_name = Scenes::instance()->table;
    $scenes_sql = "CREATE TABLE $table_name (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `tour_id` bigint(20) unsigned NOT NULL,
        `post_id` bigint(20) unsigned NOT NULL,
        `position` smallint(5) unsigned NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `shtm_scenes_unique_post_id` (`post_id`),
        KEY `shtm_scenes_tour_position` (`tour_id`,`position`)
    ) $charset_collate;";

    // sql for joining mapstops to scenes with a coordinate
    $table_name = Scenes::instance()->join_mapstops_table;
    $scenes_table = Scenes::instance()->table;
    $mapstops_to_scenes_sql = "CREATE TABLE $table_name (
        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `mapstop_id` bigint(20) unsigned NOT NULL,
        `scene_id` bigint(20) NOT NULL,
        `coordinate_id` bigint(20) unsigned DEFAULT NULL,
        `type` enum('info','route') NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `mapstop_id` (`mapstop_id`,`scene_id`)
        CONSTRAINT wp_shtm_mapstops_to_scenes_ibfk_1 FOREIGN KEY (mapstop_id) REFERENCES $mapstops_table(id) ON DELETE CASCADE
        CONSTRAINT wp_shtm_mapstops_to_scenes_ibfk_2 FOREIGN KEY (scene_id) REFERENCES $scenes_table(id) ON DELETE CASCADE
    ) $charset_collate;";

    // collect queries
    $queries = array(
        $coordinates_sql, $areas_sql, $places_sql, $tours_sql,
        $tours_to_coordinates_sql, $mapstops_sql, $mapstops_to_posts_sql,
        $tour_records_sql, $scenes_sql, $mapstops_to_scenes_sql
    );

    // do the table update
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $messages = dbDelta($queries);

    // fix db version number to current version in options
    add_option('shtm_db_version', $shtm_db_version);
}

// This is meant for testing
// TODO: remove after testing
function shtm_create_test_data() {
    require_once(dirname(__FILE__) . '/models/areas.php');

    // only proceed, if there is no data present at the moment
    if(Areas::instance()->count() > 0) {
        return;
    }

    require_once(dirname(__FILE__) . '/models/coordinates.php');
    require_once(dirname(__FILE__) . '/models/places.php');
    require_once(dirname(__FILE__) . '/models/mapstops.php');
    require_once(dirname(__FILE__) . '/models/tours.php');
    require_once(dirname(__FILE__) . '/models/scenes.php');
    require_once(dirname(__FILE__) . '/user_service.php');
    require_once(dirname(__FILE__) . '/db.php');

    $user_service = UserService::instance();

    $values = array("lat" => 51.120429, "lon" => 6.534190);
    $coord1_id = DB::insert(Coordinates::instance()->table, $values);

    $values = array("lat" => 51.357928, "lon" => 7.086940);
    $coord2_id = DB::insert(Coordinates::instance()->table, $values);

    // dummy coordinates for the test area
    $coord3_id = DB::insert(Coordinates::instance()->table, $values);
    $coord4_id = DB::insert(Coordinates::instance()->table, $values);

    // Make one area for the example tour
    $values = array(
        "coordinate1_id" => $coord1_id,
        "coordinate2_id" => $coord2_id,
        "name" => "Düsseldorf"
    );
    $area_id = DB::insert(Areas::instance()->table, $values);

    // One area just for testing
    $values = array(
        "coordinate1_id" => $coord3_id,
        "coordinate2_id" => $coord4_id,
        "name" => "Test-Gebiet"
    );
    DB::insert(Areas::instance()->table, $values);

    // Make the example places
    $values = array(
        "user_id" => $user_service->user_id(),
        "area_id" => $area_id,
        "name" => "HHU-Campus 1",
        "lat" => 51.191180,
        "lon" => 6.793582
    );
    $place1 = Places::instance()->create($values);
    Places::instance()->save($place1);

    $values = array(
        "user_id" => $user_service->user_id(),
        "area_id" => $area_id,
        "name" => "HHU-Campus 2",
        "lat" => 51.190430,
        "lon" => 6.793775
    );
    $place2 = Places::instance()->create($values);
    Places::instance()->save($place2);

    $values = array(
        "user_id" => $user_service->user_id(),
        "area_id" => $area_id,
        "name" => "HHU-Campus 3",
        "lat" => 51.188801,
        "lon" => 6.794488
    );
    $place3 = Places::instance()->create($values);
    Places::instance()->save($place3);

    // Make the test tour
    $values = array(
        'area_id' => $area_id,
        'user_id' => $user_service->user_id(),
        'name' => "Heinrich-Heine-Denkmäler der Universität Düsseldorf",
        'intro' => "Sie stehen tagein tagaus unbeweglich an der selben Stelle. Jeden Tag gehen wir an ihnen vorbei, bis wir sie kaum noch wahrnehmen, wie ein Sitzbank, eine Mauer oder ein Hinweisschild. Dabei haben Denkmäler allerhand zu erzählen: über die Geschichte der Heinrich-Heine-Universität und die Person, der sie gewidmet sind. Diese Tour zeigt Dir, was Du sonst jeden Tag übersiehst.",
        'type' => 'tour',
        'walk_length' => 280,
        'duration' => 10,
        'tag_what' => "Heinrich Heine",
        'tag_where' => "Campus Universität",
        'tag_when_start' => 2449353.5,
        'tag_when_end' => 2455927.5,
        'tag_when_start_format' => 'Y',
        'tag_when_end_format' => 'Y',
        'accessibility' => 'barrierefrei'
    );
    $tour_id = DB::insert(Tours::instance()->table, $values);
    $tour = Tours::instance()->get($tour_id);

    // make the tour track
    $track = [ [ 51.188801, 6.794488 ], [ 51.189071, 6.794514 ], [ 51.189433, 6.794360 ], [ 51.189768, 6.794192 ], [ 51.190129, 6.794021 ], [ 51.190454, 6.793881 ], [ 51.190663, 6.793877 ], [ 51.190947, 6.793751 ], [ 51.191180, 6.793582 ] ];
    foreach ($track as $coord_pair) {
        $coord = new Coordinate();
        $coord->lat = $coord_pair[0];
        $coord->lon = $coord_pair[1];
        $tour->coordinates[] = $coord;
    }
    Tours::instance()->save($tour);

    // add the example mapstops
    $mapstop_ids = array();
    $values = array(
        'tour_id' => $tour_id,
        'place_id' => $place1->id,
        'name' => "Ein Posten ist vakant",
        'description' => "Gedenksteine Heinrich Heine",
        'position' => 1
    );
    $mapstop_ids[] = DB::insert(Mapstops::instance()->table, $values);

    $values = array(
        'tour_id' => $tour_id,
        'place_id' => $place2->id,
        'name' => "Heine-Denkmal (2012)",
        'description' => "Das Heine-Denkmal des ...",
        'position' => 2
    );
    $mapstop_ids[] = DB::insert(Mapstops::instance()->table, $values);

    $values = array(
        'tour_id' => $tour_id,
        'place_id' => $place3->id,
        'name' => "Heinrich-Heine-Denkmal (1994)",
        'description' => "Ein nachdenklicher Mann",
        'position' => 3
    );
    $mapstop_ids[] = DB::insert(Mapstops::instance()->table, $values);

    // an array of arrays of example posts, each subarray belongs to one stop
    // keys are wp name slugs to identify the posts between (re-)activations
    $posts = array();
    $posts[] = array(
        'shtm-example-post-m1p1' => '<h1>Ein Posten ist vakant</h1><p>An der Außenseite des Hörsaals 3A findet sich ein Gedenkstein, der an Heinrich Heine erinnert. Die Zeilen stammen aus dem Gedicht „Enfant Perdu“. Heinrich Heine wurde am 13. Dezember 1797 in Düsseldorf geboren, der „letzte Dichter der Romantik“ wurde wegen seiner jüdischen Herkunft in Deutschland über Jahrzehnte angefeindet. Die 1965 gegründete Düsseldorfer Universität stritt von 1968 bis 1988 um die Benennung nach dem Dichter. Die folgende Seite zitiert das Gedicht vollständig:</p>',
        'shtm-example-post-m1p2' => '<p>Verlorner Posten in dem Freiheitskriege,<br>Hielt ich seit dreißig Jahren treulich aus.<br>Ich kämpfte ohne Hoffnung, daß ich siege,<br>Ich wußte, nie komm ich gesund nach Haus.<br></p><p>Ich wachte Tag und Nacht – Ich konnt nicht schlafen,<br>Wie in dem Lagerzelt der Freunde Schar – <br>(Auch hielt das laute Schnarchen dieser Braven<br>Mich wach, wenn ich ein bißchen schlummrig war).<br></p><p>In jenen Nächten hat Langweil ergriffen<br>Mich oft, auch Furcht – (nur Narren fürchten nichts) –<br>Sie zu verscheuchen, hab ich dann gepfiffen<br>Die frechen Reime eines Spottgedichts.<br></p><p>Ja, wachsam stand ich, das Gewehr im Arme,<br>Und nahte irgendein verdächtger Gauch,<br>So schoß ich gut und jagt ihm eine warme,<br>Brühwarme Kugel in den schnöden Bauch.<br></p><p>Mitunter freilich mocht es sich ereignen,<br>Daß solch ein schlechter Gauch gleichfalls sehr gut<br>Zu schießen wußte – ach, ich kann’s nicht leugnen – <br>Die Wunden klaffen – es verströmt mein Blut.<br></p><p>Ein Posten ist vakant! …</p>',
        'shtm-example-post-m1p3' => '<div><img style="display: block;margin: 0 auto;max-width: 100%" src="file:///android_asset/campus_bau_zentrum_1973.jpg"></div><div><img style="display: block;margin: 0 auto;max-width: 100%" src="file:///android_asset/philfak_bau_um_1973.jpg"></div>'
    );
    $posts[] = array(
        'shtm-example-post-m2p1' => '<h1>Heine-Denkmal (2012)</h1><p><audio src="file:///storage/emulated/0/Android/data/de.smarthistory/files/rec_20170123-1542.wav" controls style="width: 100%"></audio></p><p>Das Heinrich-Heine-Denkmal des renommierten Künstlers Bert Gerresheim wurde 2012 auf dem Campus der<a href="lexikon:///l1.html">Heinrich-Heine-Universität</a>aufgestellt. Es ist vier Meter hoch und wiegt drei Tonnen. Die Schere erinnert daran, dass Heines Schriften lange Zeit in Deutschland zensiert wurden. Die Schelle ist eine Narrenschelle und nimmt die Selbstbezeichnung Heines als „Narr des Glücks“ auf. Gerresheim fasziniert an Heine, dass dieser ein Symbol für die Gespaltenheit der Moderne ist, er irritiere und verletze.</p><p>Gestiftet wurde das Denkmal von Lutz Aengevelt, seinem Bruder Wulff und der Rheinische Post Mediengruppe.</p>',
    );
    $posts[] = array(
        'shtm-example-post-m3p1' => '<h1>Ein nachdenklicher Mann</h1><div><img style="display: block;margin: 0 auto;max-width: 100%" src="file:///android_asset/Heinrich-heine_1.jpg"></div><p>Seit 1994 steht auf dem Campus der<a href="lexikon:///l1.html">Heinrich-Heine-Universität</a>vor dem Gebäude der Universitäts- und Landesbibliothek ein Denkmal für Heinrich Heine. Es ist eine vergrößerte Nachbildung eines Werkstattmodells des Bildhauers Hugo Lederer. Dieser hatte das Modell für ein Heine-Denkmal der Stadt Hamburg entworfen. Nachdem das 1926 aufgestellte Denkmal 1933 von den Nationalsozialisten demontiert wurde, wurde es 1943 für die Rüstungsproduktion verschrottet und eingeschmolzen. Das Werkstattmodell, das nun als Vorbild für die Vergrößerung diente, gehörte ursprünglich dem Düsseldorfer Rechtsanwalt Friedrich Maase. Das Denkmal stellt Heine im Alter von 21 Jahren dar, als ihm der Zugang zur Promotion aufgrund seines jüdischen Glaubens verwehrt wurde.</p>',
        'shtm-example-post-m3p2' => '<p>Die Geschichte Düsseldorfer Denkmäler und Skulpturen findet sich in der Publikation:<i>Ars Publica Düsseldorf : Geschichte der Kunstwerke und kulturellen Zeichen im öffentlichen Raum der Landeshauptstadt von Wolfgang Funken (Essen 2012)</i></p>',
    );

    // link each mapstop to it's posts
    for($i = 0; $i < count($mapstop_ids); $i++) {
        $mapstop_id = $mapstop_ids[$i];
        foreach ($posts[$i] as $name => $content) {
            // insert posts or get existing ids by slug
            $old = get_posts(array('post_status' => 'draft', 'name' => $name));
            if(empty($old)) {
                $values = array(
                    'post_title' => "Example post ($name)",
                    'post_status' => 'draft',
                    'post_content' => $content,
                    'post_name' => $name,
                );
                $post_id = wp_insert_post($values);
            } else {
                $post_id = $old[0]->ID;
            }
            // insert the connection to the mapstop
            $values = array(
                'mapstop_id' => $mapstop_id,
                'post_id' => $post_id
            );
            DB::insert(Mapstops::instance()->join_posts_table, $values);
        }
    }
}

register_activation_hook(__FILE__, 'SmartHistoryTourManager\shtm_install');
register_activation_hook(__FILE__, 'SmartHistoryTourManager\shtm_create_test_data');

/**
 * TOUR MANAGER
 * This sets up the tour manager as an admin page
 */
function shtm_setup_tour_manager() {
    // title used in the menu and in the browser tab
    $title_menu = 'HistoriaInterface';
    $title_tab = 'HistoriaInterface';

    // Capability available to Contributor role and above, but not to Subscriber
    $capability = 'edit_posts';

    // Unique identifier for this menu
    $menu_slug = 'shtm_tour_manager';

    // the callable function used to render the tour manager
    $render_func = 'SmartHistoryTourManager\shtm_render_tour_manager';

    // we do not set an icon or menu position for now
    $icon = "";
    $menu_pos = null;

    add_menu_page($title_menu, $title_tab, $capability, $menu_slug,
        $render_func, $icon, $menu_pos);
}

function shtm_render_tour_manager() {
    require_once(dirname(__FILE__) . '/route_params.php');

    $controller = RouteParams::get_controller_value();
    $action = RouteParams::get_action_value();

    switch($controller) {
        case 'area':
            require_once( dirname(__FILE__) . '/controllers/areas_controller.php');
            switch($action) {
                case 'index':
                    AreasController::index();
                    break;
                case 'new':
                    AreasController::new();
                    break;
                case 'create':
                    AreasController::create();
                    break;
                case 'edit':
                    AreasController::edit();
                    break;
                case 'update':
                    AreasController::update();
                    break;
                case 'delete':
                    AreasController::delete();
                    break;
                case 'destroy':
                    AreasController::destroy();
                    break;
                default:
                    AreasController::index();
                    break;
            }
            break;
        case 'place':
            require_once( dirname(__FILE__) . '/controllers/places_controller.php');
            switch ($action) {
                case 'index':
                    PlacesController::index();
                    break;
                case 'new':
                    PlacesController::new();
                    break;
                case 'create':
                    PlacesController::create();
                    break;
                case 'edit':
                    PlacesController::edit();
                    break;
                case 'update':
                    PlacesController::update();
                    break;
                case 'delete':
                    PlacesController::delete();
                    break;
                case 'destroy':
                    PlacesController::destroy();
                    break;
                default:
                    PlacesController::index();
                    break;
            }
            break;
        case 'tour':
            require_once( dirname(__FILE__) . '/controllers/tours_controller.php');
            switch ($action) {
                case 'index':
                    ToursController::index();
                    break;
                case 'report':
                    ToursController::report();
                    break;
                case 'new':
                    ToursController::new();
                    break;
                case 'create':
                    ToursController::create();
                    break;
                case 'edit':
                    ToursController::edit();
                    break;
                case 'edit_track':
                    ToursController::edit_track();
                    break;
                case 'edit_stops':
                    ToursController::edit_stops();
                    break;
                case 'update':
                    ToursController::update();
                    break;
                case 'update_stops':
                    ToursController::update_stops();
                    break;
                case 'update_scenes':
                    ToursController::update_scenes();
                    break;
                case 'delete':
                    ToursController::delete();
                    break;
                case 'destroy':
                    ToursController::destroy();
                    break;
                default:
                    ToursController::index();
                    break;
            }
            break;
        case 'mapstop':
            require_once( dirname(__FILE__) . '/controllers/mapstops_controller.php');
            switch ($action) {
                case 'new':
                    MapstopsController::new();
                    break;
                case 'create':
                    MapstopsController::create();
                    break;
                case 'edit':
                    MapstopsController::edit();
                    break;
                case 'update':
                    MapstopsController::update();
                    break;
                case 'delete':
                    MapstopsController::delete();
                    break;
                case 'destroy':
                    MapstopsController::destroy();
                    break;
                default:
                    MapstopsController::index();
                    break;
            }
            break;
        case 'tour_record':
            require_once( dirname(__FILE__) . '/controllers/tour_records_controller.php');
            switch ($action) {
                case 'index':
                    TourRecordsController::index();
                    break;
                case 'view':
                    TourRecordsController::view();
                    break;
                case 'new':
                    TourRecordsController::new();
                    break;
                case 'create':
                    TourRecordsController::create();
                    break;
                case 'deactivate':
                    TourRecordsController::deactivate();
                    break;
                case 'delete':
                    TourRecordsController::delete();
                    break;
                case 'destroy':
                    TourRecordsController::destroy();
                    break;
                default:
                    TourRecordsController::index();
                    break;
            }
            break;
        case 'scene':
            require_once( dirname(__FILE__) . '/controllers/scenes_controller.php');
            switch ($action) {
                case 'new':
                    ScenesController::new();
                    break;
                case 'add':
                    ScenesController::add();
                    break;
                case 'delete':
                    ScenesController::delete();
                    break;
                case 'destroy':
                    ScenesController::destroy();
                    break;
                case 'new_stop':
                    ScenesController::new_stop();
                    break;
                case 'set_marker':
                    ScenesController::set_marker();
                    break;
            }
            break;
        default:
            require_once( dirname(__FILE__) . '/controllers/abstract_controller.php');
            AbstractController::redirect(RouteParams::default_page());
            break;
    }

    // end the output buffering that was started on 'init' for this page
    //  - cf. the hook to do_output_buffer()
    ob_end_flush();
}
add_action('admin_menu', 'SmartHistoryTourManager\shtm_setup_tour_manager');

// allow redirection and setting status headers, even if wordpress wants to
// start sending output to the browser.
// NOTE: This will hinder performance, but we need the redirection to work.
// NOTE: The corresponding ob_end_flush() is found at the end of
//          shtm_render_tour_manager()
function do_output_buffer() {
    // restrict output buffering to our plugins admin page
    if(!empty($_GET['page']) && ($_GET['page'] == 'shtm_tour_manager')) {
        ob_start();
    }
}
add_action('init', 'SmartHistoryTourManager\do_output_buffer');


// use session data to pass messages to the user on redirection
function check_session_messages() {
    require_once(dirname(__FILE__) . '/message_service.php');

    if(!session_id()) {
        session_start();
    }

    if(isset($_SESSION['shtm_messages'])) {
        $message_service = MessageService::instance();
        $message_service->messages = $_SESSION['shtm_messages'];
        unset($_SESSION['shtm_messages']);
    }
}
add_action('admin_menu', 'SmartHistoryTourManager\check_session_messages');


// if a post is put into the trash we might need to delete mapstop joins
function delete_mapstop_join($post_id) {
    require_once(dirname(__FILE__) . '/db.php');
    require_once(dirname(__FILE__) . '/models/mapstops.php');

    $where = array('post_id' => $post_id);
    DB::delete(Mapstops::instance()->join_posts_table, $where);
}
add_action('wp_trash_post', 'SmartHistoryTourManager\delete_mapstop_join');


// conditionally adds the leaflet dependencies to certain admin pages
function add_leaflet_js() {
    require_once(dirname(__FILE__) . '/route_params.php');

    $controller = RouteParams::get_controller_value();
    $action = RouteParams::get_action_value();

    $is_script_page = false;
    if($controller == 'place' && $action != 'index') {
        $is_script_page = true;
    } else if($controller == 'tour' && ($action == 'edit_track' || $action == 'edit_stops')) {
        $is_script_page = true;
    } else if($controller == 'area' && $action != 'index') {
        $is_script_page = true;
    }

    if($is_script_page) {
        // add the script
        // TODO: remove '-src' from js url
        $url = esc_url_raw('https://unpkg.com/leaflet@1.0.3/dist/leaflet.js');
        wp_enqueue_script('shtm-leaflet-script', $url);

        // add the style
        $url = esc_url_raw('https://unpkg.com/leaflet@1.0.3/dist/leaflet.css');
        wp_enqueue_style('shtm-leaflet-style', $url);

        // add leaflet draw style and js
        // TODO: add leaflet as dependency
        // TODO: remove '-src' from js url
        wp_enqueue_style('shtm-leaflet-draw-style',
            'https://unpkg.com/leaflet-draw@0.4.9/dist/leaflet.draw.css');
        wp_enqueue_script('shtm-leaflet-draw-script',
            'https://unpkg.com/leaflet-draw@0.4.9/dist/leaflet.draw.js');
    }
}
add_action('admin_enqueue_scripts', 'SmartHistoryTourManager\add_leaflet_js');

?>