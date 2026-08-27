#!/bin/sh
set -eu

SOURCE_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

if ! command -v sudo >/dev/null 2>&1; then
    echo "install-labelle-open.sh: sudo is required" >&2
    exit 1
fi

if ! command -v labelle >/dev/null 2>&1; then
    echo "install-labelle-open.sh: labelle is not installed or not in PATH" >&2
    exit 1
fi

if ! command -v notify-send >/dev/null 2>&1; then
    echo "install-labelle-open.sh: notify-send is required" >&2
    echo "On Debian or Ubuntu, install it with: sudo apt-get install libnotify-bin" >&2
    exit 1
fi

sudo install -d -m 755 "/opt/labelle-open"
sudo install -m 755 "$SOURCE_DIR/labelle-open" "/opt/labelle-open/labelle-open"

sudo install -d -m 755 "/usr/local/share/applications"
sudo install -m 644 "$SOURCE_DIR/labelle.desktop" "/usr/local/share/applications/labelle.desktop"

sudo install -d -m 755 "/usr/local/share/mime/packages"
sudo install -m 644 "$SOURCE_DIR/labelle.xml" "/usr/local/share/mime/packages/labelle.xml"

sudo update-mime-database "/usr/local/share/mime"

if command -v update-desktop-database >/dev/null 2>&1; then
    sudo update-desktop-database "/usr/local/share/applications"
fi

xdg-mime default labelle.desktop application/x-labelle

echo "Labelle file association installed."
