<?php

namespace App\Helpers;

class PdfHelper {
    
    private $pdfPath; 

    /**
     * Constructor
     * 
     * @param string $pdfPath Path to the PDF file
     */
    public function __construct($pdfPath) {
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
            
            // Write the image to the output path
            $imagick->writeImage($outputPath);
            
            // Clear the Imagick object
            $imagick->clear();
            $imagick->destroy();
            
            return true;
        } catch (\Exception $e) {
            echo('PDF thumbnail extraction failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fallback method for extracting first page if Imagick is not available
     */
    private function fallbackExtractFirstPage($pdfPath, $outputPath) {
        // If no Imagick, we can try to use a default placeholder image
        if (file_exists('public/images/pdf-placeholder.jpg')) {
            copy('public/images/pdf-placeholder.jpg', $outputPath);
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
    public function getPdfThumbnail( $thumbnailDir = 'uploads/thumbnails') {
        // Create the thumbnail directory if it doesn't exist
        if (!file_exists($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }
        
        // Generate a unique name for the thumbnail
        $thumbnailName = md5(basename($this->pdfPath)) . '.jpg';
        $thumbnailPath = $thumbnailDir . '/' . $thumbnailName;
        // Check if thumbnail already exists
        if (!file_exists($thumbnailPath)) {
            // Extract the first page
            if (!$this->extractFirstPageAsImage( $this->pdfPath, $thumbnailPath)) {
                // Return a default image if extraction fails
                return '/images/default-pdf-cover.jpg';
            }
        }
        
        return '/' . $thumbnailPath;
    }

    public function storePdf($pdfFile) {
        // Check if the file is a valid PDF
        if ($pdfFile['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $pdfFile['tmp_name'];
            $fileName = $pdfFile['name'];
            $fileSize = $pdfFile['size'];
            $fileType = $pdfFile['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            // Allowed file extensions
            $allowedExtensions = ['pdf'];

            if (in_array($fileExtension, $allowedExtensions)) {
                // Generate a unique name for the file
                $newFileName = uniqid('pdf_', true) . '.' . $fileExtension;

                // Directory to store uploaded files
                $uploadFileDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }

                // Destination path
                $dest_path = $uploadFileDir . $newFileName;

                // Move the file to the destination path
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $this->getPdfThumbnail(); 
                    return "PDF uploaded successfully!";
                } else {
                    return "Error moving the file.";
                }
            } else {
                return "Only PDF files are allowed.";
            }
        } else {
            return "No file uploaded or upload error.";
        }
    }
}
