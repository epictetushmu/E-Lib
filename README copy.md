# E-Lib Project

## Overview
E-Lib is a PHP-based web application designed for managing and accessing a library of resources. The application utilizes a modular routing system to handle both web page requests and API calls, making it versatile for various use cases.

## Project Structure
The project is organized into several directories and files, each serving a specific purpose:

- **App/**: Contains the core application logic.
  - **Router/**: Houses routing classes.
    - `ApiRouter.php`: Manages API routing.
    - `PageRouter.php`: Handles web page routing.
    - `Router.php`: Base class for routing functionality.
  - **includes/**: Contains additional functionality.
    - `MongoDb.php`: Connects to and interacts with a MongoDB database.

- **public/**: Contains publicly accessible files.
  - `index.php`: The entry point of the application, initializing error reporting and routing.

- **Dockerfile**: Defines the environment for the application using Docker.

- **docker-compose.yml**: Configures multi-container Docker applications.

- **.dockerignore**: Specifies files to ignore during the Docker build process.

## Getting Started
To get started with the E-Lib project, follow these steps:

1. **Clone the Repository**: Clone the project repository to your local machine.
2. **Build the Docker Image**: Navigate to the project directory and run:
   ```
   docker build -t e-lib .
   ```
3. **Run the Application**: Use Docker Compose to start the application:
   ```
   docker-compose up
   ```
4. **Access the Application**: Open your web browser and go to `http://localhost`.

## Dependencies
Ensure you have Docker and Docker Compose installed on your machine to run this application.

## License
This project is licensed under the MIT License. See the LICENSE file for more details.