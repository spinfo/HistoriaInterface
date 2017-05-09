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
$helper = new TestHelper();
$t_admin = $helper->make_tour();
$t_admin->user_id = $helper->get_test_user()->ID;
$t_id_admin = Tours::instance()->insert($t_admin);



// TEST NEW
function test_new($con, $tour, $name) {
    $url = $con->helper->tc_url('mapstop', 'new') . "&shtm_tour_id=$tour->id";
    $con->test_fetch($url, null, 200,
        "Should have status 200 on new mapstop ($name).");

    $con->test_input_field('shtm_mapstop[name]', '', $name);
    $con->test_textarea('shtm_mapstop[description]', '', $name);

    // all places in the tour's area should be available to a new tour
    $places = Places::instance()->list_by_area($tour->area_id);
    $con->assert(!empty($places), "Should test on an area with places.");
    $select_name = 'shtm_mapstop[place_id]';
    foreach ($places as $p) {
        $con->test_option($select_name, $p->name, $p->id, false, $name);
    }

    // if all places are taken we should be redirected to place->new, simulate
    // that by creating a mapstop for every place in the area
    $mapstops = array();
    foreach ($places as $place) {
        $mapstop = $con->helper->make_mapstop(false, 0);
        $mapstop->place_id = $place->id;
        $mapstop->tour_id = $tour->id;
        Mapstops::instance()->insert($mapstop);
        $mapstops[] = $mapstop;
    }
    $con->test_fetch($url, null, 200,
        "Should have status 200 on new mapstop redirect to place new ($name).");
    $con->test_redirect_params('place', 'new');
    foreach ($mapstops as $mapstop) {
        Mapstops::instance()->delete($mapstop);
    }
}

test_new($admin_con, $t_admin, 'Admin visits mapstop new for own tour.');


// TEST CREATE
// test the creation of a mapstop, return the created mapstop
function test_create($con, $post, $tour, $name) {
    $url = $con->helper->tc_url('mapstop', 'create') ."&shtm_tour_id=$tour->id";

    $id_before = Mapstops::instance()->last_id();
    $con->test_fetch($url, $post, 200,
        "Should have status 200 on mapstop create ($name).");
    $id = Mapstops::instance()->last_id();
    $con->assert($id > $id_before, "Should have created a mapstop ($name).");


    $con->test_redirect_params('mapstop', 'edit', $id);

    $mapstop = Mapstops::instance()->get(intval($id));
    $con->assert(!empty($mapstop), "Should test new mapstop ($name).");

    // test that all relevant values appear on the edit page
    test_edit($con, $mapstop, $name, false);

    return $mapstop;
}

$place = Places::instance()->list_by_area($t_admin->area_id, 0, 1)[0];
$post = array(
    'shtm_mapstop[place_id]' => $place->id,
    'shtm_mapstop[name]' => 'mapstop-name ' . $helper->random_str(),
    'shtm_mapstop[description]' => 'mapstop-desc ' . $helper->random_str(),
);
$m_admin = test_create($admin_con, $post, $t_admin,
    'Admin creates mapstop for own tour');



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
    $select_name = 'shtm_mapstop[place_id]';
    $place = Places::instance()->get($mapstop->place_id);
    $con->test_option($select_name, $place->name, $place->id, true, $name);

    // Test that the page contains all other eligible places as options
    $places = Mapstops::instance()->get_possible_places($mapstop);
    $con->assert(count($places) > 1, "Should test > 1 possible places.");
    foreach($places as $place) {
        if($place->id == $mapstop->place_id) {
            continue;
        }
        $con->test_option($select_name, $place->name, $place->id, false, $name);
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

// create a few posts for the mapstop to properly test the edit
$other = $helper->make_mapstop(false, 3);
$m_admin->post_ids = $other->post_ids;
Mapstops::instance()->update($m_admin);

// test the edit
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

    $con->ensure_xpath("//li[contains(., '$mapstop->name')]", 1,
        "Should show name on mapstop delete ($name).");
    $con->ensure_xpath("//li[contains(., '$mapstop->description')]", 1,
        "Should show description on mapstop delete ($name).");
}

test_delete($admin_con, $m_admin, "admin for own mapstop");

$url404 = $helper->tc_url('mapstop', 'delete', ($m_admin->id + 1));
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
test_destroy($admin_con, $m_admin->id, $m_admin->tour_id, "admin - own mapstop");

$url404 = $helper->tc_url('mapstop', 'destroy', ($m_admin->id + 1));
$admin_con->test_not_found($url404, null, "admin - destroy invalid mapstop id");

// cleanup
$helper->delete_wp_posts_created();
Tours::instance()->delete($t_admin);


?>