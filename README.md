# E-Lib
Simple library management web application for HMU

## Overview
E-Lib is a PHP-based web application designed for managing and accessing a library collection. The application features a modular architecture with separate routes for web pages and API endpoints, allowing for a clean separation of concerns and easy extensibility.

## Project Structure
The project is organized into several directories and files:

- **App/**: Contains the core application logic.
  - **Router/**: Houses routing classes.
    - `ApiRouter.php`: Manages API routing.
    - `PageRouter.php`: Handles web page routing.
    - `BaseRouter.php`: Base class for routing functionality.
  - **includes/**: Contains additional functionality.
    - `MongoDb.php`: Connects to and interacts with a MongoDB database.
    - `Environment.php`: Manages environment variables.
  - **views/**: Contains the application's view templates.
    - `add_book.php`: Form for adding new books to the library.
    - Other view files for different pages.

- **public/**: Contains publicly accessible files.
  - **styles/**: CSS files for styling the application.
    - `add_book.css`: Styles for the add book form.
  - `index.php`: The entry point of the application.

- **Dockerfile**: Defines the Docker environment for the application.

- **docker-compose.yml**: Configures multi-container Docker applications (if present).

## Features
- Add new books to the library collection
- Search functionality for finding books by title
- Categorize books by genre
- Manage book inventory with condition tracking and copy counts

## Getting Started
To get started with the E-Lib project:

### Environment Setup
1. **Create Environment File**:
   Copy the example environment file and update the values:
   ```
   cp .env.example .env
   ```
   
   Edit the `.env` file with your specific configuration values.

2. **Environment Variables**:
   The following environment variables can be configured:
   - `MONGODB_HOST`: MongoDB server hostname
   - `MONGODB_PORT`: MongoDB server port
   - `MONGODB_USERNAME`: MongoDB username
   - `MONGODB_PASSWORD`: MongoDB password
   - `MONGODB_DATABASE`: MongoDB database name
   - `APP_ENV`: Application environment (development, production)
   - `APP_DEBUG`: Debug mode (true/false)
   - `APP_URL`: Base URL for the application

### Local Development with Docker
1. **Clone the Repository**:
   ```
   git clone <repository-url>
   cd E-Lib
   ```

2. **Build the Docker Image**:
   ```
   docker build -t e-lib .
   ```

3. **Run the Container**:
   ```
   docker run -d -p 8080:80 --name e-lib-app e-lib
   ```

   For development with live code updates:
   ```
   docker run -d -p 8080:80 -v $(pwd):/var/www/html --name e-lib-app e-lib
   ```

4. **Access the Application**:
   Open your web browser and navigate to `http://localhost:8080`.

### Managing Your Docker Container
- **View logs**:
  ```
  docker logs e-lib-app
  ```

- **Stop the container**:
  ```
  docker stop e-lib-app
  ```

- **Restart the container**:
  ```
  docker start e-lib-app
  ```

## Dependencies
- Docker
- PHP 8.2
- MongoDB
- Apache

## Database Setup
The application uses MongoDB for data storage. The database connection is handled by the `MongoDb.php` class in the `App/includes` directory.

Connection parameters are configured through environment variables in the `.env` file. Make sure to set up your MongoDB instance and update the connection details in your environment configuration.

## Contributing
To contribute to this project, please follow these steps:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License
This project is licensed under the MIT License.
