<?php 

class Database {
    private $connection;
    private $db_name;

    public function __construct($db_host = 'localhost', $db_user = 'root', $db_pass = '', $db_name = '4lapy_parser') {
        $this->connection = new mysqli($db_host, $db_user, $db_pass);
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
        $this->connection->set_charset('utf8');
        $this->db_name = $db_name;
    }

    public function initDatabase() {
        $createDb = "CREATE DATABASE IF NOT EXISTS " . $this->db_name. " ";

        if (!$this->connection->query($createDb)) {
            die("Error creating database: " . $this->connection->error);
        }

        $this->connection->select_db($this->db_name);
        $createTable = "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            url VARCHAR(500) NOT NULL,
            image_url VARCHAR(500),
            price INT,
            title VARCHAR(255),
            description TEXT,
            characteristics TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        if (!$this->connection->query($createTable)) {
            die("Error creating table: " . $this->connection->error);
        }
    }

    public function saveData(array $data) {
        $this->initDatabase();

        foreach ($data as $product) {

            $url = $this->connection->real_escape_string($product['url']);
            $imageUrl = $this->connection->real_escape_string($product['image_url']);
            $price = (int)$product['price']; 
            $title = $this->connection->real_escape_string($product['title']);
            $description = $this->connection->real_escape_string($product['description']);
            $characteristics = $this->connection->real_escape_string($product['characteristics']);
            
            $query = "INSERT INTO products 
                (url, image_url, price, title, description, characteristics)
                VALUES ('$url', '$imageUrl', $price, '$title', '$description', '$characteristics')";
                
            if (!$this->connection->query($query)) {
                die("Error inserting data: " . $this->connection->error);
            }
        }

        $this->connection->close();
    }
}

