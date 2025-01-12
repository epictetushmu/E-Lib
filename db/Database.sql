-- Create the database with a name
CREATE DATABASE LibraryDB 
    DEFAULT CHARACTER SET = 'utf8mb4';

-- Select the database
USE LibraryDB;

-- Create the Category table
CREATE TABLE Category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

-- Create the Book table
CREATE TABLE Book (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    publication_year INT,
    `condition` VARCHAR(10),
    number_of_copies INT,
    description VARCHAR(255),
    category_id INT,
    FOREIGN KEY (category_id) REFERENCES Category(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- Create the Borrow table
CREATE TABLE Borrow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    borrower_name VARCHAR(255) NOT NULL,
    borrow_date DATE NOT NULL,
    return_date DATE,
    FOREIGN KEY (book_id) REFERENCES Book(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
