<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../../models/areas.php');
require_once(dirname(__FILE__) . '/../../models/tours.php');
require_once(dirname(__FILE__) . '/../wp_test_connection.php');

// setup the test cases
$admin_con = new WPTestConnection('Tours API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contrib_con = new WPTestConnection('Tours API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

// add the test cases to the global test variables
global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $admin_con;
$shtm_test_cases[] = $contrib_con;

// TOUR NEW
function test_tour_new($con, $name) {
    $helper = $con->helper;
    $name = "tour new ($name)";

    $con->test_fetch($helper->tc_url('tour', 'new'), null, 200,
        "Should have status 200 on $name");

    $con->test_simple_page($name);
    $con->test_page_heading('Tour erstellen', $name);

    $con->test_input_field('shtm_tour[name]','',
        "empty form field for tour name on $name");

    // test the area selection
    $con->ensure_xpath("//select[@name='shtm_tour[area_id]']", 1,
        "Should contain a selection for the tour's area id on $name");
    $areas = Areas::instance()->list_simple();
    foreach ($areas as $area) {
        $xpath = "//option[@value='$area->id' and contains(text(), '$area->name')]";
        $con->ensure_xpath($xpath, 1,
            "Should contain an option for area: '$area->id' on $name");
    }
}
test_tour_new($admin_con, 'admin');
test_tour_new($contrib_con, 'contributor');


// TOUR CREATE
function test_tour_create($con, $post, $name) {
    $name = "tour create ($name)";

    $con->test_fetch($con->helper->tc_url('tour', 'create'), $post, 200,
        "Should have status 200 on $name.");

    $con->test_success_message("Tour erstellt!", $name);

    // should redirect to the edit page of the tour
    $con->test_redirect_param('shtm_c', 'tour');
    $con->test_redirect_param('shtm_a', 'edit');
    $id = $con->test_redirect_param('shtm_id');

    // test that the id exists in the database
    $id = intval($id);
    $con->assert(DB::valid_id(Tours::instance()->table, $id),
        "Returned id should be valid");

    // return the id param we were redirected to
    return $id;
}

function test_bad_create($con, $post, $name) {
    $name = "bad tour create ($name)";

    $id_before = Tours::instance()->last_id();

    $con->test_fetch($con->helper->tc_url('tour', 'create'), $post, 200,
        "Should have status 200 on $name.");

    $con->test_error_message("Nicht gespeichert", $name);

    // should have redirect back to new page
    $con->test_redirect_param('shtm_c', 'tour');
    $con->test_redirect_param('shtm_a', 'new');

    $id_after = Tours::instance()->last_id();
    $con->assert($id_before == $id_after, "Should not have created any tour.");
}

$tour = $helper->make_tour();
$post = array(
    'shtm_tour[name]' => $tour->name,
    'shtm_tour[area_id]' => $tour->area_id,
);
// test the normal create and save the returned id values for later tests
$t_id_admin = test_tour_create($admin_con, $post, 'admin');
$t_id_contributor =
    test_tour_create($contrib_con, $post, 'contributor');

$bad_post = array_merge($post, array('shtm_tour[name]' => ''));
test_bad_create($admin_con, $bad_post, 'admin - bad tour name');
test_bad_create($contrib_con, $bad_post, 'contributor - bad tour name');

$bad_id = Areas::instance()->last_id() + 1;
$bad_post = array_merge($post, array('shtm_tour[area_id]' => $bad_id));
test_bad_create($admin_con, $bad_post, 'admin - bad area_id');
test_bad_create($contrib_con, $bad_post, 'contributor - bad area_id');



// TEST EDIT
function test_edit_with_bad_id($con, $id, $name) {
    $con->test_fetch($con->helper->tc_url('tour', 'edit', $id), null, 404,
        "Should have status 404 on tour edit with wrong id ($name).");
    $con->test_error_message('existiert nicht', $name);
}

function test_tour_edit($con, $id, $tour, $name) {
    $con->test_fetch($con->helper->tc_url('tour', 'edit', $id), null, 200,
        "Should have status 200 on tour edit ($name).");
    $con->test_simple_page("tour edit ($name)");

    // page should contain the areas name
    $area = Areas::instance()->get($tour->area_id);
    $con->test_page_contains($area->name, $name);
    // as well as the tour's name, but in an input field
    $con->test_input_field('shtm_tour[name]', $tour->name, $name);
}

// test bad ids
$bad_id = Tours::instance()->last_id() + 1;
test_edit_with_bad_id($admin_con, $bad_id, 'admin');
test_edit_with_bad_id($contrib_con, $bad_id, 'contributor');

// admin should be able to edit all tours
test_tour_edit($admin_con, $t_id_admin, $tour, 'admin - own tour');
test_tour_edit($admin_con, $t_id_contributor, $tour, 'admin - other tour');

// contributor should only be able to edit her own tour
test_tour_edit($contrib_con, $t_id_contributor, $tour,
    'contributor - own tour');
$contrib_con->test_no_access(
    $helper->tc_url('tour', 'edit', $t_id_admin), null,
    'contributor tries to edit admin tour');


// TEST EDIT TRACK
function test_tour_edit_track($con, $id, $name) {
    // setup some mapstops, so that we can test for their presence
    $mapstop1 = $con->helper->make_mapstop(true);
    $mapstop2 = $con->helper->make_mapstop(false);
    $mapstop1->tour_id = $id;
    $mapstop2->tour_id = $id;
    Mapstops::instance()->save($mapstop1);
    Mapstops::instance()->save($mapstop2);

    $con->test_fetch($con->helper->tc_url('tour', 'edit_track', $id), null, 200,
        "Should have status 200 on edit_track ($name)");

    // check that leaflet scripts/styles are included
    $con->ensure_xpath("//link[@id='shtm-leaflet-style-css']", 1,
        "Should contain the leaflet style.");
    $con->ensure_xpath("//link[@id='shtm-leaflet-draw-style-css']", 1,
        "Should contain the leaflet draw style.");
    $con->ensure_xpath("//script[contains(@src, 'leaflet')]", 2,
        "Should contain two leaflet script.");

    // testing that coordinate values are present is done on testing the update
    // (which redirects to the edit url), here only test for the mapstops
    $con->test_mapstop_tag($mapstop1, "1st mapstop on tour edit_track");
    $con->test_mapstop_tag($mapstop1, "2nd mapstop on tour edit_track");

    // cleanup
    Mapstops::instance()->delete($mapstop1);
    Mapstops::instance()->delete($mapstop2);
    $con->helper->delete_wp_posts_created();
}
test_tour_edit_track($admin_con, $t_id_admin, 'admin');
test_tour_edit_track($contrib_con, $t_id_contributor, 'contributor');


// TEST UPDATE
function test_tour_update($con, $id, $post, $tour, $name) {
    $con->test_fetch($con->helper->tc_url('tour', 'update', $id), $post, 200,
        "Should have status 200 on normal tour update ($name).");

    // We should have been redirected to the edit page or the edit_track_page
    $con->test_redirect_param('shtm_c', 'tour');
    $con->test_redirect_param('shtm_id', $id);
    // test what update this is by looking for the name param (edit info only)
    if(isset($post['shtm_tour[name]'])) {
        // should have been rediract
        $con->test_redirect_param('shtm_a', 'edit');
        // Everything we posted should reappear on the page as an input
        foreach ($post as $key => $value) {
            if ($key != 'shtm_tour[intro]') {
                $con->test_input_field($key, $value, $name);
            } else {
                $xp = "//textarea[@name='$key' and text()='$value']";
                $con->ensure_xpath($xp, 1, "Should have intro textarea");
            }
        }
    } else {
        $con->test_redirect_param('shtm_a', 'edit_track');
        // the tour's coordinates should exist as tags in the page
        foreach ($tour->coordinates as $coord) {
            $con->test_coordinate($coord->lat, $coord->lon,
                "tour track update - $name");
        }
    }

    // There should be a success message
    $con->test_success_message('gespeichert', "normal tour update ($name).");
}

// make a new tour with more coordinates
$tour = $helper->make_tour(count($tour->coordinates) + 1);

// create a post to update the meta information
// set dates to explicit strings to test for the conversion mechanism
$post = array(
    'shtm_tour[name]' => $tour->name,
    'shtm_tour[intro]' => $tour->intro,
    'shtm_tour[type]' => $tour->type,
    'shtm_tour[walk_length]' => $tour->walk_length,
    'shtm_tour[duration]' => $tour->duration,
    'shtm_tour[tag_what]' => $tour->tag_what,
    'shtm_tour[tag_where]' => $tour->tag_where,
    'shtm_tour[tag_when_start]' => '06.02.1689 13:13:13',
    'shtm_tour[tag_when_end]' => '04.07.1776 07:07:07',
    'shtm_tour[accessibility]' => $tour->accessibility,
);

$track_post = array();
for($i = 0; $i < count($tour->coordinates); $i++) {
    $coord = $tour->coordinates[$i];
    $pre = "shtm_tour[coordinates][$i]";
    $track_post["${pre}[id]"] = '';
    $track_post["${pre}[lat]"] = $helper->coord_value_string($coord->lat);
    $track_post["${pre}[lon]"] = $helper->coord_value_string($coord->lon);
}

// test updating the meta information
test_tour_update($admin_con, $t_id_admin, $post, $tour,
    'admin updates own tour');
test_tour_update($contrib_con, $t_id_contributor, $post, $tour,
    'contributor updates own tour');
test_tour_update($admin_con, $t_id_contributor, $post, $tour,
    'admin updates contributors tour');

$contrib_con->test_no_access(
    $helper->tc_url('tour', 'update', $t_id_admin), $post,
    'contributor tries to update admin tour');

// test updating the tour track
test_tour_update($admin_con, $t_id_admin, $track_post, $tour,
    'admin updates own tour track');
test_tour_update($contrib_con, $t_id_contributor, $track_post, $tour,
    'contributor updates own tour track');
test_tour_update($admin_con, $t_id_contributor, $track_post, $tour,
    'admin updates contributors tour track');

$contrib_con->test_no_access(
    $helper->tc_url('tour', 'update', $t_id_admin), $track_post,
    'contributor tries to update admin tour track');


// cleanup created tours
Tours::instance()->delete(Tours::instance()->get($t_id_admin, true, true));
Tours::instance()->delete(Tours::instance()->get($t_id_contributor, true, true));

// invalidate logins
$admin_con->invalidate_login();
$contrib_con->invalidate_login();

// report results
$admin_con->report();
$contrib_con->report();

?>