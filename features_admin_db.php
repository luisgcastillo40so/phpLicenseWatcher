<?php
require_once __DIR__ . "/common.php";

/**
 * Retrieve feature details by feature ID.
 *
 * @param int $id
 * @return mixed either a feature's details in associative array or error message on failure.
 */
function db_get_feature_details_by_id($id) {
    if (!ctype_digit($id)) {
        return false;
    }

    $id = intval($id);

    db_connect($db);
    $sql = "SELECT `name`, `label`, `show_in_lists`, `is_tracked` FROM `features` WHERE `id`=?";
    $params = array('i', $id);
    $query = $db->prepare($sql);
    $query->bind_param(...$params);
    $query->execute();
    $query->bind_result($feature['name'], $feature['label'], $feature['show_in_lists'], $feature['is_tracked']);
    $query->fetch();

    if (!empty($db->error_list)) {
        $err_msg = htmlspecialchars($db->error);
        return "<p class='red-text'>&#10006; DB Error: {$err_msg}.";
    }

    $query->close();
    $db->close();

    // Make sure that $feature isn't an empty set.  Return error message when an empty set.
    $validate_feature = array_filter($feature, function($val) { return strlen($val) > 0; });
    return !empty($validate_feature) ? $feature : "<p class='red-text'>&#10006; DB returned empty set during feature lookup.";
} // END function db_get_feature_details_by_id()

/**
 * Change the status of either 'show_in_lists' or is_tracked' for all features.
 *
 * @return string response message to display on main form.  Or empty string for no message.
 */
function db_change_column() {
    clean_post();

    // validate
    switch (false) {
    case isset($_POST['val']) && preg_match("/^[01]$/", $_POST['val']):
    case isset($_POST['col']) && preg_match("/^show_in_lists$|^is_tracked$/", $_POST['col']):
    case isset($_POST['page']) && ctype_digit($_POST['page']):
    case isset($_POST['search']):
        // Return to main form.  No DB process.
        return "Validation failed for check/uncheck column";
    }

    // extract values from POST
    $col = $_POST['col'];
    $val = $_POST['val'];
    $page = intval($_POST['page']);
    $search_token = $_POST['search'];

    $rows_per_page = ROWS_PER_PAGE;  // defined in common.php
    $first_row = ($page-1) * $rows_per_page;  // starting row, zero based.

    if ($search_token !== "") {
        $regexp = "WHERE `name` REGEXP ?";
        $params = array("siii",  $search_token, $first_row, $rows_per_page, $val);
    } else {
        $regexp = "";
        $params = array("iii", $first_row, $rows_per_page, $val);
    }

    // DB query.
    $sql = <<<SQL
    UPDATE `features` f, (
        SELECT `id`
        FROM `features`
        {$regexp}
        ORDER BY `id` ASC
        LIMIT ?,?
    ) AS ftmp
    SET f.`{$col}`=?
    WHERE f.`id`=ftmp.`id`;
    SQL;

    db_connect($db);
    $query = $db->prepare($sql);
    if (is_bool($query)) {
        return $db->error;
    }
    $query->bind_param(...$params);
    $query->execute();

    // result from query.
    if (!empty($db->error_list)) {
        $response_msg = "DB Error: {$db->error}.";
    } else {
        $response_msg = "OK";
    }

    $query->close();
    $db->close();
    return $response_msg;
} // END function db_change_column()

/**
 * Change a single feature's 'show_in_lists' or 'is_tracked' column.
 *
 * @return string response message to indocate success or error.
 */
function db_change_single() {
    //validate
    clean_post();
    switch(false) {
    case isset($_POST['id']) && ctype_digit($_POST['id']):
    case isset($_POST['col']) && preg_match("/^show_in_lists$|^is_tracked$/", $_POST['col']):
    case isset($_POST['state']) && preg_match("/^[01]$/", $_POST['state']):
        // Return to main form.  No DB process.
        return "Validation failed for checkbox toggle.";
    }

    $id = $_POST['id'];
    $state = $_POST['state'];
    $col = $_POST['col'];

    $sql = "UPDATE `features` SET `{$col}`=? WHERE `id`=?";
    $params = array("ii", intval($state), intval($id));

    db_connect($db);
    $query = $db->prepare($sql);
    $query->bind_param(...$params);
    $query->execute();

    if (!empty($db->error_list)) {
        $response_msg = "DB Error: {$db->error}.";
    } else {
        $response_msg = "OK";  // indicate success.
    }

    $query->close();
    $db->close();
    return $response_msg;
} // END function db_change_single()

/**
 * DB operation to either add or edit a feature, based on $_POST['id']
 *
 * @return string response message from operation (either success or error message).
 */
function db_process() {
    // Validate and set.
    clean_post();
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    $name = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : null;
    $label = isset($_POST['label']) && !empty($_POST['label']) ? htmlspecialchars($_POST['label']) : null;
    $show_in_lists = isset($_POST['show_in_lists']) && ($_POST['show_in_lists'] === "on" || $_POST['show_in_lists'] === true) ? 1 : 0;
    $is_tracked = isset($_POST['is_tracked']) && ($_POST['is_tracked'] === "on" || $_POST['is_tracked'] === true) ? 1 : 0;
    $page = isset($_POST['page']) && ctype_digit($_POST['page']) ? intval($_POST['page']) : 1;

    // Further validate.  On error, stop and return error message.
    switch(false) {
    // $id must be all numbers or the word "new"
    case preg_match("/^\d+$|^new$/", $id):
        return array('msg'=>"<p class='red-text'>&#10006; Invalid feature ID \"{$id}\"", 'page'=>$page);
    // $name cannot be blank
    case !empty($name):
        return array('msg'=>"<p class='red-text'>&#10006; Feature name cannot be blank", 'page'=>$page);
    }
    // $label can be blank.
    // END error check

    if ($id === "new") {
        // Adding a new server
        $sql = "INSERT INTO `features` (`name`, `label`, `show_in_lists`, `is_tracked`) VALUES (?, ?, ?, ?)";
        $params = array("ssii", $name, $label, $show_in_lists, $is_tracked);
        $op = "added";
    } else {
        // Editing an existing server
        $id = intval($id);
        $sql = "UPDATE `features` SET `name`=?, `label`=?, `show_in_lists`=?, `is_tracked`=? WHERE `ID`=?";
        $params = array("ssiii", $name, $label, $show_in_lists, $is_tracked, $id);
        $op = "updated";
    }

    db_connect($db);
    $query = $db->prepare($sql);
    $query->bind_param(...$params);
    $query->execute();

    if (empty($db->error_list)) {
        if (!empty($label)) $label = " ({$label})";
        $response_msg = "<p class='green-text'>&#10004; {$name}{$label} successfully {$op}.";
    } else {
        $response_msg = "<p class='red-text'>&#10006; (${name}) DB Error: {$db->error}.";
    }

    $query->close();
    $db->close();

    return array('msg'=>$response_msg, 'page'=>$page);
} // END function db_process()

/**
 * Delete feature from DB by feature's ID.
 *
 * @return array success/error message and page to return to.
 */
function db_delete_feature() {
    // validate
    clean_post();
    switch (false) {
    case isset($_POST['name']):
    case isset($_POST['id']) && ctype_digit($_POST['id']):
    case isset($_POST['page']) && ctype_digit($_POST['page']):
        // Do not process
        return array('msg'=>"<p class='red-text'>&#10006; Request to delete a feature has failed validation.", 'page'=>1);
    }

    $name = htmlspecialchars($_POST['name']);
    $id = intval($_POST['id']);
    $page = intval($_POST['page']);

    db_connect($db);
    $sql = "DELETE FROM `features` WHERE `id`=?";
    $params = array("i", $id);
    $query = $db->prepare($sql);
    $query->bind_param(...$params);
    $query->execute();

    if (empty($db->error_list)) {
        $response = "<p class='green-text'>&#10004; Successfully deleted ID {$id}: {$name}";
    } else {
        $response = "<p class='red-text'>&#10006; ID ${id}: ${name}, DB Error: {$db->error}.";
    }

    $query->close();
    $db->close();

    return array('msg'=>$response, 'page'=>intval($page));
} //END function delete_feature()

function db_get_page_data($page, $search_token="") {
    $rows_per_page = ROWS_PER_PAGE;  // defined in common.php
    $first_row = ($page-1) * $rows_per_page;  // starting row, zero based.
    $results = array();

    // Used in 'feature_list' query.  Constrain query by search token or select entire table.
    if ($search_token === "") {
        $where = "";
        $params['feature_list'] = array("ii", $first_row, $rows_per_page);
        $params['feature_count'] = null;

    } else {
        $where = "WHERE `name` REGEXP ?";
        $params['feature_list'] = array ("sii", $search_token, $first_row, $rows_per_page);
        $params['feature_count'] = array("s", $search_token);
    }

    file_put_contents("/home/vagrant/var.out", var_export($first_row, true) . PHP_EOL);

    // Query to get current page of features
    $sql['feature_list'] = <<<SQL
    SELECT * FROM `features`
    {$where}
    ORDER BY `id` ASC
    LIMIT ?, ?
    SQL;

    // Query for how many features are in the DB?  (to determine how many pages there are)
    $sql['feature_count'] = "SELECT COUNT(*) FROM `features` {$where}";

    db_connect($db);
    // Run query to get features for current page
    $query = $db->prepare($sql['feature_list']);
    $query->bind_param(...$params['feature_list']);
    $query->execute();
    $query->bind_result($r_id, $r_name, $r_label, $r_lists, $r_tracked);
    while ($query->fetch()) {
        $results[] = array(
            'id' => $r_id,
            'name' => $r_name,
            'label' => $r_label,
            'show_in_lists' => $r_lists,
            'is_tracked' => $r_tracked
        );
    }
    $query->close();

    // Run query to get feature count and determine how many pages there are.
    $query = $db->prepare($sql['feature_count']);
    if (!is_null($params['feature_count'])) $query->bind_param(...$params['feature_count']);
    $query->execute();
    $query->bind_result($r_count);
    $query->fetch();

    file_put_contents("/home/vagrant/var.out", var_export($sql['feature_count'], true) . PHP_EOL);
    $total_pages = intval(ceil($r_count / $rows_per_page));

    $response = !empty($db->error_list) ? "<p class='red-text'>&#10006; DB Error: {$db->error}." : "";

    $query->close();
    $db->close();

    return array('response' => $response, 'features' => $results, 'last_page' => $total_pages);
} //END function db_search()
?>
