<?php
require_once('config.php');

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8';
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
            die();
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function execQuery($sql, $params = array(), $returnLastInsertId = false) {
        try{
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        
            if ($returnLastInsertId) {
                return $this->pdo->lastInsertId();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);    
        }catch (PDOException $e) {
            echo 'Error:'. $e->getMessage();
            return false; 
        }
    }
}
