<?php
return ['server_url'=>env('DESKTOP_SERVER_URL','https://micv.sistemajz.com'),'require_https'=>env('DESKTOP_REQUIRE_HTTPS',true),'signing_key'=>env('DESKTOP_SIGNING_KEY_PATH',storage_path('app/private/desktop-signing.key'))];
