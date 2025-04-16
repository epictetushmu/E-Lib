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
   - `MONGO_URI`: MongoDB server hostname
   - `MONGO_PASSWORD`: MongoDB password
   - `NGROK_AUTH_TOKEN`: Ngrok token for development tunneling
   - `CAS_SERVER_URL` : Cas server url for authentication

### Local Development with Docker
1. **Clone the Repository**:
   ```
   git clone <repository-url>
   cd E-Lib
   ```

2. **Setup Environment**:
   ```
   cp .env.example .env
   ```
   Edit the `.env` file with your specific configuration values.

3. **Build the Docker Image**:
   ```
   docker build -t e-lib .
   ```

4. **Start the Docker Environment**:
   ```
   docker-compose up -d
   ```
   This command builds and starts all containers defined in docker-compose.yml (PHP/Apache, MongoDB).

5. **Install Dependencies**:
   ```
   docker exec elib-app composer install
   ```

6. **Set Permissions** (if needed):
   ```
   docker exec elib-app chown -R www-data:www-data /var/www/html
   ```

7. **Access the Application**:
   Open your web browser and navigate to `http://localhost:8080`.

### Rebuilding Docker Environment
If you need to completely rebuild your Docker environment:

```
# Stop and remove containers, networks, and volumes
docker-compose down -v

# Rebuild and start containers
docker-compose up -d --build
```

### Managing Your Docker Environment
- **Start containers**:
  ```
  docker-compose up -d
  ```

- **Stop containers**:
  ```
  docker-compose stop
  ```

- **View logs**:
  ```
  # View all logs
  docker-compose logs
  
  # View app logs only
  docker-compose logs app
  
  # Follow logs in real-time
  docker-compose logs -f
  ```

### Common Docker Commands
- **Enter the container for debugging**:
  ```
  docker exec -it elib-app bash
  ```

- **Check container status**:
  ```
  docker-compose ps
  ```

- **Restart the application container**:
  ```
  docker-compose restart app
  ```

- **View PHP error logs**:
  ```
  docker exec elib-app tail -f /var/log/apache2/error.log
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
