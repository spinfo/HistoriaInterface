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
    $areas = Areas::instance()->list_simple();
    $select_name = 'shtm_tour[area_id]';
    foreach ($areas as $area) {
        $con->test_option($select_name, $area->name, $area->id, false, $name);
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
    $con->test_redirect_params('tour', 'edit');
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
    $con->test_redirect_params('tour', 'new');

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
        // Everything we posted should reappear on the page as a form field
        // with the new value set
        foreach ($post as $key => $value) {
            if($key == 'shtm_tour[intro]') {
                $con->test_textarea($key, $value, $name);
            } elseif($key === 'shtm_tour[type]') {
                $con->test_option($key, Tour::TYPES[$tour->type], $tour->type,
                    true, $name);
            } else {
                $con->test_input_field($key, $value, $name);
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
    $con->test_success_message('Gespeichert!', "normal tour update ($name).");
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
    'shtm_tour[author]' => $tour->author
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


// TEST EDIT/UPDATE STOPS
function test_edit_stops($con, $tour_id, $ids, $fetch = true, $name) {
    if($fetch) {
        $url = $con->helper->tc_url('tour', 'edit_stops', $tour_id);
        $con->test_fetch($url, null, 200,
            "Should have status 200 on edit stops ($name).");
        $con->test_simple_page("edit stops ($name)");
    }

    // test that for each id and position there is a select option and that
    // those options are selected that match the order of the input ids
    $n = count($ids);
    for($i = 1; $i < $n; $i++) {
        $id = $ids[$i - 1];
        $select_name = "shtm_tour[mapstop_ids][$id]";
        for($j = 1; $j < $n; $j++) {
            $selected = false;
            if($i === $j) {
                $selected = true;
            }
            $con->test_option($select_name, "$j", null, $selected, $name);
        }
    }
}


function test_edit_and_update_stops($con, $tour_id, $name) {
    // add four mapstops to the tour in question, retrieve created ids
    $con->helper->add_mapstops_to_tour(Tours::instance()->get($tour_id), 4);
    $ids = Tours::instance()->get($tour_id, true)->mapstop_ids;

    // test that the edit page renders the options we created
    test_edit_stops($con, $tour_id, $ids, true, "$name - before post");

    // build a post where the ids are in the opposite order
    $post = array();
    $altered_ids = array();
    for($i = 3, $n = 1; $i >= 0; $i--, $n++) {
        $id = $ids[$i];
        $altered_ids[] = $id;
        $post["shtm_tour[mapstop_ids][$id]"] = $n;
    }

    $url = $con->helper->tc_url('tour', 'update_stops', $tour_id);
    $con->test_fetch($url, $post, 200,
        "Should have status 200 on tour update stops ($name).");

    test_edit_stops($con, $tour_id, $altered_ids, false, "$name - after post");

    return $post;
}

// test that the edit page works without any mapstops
test_edit_stops($admin_con, $t_id_admin, array(), true, 'admin - empty stops');
test_edit_stops($contrib_con, $t_id_contributor, array(), true,
    'contributor - empty stops');

// test the update of stops' positions, save the post used to later check that
// the contributor cannot do the same
$post = test_edit_and_update_stops($admin_con, $t_id_admin,
    'admin updates own stops positions');
test_edit_and_update_stops($contrib_con, $t_id_contributor,
    'contributor updates own stops positions');

// test that the contributor can neither edit nor update an admin's tour's
// mapstop positions
$contrib_con->test_no_access(
    $helper->tc_url('tour', 'edit_stops', $t_id_admin), null,
    'contributor tries to edit admin tour stops');
$contrib_con->test_no_access(
    $helper->tc_url('tour', 'update_stops', $t_id_admin), $post,
    'contributor tries to update admin tour stops');


// TEST DELETE & DESTROY
function test_tour_delete($con, $id, $name) {
    $url = $con->helper->tc_url('tour', 'delete', $id);
    $tour = Tours::instance()->get($id);

    $con->test_fetch($url, null, 200,
        "Should have status 200 on tour delete ($name).");
    $con->ensure_xpath("//li[contains(., '$tour->name')]", 1,
        "Should show the tours name on delete ($name).");
}

function test_tour_destroy($con, $id, $name) {
    $url = $con->helper->tc_url('tour', 'destroy', $id);

    $count_before = Tours::instance()->count();
    $con->test_fetch($url, null, 200,
        "Should have status 200 on tour delete ($name).");

    $con->test_redirect_params('tour', 'index');

    $con->assert(($count_before - Tours::instance()->count()) === 1,
        "Should have deleted one tour ($name).");
    $con->assert(!Tours::instance()->valid_id($id),
        "Tour id should no longer be valid.");
}

// test the contributor gets a 403 on deleting or destroying the admin's tour
$url = $helper->tc_url('tour', 'delete', $t_id_admin);
$contrib_con->test_no_access($url, null,
    "Contributor tries to delete admin's tour");

$url = $helper->tc_url('tour', 'destroy', $t_id_admin);
$contrib_con->test_no_access($url, null,
    "Contributor tries to destroy admin's tour");

// test that the delete/destroys work
test_tour_delete($contrib_con, $t_id_contributor, 'Contributor deletes tour');
test_tour_delete($admin_con, $t_id_admin, 'Admin deletes tour');

test_tour_destroy($contrib_con, $t_id_contributor, 'Contributor destroys tour');
test_tour_destroy($admin_con, $t_id_admin, 'Admin destroys tour');


// cleanup created posts
$helper->delete_wp_posts_created();

// invalidate logins
$admin_con->invalidate_login();
$contrib_con->invalidate_login();

// report results
$admin_con->report();
$contrib_con->report();

?>