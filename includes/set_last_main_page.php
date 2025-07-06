<?php
@session_start();
if (isset($_POST['main_page'])) {
    $_SESSION['last_main_page'] = $_POST['main_page'];
    echo "OK";
    echo "last_main_page set to: " . $_SESSION['last_main_page'];
} else {
    http_response_code(400);
    echo "Missing main_page";
}
?>