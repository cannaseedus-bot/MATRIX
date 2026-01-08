<?php
require __DIR__ . '/config.php';
require __DIR__ . '/lib/helpers.php';

use function Matrix\Helpers\e;
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo e($site_name); ?></title>
</head>
<body>
    <header>
        <img src="<?php echo e($logo_url); ?>" alt="<?php echo e($site_name); ?> logo" height="48">
        <h1><?php echo e($site_header); ?></h1>
    </header>
    <input id="cmd" placeholder="Enter Python command">
    <button onclick="sendTask()">Send Task</button>
    <h2>Output</h2>
    <pre id="output"></pre>

<script>
const apiBase = <?php echo json_encode($api_base, JSON_THROW_ON_ERROR); ?>;

async function sendTask() {
    let cmd = document.getElementById("cmd").value;
    let res = await fetch(`${apiBase}/add_task.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ command: cmd })
    });
    let data = await res.json();
    document.getElementById("output").textContent = JSON.stringify(data, null, 2);
}
</script>
</body>
</html>
