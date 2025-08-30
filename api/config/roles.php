<?php
return [
    // Basic role constants
    'ADMIN' => 'admin',
    'TEACHER' => 'teacher',
    'CLIENT_ADMIN' => 'client_admin',
    'STUDENT' => 'student',
    'PARENT' => 'parent',

    // Convenience groups (can be changed later)
    'ADMIN_ONLY' => ['admin'],
    'ADMIN_TEACHER' => ['admin', 'teacher'],
    'CLIENTADMIN_TEACHER' => ['client_admin', 'teacher']
];
