<?php
require_once __DIR__ . "/common.php";
require_once __DIR__ . "/html_table.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    switch(true) {
    case isset($_POST['submit_id']):
        $msg = db_process();
        main_form($msg);
        break;
    case isset($_POST['edit_id']):
        edit_form();
        break;
    case isset($_POST['delete_id']):
        $msg = db_delete_server();
        main_form($msg);
        break;
    case isset($_POST['cancel']):
    default:
        main_form();
    }
} else {
    main_form();
}

exit;

/**
 * Display server list and controls to add or edit a server.
 *
 * @param string $response Print any error/success messages from a add or edit.
 */
function main_form($response="") {
    db_connect($db);
    $server_list = db_get_servers($db, array(), array(), "id", false);
    $db->close();

    $table = new html_table(array('class' => "table alt-rows-bgcolor"));
    $headers = array("ID", "Name", "Label", "Is Active", "Status", "LMGRD Version", "Last Updated", "");
    $table->add_row($headers, array(), "th");

    foreach($server_list as $i => $server) {
        $row = array(
            $server['id'],
            $server['name'],
            $server['label'],
            $server['is_active'] ? "True" : "False",
            $server['status'],
            $server['lmgrd_version'],
            date_format(date_create($server['last_updated']), "m/d/Y h:ia"),
            "<button type='submit' form='server_list' name='edit_id' class='edit-submit' value='{$server['id']}'>EDIT</button>"
        );

        $table->add_row($row);
        switch($server['status']) {
        case null:
            $table->update_cell($table->get_rows_count()-1, 4, array('class'=>"info"), "Not Polled");
            break;
        case SERVER_UP:
            // Do nothing.
            break;
        case SERVER_VENDOR_DOWN:
            $table->update_cell($table->get_rows_count()-1, 4, array('class'=>"warning"));
            break;
        case SERVER_DOWN:
        default:
            $table->update_cell($table->get_rows_count()-1, 4, array('class'=>"danger"));
            break;
        }
    }

    // Print view.
    print_header();

    print <<<HTML
    <h1>Server Administration</h1>
    <p>You may edit an existing server's name, label, active status, or add a new server to the database.<br>
    Server names must be unique and in the form of "<code>port@domain.tld</code>".
    {$response}
    <form id='server_list' action='servers_admin.php' method='POST'>
    {$table->get_html()}
    <p><button type='submit' form='server_list' name='edit_id' class='btn' value='new'>New Server</button>
    </form>
    HTML;

    print_footer();
} // END function main_form()

/** Add/Edit server form.  No DB operations. */
function edit_form() {
    $id = $_POST['edit_id'];

    // Validate adding a new server or editing an existing server.
    // Skip back to the main_form() if something is wrong.
    $err_msg = "<p class='red-text'>&#10006; Validation failed when requesting form to edit an existing server.";
    switch(true) {
    case ctype_digit($id):
        $server_details = server_details_by_getid($id);
        $delete_button = "<button type='button' class='btn edit-form' id='delete-button'>Remove</button>";
        if ($server_details === false) {
            main_form($err_msg);
            return null;
        }
        break;
    case $id === "new":
        $server_details = array('name'=>"", 'label'=>"", 'is_active'=>"1");
        $delete_button = "";
        break;
    default:
        main_form($err_msg);
        return null;
    }

    // print view
    $is_checked = $server_details['is_active'] === '1' ? " CHECKED" : "";
    print_header();

    print <<<HTML
    <h1>Server Details</h1>
    <form action='servers_admin.php' method='post' class='edit-form'>
        <div class='edit-form block'>
            <label for='name'>Name (format: <code>port@domain.tld</code>)</label><br>
            <input type='text' name='name' id='name' class='edit-form' value='{$server_details['name']}'>
        </div><div class='edit-form block'>
            <label for='label'>Label</label><br>
            <input type='text' name='label' id='label' class='edit-form' value='{$server_details['label']}'>
        </div><div class='edit-form inline-block'>
            <label for='is_active'>Is Active?</label>
            <input type='checkbox' name='is_active' id='is_active' class='edit-form'{$is_checked}>
        </div><div class='edit-form inline-block float-right'>
            <input type='hidden' id='delete-server'>
            <button type='submit' class='btn btn-cancel edit-form' name='cancel' value='1'>Cancel</button>
            <button type='submit' class='btn btn-primary edit-form' name='submit_id' value='{$id}'>Submit</button>
            {$delete_button}
        </div>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
        <script>
        $('#delete-button').click(function() {
            if (confirm("Confirm removal for \\"{$server_details['name']}\\" ({$server_details['label']})\\n*** THIS WILL REMOVE USAGE HISTORY FOR EVERY FEATURE\\n*** THIS CANNOT BE UNDONE")) {
                $('#delete-server').attr('name', "delete_id");
                $('#delete-server').attr('value', {$id});
                $('form').submit();
            }
        });
        </script>
    </form>
    HTML;

    print_footer();
} // END function edit_form()

/** DB operation to either add or edit a form, based on $_POST['id'] */
function db_process() {
    $id = $_POST['submit_id'];
    $name = $_POST['name'];
    $label = $_POST['label'];
    $is_active = $_POST['is_active'] === "on" ? 1 : 0;

    // Error check.  On error, stop and return error message.
    switch(false) {
    // $id must be all numbers or the word "new"
    case preg_match("/^\d+$|^new$/", $id):
        return "<p class='red-text'>&#10006; Invalid server ID \"{$id}\"";
    // $name must match port@domain.tld
    case preg_match("/^\d{1,5}@(?:[a-z\d\-]+\.)+[a-z\-]{2,}$/i", $name,):
        return "<p class='red-text'>&#10006; Server name MUST be in form <code>port@domain.tld</code>";
    // $label cannot be blank
    case !empty($label):
        return "<p class='red-text'>&#10006; Server's label cannot be blank";
    }
    // END error check

    if ($id === "new") {
        // Adding a new server
        $sql = "INSERT INTO `servers` (`name`, `label`, `is_active`) VALUES (?, ?, ?)";
        $params = array("ssi", $name, $label, $is_active);
        $op = "added";
    } else {
        // Editing an existing server
        $sql = "UPDATE `servers` SET `name`=?, `label`=?, `is_active`=? WHERE `ID`=?";
        $params = array("ssii", $name, $label, $is_active, $id);
        $op = "updated";
    }

    db_connect($db);
    $query = $db->prepare($sql);
    $query->bind_param(...$params);
    $query->execute();

    if (empty($db->error_list)) {
        $response_msg = "<p class='green-text'>&#10004; {$name} ({$label}) successfully {$op}.";
    } else {
        $response_msg = "<p class='red-text'>&#10006; (${name}) DB Error: {$db->error}.";
    }

    $query->close();
    $db->close();
    return $response_msg;
} // END function db_process()

/**
 * Retrieve server details by server ID.
 *
 * @param int $id
 * @return array server's name, label and active status.
 */
function server_details_by_getid($id) {
    db_connect($db);
    $server_details = db_get_servers($db, array("name", "label", "is_active"), array($id), "", false);
    $db->close();
    return !empty($server_details) ? $server_details[0] : false;
} // END function server_details_by_getid()

function db_delete_server() {
    // validate
    if (ctype_digit($_POST['delete_id'])) {
        $id = $_POST['delete_id'];
    } else {
        return "<p class='red-text'>&#10006; Validation failed when attempting to remove a server from DB.";
    }

    $sql = "DELETE FROM `servers` WHERE `id`=?";
    $params = array("i", intval($id));

    db_connect($db);
    $details = db_get_servers($db, array('name', 'label'), array($id), "", false)[0];
    $name = $details['name'];
    $label = $details['label'];
    $query = $db->prepare($sql);
    $query->bind_param(...$params);
    $query->execute();

    if (empty($db->error_list)) {
        $response = "<p class='green-text'>&#10004; Successfully deleted ID {$id}: \"{$name}\" ({$label})";
    } else {
        $response = "<p class='red-text'>&#10006; ID ${id}: \"${name}\" ({$label}), DB Error: \"{$db->error}\"";
    }

    $query->close();
    $db->close();

    return $response;
} // END function db_delete_server()
?>
