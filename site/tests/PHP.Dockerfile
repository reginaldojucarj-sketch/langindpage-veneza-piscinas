FROM php:8.4-cli

# Fail the build if the official CLI image loses a renderer dependency.
RUN php -r 'foreach (["curl", "dom", "mbstring"] as $extension) { if (!extension_loaded($extension)) exit(1); }'

WORKDIR /site
