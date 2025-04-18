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

    public function extractFirstPageAsImage($outputPath, $format = 'jpg') {
        // Check if Imagick is installed
        if (!extension_loaded('imagick')) {
            error_log('Imagick extension not installed');
            return false;
        }

        try {
            // Create Imagick instance
            $imagick = new \Imagick();
            
            // Set resolution for better quality
            $imagick->setResolution(300, 300);
            
            // Read only the first page of the PDF
            $imagick->readImage($this->pdfPath . '[0]');
            
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
            error_log('PDF thumbnail extraction failed: ' . $e->getMessage());
            return false;
        }
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
            if (!$this->extractFirstPageAsImage( $thumbnailPath)) {
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
