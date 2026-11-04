<?php

header("Content-Type: application/json; charset=UTF-8");

function sendResponse(
    int $statusCode,
    bool $success,
    string $message,
    array $data = []
): void {
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}