#!/bin/bash
# BetVibe - Asset Minification Script
# Requires: terser (npm install -g terser), clean-css-cli (npm install -g clean-css-cli)

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PUBLIC_DIR="$PROJECT_ROOT/public"

echo "=== BetVibe Asset Minification ==="

# Minify CSS
echo "Minifying CSS..."
for css in "$PUBLIC_DIR"/assets/css/*.css; do
    if [ -f "$css" ] && [[ "$css" != *.min.css ]]; then
        out="${css%.css}.min.css"
        echo "  $css -> $out"
        cleancss -o "$out" "$css" 2>/dev/null || cp "$css" "$out"
    fi
done

# Minify JS
echo "Minifying JS..."
for js in "$PUBLIC_DIR"/assets/js/*.js; do
    if [ -f "$js" ] && [[ "$js" != *.min.js ]]; then
        out="${js%.js}.min.js"
        echo "  $js -> $out"
        terser "$js" --compress --mangle -o "$out" 2>/dev/null || cp "$js" "$out"
    fi
done

# Minify Service Worker
if [ -f "$PUBLIC_DIR/sw.js" ]; then
    echo "Minifying Service Worker..."
    terser "$PUBLIC_DIR/sw.js" --compress --mangle -o "$PUBLIC_DIR/sw.min.js" 2>/dev/null || cp "$PUBLIC_DIR/sw.js" "$PUBLIC_DIR/sw.min.js"
fi

# Convert images to WebP (if cwebp is available)
if command -v cwebp &> /dev/null; then
    echo "Converting images to WebP..."
    find "$PUBLIC_DIR/assets/images" -type f \( -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" \) | while read img; do
        webp="${img%.*}.webp"
        if [ ! -f "$webp" ]; then
            echo "  $img -> $webp"
            cwebp -q 85 "$img" -o "$webp" 2>/dev/null
        fi
    done
else
    echo "SKIP: cwebp not installed. Run: apt install webp"
fi

echo "=== Minification Complete ==="
echo ""
echo "To use minified files in production, update HTML references:"
echo "  style.css -> style.min.css"
echo "  socket.js -> socket.min.js"
