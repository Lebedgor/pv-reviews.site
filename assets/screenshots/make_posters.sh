#!/usr/bin/env bash
# Generates poster frames (JPG) for the deployed *.mp4 demo videos.
# Posters are extracted from the videos themselves, so the preview always
# matches the content (replaces the old reused-screenshot posters).
#
# Usage: bash make_posters.sh [seconds]   (default frame time: 1s)
set -euo pipefail
cd "$(dirname "$0")"

FRAME_AT="${1:-1}"
mkdir -p posters

for mp4 in *.mp4; do
  name="${mp4%.mp4}"
  out="posters/${name}.jpg"
  ffmpeg -hide_banner -loglevel error -y -ss "$FRAME_AT" -i "$mp4" \
    -frames:v 1 -vf "scale='min(1600,iw)':-2" -q:v 4 "$out"
  echo "ok: $out"
done
