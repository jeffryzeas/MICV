<?php

return [
    "antivirus" => env("CHAT_ANTIVIRUS", "/usr/bin/clamscan"),
    "rp_id" => env(
        "PASSKEY_RP_ID",
        parse_url(env("APP_URL", "https://localhost"), PHP_URL_HOST),
    ),
    "origin" => rtrim(env("APP_URL", "https://localhost"), "/"),
];
