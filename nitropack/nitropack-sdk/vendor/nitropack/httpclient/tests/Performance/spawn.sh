#!/bin/sh

# Create the Caddyfile with the correct configuration
cat <<EOL > Caddyfile
:80 {
    file_server {
        precompressed gzip br
    }

    root * /srv

    respond / "Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World!
Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World!
Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World!
Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World!
Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World! Hello, World!" 200
}

EOL

# Run the Docker container with the custom Caddy image
docker run --rm --name caddy -v "$PWD/data":/srv -v "$PWD"/Caddyfile:/etc/caddy/Caddyfile -p 3000:80 caddy:2.8.4-alpine

# Clean up temporary files
rm -v Caddyfile
