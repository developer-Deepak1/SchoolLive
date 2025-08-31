<?php
namespace SchoolLive\Core;

class Validator {
    public static function missing(array $data, array $required): array {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}
