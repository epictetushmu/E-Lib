{ pkgs ? import <nixpkgs> { } }:

let
  # PHP 8.2 (matches Dockerfile) with the extensions E-Lib actually needs:
  # mongodb + curl + openssl for the MongoDB/API layer, gd/imagick/exif for
  # thumbnails, zip for archive handling, pcntl for CLI scripts, mbstring for
  # string handling, and the always-on core exts check-system.php probes for.
  php = pkgs.php82.buildEnv {
    extensions = { enabled, all }:
      enabled ++ (with all; [
        mongodb
        imagick
        gd
        curl
        openssl
        mbstring
        zip
        exif
        pcntl
        fileinfo
      ]);
    extraConfig = ''
      upload_max_filesize = 200M
      post_max_size = 200M
      memory_limit = 512M
      max_execution_time = 600
    '';
  };
in
pkgs.mkShell {
  name = "e-lib";

  buildInputs = with pkgs; [
    php
    php.packages.composer

    # Document/thumbnail pipeline (App/Helpers/FileHelper.php, Docs component):
    # pdftoppm (poppler) for PDF thumbnails, ImageMagick for image/PDF
    # processing, LibreOffice for office-doc conversion — mirrors Dockerfile.
    poppler-utils
    imagemagick
    libreoffice

    # General tooling used by the app/build (matches Dockerfile & README)
    git
    unzip
    openssl
    curl
    nodejs # for package-lock.json / any front-end tooling
  ];

  shellHook = ''
    echo "E-Lib dev shell"
    echo "  php:      $(php --version | head -n1)"
    echo "  composer: $(composer --version)"
    echo ""
    echo "Quick start:"
    echo "  composer install"
    echo "  cp .env.example .env   # then set MONGO_URI to your Atlas connection string"
    echo "  php -S localhost:8000 -t public"
    echo ""
    echo "Note: this shell has no local MongoDB server — the app connects"
    echo "      to MongoDB Atlas (see MONGO_URI in .env.example)."
  '';
}
