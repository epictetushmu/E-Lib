<?php

namespace App\Helpers;

class PdfHelper {
    
    private $pdfPath;
    private $thumbnailPath;

    /**
     * Constructor
     * 
     * @param string $pdfPath Path to the PDF file
     */
    public function __construct($pdfPath = null, $thumbnailPath = null) {
        $this->pdfPath = $pdfPath;
        $this->thumbnailPath = $thumbnailPath;
    }

    /**
     * Extracts the first page of a PDF and saves it as an image
     */
    public function extractFirstPageAsImage($pdfPath, $outputPath, $format = 'jpg') {
        // Check if Imagick is installed
        if (!extension_loaded('imagick')) {
            return $this->fallbackExtractFirstPage($pdfPath, $outputPath);
        }

        try {
            // Check if the PDF exists
            if (!file_exists($pdfPath)) {
                error_log("PDF file not found: $pdfPath");
                return false;
            }
            
            // Create Imagick instance
            $imagick = new \Imagick();
            
            // Set resolution for better quality
            $imagick->setResolution(300, 300);
            
            // Read only the first page of the PDF
            $imagick->readImage($pdfPath . '[0]');
            
            // Convert to the desired format
            $imagick->setImageFormat($format);
            
            // Optimize the image
            $imagick->setImageCompressionQuality(90);
            
            // Make sure output directory exists
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                if (!@mkdir($outputDir, 0777, true)) {
                    error_log("Failed to create thumbnail directory: $outputDir");
                    return false;
                }
                // Explicitly set permissions after creation
                @chmod($outputDir, 0777);
            }
            
            // Write the image to the output path
            $imagick->writeImage($outputPath);
            
            // Clear the Imagick object
            $imagick->clear();
            $imagick->destroy();
            
            return true;
        } catch (\Exception $e) {
            error_log("PDF thumbnail extraction failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fallback method for extracting first page if Imagick is not available
     */
    private function fallbackExtractFirstPage($pdfPath, $outputPath) {
        // Make sure output directory exists
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            if (!@mkdir($outputDir, 0777, true)) {
                error_log("Failed to create directory: $outputDir");
                return false;
            }
        }
        
        // Use default placeholder image
        $defaultImage = __DIR__ . '/../../public/assets/images/pdf-placeholder.jpg';
        $altDefaultImage = __DIR__ . '/../../public/assets/uploads/thumbnails/placeholder-book.jpg';
        
        if (file_exists($defaultImage)) {
            copy($defaultImage, $outputPath);
            return true;
        } elseif (file_exists($altDefaultImage)) {
            copy($altDefaultImage, $outputPath);
            return true;
        }
        
        return false;
    }
    
    /**
     * Gets or creates a thumbnail for a PDF
     */
    public function getPdfThumbnail() {
        // Use environment detection for Docker compatibility
        if (getenv('DOCKER_ENV') === 'true') {
            $uploadDir = '/var/www/html/public';
            $thumbnailDir = $uploadDir . '/assets/uploads/thumbnails';
            $webPath = '/assets/uploads/thumbnails';
        } else {
            // Local development path
            $projectRoot = dirname(dirname(dirname(__DIR__)));
            $uploadDir = $projectRoot . '/public';
            $thumbnailDir = $uploadDir . '/assets/uploads/thumbnails';
            $webPath = '/assets/uploads/thumbnails';
        }
        
        // Create the directory if it doesn't exist
        if (!is_dir($thumbnailDir)) {
            if (!@mkdir($thumbnailDir, 0777, true)) {
                error_log("Failed to create thumbnail directory: $thumbnailDir");
                return '/assets/uploads/thumbnails/placeholder-book.jpg';
            }
            // Set permissions explicitly
            @chmod($thumbnailDir, 0777);
        }
        
        // Generate a unique name for the thumbnail
        $thumbnailName = md5(basename($this->pdfPath)) . '.jpg';
        $thumbnailPath = $thumbnailDir . '/' . $thumbnailName;
        
        // Check if thumbnail already exists
        if (!file_exists($thumbnailPath)) {
            // Extract the first page
            if (!$this->extractFirstPageAsImage($this->pdfPath, $thumbnailPath)) {
                // Return a default image if extraction fails
                return '/assets/uploads/thumbnails/placeholder-book.jpg';
            }
        }
        
        return $webPath . '/' . $thumbnailName;
    }

    /**
     * Stores a PDF file with a proper name
     */
    public function storePdf($pdfFile) {
        try {
            // Check if the file is a valid PDF
            if ($pdfFile['error'] !== UPLOAD_ERR_OK) {
                error_log("Upload error code: " . $pdfFile['error']);
                return false;
            }
            
            // Get file information
            $fileTmpPath = $pdfFile['tmp_name'];
            $fileName = $pdfFile['name'];
            $fileType = $pdfFile['type'];
            
            // Extract file extension
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Check if it's a PDF
            if ($fileExtension !== 'pdf') {
                error_log("Invalid file extension: $fileExtension");
                return false;
            }
            
            // Generate a unique name for the file
            $newFileName = uniqid('pdf_') . '.' . $fileExtension;
            
            // Use environment detection for Docker compatibility
            if (getenv('DOCKER_ENV') === 'true') {
                $uploadDir = '/var/www/html/public';
                $uploadFileDir = $uploadDir . '/assets/uploads/pdfs/';
                $webPath = '/assets/uploads/pdfs';
            } else {
                // Local development path
                $projectRoot = dirname(dirname(dirname(__DIR__)));
                $uploadDir = $projectRoot . '/public';
                $uploadFileDir = $uploadDir . '/assets/uploads/pdfs/';
                $webPath = '/assets/uploads/pdfs';
            }
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadFileDir)) {
                if (!@mkdir($uploadFileDir, 0777, true)) {
                    error_log("Failed to create directory: $uploadFileDir");
                    return false;
                }
                // Set permissions explicitly
                @chmod($uploadFileDir, 0777);
            }
            
            // Destination path
            $dest_path = $uploadFileDir . $newFileName;
            
            // Move the file
            if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                error_log("Failed to move file from $fileTmpPath to $dest_path");
                return false;
            }
            
            // Set the full server path for internal use
            $this->pdfPath = $dest_path;
            
            // Return web-accessible path
            return $webPath . '/' . $newFileName;
        } catch (\Exception $e) {
            error_log("Error storing PDF: " . $e->getMessage());
            return false;
        }
    }
}
