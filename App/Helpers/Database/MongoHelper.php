<?php

namespace App\Helpers\Database;

/**
 * MongoDB helper utility to provide safe creation of MongoDB specific objects
 */
class MongoHelper
{
    /**
     * Safely create an ObjectId from a string
     *
     * @param string $id The ID string
     * @return \MongoDB\BSON\ObjectId|string The ObjectId, or the original string if it isn't a valid ObjectId
     */
    public static function createObjectId($id)
    {
        try {
            return new \MongoDB\BSON\ObjectId($id);
        } catch (\Exception $e) {
            error_log("Error creating ObjectId: " . $e->getMessage());
            return $id;
        }
    }

    /**
     * Safely create a MongoDB Regex object
     *
     * @param string $pattern The regex pattern
     * @param string $flags Regex flags (e.g., 'i' for case-insensitive)
     * @return \MongoDB\BSON\Regex|array<string, string> A MongoDB Regex object or an array simulating one
     */
    public static function createRegex($pattern, $flags = '')
    {
        try {
            return new \MongoDB\BSON\Regex($pattern, $flags);
        } catch (\Exception $e) {
            error_log("Error creating Regex: " . $e->getMessage());
            return ['$regex' => $pattern, '$options' => $flags];
        }
    }

    /**
     * Safely convert a BSON Array or Object to a PHP array
     *
     * @param mixed $bsonArray The BSON Array or Object
     * @return array<int, string> The PHP array
     */
    public static function toArray($bsonArray): array
    {
        $arr = is_object($bsonArray) && method_exists($bsonArray, 'getArrayCopy')
            ? $bsonArray->getArrayCopy()
            : (array) $bsonArray;

        return array_values(array_map('strval', $arr));
    }
}
