<?php

namespace App\Helpers;

use App\Includes\Environment;

class FileHelper
{
    private ?string $filePath;
    private ?string $fileType = null;
    private ?string $fileExtension = null;

    /** Why the last storeFile() rejected the upload (null if it failed for a server-side reason). */
    public ?string $rejectReason = null;

    /**
     * Constructor
     *
     * @param string|null $filePath Path to the document file
     */
    public function __construct($filePath = null)
    {
        $this->filePath = $filePath;

        if ($filePath) {
            $this->detectFileType($filePath);
        }
    }

    /**
     * Detect the file type and extension from a file
     */
    private function detectFileType(string $filePath): void
    {
        // Get file extension
        $this->fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Determine file type from extension
        if ($this->fileExtension === 'pdf') {
            $this->fileType = 'pdf';
        } else {
            $this->fileType = 'unknown';
        }
    }

    /**
     * Extracts a thumbnail image from the document
     */
    public function extractThumbnail(string $filePath, string $outputPath, string $format = 'jpg'): bool
    {
        // If we don't know the file type yet, detect it
        if (empty($this->fileType)) {
            $this->detectFileType($filePath);
        }

        // Only support PDF
        if ($this->fileType === 'pdf') {
            return $this->extractPdfThumbnail($filePath, $outputPath, $format);
        }

        return $this->useTypePlaceholder($outputPath, $this->fileType);
    }




    /**
     * Extract thumbnail from PDF file (Imagick → pdftoppm → GD placeholder).
     */
    private function extractPdfThumbnail(string $pdfPath, string $outputPath, string $format = 'jpg'): bool
    {
        if (!file_exists($pdfPath) || !is_readable($pdfPath)) {
            error_log("PDF file not found or not readable: $pdfPath");
            return $this->useTypePlaceholder($outputPath, 'pdf');
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            if (!@mkdir($outputDir, 0777, true)) {
                error_log("Failed to create thumbnail directory: $outputDir");
                return $this->useTypePlaceholder($outputPath, 'pdf');
            }
            @chmod($outputDir, 0777);
        }

        // 1) Imagick (when ext-imagick is installed)
        if (extension_loaded('imagick') && class_exists('\\Imagick')) {
            try {
                error_log("Attempting PDF thumbnail via Imagick: $pdfPath");
                $imagick = new \Imagick();
                $imagick->setResolution(150, 150);
                try {
                    if (!$imagick->readImage($pdfPath . '[0]')) {
                        throw new \Exception('Failed to read the file');
                    }
                } catch (\Exception $e) {
                    error_log('Imagick read failed: ' . $e->getMessage() . ' — retrying at 72 DPI');
                    $imagick->clear();
                    $imagick->setResolution(72, 72);
                    if (!$imagick->readImage($pdfPath . '[0]')) {
                        throw new \Exception('Failed to read with alternative method');
                    }
                }
                $imagick->setImageFormat($format);
                $imagick->setImageCompressionQuality(85);
                $width = $imagick->getImageWidth();
                if ($width > 800) {
                    $imagick->resizeImage(800, 0, \Imagick::FILTER_LANCZOS, 1);
                }
                $imagick->writeImage($outputPath);
                $imagick->clear();
                $imagick->destroy();
                if (file_exists($outputPath)) {
                    error_log("PDF thumbnail created via Imagick: $outputPath");
                    return true;
                }
            } catch (\Exception $e) {
                error_log('Imagick thumbnail failed: ' . $e->getMessage());
            }
        } else {
            error_log('Imagick extension not loaded; trying pdftoppm (Poppler) if available');
        }

        // 2) pdftoppm — common on macOS/Linux (brew install poppler); no PHP extension
        if ($this->extractPdfThumbnailWithPdftoppm($pdfPath, $outputPath)) {
            return true;
        }

        // 3) Copy / generate static placeholder
        return $this->useTypePlaceholder($outputPath, 'pdf');
    }

    /**
     * Locate a Poppler binary (pdftoppm, pdftotext); PDFTOPPM_PATH / PDFTOTEXT_PATH override the search.
     */
    private function resolvePopplerBinary(string $name): ?string
    {
        $env = Environment::get(strtoupper($name) . '_PATH');
        if (is_string($env) && $env !== '' && is_executable($env)) {
            return $env;
        }
        foreach (["/opt/homebrew/bin/$name", "/usr/local/bin/$name", "/usr/bin/$name"] as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        if (!function_exists('shell_exec')) {
            return null;
        }
        $which = shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null');
        if (is_string($which)) {
            $w = trim($which);
            if ($w !== '' && is_executable($w)) {
                return $w;
            }
        }
        return null;
    }

    /**
     * Text of every page of a PDF, in page order (index 0 = page 1). Pages without a text layer
     * (scanned images) come back as empty strings so page numbers stay aligned with the PDF.
     *
     * @return list<string>
     * @throws \RuntimeException When pdftotext is missing or fails
     */
    public function extractPageTexts(string $pdfPath): array
    {
        $bin = $this->resolvePopplerBinary('pdftotext');
        if ($bin === null) {
            throw new \RuntimeException('pdftotext (poppler-utils) is not installed');
        }
        // Written to a file rather than read through exec(): exec() strips trailing whitespace from
        // every line, which eats the form feeds pdftotext uses as page separators.
        $out = tempnam(sys_get_temp_dir(), 'elib_text_');
        try {
            $lines = [];
            $code = 0;
            exec(sprintf('%s -enc UTF-8 %s %s 2>&1', escapeshellarg($bin), escapeshellarg($pdfPath), escapeshellarg($out)), $lines, $code);
            $text = file_get_contents($out);
            if ($code !== 0 || $text === false) {
                error_log("pdftotext failed for $pdfPath (code $code): " . implode(' ', $lines));
                throw new \RuntimeException("Could not read the PDF's text (pdftotext exit code $code)");
            }
        } finally {
            @unlink($out);
        }
        // Every page ends with a form feed, so the piece after the last one is always empty.
        $pages = explode("\f", $text);
        array_pop($pages);
        return array_map('trim', $pages);
    }

    private function extractPdfThumbnailWithPdftoppm(string $pdfPath, string $outputPath): bool
    {
        $bin = $this->resolvePopplerBinary('pdftoppm');
        if ($bin === null) {
            return false;
        }
        $outDir = dirname($outputPath);
        if (!is_dir($outDir) && !@mkdir($outDir, 0777, true)) {
            error_log("pdftoppm: cannot create directory: $outDir");
            return false;
        }
        $tmpBase = sys_get_temp_dir() . '/elib_thumb_' . bin2hex(random_bytes(8));
        $cmd = sprintf(
            '%s -jpeg -f 1 -l 1 -singlefile -r 144 %s %s',
            escapeshellarg($bin),
            escapeshellarg($pdfPath),
            escapeshellarg($tmpBase)
        );
        $lines = [];
        $code = 0;
        if (!function_exists('exec')) {
            return false;
        }
        @exec($cmd . ' 2>&1', $lines, $code);
        $jpg = $tmpBase . '.jpg';
        if ($code !== 0 || !is_file($jpg) || !is_readable($jpg)) {
            error_log('pdftoppm failed (code ' . $code . '): ' . implode(' ', $lines));
            @unlink($jpg);
            return false;
        }
        $ok = @copy($jpg, $outputPath);
        @unlink($jpg);
        if ($ok && file_exists($outputPath)) {
            error_log("PDF thumbnail created via pdftoppm: $outputPath");
            return true;
        }
        return false;
    }



    /**
     * Use a placeholder image for the specified file type
     */
    private function useTypePlaceholder(string $outputPath, string $type = 'generic'): bool
    {
        try {
            // Make sure output directory exists
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir) && !@mkdir($outputDir, 0777, true)) {
                error_log("Failed to create directory: $outputDir");
                return false;
            }

            $placeholderFile = 'placeholder-pdf.jpg';

            // Path to placeholder file
            $placeholderPath = __DIR__ . '/../../public/assets/uploads/thumbnails/' . $placeholderFile;

            // If the specific placeholder doesn't exist, fall back to the generic one
            if (!file_exists($placeholderPath)) {
                $placeholderPath = __DIR__ . '/../../public/assets/uploads/thumbnails/placeholder-book.jpg';
            }

            // If even the generic placeholder doesn't exist, create a blank one
            if (!file_exists($placeholderPath)) {
                // Create a blank image
                $img = imagecreatetruecolor(200, 300);
                $bgColor = imagecolorallocate($img, 240, 240, 240);
                $textColor = imagecolorallocate($img, 50, 50, 50);
                imagefilledrectangle($img, 0, 0, 200, 300, $bgColor);

                // Add text
                $text = strtoupper($type);
                $fontFile = __DIR__ . '/../../public/assets/fonts/roboto/Roboto-Regular.ttf';
                if (!file_exists($fontFile)) {
                    // Use built-in font if TTF file doesn't exist
                    imagestring($img, 5, 50, 140, $text, $textColor);
                } else {
                    // Use custom font
                    imagettftext($img, 16, 0, 50, 150, $textColor, $fontFile, $text);
                }

                // Save the image to the placeholder path
                imagejpeg($img, $placeholderPath, 90);
                imagedestroy($img);
            }

            // Copy the placeholder to the output path
            if (file_exists($placeholderPath)) {
                return copy($placeholderPath, $outputPath);
            }

            return false;
        } catch (\Exception $e) {
            error_log("Error using placeholder image: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Absolute upload directory and public web path for a subdirectory under public/assets/uploads/.
     * App/Helpers → project root is two levels up (not three — three was wrong and wrote outside E-Lib).
     *
     * @return array{0: string, 1: string}
     */
    private function resolveUploadPaths(string $subDir): array
    {
        $uploadDir = getenv('DOCKER_ENV') === 'true' ? '/var/www/html/public' : dirname(__DIR__, 2) . '/public';
        $webPath = '/assets/uploads/' . $subDir;
        return [$uploadDir . $webPath, $webPath];
    }

    /**
     * Gets or creates a thumbnail for the document
     */
    public function getThumbnail(): string
    {
        [$thumbnailDir, $webPath] = $this->resolveUploadPaths('thumbnails');

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
        $thumbnailName = md5(basename($this->filePath)) . '.jpg';
        $thumbnailPath = $thumbnailDir . '/' . $thumbnailName;

        // Check if thumbnail already exists
        if (!file_exists($thumbnailPath)) {
            // Extract thumbnail from document
            if (!$this->extractThumbnail($this->filePath, $thumbnailPath)) {
                // Return a default image if extraction fails
                return '/assets/uploads/thumbnails/placeholder-book.jpg';
            }
        }

        return $webPath . '/' . $thumbnailName;
    }

    /**
     * Stores a document file with a proper name
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed>|false
     */
    public function storeFile($file)
    {
        $this->rejectReason = null;
        try {
            // Check if the upload was successful
            if ($file['error'] !== UPLOAD_ERR_OK) {
                error_log("Upload error code: " . $file['error']);
                return false;
            }

            // Get file information
            $fileTmpPath = $file['tmp_name'];
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileType = $file['type'];

            // Extract file extension
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Set file type based on extension
            $this->fileExtension = $fileExtension;
            $this->detectFileType($fileName);

            // Validate supported file types
            $supportedTypes = ['pdf'];

            if (!in_array($fileExtension, $supportedTypes)) {
                $this->rejectReason = 'Only PDF files are supported.';
                return false;
            }

            // The extension is client-controlled; a real PDF starts with this header
            if (@file_get_contents($fileTmpPath, false, null, 0, 5) !== '%PDF-') {
                $this->rejectReason = 'File is not a valid PDF.';
                return false;
            }

            // Generate a unique name for the file
            $newFileName = uniqid('doc_') . '.' . $fileExtension;

            [$uploadFileDir, $webPath] = $this->resolveUploadPaths('documents');
            $uploadFileDir .= '/';

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
            $this->filePath = $dest_path;

            // Return web-accessible path
            return [
                'path' => $webPath . '/' . $newFileName,
                'type' => $this->fileType,
                'extension' => $fileExtension
            ];
        } catch (\Exception $e) {
            error_log("Error storing file: " . $e->getMessage());
            return false;
        }
    }
}
