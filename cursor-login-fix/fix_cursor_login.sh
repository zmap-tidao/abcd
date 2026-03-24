#!/usr/bin/env bash
# Fix: Cursor IDE desktop app login button doesn't open browser on Linux.
#
# Root cause: Linux doesn't know how to handle cursor:// protocol URLs by
# default. When Cursor triggers the OAuth redirect, the browser callback
# URL (cursor://...) has no registered handler, so nothing happens.
#
# This script registers a xdg MIME handler so that cursor:// links are
# forwarded to the Cursor binary, completing the login flow.

set -euo pipefail

DESKTOP_DIR="$HOME/.local/share/applications"
DESKTOP_FILE="$DESKTOP_DIR/cursor-url-handler.desktop"

# ── 1. Locate the Cursor binary ──────────────────────────────────────────────

find_cursor_binary() {
    # Common install locations (AppImage, deb/rpm, snap, flatpak, manual)
    local candidates=(
        "$HOME/Applications/cursor.appimage"
        "$HOME/.local/bin/cursor"
        "/usr/bin/cursor"
        "/usr/local/bin/cursor"
        "/opt/cursor/cursor"
        "/opt/Cursor/cursor"
    )

    # Also search PATH
    if command -v cursor &>/dev/null; then
        command -v cursor
        return 0
    fi

    # AppImage in common user directories
    local appimage
    appimage=$(find "$HOME" -maxdepth 4 -iname "cursor*.appimage" 2>/dev/null | head -n1)
    if [[ -n "$appimage" ]]; then
        echo "$appimage"
        return 0
    fi

    for c in "${candidates[@]}"; do
        if [[ -x "$c" ]]; then
            echo "$c"
            return 0
        fi
    done

    return 1
}

CURSOR_BIN=""
if ! CURSOR_BIN=$(find_cursor_binary); then
    echo ""
    echo "ERROR: Could not locate the Cursor binary automatically."
    echo "Please re-run this script with the path as an argument:"
    echo "  $0 /path/to/cursor"
    exit 1
fi

# Allow explicit override via first argument
if [[ $# -ge 1 && -n "$1" ]]; then
    CURSOR_BIN="$1"
fi

echo "Using Cursor binary: $CURSOR_BIN"

# ── 2. Create the .desktop file ───────────────────────────────────────────────

mkdir -p "$DESKTOP_DIR"

cat > "$DESKTOP_FILE" <<EOF
[Desktop Entry]
Name=Cursor URL Handler
Comment=Handles cursor:// protocol links for Cursor IDE login
Exec=$CURSOR_BIN --open-url %u
Type=Application
Terminal=false
NoDisplay=true
MimeType=x-scheme-handler/cursor;
EOF

echo "Created: $DESKTOP_FILE"

# ── 3. Register the cursor:// scheme ─────────────────────────────────────────

xdg-mime default cursor-url-handler.desktop x-scheme-handler/cursor
echo "Registered cursor:// scheme handler."

# ── 4. Refresh the desktop database ──────────────────────────────────────────

if command -v update-desktop-database &>/dev/null; then
    update-desktop-database "$DESKTOP_DIR"
    echo "Desktop database updated."
else
    echo "WARNING: update-desktop-database not found. You may need to log out and back in."
fi

# ── 5. Verify ────────────────────────────────────────────────────────────────

REGISTERED=$(xdg-mime query default x-scheme-handler/cursor 2>/dev/null || true)
if [[ "$REGISTERED" == "cursor-url-handler.desktop" ]]; then
    echo ""
    echo "SUCCESS: cursor:// links will now be handled by Cursor."
    echo "You can test it with:  xdg-open 'cursor://test'"
    echo "Then try logging in again from the Cursor desktop app."
else
    echo ""
    echo "WARNING: Registration may not have taken effect immediately."
    echo "Try logging out and back in to your desktop session, then re-run the login."
fi
