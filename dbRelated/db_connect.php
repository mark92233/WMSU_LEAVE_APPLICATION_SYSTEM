<?php
// Set default PHP timezone to match database requirements
date_default_timezone_set('Asia/Manila'); 

class Database {
    // Database connection credentials and configuration
    private $servername = "localhost";
    private $username   = "root";
    private $password   = "";
    private $dbname     = "employee_leave_db"; 

    protected $conn;

    // Establish and return a secure PDO database connection
    public function connect() {
        // Define data source name with host, dbname, and charset
        $dsn = "mysql:host=$this->servername;dbname=$this->dbname;charset=utf8mb4";
        
        // Configure PDO error mode, preparation emulation, and timezone sync
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+8:00'"
        ];
        
        try {
            // Initialize PDO connection with defined settings
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;

        } catch (PDOException $e) {
            // Handle connection errors by logging and terminating execution
            error_log("Database connection failed: " . $e->getMessage());
            die("CRITICAL ERROR: Database connection failed. Please ensure MySQL is running and correctly configured. Error: " . $e->getMessage());
        }
    }
}