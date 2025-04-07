<?php
namespace App\Includes;

use PDO; 
use PDOException; 


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
            
            // For SELECT queries
            if (stripos($sql, 'SELECT') === 0) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // For other queries (UPDATE, DELETE)
            return $stmt->rowCount();    
        } catch (PDOException $e) {
            error_log('Database Error: '. $e->getMessage());
            return false; 
        }
    }
    
    // Methods for transaction handling
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollBack() {
        return $this->pdo->rollBack();
    }
}
