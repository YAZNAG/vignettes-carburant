<?php

return [

    /*
     * Hachage des mots de passe : Argon2id (exigence du cahier des charges).
     * Repli bcrypt coût 12 possible via HASH_DRIVER=bcrypt si la plateforme
     * de production ne dispose pas d'Argon2.
     */
    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    'rehash_on_login' => true,

];
