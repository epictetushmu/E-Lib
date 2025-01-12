<?php
class Database {
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $dbname = "LibraryDB";
    private $connection;

    public function __construct() {
        // Create a new MySQLi connection
        $this->connection = new mysqli($this->host, $this->username, $this->password, $this->dbname);

        // Check connection
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
    }

    // Method to get the connection
    public function getConnection() {
        return $this->connection;
    }

    // Close connection when object is destroyed
    public function __destruct() {
        $this->connection->close();
    }
}
