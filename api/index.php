<?php

require_once __DIR__ . "/response.php";

sendResponse(
    200,
    true,
    "Secure Voting API is working.",
    [
        "project" => "Secure Online Voting System",
        "version" => "1.0",
        "status" => "operational"
    ]
);