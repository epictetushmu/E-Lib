<?php

namespace App\Helpers;

class PdfHelper {
    
    private $pdfPath; 

    /**
     * Constructor
     * 
     * @param string $pdfPath Path to the PDF file
     */
    public function __construct($pdfPath = null) {
        $this->pdfPath = $pdfPath;
    }

    /**
     * Extracts the first page of a PDF and saves it as an image
     * 
     * @param string $pdfPath Path to the PDF file
     * @param string $outputPath Path where to save the image
     * @param string $format Image format (jpg, png)
     * @return bool True if successful, false otherwise
     */
    public function extractFirstPageAsImage($pdfPath, $outputPath, $format = 'jpg') {
        // Check if Imagick is installed
        if (!extension_loaded('imagick')) {
            // Fallback if Imagick is not available
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
                mkdir($outputDir, 0755, true);
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
            mkdir($outputDir, 0755, true);
        }
        
        // If no Imagick, use default placeholder image
        if (file_exists(__DIR__ . '/../../public/assets/images/pdf-placeholder.jpg')) {
            copy(__DIR__ . '/../../public/assets/images/pdf-placeholder.jpg', $outputPath);
            return true;
        }
        return false;
    }
    
    /**
     * Gets or creates a thumbnail for a PDF
     * 
     * @param string $thumbnailDir Directory to store thumbnails
     * @return string Path to the thumbnail
     */
    public function getPdfThumbnail() {
        // Use project root to determine paths
        $projectRoot = dirname(dirname(dirname(__DIR__)));
        $publicDir = $projectRoot . '/public';
        $thumbnailDir = $publicDir . '/assets/uploads/thumbnails';
        
        // Create the directory if it doesn't exist
        if (!is_dir($thumbnailDir)) {
            if (!mkdir($thumbnailDir, 0755, true)) {
                error_log("Failed to create thumbnail directory: $thumbnailDir");
                return '/assets/images/default-pdf-cover.jpg';
            }
        }
        
        // Generate a unique name for the thumbnail
        $thumbnailName = md5(basename($this->pdfPath)) . '.jpg';
        $thumbnailPath = $thumbnailDir . '/' . $thumbnailName;
        
        // Check if thumbnail already exists
        if (!file_exists($thumbnailPath)) {
            // Extract the first page
            if (!$this->extractFirstPageAsImage($this->pdfPath, $thumbnailPath)) {
                // Return a default image if extraction fails
                return '/assets/images/default-pdf-cover.jpg';
            }
        }
        
        return '/assets/uploads/thumbnails/' . $thumbnailName;
    }

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
            $newFileName = uniqid('pdf_', true) . '.' . $fileExtension;
            
            // Use project root to determine the public directory
            $projectRoot = dirname(dirname(dirname(__DIR__)));
            $publicDir = $projectRoot . '/public';
            $uploadFileDir = $publicDir . '/assets/uploads/pdfs/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadFileDir)) {
                if (!@mkdir($uploadFileDir, 0755, true)) {
                    error_log("Failed to create directory: $uploadFileDir");
                    return false;
                }
            }
            
            // Destination path
            $dest_path = $uploadFileDir . $newFileName;
            
            // Move the file
            if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                error_log("Failed to move file from $fileTmpPath to $dest_path");
                return false;
            }
            
            // Set pdfPath property
            $this->pdfPath = $dest_path;
            
            // Return web-accessible path
            return '/assets/uploads/pdfs/' . $newFileName;
        } catch (\Exception $e) {
            error_log("Error storing PDF: " . $e->getMessage());
            return false;
        }
    }
}
