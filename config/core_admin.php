<?php

return [
    'emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', env('CORE_ADMIN_EMAILS', '')),
    ))),
];
