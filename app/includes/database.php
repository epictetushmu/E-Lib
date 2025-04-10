<?php
namespace App\Includes;
use Dotenv\Dotenv;

use PDO; 
use PDOException; 


class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Use environment variables to configure the database connection
        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'];
        $dbname = $_ENV['DB_DATABASE'];
        $username = $_ENV['DB_USERNAME'];
        $password = $_ENV['DB_PASSWORD'];

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $this->connection = new PDO($dsn, $username, $password);
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function execQuery($sql, $params = array(), $returnLastInsertId = false) {
        try{
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
        
            if ($returnLastInsertId) {
                return $this->connection->lastInsertId();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);    
        }catch (PDOException $e) {
            echo 'Error:'. $e->getMessage();
            return false; 
        }
    }
}
