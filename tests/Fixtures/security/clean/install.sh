#!/bin/bash
# A benign installer: copies local files into place.
set -euo pipefail
source_dir="$(dirname "$0")/src"
install_dir="${XDG_CONFIG_HOME:-$HOME/.config}/example-widget"
mkdir -p "$install_dir"
cp -r "$source_dir"/* "$install_dir"/
echo "Installed."
