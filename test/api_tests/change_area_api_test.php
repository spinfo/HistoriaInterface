<?php
namespace SmartHistoryTourManager;

require_once(dirname(__FILE__) . '/../wp_test_connection.php');
require_once(dirname(__FILE__) . '/../../models/areas.php');

/**
 * Setting the user's current area on one route also affects other routes. This
 * test's that this is working correctly.
 */

// test that the index page has the right area selection with or without
// setting it via GET parameter
// the param $type is either 'place' odr 'tour' index
function test_index_page($con, $type, $area, $name, $set_area = true) {
    if($set_area) {
        $url = $con->helper->tc_url($type, 'index', null, null, $area->id);
    } else {
        $url = $con->helper->tc_url($type, 'index');
    }

    $con->test_fetch($url, null, 200,
        "Should have status 200 on $tpye index ($name).");

    $select = "//select[@name='shtm_area_id']";
    $xpath = "$select/option[@value='$area->id' and @selected]";
    $con->ensure_xpath($xpath, 1,
        "Should have the correct area selected on $type index ($name).");
}

// test that the new page has the area given selected
// the param $type is either 'place' odr 'tour' index
function test_new_page($con, $type, $area, $name) {
    $url = $con->helper->tc_url($type, 'new');
    $con->test_fetch($url, null, 200,
        "Should have status 200 on $type new ($name).");

    $select = "//select[@name='shtm_${type}[area_id]']";
    $xpath = "$select/option[@value='$area->id' and @selected]";
    $con->ensure_xpath($xpath, 1,
        "Should have the right area selected on $type new ($name)");
}

// setup the test connections and two areas to work with
$admin_con = new WPTestConnection('Change Area API Test (admin)',
    'test-admin', 'test-admin', $helper->config->wp_url);
$contrib_con = new WPTestConnection('Change Area API Test (contributor)',
    'test-contributor', 'test-contributor', $helper->config->wp_url);

global $shtm_test_cases;
if(empty($shtm_test_cases)) {
    $shtm_test_cases = array();
}
$shtm_test_cases[] = $admin_con;
$shtm_test_cases[] = $contrib_con;

$area1 = Areas::instance()->save($helper->make_area());
$area2 = Areas::instance()->save($helper->make_area());

// First we visit places index with admin -> area1, contributor -> area2
// This should set the areas in the user preferences, so that they are
// available without specifying the get parameter
test_index_page($admin_con, 'place', $area1, "Admin sets area1 on place index", true);
test_index_page($contrib_con, 'place', $area2, "Contributor sets area2 on place index", true);

// visiting the same page again without explicitly setting the area should have
// the same result
test_index_page($admin_con, 'place', $area1, "Admin visits place index for area1", false);
test_index_page($contrib_con, 'place', $area2, "Contributor visits place index for area2", false);

// now visitng the tour index without setting the area should show the same
// area's tour as was set by using the places route
test_index_page($admin_con, 'tour', $area1, "Admin visits tour index for area1", false);
test_index_page($contrib_con, 'tour', $area2, "Contributor visits tour index for area2", false);

// visiting the new routes should show the area in question as selected
test_new_page($admin_con, 'tour', $area1, "Admin visits tour new for area1");
test_new_page($contrib_con, 'tour', $area2, "Contributor visits tour new for area2");
test_new_page($admin_con, 'place', $area1, "Admin visits place new for area1");
test_new_page($contrib_con, 'place', $area2, "Contributor visits place new for area2");


// Now setting the other areas on the tours route should result in those areas
// being selected on the places and tours route without setting them via param
test_index_page($admin_con, 'tour', $area2, "Admin sets area2 on tour index", true);
test_index_page($contrib_con, 'tour', $area1, "Contributor sets area1 on tour index", true);
test_index_page($admin_con, 'tour', $area2, "Admin visits tour index for area2", false);
test_index_page($contrib_con, 'tour', $area1, "Contributor visits tour index for area1", false);
test_index_page($admin_con, 'place', $area2, "Admin visits place index for area2", false);
test_index_page($contrib_con, 'place', $area1, "Contributor visits place index for area1", false);

// And on the new routes the areas should have changed as well
test_new_page($admin_con, 'tour', $area2, "Admin visits tour new for area2");
test_new_page($contrib_con, 'tour', $area1, "Contributor visits tour new for area1");
test_new_page($admin_con, 'place', $area2, "Admin visits place new for area2");
test_new_page($contrib_con, 'place', $area1, "Contributor visits place new for area1");


// cleanup
Areas::instance()->delete($area1);
Areas::instance()->delete($area2);

?>