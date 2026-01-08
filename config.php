<?php
$domain = "https://YOURDOMAIN.com";
$site_name = "Distributed Task Runner";
$site_header = "Distributed Task Runner";
$logo_url = $domain . "/app.kuhul.svg";

$db_host = "localhost";
$db_name = "YOUR_DB_NAME";
$db_user = "YOUR_DB_USER";
$db_pass = "YOUR_DB_PASS";

$api_key = "";

$api_base = $domain . "/api";

$google_oauth_client_id = "YOUR_GOOGLE_CLIENT_ID";
$google_oauth_client_secret = "YOUR_GOOGLE_CLIENT_SECRET";
$google_oauth_redirect_url = $domain . "/oauth/google";

$stripe_public_key = "YOUR_STRIPE_PUBLIC_KEY";
$stripe_secret_key = "YOUR_STRIPE_SECRET_KEY";

$components = [
    "header" => "header.php",
    "footer" => "footer.php",
];
?>
