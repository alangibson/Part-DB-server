#!/bin/sh
set -eu

if ! command -v sudo >/dev/null 2>&1; then
    echo "install-labelle.sh: sudo is required" >&2
    exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
    echo "install-labelle.sh: this installer requires apt-get (Debian or Ubuntu)" >&2
    exit 1
fi

sudo apt-get install -y pipx

if ! command -v labelle >/dev/null 2>&1 && [ ! -x "$HOME/.local/bin/labelle" ]; then
    pipx install labelle
fi

if ! command -v labelle >/dev/null 2>&1; then
    echo "install-labelle.sh: pipx installed Labelle, but labelle is not in PATH" >&2
    echo "Log out and back in, then run this installer again." >&2
    exit 1
fi

echo "Labelle is installed. Starting it now so it can report any required udev rule."
echo "Make sure your label printer is connected by USB and powered on!"

exec labelle
