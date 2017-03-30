<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../wp_test_connection.php');
require_once(dirname(__FILE__) . '/../test_helper.php');

// Create the test cases and add them to the global test cases
$admin_con = new WPTestConnection('Mapstops API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contrib_con = new WPTestConnection('Mapstops API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $admin_con;
$shtm_test_cases[] = $contrib_con;


// Create a tour that we can work with
// TODO: Remove once this is done by testing the 'create' route
$helper = new TestHelper();
$t_admin = $helper->make_tour();
$t_admin->user_id = $helper->get_test_user()->ID;
$t_id_admin = Tours::instance()->insert($t_admin);

$m_admin = $helper->make_mapstop();
$m_admin->tour_id = $t_admin->id;
$m_id_admin = Mapstops::instance()->insert($m_admin);


// TEST EDIT
function test_edit($con, $mapstop, $name, $do_fetch = true) {
    if($do_fetch) {
        $url = $con->helper->tc_url('mapstop', 'edit', $mapstop->id);
        $con->test_fetch($url, null, 200,
            "Should have status 200 on mapstop edit");
    }

    $con->test_input_field('shtm_mapstop[name]', $mapstop->name, $name);
    $con->test_textarea('shtm_mapstop[description]', $mapstop->description,
        $name);

    // Test that there is a select box with the right place_id selected
    $select_xp = "//select[@name='shtm_mapstop[place_id]']";
    $xp = $select_xp . "/option[@value='$mapstop->place_id' and @selected]";
    $con->ensure_xpath($xp, 1, "Should have the place_id selected ($name).");

    // Test that the page contains all other eligible places as options
    $places = Mapstops::instance()->get_possible_places($mapstop);
    $con->assert(count($places) > 1, "Should test > 1 possible places.");
    foreach($places as $place) {
        if($place->id == $mapstop->place_id) {
            continue;
        }
        $xp = $select_xp . "/option[@value='$place->id' and not(@selected)]";
        $con->ensure_xpath($xp, 1, "Should contain place as option ($name).");
    }

    // Test that post_ids are given in the right order
    $m = Mapstops::instance()->get($mapstop->id);
    $select_xp = "//select[@name='shtm_mapstop[post_ids][]']";
    for($i = 0; $i < count($mapstop->post_ids); $i++) {
        $id = $mapstop->post_ids[$i];
        // xpath expressions are 1-indexed and there is a zero option
        $pos = $i + 2;
        $opt_xp = "/option[position()=$pos and @value='$id' and @selected]";
        $con->ensure_xpath($select_xp . $opt_xp, 1,
            "Should show an option with post id selected at position ($name).");
    }
}

test_edit($admin_con, $m_admin, 'Admin edits own mapstop');


// TEST UPDATE
function test_update($con, $mapstop, $name) {
    // get a new mapstop for name/description/place_id values
    $other = $con->helper->make_mapstop(true);
    $other->tour_id = $mapstop->tour_id;
    // determine new post_ids to set, by reversing, removing, then adding
    $con->assert(count($mapstop->post_ids) > 2, "Should test > 2 post ids.");
    $new_ids = array_reverse($mapstop->post_ids);
    array_pop($new_ids);
    array_push($new_ids, array_pop($other->post_ids));
    shuffle($new_ids);

    // tranlate those values into a valid post
    $post = array(
        'shtm_mapstop[place_id]' => $other->place_id,
        'shtm_mapstop[name]' => $other->name,
        'shtm_mapstop[description]' => $other->description,
    );
    for($i = 0; $i < count($new_ids); $i++) {
        $post["shtm_mapstop[post_ids][$i]"] = $new_ids[$i];
    }

    // do the post
    $url = $con->helper->tc_url('mapstop', 'update', $mapstop->id);
    $con->test_fetch($url, $post, 200,
        "Should have status 200 on mapstop update ($name).");

    // test that we were redirected to the edit page
    $con->test_redirect_params('mapstop', 'edit', $mapstop->id);

    // check fields by simply doing the edit check with the other mapstop
    $other->id = $mapstop->id;
    $other->post_ids = $new_ids;
    test_edit($con, $other, $name, false);

    // return the post used
    return $post;
}

$post_admin = test_update($admin_con, $m_admin, 'Admin updates own mapstop');


// reset the mapstop to the updated version for further tests to work
$m_admin = Mapstops::instance()->get($m_admin->id);

// TEST DELETE
function test_delete($con, $mapstop, $name) {
    $url = $con->helper->tc_url('mapstop', 'delete', $mapstop->id);
    $con->test_fetch($url, null, 200,
        "Should have status 200 on mapstop delete ($name).");

    $con->ensure_xpath("//li[contains(., '$mapstop->id')]", 1,
        "Should show id on mapstop delete ($name).");
    $con->ensure_xpath("//li[contains(., '$mapstop->name')]", 1,
        "Should show name on mapstop delete ($name).");
    $con->ensure_xpath("//li[contains(., '$mapstop->description')]", 1,
        "Should show description on mapstop delete ($name).");
}

test_delete($admin_con, $m_admin, "admin for own mapstop");

$url404 = $helper->tc_url('mapstop', 'delete', ($m_id_admin + 1));
$admin_con->test_not_found($url404, null, "admin - delete invalid mapstop id");


// TEST DESTROY
function test_destroy($con, $id, $tour_id, $name) {
    $con->assert(Mapstops::instance()->valid_id($id),
        "Should test to destroy a valid mapstop id ($name).");

    $con->test_fetch($con->helper->tc_url('mapstop', 'destroy', $id), null, 200,
        "Should have status 200 on tour destroy ($name).");

    // should redirect to the tour stops' edit page
    $con->test_redirect_params('tour', 'edit_stops', $tour_id);

    $con->assert(!Mapstops::instance()->valid_id($id),
        "Should have removed the mapstop from db ($name).");
}
test_destroy($admin_con, $m_id_admin, $m_admin->tour_id, "admin - own mapstop");

$url404 = $helper->tc_url('mapstop', 'destroy', ($m_id_admin + 1));
$admin_con->test_not_found($url404, null, "admin - destroy invalid mapstop id");

// cleanup
$helper->delete_wp_posts_created();
Tours::instance()->delete($t_admin);


?>